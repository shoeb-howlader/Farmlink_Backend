<?php

namespace App\Services\Sms\Gateways;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

class LogGateway implements SmsGatewayInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[SMS GATEWAY: LOG] Dispatched SMS', [
            'gateway' => 'log',
            'phone' => $phone,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);

        return true;
    }

    public function getName(): string
    {
        return 'log';
    }
}
