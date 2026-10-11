<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public int $conversationId,
        public array $payload
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.' . $this->conversationId),
        ];

        $conversation = Conversation::find($this->conversationId);
        if (! $conversation) {
            return $channels;
        }

        $senderId = $this->payload['sender_id'] ?? null;

        // 1. If support conversation, always broadcast to support hunt-group
        if ($conversation->type === 'support') {
            $channels[] = new PrivateChannel('support.inbox');
        }

        // 2. Direct broadcast to all conversation participants & other devices of sender on user.{id}
        $participantIds = $conversation->participants()
            ->pluck('user_id')
            ->toArray();

        if ($conversation->created_by) {
            $participantIds[] = (int) $conversation->created_by;
        }

        if ($senderId) {
            $participantIds[] = (int) $senderId;
        }

        if ($conversation->context_type === 'service_request' && $conversation->context_id) {
            $sr = \App\Models\ServiceRequest::find($conversation->context_id);
            if ($sr) {
                if ($sr->assigned_to) {
                    $participantIds[] = (int) $sr->assigned_to;
                }
                if ($sr->farmer_id) {
                    $participantIds[] = (int) $sr->farmer_id;
                }
            }
        }

        foreach (array_unique($participantIds) as $pid) {
            $channels[] = new PrivateChannel('user.' . $pid);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge([
            'conversation_id' => $this->conversationId,
            'timestamp' => now()->toIso8601String(),
        ], $this->payload);
    }
}
