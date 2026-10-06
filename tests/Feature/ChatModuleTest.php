<?php

namespace Tests\Feature;

use App\Events\MessagesSeen;
use App\Events\UserTyping;
use App\Models\AdminNotification;
use App\Models\Conversation;
use App\Models\Farm;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected User $otherFarmer;
    protected User $vet;
    protected Farm $farm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->admin->assignRole('admin');

        $this->deo = User::factory()->create(['name' => 'DEO Staff']);
        $this->deo->assignRole('data_entry_operator');

        $this->farmer = User::factory()->create(['name' => 'Sadek Farmer', 'phone' => '01711223344']);
        $this->farmer->assignRole('farmer');

        $this->otherFarmer = User::factory()->create(['name' => 'Jamal Farmer', 'phone' => '01799887766']);
        $this->otherFarmer->assignRole('farmer');

        $this->vet = User::factory()->create(['name' => 'Dr. Tanvir', 'district' => 'Khulna']);
        $this->vet->assignRole('veterinary_doctor');

        $this->farm = Farm::factory()->create([
            'user_id' => $this->farmer->id,
            'district' => 'Khulna',
        ]);
    }

    public function test_farmer_can_start_general_support_conversation(): void
    {
        Sanctum::actingAs($this->farmer);

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'support',
            'message' => 'Hello support, I need help with pond oxygenation.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conversation.type', 'support')
            ->assertJsonPath('data.conversation.status', 'open');

        $this->assertDatabaseHas('conversations', [
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'body' => 'Hello support, I need help with pond oxygenation.',
            'sender_id' => $this->farmer->id,
        ]);
    }

    public function test_farmer_support_conversation_is_reused_if_already_open(): void
    {
        Sanctum::actingAs($this->farmer);

        $first = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'support',
            'message' => 'First inquiry',
        ]);
        $firstId = $first->json('data.conversation_id');

        $second = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'support',
            'message' => 'Second inquiry without context',
        ]);
        $secondId = $second->json('data.conversation_id');

        $this->assertEquals($firstId, $secondId);
        $this->assertEquals(1, Conversation::where('type', 'support')->where('created_by', $this->farmer->id)->count());
    }

    public function test_farmer_can_start_order_linked_support_conversation(): void
    {
        Sanctum::actingAs($this->farmer);

        $order = Order::factory()->create([
            'user_id' => $this->farmer->id,
            'farm_id' => $this->farm->id,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'support',
            'context_type' => 'order',
            'context_id' => $order->id,
            'message' => "Inquiring about consignment delivery for Order #{$order->invoice_number}",
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.conversation.context_type', 'order')
            ->assertJsonPath('data.conversation.context_id', $order->id);
    }

    public function test_practitioner_chat_is_forbidden_if_service_request_is_not_assigned(): void
    {
        Sanctum::actingAs($this->farmer);

        $sr = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'status' => 'pending',
            'assigned_to' => null,
        ]);

        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'practitioner',
            'context_type' => 'service_request',
            'context_id' => $sr->id,
            'message' => 'Hello doctor',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Chat with a practitioner is only available once the service request has been assigned.');
    }

    public function test_farmer_and_assigned_practitioner_can_chat_once_service_request_is_assigned(): void
    {
        $sr = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
        ]);

        // Farmer starts practitioner chat
        Sanctum::actingAs($this->farmer);
        $startRes = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'practitioner',
            'context_type' => 'service_request',
            'context_id' => $sr->id,
            'message' => 'Dr. Tanvir, our post-larvae are showing slow movement.',
        ]);

        $startRes->assertStatus(201)
            ->assertJsonPath('data.conversation.type', 'practitioner');

        $convId = $startRes->json('data.conversation_id');

        // Practitioner can view and reply
        Sanctum::actingAs($this->vet);
        $replyRes = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'I am on my way with a DO meter. Keep aeration on until I arrive.',
        ]);

        $replyRes->assertStatus(201)
            ->assertJsonPath('data.message.body', 'I am on my way with a DO meter. Keep aeration on until I arrive.');

        // Chat messages do not clutter the general notification bell
        $this->assertDatabaseMissing('admin_notifications', [
            'type' => 'chat.practitioner_message',
        ]);
    }

    public function test_unauthorized_farmer_cannot_access_other_farmer_service_request_chat(): void
    {
        $sr = ServiceRequest::factory()->create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
        ]);

        Sanctum::actingAs($this->otherFarmer);
        $response = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'practitioner',
            'context_type' => 'service_request',
            'context_id' => $sr->id,
            'message' => 'Intruder message',
        ]);

        $response->assertStatus(403);
    }

    public function test_message_with_image_attachment(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->farmer);

        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
        ]);

        $file = UploadedFile::fake()->image('sick_fish.jpg', 600, 400);

        $response = $this->postJson("/api/v1/chat/conversations/{$conv->id}/messages", [
            'body' => 'Here is a photo of the pond water color.',
            'attachment' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('data.message.attachment_url'));
        $attachmentPath = $response->json('data.message.attachment_path');
        Storage::disk('public')->assertExists($attachmentPath);
    }

    public function test_admin_and_deo_can_view_support_inbox_and_reply(): void
    {
        // Farmer starts support conversation
        Sanctum::actingAs($this->farmer);
        $convRes = $this->postJson('/api/v1/chat/conversations', [
            'type' => 'support',
            'message' => 'Help with delivery delay',
        ]);
        $convId = $convRes->json('data.conversation_id');

        // DEO views support inbox
        Sanctum::actingAs($this->deo);
        $inboxRes = $this->getJson('/api/v1/admin/support-inbox');
        $inboxRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotEmpty($inboxRes->json('data.conversations'));

        // DEO replies in shared inbox
        $replyRes = $this->postJson("/api/v1/chat/conversations/{$convId}/messages", [
            'body' => 'Hello Sadek, your consignment #FL-12 is currently dispatched from Khulna hub.',
        ]);
        $replyRes->assertStatus(201);

        // Chat messages do not clutter the general notification bell
        $this->assertDatabaseMissing('admin_notifications', [
            'type' => 'chat.support_reply',
        ]);

        // Admin marks conversation as closed
        Sanctum::actingAs($this->admin);
        $closeRes = $this->patchJson("/api/v1/chat/conversations/{$convId}/close");
        $closeRes->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        $this->assertDatabaseHas('conversations', [
            'id' => $convId,
            'status' => 'closed',
        ]);
    }

    public function test_unread_count_and_mark_as_read(): void
    {
        Sanctum::actingAs($this->farmer);
        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
        ]);

        // Admin sends message to farmer
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/chat/conversations/{$conv->id}/messages", [
            'body' => 'Notice: Your order is on the truck.',
        ]);

        // Farmer checks unread count
        Sanctum::actingAs($this->farmer);
        $unreadRes = $this->getJson('/api/v1/chat/unread-count');
        $unreadRes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // Farmer views conversation (show endpoint auto marks as read)
        $this->getJson("/api/v1/chat/conversations/{$conv->id}");

        // Now unread count is 0
        $unreadAfter = $this->getJson('/api/v1/chat/unread-count');
        $unreadAfter->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_channel_authorization_for_chat_conversation(): void
    {
        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
        ]);
        $conv->participants()->create(['user_id' => $this->farmer->id]);

        $authCallback = function ($user, $conversationId) {
            $conversation = Conversation::find($conversationId);
            if (! $conversation) {
                return false;
            }
            if ($conversation->type === 'support' && $user->hasAnyRole(['admin', 'data_entry_operator'])) {
                return true;
            }
            if ($conversation->participants()->where('user_id', $user->id)->exists()) {
                return true;
            }
            if ($user->hasRole('admin')) {
                return true;
            }
            return false;
        };

        // Participant farmer is authorized
        $this->assertTrue($authCallback($this->farmer, $conv->id));

        // Other farmer is forbidden
        $this->assertFalse($authCallback($this->otherFarmer, $conv->id));

        // Staff DEO is authorized (shared support inbox)
        $this->assertTrue($authCallback($this->deo, $conv->id));

        // Admin is authorized
        $this->assertTrue($authCallback($this->admin, $conv->id));
    }

    public function test_send_n_messages_and_mark_read_keeps_counts_accurate(): void
    {
        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $conv->participants()->create(['user_id' => $this->farmer->id]);
        $conv->participants()->create(['user_id' => $this->admin->id]);

        // Clear any leftover notifications for a fresh count
        AdminNotification::where('user_id', $this->farmer->id)->delete();

        // Admin sends 3 messages in a row
        Sanctum::actingAs($this->admin);
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson("/api/v1/chat/conversations/{$conv->id}/messages", [
                'body' => "Support reply #{$i}",
            ])->assertStatus(201);
        }

        // Farmer checks chat unread count and confirms notification bell has 0 chat entries
        Sanctum::actingAs($this->farmer);
        $chatCountRes = $this->getJson('/api/v1/chat/unread-count');
        $chatCountRes->assertStatus(200)->assertJsonPath('data.unread_count', 3);

        // Bell count stays 0 (chat messages are excluded from notification bell)
        $notifCountRes = $this->getJson('/api/v1/notifications/unread-count');
        $notifCountRes->assertStatus(200)->assertJsonPath('data.unread_count', 0);

        // Farmer opens conversation (show endpoint marks read)
        $showRes = $this->getJson("/api/v1/chat/conversations/{$conv->id}");
        $showRes->assertStatus(200);

        // Assert chat unread count drops to 0
        $chatCountAfter = $this->getJson('/api/v1/chat/unread-count');
        $chatCountAfter->assertStatus(200)->assertJsonPath('data.unread_count', 0);

        // Admin sends 1 more message
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/chat/conversations/{$conv->id}/messages", [
            'body' => 'Another update',
        ])->assertStatus(201);

        // Farmer unread count should now be exactly 1, while bell remains 0
        Sanctum::actingAs($this->farmer);
        $this->getJson('/api/v1/chat/unread-count')->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);

        // Farmer calls explicit markAsRead endpoint
        $this->patchJson("/api/v1/chat/conversations/{$conv->id}/read")->assertStatus(200);

        // Chat unread count drops back to 0
        $this->getJson('/api/v1/chat/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_sending_chat_message_does_not_create_general_notification_bell_entry(): void
    {
        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $conv->participants()->create(['user_id' => $this->farmer->id]);

        Sanctum::actingAs($this->farmer);
        $this->postJson("/api/v1/chat/conversations/{$conv->id}/messages", [
            'body' => 'Hello Support',
        ])->assertStatus(201);

        // Assert NO general notification-bell row is created in admin_notifications
        $this->assertDatabaseMissing('admin_notifications', [
            'type' => 'chat.support_message',
        ]);
        $this->assertDatabaseMissing('admin_notifications', [
            'type' => 'chat.support_reply',
        ]);

        // Assert Messages badge updates for staff
        Sanctum::actingAs($this->admin);
        $chatCountRes = $this->getJson('/api/v1/chat/unread-count');
        $chatCountRes->assertStatus(200)->assertJsonPath('data.unread_count', 1);

        // Assert notification bell count is NOT incremented
        $notifCountRes = $this->getJson('/api/v1/notifications/unread-count');
        $notifCountRes->assertStatus(200)->assertJsonPath('data.unread_count', 0);
    }

    public function test_conversations_tab_filtering_strictly_isolates_support_and_advisory(): void
    {
        // 1. Create a support conversation
        $supportConv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $supportConv->participants()->create(['user_id' => $this->farmer->id]);

        // 2. Create an assigned service request and practitioner conversation (Advisory)
        $sr = ServiceRequest::create([
            'farm_id' => $this->farm->id,
            'farmer_id' => $this->farmer->id,
            'type' => 'vet',
            'status' => 'assigned',
            'assigned_to' => $this->vet->id,
            'description' => 'Cattle lethargy',
            'urgency' => 'urgent',
        ]);

        $advisoryConv = Conversation::create([
            'type' => 'practitioner',
            'context_type' => 'service_request',
            'context_id' => $sr->id,
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $advisoryConv->participants()->create(['user_id' => $this->farmer->id]);
        $advisoryConv->participants()->create(['user_id' => $this->vet->id]);

        Sanctum::actingAs($this->farmer);

        // Filter: Support tab
        $supportRes = $this->getJson('/api/v1/chat/conversations?type=support');
        $supportRes->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.conversations.0.id', $supportConv->id)
            ->assertJsonPath('data.conversations.0.type', 'support');

        // Filter: Advisory tab (type=practitioner)
        $advisoryRes = $this->getJson('/api/v1/chat/conversations?type=practitioner');
        $advisoryRes->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.conversations.0.id', $advisoryConv->id)
            ->assertJsonPath('data.conversations.0.type', 'practitioner');

        // Filter: All tab
        $allRes = $this->getJson('/api/v1/chat/conversations');
        $allRes->assertStatus(200)
            ->assertJsonPath('data.meta.total', 2);
    }

    public function test_typing_indicator_broadcasts_user_typing_event(): void
    {
        Event::fake([UserTyping::class]);

        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $conv->participants()->create(['user_id' => $this->farmer->id]);

        Sanctum::actingAs($this->farmer);

        $response = $this->postJson("/api/v1/chat/conversations/{$conv->id}/typing", [
            'is_typing' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_typing' => true,
                ],
            ]);

        Event::assertDispatched(UserTyping::class, function ($event) use ($conv) {
            return $event->conversationId === $conv->id &&
                $event->userId === $this->farmer->id &&
                $event->isTyping === true;
        });
    }

    public function test_viewing_conversation_marks_messages_read_and_broadcasts_messages_seen(): void
    {
        Event::fake([MessagesSeen::class]);

        $conv = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);
        $conv->participants()->create(['user_id' => $this->farmer->id]);

        // Admin sends a message to the farmer
        $msg = $conv->messages()->create([
            'sender_id' => $this->admin->id,
            'body' => 'Hello from Admin, please confirm pond pH.',
        ]);

        $this->assertNull($msg->read_at);

        // Farmer opens conversation
        Sanctum::actingAs($this->farmer);
        $response = $this->getJson("/api/v1/chat/conversations/{$conv->id}");
        $response->assertStatus(200);

        // Message is marked read in DB
        $this->assertNotNull($msg->fresh()->read_at);

        // MessagesSeen event dispatched with message id and reader id
        Event::assertDispatched(MessagesSeen::class, function ($event) use ($conv, $msg) {
            return $event->conversationId === $conv->id &&
                $event->readerId === $this->farmer->id &&
                in_array($msg->id, $event->messageIds);
        });
    }
}
