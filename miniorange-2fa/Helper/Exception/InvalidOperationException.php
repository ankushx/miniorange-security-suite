<?php

namespace MiniOrange\TwoFA\Helper\Exception;

use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that there was an Invalid Operation
 */
class InvalidOperationException extends \Exception
{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('INVALID_OP');
        $code         = 105;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
