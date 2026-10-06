<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.' . $this->message->conversation_id),
        ];

        $conversation = $this->message->conversation;
        if ($conversation) {
            $sender = $this->message->sender;
            $isSenderStaff = $sender->hasAnyRole(['admin', 'data_entry_operator']);

            if ($conversation->type === 'support') {
                if (! $isSenderStaff) {
                    $channels[] = new PrivateChannel('support.inbox');
                } else {
                    $farmer = $conversation->users()->whereDoesntHave('roles', function ($q) {
                        $q->whereIn('name', ['admin', 'data_entry_operator']);
                    })->first() ?? $conversation->creator;

                    if ($farmer && (int) $farmer->id !== (int) $this->message->sender_id) {
                        $channels[] = new PrivateChannel('user.' . $farmer->id);
                    }
                }
            } elseif ($conversation->type === 'practitioner') {
                $otherParticipant = $conversation->participants()
                    ->where('user_id', '!=', $this->message->sender_id)
                    ->first();

                $recipientId = $otherParticipant?->user_id;

                if (! $recipientId && $conversation->context_type === 'service_request') {
                    $sr = \App\Models\ServiceRequest::find($conversation->context_id);
                    if ($sr) {
                        $recipientId = ((int) $sr->farmer_id === (int) $this->message->sender_id) ? $sr->assigned_to : $sr->farmer_id;
                    }
                }

                if ($recipientId) {
                    $channels[] = new PrivateChannel('user.' . $recipientId);
                }
            }
        }

        return $channels;
    }

    /**
     * Broadcast event name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        $conversation = $this->message->conversation;

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'conversation_type' => $conversation?->type,
                'context_label' => $conversation?->getContextLabel(),
                'sender_id' => $this->message->sender_id,
                'sender' => [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'phone' => $sender->phone,
                    'role' => $sender->roles->first()?->name ?? 'farmer',
                    'avatar' => $sender->avatar_url ?? null,
                ],
                'body' => $this->message->body,
                'attachment_path' => $this->message->attachment_path,
                'attachment_url' => $this->message->attachment_url,
                'read_at' => $this->message->read_at?->toISOString(),
                'created_at' => $this->message->created_at?->toISOString(),
            ],
        ];
    }
}
