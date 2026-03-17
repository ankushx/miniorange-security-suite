<?php

namespace MiniOrange\TwoFA\Helper\Exception;

use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that there was a password mismatch
 */
class PasswordMismatchException extends \Exception
{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('PASS_MISMATCH');
        $code         = 122;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
