<?php

namespace MiniOrange\TwoFA\Api;

/**
 * Headless API Interface
 */
interface HeadlessApiInterface
{
    /**
     * Sends an OTP (One-Time Password) for authentication.
     *
     * @param string $username The username or identifier of the recipient.
     * @param string $phone The phone number of the recipient.
     * @param string $authType The type of authentication.
     * @return array An array containing the result of the operation.
     */
    public function sendOtpApi(string $username, string $phone, string $authType): array;
}

