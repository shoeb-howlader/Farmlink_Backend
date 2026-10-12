<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GreenwebGateway implements SmsGatewayInterface
{
    public function __construct(
        protected ?string $token = null,
        protected string $endpoint = 'https://api.greenweb.com.bd/api.php',
        protected int $timeout = 5
    ) {
        $this->token = $this->token ?? config('sms.gateways.greenweb.token');
        $this->endpoint = config('sms.gateways.greenweb.endpoint', $this->endpoint);
        $this->timeout = (int) config('sms.gateways.greenweb.timeout', $this->timeout);
    }

    public function send(string $phone, string $message): bool
    {
        if (empty($this->token)) {
            Log::warning('[SMS GATEWAY: GREENWEB] Missing API token');
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)->asForm()->post($this->endpoint, [
                'token' => $this->token,
                'to' => $phone,
                'message' => $message,
            ]);

            if ($response->successful()) {
                $body = $response->body();
                // Greenweb returns "Ok: ..." on success
                if (str_contains(strtolower($body), 'ok') || str_contains($body, '100')) {
                    Log::info('[SMS GATEWAY: GREENWEB] SMS dispatched successfully', [
                        'phone' => $phone,
                        'response' => $body,
                    ]);
                    return true;
                }

                Log::warning('[SMS GATEWAY: GREENWEB] API responded with error body', [
                    'phone' => $phone,
                    'body' => $body,
                ]);
                return false;
            }

            Log::warning('[SMS GATEWAY: GREENWEB] HTTP request failed', [
                'phone' => $phone,
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::warning('[SMS GATEWAY: GREENWEB] Connection exception: ' . $e->getMessage(), [
                'phone' => $phone,
            ]);
            return false;
        }
    }

    public function getName(): string
    {
        return 'greenweb';
    }
}
