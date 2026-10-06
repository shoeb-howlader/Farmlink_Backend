<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageSent;
use App\Events\MessagesSeen;
use App\Events\UserTyping;
use App\Http\Requests\Api\V1\SendChatMessageRequest;
use App\Http\Requests\Api\V1\StoreConversationRequest;
use App\Models\AdminNotification;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\Order;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends ApiController
{
    /**
     * List conversations for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);
        $isInboxMode = $request->boolean('inbox') || ($isStaff && $request->query('view') === 'inbox');

        $query = Conversation::query()
            ->with(['creator', 'users', 'latestMessage.sender']);

        if ($isInboxMode) {
            // Admin/DEO shared support inbox
            $query->where('type', 'support');

            if ($request->filled('status')) {
                if ($request->query('status') !== 'all') {
                    $query->where('status', $request->query('status'));
                }
            } else {
                // Default view shows open conversations
                $query->where('status', 'open');
            }

            if ($request->filled('search')) {
                $search = trim($request->query('search'));
                $query->whereHas('users', function ($q) use ($search) {
                    $q->where('name', 'ilike', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }
        } else {
            // User's own conversations (farmer, practitioner, or staff personal)
            $query->where(function ($q) use ($user, $isStaff) {
                $q->whereHas('participants', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id);
                })->orWhere('created_by', $user->id);

                // If practitioner, also include assigned service request practitioner conversations
                if ($user->hasAnyRole(['veterinary_doctor', 'consultant'])) {
                    $q->orWhere(function ($sq) use ($user) {
                        $sq->where('type', 'practitioner')
                           ->where('context_type', 'service_request')
                           ->whereIn('context_id', ServiceRequest::where('assigned_to', $user->id)->pluck('id'));
                    });
                }

                // If staff and filtering by support, allow staff access
                if ($isStaff) {
                    $q->orWhere('type', 'support');
                }
            });

            if ($request->filled('type')) {
                $query->where('type', $request->query('type'));
            }

            if ($request->filled('status') && $request->query('status') !== 'all') {
                $query->where('status', $request->query('status'));
            }
        }

        // Sort by latest message activity, fallback to created_at
        $conversations = $query->orderByRaw('COALESCE(last_message_at, created_at) DESC')->paginate(30);

        $data = $conversations->getCollection()->map(function (Conversation $conv) use ($user) {
            $latest = $conv->latestMessage;
            $otherParty = $conv->getDisplayParty($user);

            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'status' => $conv->status,
                'context_type' => $conv->context_type,
                'context_id' => $conv->context_id,
                'context_label' => $conv->getContextLabel(),
                'other_party' => $otherParty,
                'latest_message' => $latest ? [
                    'id' => $latest->id,
                    'body' => $latest->body,
                    'has_attachment' => ! empty($latest->attachment_path),
                    'sender_id' => $latest->sender_id,
                    'sender_name' => $latest->sender?->name ?? 'User',
                    'created_at' => $latest->created_at?->toISOString(),
                ] : null,
                'unread_count' => $conv->unreadCountFor($user),
                'last_message_at' => ($conv->last_message_at ?? $conv->created_at)?->toISOString(),
                'created_at' => $conv->created_at?->toISOString(),
            ];
        });

        return $this->successResponse([
            'conversations' => $data,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ], 'Conversations retrieved successfully');
    }

    /**
     * Get a specific conversation with message history and context.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::with(['creator', 'users', 'participants'])->findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have permission to view this conversation.');
        }

        // Mark as read for the viewer
        $this->performMarkAsRead($conversation, $user);

        // Fetch messages
        $messages = $conversation->messages()
            ->with('sender:id,name,phone')
            ->orderBy('id', 'asc')
            ->take(200)
            ->get();

        // Build context object
        $contextData = null;
        if ($conversation->context_type === 'order' && $conversation->context_id) {
            $order = Order::with('farm')->find($conversation->context_id);
            if ($order) {
                $contextData = [
                    'type' => 'order',
                    'id' => $order->id,
                    'invoice_number' => $order->invoice_number,
                    'status' => $order->status,
                    'total' => $order->total,
                    'farm_name' => $order->farm?->farm_name,
                ];
            }
        } elseif ($conversation->context_type === 'service_request' && $conversation->context_id) {
            $sr = ServiceRequest::with(['farm', 'assignedPractitioner', 'farmer'])->find($conversation->context_id);
            if ($sr) {
                $contextData = [
                    'type' => 'service_request',
                    'id' => $sr->id,
                    'request_type' => $sr->type,
                    'status' => $sr->status,
                    'urgency' => $sr->urgency,
                    'farm_name' => $sr->farm?->farm_name,
                    'assigned_to' => $sr->assignedPractitioner?->name,
                ];
            }
        }

        $otherParty = $conversation->getDisplayParty($user);

        return $this->successResponse([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'context_type' => $conversation->context_type,
                'context_id' => $conversation->context_id,
                'context_label' => $conversation->getContextLabel(),
                'context_data' => $contextData,
                'other_party' => $otherParty,
                'last_message_at' => $conversation->last_message_at?->toISOString(),
                'created_at' => $conversation->created_at?->toISOString(),
            ],
            'messages' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'sender_id' => $m->sender_id,
                'sender' => [
                    'id' => $m->sender->id,
                    'name' => $m->sender->name,
                    'phone' => $m->sender->phone,
                    'role' => $m->sender->roles->first()?->name ?? 'farmer',
                ],
                'body' => $m->body,
                'attachment_path' => $m->attachment_path,
                'attachment_url' => $m->attachment_url,
                'read_at' => $m->read_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
            ]),
        ], 'Conversation loaded successfully');
    }

    /**
     * Start a new conversation or return an existing matching open conversation.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $type = $validated['type'];
        $contextType = $validated['context_type'] ?? null;
        $contextId = $validated['context_id'] ?? null;

        $conversation = null;

        if ($type === 'support') {
            // Find existing open support conversation with matching context for this user
            $existing = Conversation::where('type', 'support')
                ->where('status', 'open')
                ->where('context_type', $contextType)
                ->where('context_id', $contextId)
                ->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('participants', fn ($pq) => $pq->where('user_id', $user->id));
                })
                ->first();

            if ($existing) {
                $conversation = $existing;
            } else {
                $conversation = Conversation::create([
                    'type' => 'support',
                    'context_type' => $contextType,
                    'context_id' => $contextId,
                    'status' => 'open',
                    'created_by' => $user->id,
                    'last_message_at' => now(),
                ]);

                // Add user as participant
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                ], [
                    'last_read_at' => now(),
                ]);
            }
        } elseif ($type === 'practitioner') {
            if ($contextType !== 'service_request' || ! $contextId) {
                return $this->errorResponse('A practitioner conversation must be linked to a valid service request.', 422);
            }

            $serviceRequest = ServiceRequest::with(['farmer', 'assignedPractitioner'])->find($contextId);
            if (! $serviceRequest) {
                return $this->notFoundResponse('Service request not found.');
            }

            // Enforce requirement: only available once assigned, and remains available through completed
            if (! in_array($serviceRequest->status, ['assigned', 'in_progress', 'completed']) || ! $serviceRequest->assigned_to) {
                return $this->errorResponse('Chat with a practitioner is only available once the service request has been assigned.', 422);
            }

            // Verify user is either the farmer, the assigned practitioner, or an admin
            $isFarmer = (int) $serviceRequest->farmer_id === (int) $user->id;
            $isPractitioner = (int) $serviceRequest->assigned_to === (int) $user->id;
            $isAdmin = $user->hasRole('admin');

            if (! $isFarmer && ! $isPractitioner && ! $isAdmin) {
                return $this->forbiddenResponse('You can only start a chat with the practitioner assigned to your own service request.');
            }

            // Check if conversation already exists for this service request
            $existing = Conversation::where('type', 'practitioner')
                ->where('context_type', 'service_request')
                ->where('context_id', $serviceRequest->id)
                ->first();

            if ($existing) {
                $conversation = $existing;
                if ($conversation->status === 'closed') {
                    $conversation->update(['status' => 'open']);
                }
            } else {
                $conversation = Conversation::create([
                    'type' => 'practitioner',
                    'context_type' => 'service_request',
                    'context_id' => $serviceRequest->id,
                    'status' => 'open',
                    'created_by' => $user->id,
                    'last_message_at' => now(),
                ]);

                // Add both farmer and practitioner as participants
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $serviceRequest->farmer_id,
                ], ['last_read_at' => $isFarmer ? now() : null]);

                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $serviceRequest->assigned_to,
                ], ['last_read_at' => $isPractitioner ? now() : null]);
            }
        }

        // If an initial message was passed, create and dispatch it
        $initialMessage = null;
        if (! empty($validated['message']) || $request->hasFile('attachment')) {
            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('chat_attachments', 'public');
            }

            $initialMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $validated['message'] ?? null,
                'attachment_path' => $attachmentPath,
                'read_at' => null,
            ]);

            $conversation->update(['last_message_at' => now()]);

            // Broadcast real-time event
            broadcast(new MessageSent($initialMessage));

            // Trigger notification to recipient
            $this->notifyRecipients($conversation, $initialMessage, $user);
        }

        return $this->successResponse([
            'conversation_id' => $conversation->id,
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'status' => $conversation->status,
                'context_type' => $conversation->context_type,
                'context_id' => $conversation->context_id,
                'context_label' => $conversation->getContextLabel(),
                'other_party' => $conversation->getDisplayParty($user),
            ],
            'initial_message' => $initialMessage ? [
                'id' => $initialMessage->id,
                'body' => $initialMessage->body,
                'attachment_url' => $initialMessage->attachment_url,
                'created_at' => $initialMessage->created_at?->toISOString(),
            ] : null,
        ], 'Conversation initialized successfully', 201);
    }

    /**
     * Send a message in an existing conversation.
     */
    public function sendMessage(SendChatMessageRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have permission to post messages in this conversation.');
        }

        if ($conversation->status === 'closed') {
            return $this->errorResponse('This conversation has been closed. Please open or resume a new request.', 422);
        }

        $validated = $request->validated();
        $attachmentPath = null;

        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('chat_attachments', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $attachmentPath,
            'read_at' => null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Ensure sender is in participants and update their read timestamp
        ConversationParticipant::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['last_read_at' => now()]
        );

        // Broadcast real-time event to conversation channel
        broadcast(new MessageSent($message));

        // Notify other participants via existing AdminNotification infrastructure
        $this->notifyRecipients($conversation, $message, $user);

        return $this->successResponse([
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'sender_id' => $message->sender_id,
                'sender' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'role' => $user->roles->first()?->name ?? 'farmer',
                ],
                'body' => $message->body,
                'attachment_path' => $message->attachment_path,
                'attachment_url' => $message->attachment_url,
                'read_at' => null,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 'Message sent successfully', 201);
    }

    /**
     * Mark all unread messages in conversation as read for the current user.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse();
        }

        $this->performMarkAsRead($conversation, $user);

        return $this->successResponse(null, 'Conversation marked as read');
    }

    /**
     * Close a conversation.
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have permission to close this conversation.');
        }

        $conversation->update(['status' => 'closed']);

        return $this->successResponse([
            'id' => $conversation->id,
            'status' => 'closed',
        ], 'Conversation closed successfully');
    }

    /**
     * Reopen a closed conversation.
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have permission to reopen this conversation.');
        }

        $conversation->update(['status' => 'open']);

        return $this->successResponse([
            'id' => $conversation->id,
            'status' => 'open',
        ], 'Conversation reopened successfully');
    }

    /**
     * Get global unread message count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user->hasAnyRole(['admin', 'data_entry_operator']);

        if ($isStaff) {
            // For staff, count unread support conversations where last message is unread
            $openSupportIds = Conversation::where('type', 'support')
                ->where('status', 'open')
                ->pluck('id');

            $unreadCount = Message::whereIn('conversation_id', $openSupportIds)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        } else {
            // For farmers / practitioners, count unread messages in their conversations
            $userConvIds = Conversation::where('status', 'open')
                ->where(function ($q) use ($user) {
                    $q->whereHas('participants', fn ($pq) => $pq->where('user_id', $user->id))
                      ->orWhere('created_by', $user->id);
                })
                ->pluck('id');

            $unreadCount = Message::whereIn('conversation_id', $userConvIds)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->count();
        }

        return $this->successResponse([
            'unread_count' => $unreadCount,
        ], 'Unread count retrieved successfully');
    }

    /**
     * Broadcast typing status for the authenticated user in a conversation.
     */
    public function typing(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($id);

        if (! $this->canAccessConversation($user, $conversation)) {
            return $this->forbiddenResponse('You do not have permission to access this conversation.');
        }

        $isTyping = $request->boolean('is_typing', true);
        $role = $user->roles->first()?->name ?? ($user->role ?? 'farmer');

        try {
            broadcast(new UserTyping(
                $conversation->id,
                $user->id,
                $user->name,
                $role,
                $isTyping
            ))->toOthers();
        } catch (\Throwable $e) {
            // Ignore broadcasting failure
        }

        return $this->successResponse(['is_typing' => $isTyping], 'Typing event broadcasted');
    }

    /**
     * Helper: Check if a user can access a conversation.
     */
    protected function canAccessConversation($user, Conversation $conversation): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($conversation->type === 'support' && $user->hasAnyRole(['admin', 'data_entry_operator'])) {
            return true;
        }

        if ($conversation->participants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($conversation->created_by === $user->id) {
            return true;
        }

        if ($conversation->type === 'practitioner' && $conversation->context_type === 'service_request') {
            $sr = ServiceRequest::find($conversation->context_id);
            if ($sr && ((int) $sr->farmer_id === (int) $user->id || (int) $sr->assigned_to === (int) $user->id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper: Mark conversation read for a user.
     */
    protected function performMarkAsRead(Conversation $conversation, $user): void
    {
        ConversationParticipant::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['last_read_at' => now()]
        );

        $messagesToUpdate = Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->pluck('id')
            ->toArray();

        if (count($messagesToUpdate) > 0) {
            $now = now();
            Message::whereIn('id', $messagesToUpdate)
                ->update(['read_at' => $now]);

            try {
                broadcast(new MessagesSeen($conversation->id, $user->id, $messagesToUpdate, $now))->toOthers();
            } catch (\Throwable $e) {
                // Ignore broadcast failure
            }
        }

        // Also mark corresponding chat notifications as read for this user
        $notifQuery = AdminNotification::whereNull('read_at')
            ->where(function ($q) use ($conversation) {
                $q->where('data->conversation_id', $conversation->id)
                  ->orWhere('data->conversation_id', (string) $conversation->id);
            });

        if ($user->hasAnyRole(['admin', 'data_entry_operator'])) {
            $notifQuery->where(function ($q) use ($user) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', $user->id);
            });
        } else {
            $notifQuery->where('user_id', $user->id);
        }

        $notifQuery->update(['read_at' => now()]);
    }

    /**
     * Helper: Trigger notifications for conversation participants.
     * Note: Ordinary chat messages rely on WebSocket broadcasts (MessageSent) and
     * the dedicated Messages nav badge/toast, and are intentionally excluded
     * from the general Admin Alerts notification bell.
     */
    protected function notifyRecipients(Conversation $conversation, Message $message, $sender): void
    {
        // Intentionally excluded from general notification bell to avoid duplicate clutter.
    }
}
