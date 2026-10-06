<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\Broadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FanOutBroadcastNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int>  $recipientIds
     */
    public function __construct(
        public Broadcast $broadcast,
        public array $recipientIds,
        public int $senderId,
        public string $senderName
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->recipientIds)) {
            return;
        }

        $now = now();
        $notificationRows = [];

        foreach ($this->recipientIds as $userId) {
            $notificationRows[] = [
                'user_id' => $userId,
                'broadcast_id' => $this->broadcast->id,
                'type' => 'broadcast',
                'title' => $this->broadcast->title,
                'message' => $this->broadcast->message,
                'data' => json_encode([
                    'broadcast_id' => $this->broadcast->id,
                    'sender_id' => $this->senderId,
                    'sender_name' => $this->senderName,
                    'target_audience' => $this->broadcast->target_audience,
                ]),
                'read_at' => null,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($notificationRows, 500) as $chunk) {
            AdminNotification::insert($chunk);
        }

        Log::info("Broadcast notification #{$this->broadcast->id} successfully fanned out to " . count($this->recipientIds) . " recipients.");
    }
}
