<?php

namespace MiniOrange\AdminLogs\Helper\Exception;

use MiniOrange\AdminLogs\Helper\AdminLogsMessages;

/**
 * Exception denotes that user has not completed his registration.
 */
class NotRegisteredException extends \Exception
{
    public function __construct()
    {
        $message     = AdminLogsMessages::parse('NOT_REG_ERROR');
        $code         = 102;
        parent::__construct($message, $code, null);
    }

    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}
