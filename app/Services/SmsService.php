<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an SMS message with an OTP code to a recipient phone number.
     *
     * @param string $phone The recipient phone number (e.g. 01712345678)
     * @param string $otp 6-digit OTP verification code
     * @return bool True if successfully dispatched/queued
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        $message = "Your FarmLink verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";

        // Log SMS dispatch for development, auditing, and testing inspection
        Log::info("[SMS GATEWAY] Dispatched OTP SMS to recipient", [
            'phone' => $phone,
            'otp' => $otp,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Future integration point for live Bangladesh SMS Gateway API (e.g. SSL Wireless, Greenweb, etc.)
        return true;
    }
}
