<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Integration\Api\AdminTokenServiceInterface;
use MiniOrange\BruteForceProtection\Plugin\AuthPlugin;
use Magento\Framework\Exception\AuthenticationException;

/**
 * Plugin for Admin Token Service to add BruteForce protection
 * Protects the REST API endpoint for admin token creation
 */
class AdminTokenPlugin
{
    protected $authPlugin;

    public function __construct(
        AuthPlugin $authPlugin
    ) {
        $this->authPlugin = $authPlugin;
    }

    /**
     * Around plugin to add brute force protection to admin token creation
     * 
     * @param AdminTokenServiceInterface $subject
     * @param \Closure $proceed
     * @param string $username
     * @param string $password
     * @return string
     * @throws AuthenticationException
     */
    public function aroundCreateAdminAccessToken(
        AdminTokenServiceInterface $subject,
        \Closure $proceed,
        $username,
        $password
    ) {
        // Check if username/password is empty
        if (empty($username) || empty($password)) {
            throw new AuthenticationException(__('A login and a password are required.'));
        }
        
        // Pre-check: lockout and delay BEFORE authentication (convert boolean to exception)
        $shouldBlock = $this->authPlugin->preCheckAdminLockoutAndDelay($username);
        if ($shouldBlock) {
            // Get the message from messageManager or create appropriate exception
            $lockedAccountData = $this->authPlugin->getAdminLockedAccountFromDb($username);
            if ($lockedAccountData && $lockedAccountData['lock_type'] === 'permanent') {
                throw new AuthenticationException(__('Your admin account has been permanently locked due to multiple failed login attempts. Please contact administrator.'));
            } elseif ($lockedAccountData && $lockedAccountData['lock_type'] === 'temporary') {
                $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
                $currentTime = time();
                if ($currentTime < $lockoutEndTime) {
                    $remainingTime = $lockoutEndTime - $currentTime;
                    $minutes = floor($remainingTime / 60);
                    $seconds = $remainingTime % 60;
                    if ($minutes > 0) {
                        throw new AuthenticationException(__('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 minutes and %2 seconds.', $minutes, $seconds));
                    } else {
                        throw new AuthenticationException(__('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 seconds.', $seconds));
                    }
                }
            } else {
                // Delay case - get delay info from session
                $bruteForceUtility = $this->authPlugin->getBruteForceUtility();
                // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
                $maxAttemptsDelayConfig = $bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
                $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                
                // If delay threshold is 0, delay feature is disabled - skip delay check
                if ($maxAttemptsDelay > 0) {
                    $delayKey = 'admin_login_delay_' . hash('sha256', $username);
                    $delayStart = $bruteForceUtility->getSessionValue($delayKey);
                    if ($delayStart) {
                        $delaySeconds = $bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/delay_seconds', 'default', 0) ?: 30;
                        $elapsed = time() - $delayStart;
                        if ($elapsed < $delaySeconds) {
                            $remaining = $delaySeconds - $elapsed;
                            $minutes = floor($remaining / 60);
                            $seconds = $remaining % 60;
                            if ($minutes > 0) {
                                throw new AuthenticationException(__('Too many failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds));
                            } else {
                                throw new AuthenticationException(__('Too many failed login attempts. Please wait %1 seconds before trying again.', $seconds));
                            }
                        }
                    }
                }
            }
        }

        try {
            // Attempt to create the token
            $token = $proceed($username, $password);
            
            // Reset failed attempts and clear all protection flags on successful login
            $bruteForceUtility = $this->authPlugin->getBruteForceUtility();
            $loginAttemptsKey = $this->authPlugin->getAdminLoginAttemptsSessionKey($username);
            $bruteForceUtility->setSessionValue($loginAttemptsKey, 0);
            
            // Clear admin lockout from session
            $lockoutKey = 'admin_locked_' . hash('sha256', $username);
            $bruteForceUtility->setSessionValue($lockoutKey, null);

            // Clear delay flag
            $delayKey = 'admin_login_delay_' . hash('sha256', $username);
            $bruteForceUtility->setSessionValue($delayKey, null);
            
            // Clear admin lockout from database
            $this->authPlugin->deleteAdminLockoutFromDb($username);

            return $token;

        } catch (AuthenticationException $e) {
            // Check BruteForce protection on authentication failure
            $shouldBlock = $this->authPlugin->checkBruteForceProtectionAdmin($username);
            
            // If brute force protection triggered, throw appropriate exception
            if ($shouldBlock) {
                $lockedAccountData = $this->authPlugin->getAdminLockedAccountFromDb($username);
                if ($lockedAccountData && $lockedAccountData['lock_type'] === 'permanent') {
                    throw new AuthenticationException(__('Your admin account has been permanently locked due to multiple failed login attempts. Please contact administrator.'));
                } elseif ($lockedAccountData && $lockedAccountData['lock_type'] === 'temporary') {
                    $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
                    $currentTime = time();
                    if ($currentTime < $lockoutEndTime) {
                        $remainingTime = $lockoutEndTime - $currentTime;
                        $minutes = floor($remainingTime / 60);
                        $seconds = $remainingTime % 60;
                        if ($minutes > 0) {
                            throw new AuthenticationException(__('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 minutes and %2 seconds.', $minutes, $seconds));
                        } else {
                            throw new AuthenticationException(__('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 seconds.', $seconds));
                        }
                    }
                } else {
                    // Delay case
                    $bruteForceUtility = $this->authPlugin->getBruteForceUtility();
                    $loginAttemptsKey = $this->authPlugin->getAdminLoginAttemptsSessionKey($username);
                    $attempts = $bruteForceUtility->getSessionValue($loginAttemptsKey) ?? 0;
                    // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
                    $maxAttemptsDelayConfig = $bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
                    $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                    $delaySeconds = $bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/delay_seconds', 'default', 0) ?: 30;
                    
                    // If delay threshold is 0, delay feature is disabled - skip delay check
                    if ($maxAttemptsDelay > 0 && $attempts >= $maxAttemptsDelay) {
                        $delayKey = 'admin_login_delay_' . hash('sha256', $username);
                        $delayStart = $bruteForceUtility->getSessionValue($delayKey);
                        if ($delayStart) {
                            $elapsed = time() - $delayStart;
                            if ($elapsed < $delaySeconds) {
                                $remaining = $delaySeconds - $elapsed;
                                $minutes = floor($remaining / 60);
                                $seconds = $remaining % 60;
                                if ($minutes > 0) {
                                    throw new AuthenticationException(__('Too many failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds));
                                } else {
                                    throw new AuthenticationException(__('Too many failed login attempts. Please wait %1 seconds before trying again.', $seconds));
                                }
                            }
                        }
                    }
                }
            }
            
            // Otherwise, throw the original authentication exception
            throw $e;

        } catch (\Exception $e) {
            throw new AuthenticationException(__('Invalid login or password.'));
        }
    }
}

