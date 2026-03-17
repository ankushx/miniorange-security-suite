<?php

namespace MiniOrange\TwoFA\Helper\Exception;
use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that the user trying to log in
 * or register in the plugin already has an account
 * and that the credentials provided are incorrect
 */
class InvalidPasscodeException extends \Exception

{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('INVALID_PASSCODE');
        $code         = 140;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
