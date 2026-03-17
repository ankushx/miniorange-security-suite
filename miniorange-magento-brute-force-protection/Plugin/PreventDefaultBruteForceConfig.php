<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Config\Model\ResourceModel\Config as ResourceConfig;
use Psr\Log\LoggerInterface;

/**
 * Plugin to prevent Magento's default brute force and CAPTCHA settings from being enabled
 * This ensures these settings always remain at 0 (disabled) even if someone tries to change them
 */
class PreventDefaultBruteForceConfig
{
    /**
     * Configuration paths that must always be 0
     */
    protected $protectedPaths = [
        'admin/security/lockout_threshold',
        'admin/security/max_number_password_reset_requests',
        'admin/security/min_time_between_password_reset_requests',
        'customer/password/lockout_failures',
        'customer/password/lockout_threshold',
        'customer/password/max_number_password_reset_requests',
        'customer/password/min_time_between_password_reset_requests',
        'admin/captcha/enable',
        'customer/captcha/enable',
    ];

    protected $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Intercept config save via WriterInterface and force protected paths to 0
     *
     * @param WriterInterface $subject
     * @param string $path
     * @param mixed $value
     * @param string $scope
     * @param int $scopeId
     * @return array
     */
    public function beforeSave(
        WriterInterface $subject,
        $path,
        $value,
        $scope = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeId = 0
    ) {
        // Check if this is a protected path
        if (in_array($path, $this->protectedPaths)) {
            // If someone tries to set a non-zero value, force it to 0
            if ($value != 0 && $value != '0') {
                $this->logger->info(
                    "PreventDefaultBruteForceConfig: Attempted to set '{$path}' to '{$value}' via WriterInterface, " .
                    "but forcing it to 0 to keep Magento's default brute force protection disabled."
                );
                $value = 0;
            }
        }

        return [$path, $value, $scope, $scopeId];
    }

    /**
     * Intercept config save via ResourceModel Config and force protected paths to 0
     *
     * @param ResourceConfig $subject
     * @param string $path
     * @param mixed $value
     * @param string $scope
     * @param int $scopeId
     * @return array
     */
    public function beforeSaveConfig(
        ResourceConfig $subject,
        $path,
        $value,
        $scope = \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeId = 0
    ) {
        // Check if this is a protected path
        if (in_array($path, $this->protectedPaths)) {
            // If someone tries to set a non-zero value, force it to 0
            if ($value != 0 && $value != '0') {
                $this->logger->info(
                    "PreventDefaultBruteForceConfig: Attempted to set '{$path}' to '{$value}' via ResourceModel, " .
                    "but forcing it to 0 to keep Magento's default brute force protection disabled."
                );
                $value = 0;
            }
        }

        return [$path, $value, $scope, $scopeId];
    }

}

