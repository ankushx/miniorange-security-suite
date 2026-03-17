<?php

namespace MiniOrange\TwoFA\Helper\Exception;

use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that there was an Invalid Operation
 */
class PasswordResetFailedException extends \Exception
{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('ERROR_OCCURRED');
        $code         = 116;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
