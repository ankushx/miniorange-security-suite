<?php

namespace MiniOrange\TwoFA\Helper\Exception;

use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that user has not entered all the requried fields.
 */
class RequiredFieldsException extends \Exception
{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('REQUIRED_FIELDS');
        $code         = 104;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
