<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MimoSmsGateway implements SmsGatewayInterface
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $senderId = null,
        protected string $endpoint = 'https://api.mimosms.com/api/SmsSending/SendSingleSms',
        protected int $timeout = 5
    ) {
        $this->apiKey = $this->apiKey ?? config('sms.gateways.mimosms.api_key');
        $this->senderId = $this->senderId ?? config('sms.gateways.mimosms.sender_id', 'FarmLink');
        $this->endpoint = config('sms.gateways.mimosms.endpoint', $this->endpoint);
        $this->timeout = (int) config('sms.gateways.mimosms.timeout', $this->timeout);
    }

    public function send(string $phone, string $message): bool
    {
        if (empty($this->apiKey)) {
            Log::warning('[SMS GATEWAY: MIMOSMS] Missing API key');
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)->acceptJson()->post($this->endpoint, [
                'ApiKey' => $this->apiKey,
                'SenderId' => $this->senderId,
                'PhoneNumber' => $phone,
                'Message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success' || ($data['Status'] ?? '') === 'Success' || ($data['code'] ?? 0) === 200) {
                    Log::info('[SMS GATEWAY: MIMOSMS] SMS dispatched successfully', [
                        'phone' => $phone,
                        'data' => $data,
                    ]);
                    return true;
                }

                Log::warning('[SMS GATEWAY: MIMOSMS] API returned non-success payload', [
                    'phone' => $phone,
                    'data' => $data,
                ]);
                return false;
            }

            Log::warning('[SMS GATEWAY: MIMOSMS] HTTP request failed', [
                'phone' => $phone,
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('[SMS GATEWAY: MIMOSMS] Connection exception: ' . $e->getMessage(), [
                'phone' => $phone,
            ]);
            return false;
        }
    }

    public function getName(): string
    {
        return 'mimosms';
    }
}
