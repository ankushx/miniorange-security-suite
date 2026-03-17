<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Customer\Controller\Account\LoginPost;
use MiniOrange\BruteForceProtection\Model\BruteForceService;

/**
 * Plugin for Customer LoginPost controller to add BruteForce protection
 * Works with both Magento\Customer\Controller\Account\LoginPost and MiniOrange\TwoFA\Controller\Account\LoginPost
 */
class LoginPostPlugin
{
    protected $bruteForceService;

    public function __construct(
        BruteForceService $bruteForceService
    ) {
        $this->bruteForceService = $bruteForceService;
    }

    /**
     * Around execute plugin to add brute force protection
     * 
     * @param LoginPost|object $subject - Can be Magento\Customer\Controller\Account\LoginPost or MiniOrange\TwoFA\Controller\Account\LoginPost
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute($subject, \Closure $proceed)
    {
        return $this->bruteForceService->processLogin($subject, $proceed);
    }
}
