<?php

namespace App\Services;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Gateways\GreenwebGateway;
use App\Services\Sms\Gateways\LogGateway;
use App\Services\Sms\Gateways\MimoSmsGateway;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS message with an OTP code to a recipient phone number with automatic gateway failover.
     *
     * @param string $phone The recipient phone number (e.g. 01712345678)
     * @param string $otp 6-digit OTP verification code
     * @return bool True if successfully dispatched by any gateway in the chain
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        $message = "Your FarmLink verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->sendMessage($phone, $message);
    }

    /**
     * Send an SMS message through the primary gateway with automatic fallback to secondary gateway.
     *
     * @param string $phone Recipient phone number
     * @param string $message Text content
     * @return bool True if dispatched by any gateway in the failover chain
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $chain = $this->resolveGatewayChain();

        foreach ($chain as $index => $gateway) {
            $gatewayName = $gateway->getName();

            try {
                if ($gateway->send($phone, $message)) {
                    if ($index > 0) {
                        Log::info("[SMS FAILOVER SUCCESS] Fallback gateway '{$gatewayName}' delivered message after previous gateway failure", [
                            'phone' => $phone,
                            'attempt_index' => $index,
                            'gateway' => $gatewayName,
                        ]);
                    }
                    return true;
                }

                Log::warning("[SMS GATEWAY RETRY] Gateway '{$gatewayName}' failed to deliver message. Attempting next provider...", [
                    'phone' => $phone,
                    'failed_gateway' => $gatewayName,
                    'remaining_gateways' => count($chain) - ($index + 1),
                ]);
            } catch (\Throwable $e) {
                Log::error("[SMS GATEWAY EXCEPTION] Gateway '{$gatewayName}' threw exception: " . $e->getMessage(), [
                    'phone' => $phone,
                    'failed_gateway' => $gatewayName,
                ]);
            }
        }

        Log::critical('[SMS FAILOVER EXHAUSTED] All configured SMS gateways failed to deliver message', [
            'phone' => $phone,
        ]);

        return false;
    }

    /**
     * Resolve ordered gateway chain (primary first, then fallback).
     *
     * @return SmsGatewayInterface[]
     */
    public function resolveGatewayChain(): array
    {
        $primaryDriver = config('sms.default', 'log');
        $fallbackDriver = config('sms.fallback', 'log');

        $drivers = array_unique([$primaryDriver, $fallbackDriver]);
        $instances = [];

        foreach ($drivers as $driver) {
            $gateway = $this->makeGateway($driver);
            if ($gateway) {
                $instances[] = $gateway;
            }
        }

        return !empty($instances) ? $instances : [$this->makeGateway('log')];
    }

    /**
     * Instantiate gateway driver by key.
     */
    public function makeGateway(string $driver): SmsGatewayInterface
    {
        return match (strtolower($driver)) {
            'greenweb' => app(GreenwebGateway::class),
            'mimosms' => app(MimoSmsGateway::class),
            default => app(LogGateway::class),
        };
    }
}
