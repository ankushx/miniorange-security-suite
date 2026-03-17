<?php

namespace MiniOrange\AdminLogs\Helper\Exception;

use MiniOrange\AdminLogs\Helper\AdminLogsMessages;

/**
 * Exception denotes that admin didnot fill the required
 * support query form field values.
 */
class SupportQueryRequiredFieldsException extends \Exception
{
    public function __construct()
    {
        $message     = AdminLogsMessages::parse('REQUIRED_QUERY_FIELDS');
        $code         = 109;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
