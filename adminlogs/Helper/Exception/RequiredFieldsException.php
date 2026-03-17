<?php

namespace MiniOrange\AdminLogs\Helper\Exception;

use MiniOrange\AdminLogs\Helper\AdminLogsMessages;

/**
 * Exception denotes that user has not entered all the requried fields.
 */
class RequiredFieldsException extends \Exception
{
    public function __construct()
    {
        $message     = AdminLogsMessages::parse('REQUIRED_FIELDS');
        $code         = 104;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
