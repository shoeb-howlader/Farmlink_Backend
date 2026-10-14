<?php

namespace App\Jobs;

use App\Services\WebPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param int $userId
     * @param string $title
     * @param string $message
     * @param string|null $url
     * @param array<string, mixed> $extraData
     */
    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public ?string $url = null,
        public array $extraData = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            WebPushService::sendToUser(
                $this->userId,
                $this->title,
                $this->message,
                $this->url,
                $this->extraData
            );
        } catch (\Throwable $e) {
            Log::error("SendWebPushNotificationJob failed for user #{$this->userId}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}
