<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WebRtcCallingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $deo;
    protected User $farmer;
    protected Conversation $supportConversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $this->deo = User::whereHas('roles', fn ($q) => $q->where('name', 'data_entry_operator'))->first();
        $this->farmer = User::whereHas('roles', fn ($q) => $q->where('name', 'farmer'))->first();

        // Create support conversation for farmer
        $this->supportConversation = Conversation::create([
            'type' => 'support',
            'status' => 'open',
            'created_by' => $this->farmer->id,
            'last_message_at' => now(),
        ]);

        ConversationParticipant::create([
            'conversation_id' => $this->supportConversation->id,
            'user_id' => $this->farmer->id,
            'last_read_at' => now(),
        ]);
    }

    public function test_authenticated_user_can_retrieve_bdix_turn_credentials(): void
    {
        $response = $this->actingAs($this->farmer)
            ->getJson('/api/v1/chat/turn-credentials');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'iceServers' => [
                        '*' => ['urls'],
                    ],
                    'ttl',
                    'host',
                ],
            ]);

        $iceServers = $response->json('data.iceServers');
        $this->assertNotEmpty($iceServers);

        // Verify STUN and TURN servers are present
        $turnServer = collect($iceServers)->first(fn ($server) => isset($server['username']));
        $this->assertNotNull($turnServer);
        $this->assertStringContainsString('165.99.219.252:3478', $turnServer['urls'][0]);
        $this->assertNotEmpty($turnServer['username']);
        $this->assertNotEmpty($turnServer['credential']);
    }

    public function test_user_can_send_call_signal_within_authorized_conversation(): void
    {
        Event::fake();

        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/signal", [
                'signal_type' => 'initiate',
                'call_type' => 'audio',
                'sdp' => ['type' => 'offer', 'sdp' => 'v=0...'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.signal_sent', true)
            ->assertJsonPath('data.signal_type', 'initiate');
    }

    public function test_unauthorized_user_cannot_send_call_signal(): void
    {
        $unrelatedFarmer = User::factory()->create(['gender' => 'male']);
        $unrelatedFarmer->assignRole('farmer');

        $response = $this->actingAs($unrelatedFarmer)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/signal", [
                'signal_type' => 'initiate',
                'call_type' => 'audio',
            ]);

        $response->assertForbidden();
    }

    public function test_support_team_call_claiming_and_concurrency_locking(): void
    {
        Event::fake();

        // 1. DEO answers/claims the call first
        $deoResponse = $this->actingAs($this->deo)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/claim", [
                'call_type' => 'audio',
            ]);

        $deoResponse->assertOk()
            ->assertJsonPath('data.claimed', true)
            ->assertJsonPath('data.claimed_by.staff_id', $this->deo->id);

        // 2. Admin attempts to answer same call -> blocked with 409 conflict
        $adminResponse = $this->actingAs($this->admin)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/claim", [
                'call_type' => 'audio',
            ]);

        $adminResponse->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('claimed_by.staff_id', $this->deo->id);
    }

    public function test_ending_call_creates_formatted_history_message(): void
    {
        Event::fake();

        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/end", [
                'call_type' => 'audio',
                'duration_seconds' => 135, // 2m 15s
                'reason' => 'completed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.ended', true)
            ->assertJsonPath('data.message.body', '📞 Audio call ended (2m 15s)');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->supportConversation->id,
            'sender_id' => $this->farmer->id,
            'body' => '📞 Audio call ended (2m 15s)',
        ]);
    }

    public function test_missed_video_call_creates_missed_message(): void
    {
        Event::fake();

        $response = $this->actingAs($this->farmer)
            ->postJson("/api/v1/chat/conversations/{$this->supportConversation->id}/call/end", [
                'call_type' => 'video',
                'duration_seconds' => 0,
                'reason' => 'missed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message.body', '📹 Missed Video call');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->supportConversation->id,
            'sender_id' => $this->farmer->id,
            'body' => '📹 Missed Video call',
        ]);
    }
}
