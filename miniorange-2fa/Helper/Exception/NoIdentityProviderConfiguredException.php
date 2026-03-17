<?php

namespace MiniOrange\TwoFA\Helper\Exception;

use MiniOrange\TwoFA\Helper\TwoFAMessages;

/**
 * Exception denotes that user has not configured a SP.
 */
class NoIdentityProviderConfiguredException extends \Exception
{
    public function __construct()
    {
        $message     = TwoFAMessages::parse('NO_IDP_CONFIG');
        $code         = 101;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
