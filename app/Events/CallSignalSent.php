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

        $signalType = $this->payload['signal_type'] ?? '';
        $senderId = $this->payload['sender_id'] ?? null;

        if ($conversation->type === 'support') {
            // Support hunt-group: broadcast to support.inbox for initiate/ring/claim/end/reject
            $channels[] = new PrivateChannel('support.inbox');

            // If a staff member is calling the farmer, broadcast to the farmer's user channel
            $farmer = $conversation->users()->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['admin', 'data_entry_operator']);
            })->first() ?? $conversation->creator;

            if ($farmer && (int) $farmer->id !== (int) $senderId) {
                $channels[] = new PrivateChannel('user.' . $farmer->id);
            }
        } elseif ($conversation->type === 'practitioner') {
            // Find other participant
            $otherParticipant = $conversation->participants()
                ->where('user_id', '!=', $senderId)
                ->first();

            $recipientId = $otherParticipant?->user_id;

            if (! $recipientId && $conversation->context_type === 'service_request') {
                $sr = \App\Models\ServiceRequest::find($conversation->context_id);
                if ($sr && $senderId) {
                    $recipientId = ((int) $sr->farmer_id === (int) $senderId) ? $sr->assigned_to : $sr->farmer_id;
                }
            }

            if ($recipientId) {
                $channels[] = new PrivateChannel('user.' . $recipientId);
            }
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
