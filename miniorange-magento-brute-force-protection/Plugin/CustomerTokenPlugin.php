<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Integration\Api\CustomerTokenServiceInterface;
use MiniOrange\BruteForceProtection\Model\BruteForceService;
use Magento\Framework\Exception\AuthenticationException;

/**
 * Plugin for Customer Token Service to add BruteForce protection
 * Protects the REST API endpoint for customer token creation
 */
class CustomerTokenPlugin
{
    protected $bruteForceService;

    public function __construct(
        BruteForceService $bruteForceService
    ) {
        $this->bruteForceService = $bruteForceService;
    }

    /**
     * Around plugin to add brute force protection to customer token creation
     * 
     * @param CustomerTokenServiceInterface $subject
     * @param \Closure $proceed
     * @param string $username
     * @param string $password
     * @return string
     * @throws AuthenticationException
     */
    public function aroundCreateCustomerAccessToken(
        CustomerTokenServiceInterface $subject,
        \Closure $proceed,
        $username,
        $password
    ) {
        return $this->bruteForceService->processApiTokenCreation($subject, $proceed, $username, $password);
    }
}

