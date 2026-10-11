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

        // For incoming calls in support chats, also notify the support inbox channel
        // so all online staff receive the ring banner immediately.
        $signalType = $this->payload['signal_type'] ?? '';
        if ($signalType === 'initiate' || $signalType === 'ring') {
            $conversation = Conversation::find($this->conversationId);
            if ($conversation && $conversation->type === 'support') {
                $channels[] = new PrivateChannel('support.inbox');
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
