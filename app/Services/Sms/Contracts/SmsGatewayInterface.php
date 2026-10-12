<?php

namespace App\Services\Sms\Contracts;

interface SmsGatewayInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $phone Recipient phone number (e.g. 01712345678)
     * @param string $message Text message payload
     * @return bool True if successfully sent/accepted by gateway
     */
    public function send(string $phone, string $message): bool;

    /**
     * Returns the name of the gateway provider.
     *
     * @return string
     */
    public function getName(): string;
}
