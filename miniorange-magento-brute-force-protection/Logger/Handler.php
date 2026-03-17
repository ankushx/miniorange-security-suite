<?php

namespace MiniOrange\BruteForceProtection\Logger;

/**
 * Class Handler
 * @package MiniOrange\BruteForceProtection\Logger
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    public $loggerType = \MiniOrange\BruteForceProtection\Logger\Logger::INFO;

    /**
     * File name
     * @var string
     */
    public $fileName = '/var/log/adminactivity.log';
}
