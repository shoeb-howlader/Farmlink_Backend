<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagesSeen implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $readAt;

    /**
     * @param int $conversationId
     * @param int $readerId
     * @param array<int> $messageIds
     * @param \Carbon\Carbon|null $readAt
     */
    public function __construct(
        public int $conversationId,
        public int $readerId,
        public array $messageIds,
        ?\DateTimeInterface $readAt = null
    ) {
        $this->readAt = ($readAt ?? now())->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversationId),
        ];
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'messages.seen';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'reader_id' => $this->readerId,
            'read_at' => $this->readAt,
            'message_ids' => $this->messageIds,
        ];
    }
}
