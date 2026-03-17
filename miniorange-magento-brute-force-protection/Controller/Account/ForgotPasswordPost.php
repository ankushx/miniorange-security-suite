<?php

namespace MiniOrange\BruteForceProtection\Controller\Account;

use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Customer\Model\AccountManagement;

/**
 * Customer forgot password controller with BruteForce protection
 */
class ForgotPasswordPost extends \Magento\Customer\Controller\Account\ForgotPasswordPost
{
    protected $bruteforceutility;
    protected $resourceConnection;
    protected $customerRepository;
    protected $inboxFactory;
    protected $escaper;
    protected $resultRedirectFactory;
    protected $storeManager;
    protected $transportBuilder;
    protected $inlineTranslation;
    
    public function __construct(
        Context $context,
        Session $customerSession,
        AccountManagementInterface $customerAccountManagement,
        Escaper $escaper,
        BruteForceUtility $bruteforceutility,
        ResourceConnection $resourceConnection,
        CustomerRepositoryInterface $customerRepository,
        InboxFactory $inboxFactory,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation
    ) {
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $escaper
        );
        $this->bruteforceutility = $bruteforceutility;
        $this->resourceConnection = $resourceConnection;
        $this->customerRepository = $customerRepository;
        $this->inboxFactory = $inboxFactory;
        $this->escaper = $escaper;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
    }

    public function execute()
    {
        $this->bruteforceutility->log_debug("=== BRUTEFORCE FORGOT PASSWORD CONTROLLER EXECUTED ===");

        if ($this->getRequest()->isPost()) {
            $email = (string)$this->getRequest()->getPost('email');
            $resultRedirect = $this->resultRedirectFactory->create();

            if (!empty($email)) {
                // Get thresholds to check if both are disabled (0)
                $storeId = $this->storeManager->getStore()->getId();
                $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay', 'stores', $storeId);
                $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 'stores', $storeId);
                $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                
                // If both thresholds are 0 (disabled), skip all brute force protection
                if ($maxAttemptsDelay <= 0 && $maxAttemptsLockoutTemporary <= 0) {
                    $this->bruteforceutility->log_debug("Customer Forgot Password - Both delay and temp lockout thresholds are 0 (disabled). Skipping all brute force protection.");
                    return parent::execute();
                }
                
                // Get customer ID
                $customerId = $this->getCustomerIdByEmail($email);
                
                if ($customerId) {
                    // Check if user is already in restriction lists
                    $inDelayList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'forgot_password_delay');
                    $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'forgot_password');
                    
                    // If user is already in either list, apply normal brute force protection
                    // Don't clear flags - we'll check actual counts in checkBruteForceProtection
                    if ($inDelayList || $inTempLockoutList) {
                        $this->bruteforceutility->log_debug("Customer Forgot Password - User already in restriction list. Applying normal brute force protection for customer ID: $customerId");
                        // Continue with normal brute force checks - limits will be checked in checkBruteForceProtection
                    } else {
                        // User not in list - check if limit is exceeded
                        // Check if limits are exceeded using helper functions
                        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($customerId, 'forgot_password_delay');
                        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($customerId, 'forgot_password');
                        
                        // If both limits exceeded, skip brute force protection entirely
                        if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                            $this->bruteforceutility->log_debug("Customer Forgot Password - User not in list and both limits exceeded. Skipping brute force protection for customer ID: $customerId");
                            // Let request proceed normally without brute force protection
                            return parent::execute();
                        }
                        
                        // If delay limit is NOT exceeded but temp lockout limit IS exceeded
                        if (!$delayLimitExceeded && $tempLockoutLimitExceeded) {
                            // Get delay threshold to check if it's disabled (0)
                            $storeId = $this->storeManager->getStore()->getId();
                            $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay', 'stores', $storeId);
                            $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                            
                            if ($maxAttemptsDelay <= 0) {
                                $this->bruteforceutility->log_debug("Customer Forgot Password - Temp lockout limit exceeded but delay threshold is 0 (disabled). Skipping brute force protection for customer ID: $customerId");
                                return parent::execute();
                            } else {
                                $this->bruteforceutility->log_debug("Customer Forgot Password - Temp lockout limit exceeded but delay threshold is enabled. Allowing delay feature for customer ID: $customerId");
                            }
                        }
                        
                        // If temp lockout limit is NOT exceeded but delay limit IS exceeded
                        if ($delayLimitExceeded && !$tempLockoutLimitExceeded) {
                            // Get temp lockout threshold to check if it's disabled (0)
                            $storeId = $this->storeManager->getStore()->getId();
                            $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 'stores', $storeId);
                            $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                            
                            if ($maxAttemptsLockoutTemporary <= 0) {
                                $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded but temp lockout threshold is 0 (disabled). Skipping brute force protection for customer ID: $customerId");
                                return parent::execute();
                            } else {
                                $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded but temp lockout threshold is enabled. Allowing temp lockout feature for customer ID: $customerId");
                            }
                        }
                        
                        // Store flags in session for use in brute force checks
                        $this->bruteforceutility->setSessionValue('delay_limit_exceeded', $delayLimitExceeded);
                        $this->bruteforceutility->setSessionValue('temp_lockout_limit_exceeded', $tempLockoutLimitExceeded);
                    }
                }

                // Count this attempt and enforce restrictions before processing
                $preCheck = $this->checkBruteForceProtection($email);
                if ($preCheck !== null) {
                    return $preCheck;
                }

                try {
                
                    $this->customerAccountManagement->initiatePasswordReset(
                        $email,
                        AccountManagement::EMAIL_RESET
                    );
                    
                    $this->messageManager->addSuccessMessage(
                        __(
                            'If there is an account associated with %1 you will receive an email with a link to reset your password.',
                            $this->escaper->escapeHtml($email)
                        )
                    );
                    
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;

                } catch (NoSuchEntityException $e) {
                    // Do not double-count; we already counted above
                    $this->messageManager->addSuccessMessage(
                        __(
                            'If there is an account associated with %1 you will receive an email with a link to reset your password.',
                            $this->escaper->escapeHtml($email)
                        )
                    );
                    $resultRedirect->setPath('customer/account/forgotpassword');
                    return $resultRedirect;

                } catch (\Exception $e) {
                    // Do not double-count; we already counted above
                    $this->messageManager->addErrorMessage(__('We\'re unable to send the password reset email.'));
                    $resultRedirect->setPath('customer/account/forgotpassword');
                    return $resultRedirect;
                }
            } else {
                $this->messageManager->addErrorMessage(__('Please enter your email.'));
                $resultRedirect->setPath('customer/account/forgotpassword');
                return $resultRedirect;
            }
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('customer/account/forgotpassword');
        return $resultRedirect;
    }

    /**
     * Get account-specific session key for forgot password attempts
     * Includes website identifier to avoid conflicts when same email exists in multiple websites
     * @param string $email
     * @return string
     */
    protected function getForgotPasswordAttemptsSessionKey($email)
    {
        $website = $this->getCurrentWebsite();
        $websiteId = $website ? hash('sha256', $website) : 'default';
        return 'forgot_password_attempts_' . hash('sha256', $email . '_' . $websiteId);
    }

    /**
     * Check BruteForce protection for customer forgot password
     * @param string $email
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkBruteForceProtection($email)
    {
        // Get current store ID to retrieve store-specific configuration (with inheritance)
        $storeId = $this->storeManager->getStore()->getId();
        
        // Get forgot password BruteForce settings for current store (will inherit from website → default if not set)
        // Allow 0 values to disable individual features - use null coalescing only for null/empty, not for 0
        $enabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_enabled', 'stores', $storeId);
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay', 'stores', $storeId);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_delay_seconds', 'stores', $storeId) ?: 30;
        $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 'stores', $storeId);
        $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
        $lockoutDurationMinutes = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_lockout_duration_minutes', 'stores', $storeId) ?: 30;
        $customerEmailTemplate = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_customer_email_template', 'stores', $storeId);

        
        $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Enabled: " . $enabled);
        $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Email: " . $email);
        
        if (!$enabled) {
            $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection is disabled. Skipping protection.");
            return null;
        }
        
        // Get customer ID for database operations
        $customerId = $this->getCustomerIdByEmail($email);
        if (!$customerId) {
            $this->bruteforceutility->log_debug("Customer not found for email: " . $email);
            // Still track attempts even if customer doesn't exist
        }
        
        // Check if user is already in restriction lists
        $inDelayList = false;
        $inTempLockoutList = false;
        if ($customerId) {
            $inDelayList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'forgot_password_delay');
            $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'forgot_password');
        }
        
        // Always check actual counts to determine if limits are exceeded (don't rely on session flags)
        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($customerId, 'forgot_password_delay');
        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($customerId, 'forgot_password');
        
        // If user is NOT in either list, check if both limits exceeded
        if (!$inDelayList && !$inTempLockoutList && $customerId) {
            // If both limits exceeded, skip brute force protection entirely
            if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                $this->bruteforceutility->log_debug("Customer Forgot Password - User not in list and both limits exceeded. Skipping brute force protection for customer ID: $customerId");
                return null; // Don't interfere, let Magento handle
            }
        }
        
        // Check if account is already locked (using database)
        if ($customerId) {
            $lockoutResult = $this->checkAccountLockoutFromDb($email, $customerId);
            if ($lockoutResult !== null) {
                return $lockoutResult; // Return redirect if locked
            }
        }

        $delayResult = $this->checkLoginDelayBeforeAuth($email, 'forgot_password');
        if ($delayResult !== null) {
            return $delayResult; // Return redirect if delay is active
        }

        // Get current session attempts (account-specific)
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        $sessionAttempts++;
        
        // Get locked account data from database
        // Get website for current store
        $website = $this->getCurrentWebsite();
        $lockedAccountData = $customerId ? $this->getLockedAccountFromDb($customerId, $website) : null;
        
        // Calculate total attempts
        // If there's a DB base (from previous lockout expiry), use it for cumulative tracking
        $websiteId = $website ? hash('sha256', $website) : 'default';
        $dbAttemptsBaseKey = 'forgot_password_db_attempts_base_' . hash('sha256', $email . '_' . $websiteId);
        $dbAttemptsBase = $this->bruteforceutility->getSessionValue($dbAttemptsBaseKey) ?? 0;
        
        if ($dbAttemptsBase > 0) {
            // Session was set to DB value after expiry, so total = DB base + (current session - DB base) = current session
            // But we need to account for the fact that session was set to DB value, so total = session
            $totalAttempts = $sessionAttempts;
        } else {
            // No DB base, use session attempts as is
            $totalAttempts = $sessionAttempts;
        }
        
        
        $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Session Attempts: $sessionAttempts, DB Attempts: " . ($lockedAccountData['failed_attempts'] ?? 0) . ", Total: $totalAttempts");
        
        $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, $sessionAttempts);
        $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Thresholds - Delay: $maxAttemptsDelay, Temp Lockout: $maxAttemptsLockoutTemporary (NO permanent for forgot password)");
        
        // 1. FIRST: Check for TEMPORARY lockout (NO permanent lockout for forgot password)
        // Only check if temporary lockout threshold is > 0 (feature enabled)
        if ($maxAttemptsLockoutTemporary > 0 && $totalAttempts >= $maxAttemptsLockoutTemporary) {
            // Check parity: if threshold and total attempts have same parity, trigger lockout
            // If different parity, allow the request (alternating allow/block pattern after expiry)
            $thresholdParity = $maxAttemptsLockoutTemporary % 2; // 0 for even, 1 for odd
            $totalAttemptsParity = $totalAttempts % 2; // 0 for even, 1 for odd
            
            if ($thresholdParity === $totalAttemptsParity) {
                // Same parity - trigger lockout
                $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - TEMPORARY lockout triggered (attempts: $totalAttempts >= $maxAttemptsLockoutTemporary, same parity)");
                
                // Check actual count to see if temp lockout limit is exceeded (only if user not in list)
                if (!$inTempLockoutList && $tempLockoutLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer Forgot Password - Temporary lockout limit exceeded. Skipping temp lockout, checking delay instead for customer ID: $customerId");
                    // Apply delay if user is in delay list OR delay limit is NOT exceeded
                    if ($inDelayList || !$delayLimitExceeded) {
                        // Apply delay instead of lockout when temp lockout limit is reached
                        $delayResult = $this->checkLoginDelay($email, $delaySeconds, 'forgot_password', $maxAttemptsDelay);
                        if ($delayResult !== null) {
                            // Add customer ID to the delay list if delay was applied (only if not already in list)
                            if ($customerId && !$inDelayList) {
                                $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password_delay');
                            }
                            return $delayResult; // Return redirect if delay is active
                        }
                        return null; // Delay expired, allow attempt
                    } else {
                        // Delay limit exceeded and user not in delay list - skip brute force protection
                        $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded and user not in delay list. Skipping brute force protection.");
                        return null;
                    }
                }
                
                // User in list or limit not exceeded - apply normal temp lockout
                // Only apply if user is in temp lockout list OR temp lockout limit is NOT exceeded
                if ($inTempLockoutList || !$tempLockoutLimitExceeded) {
                    $lockoutResult = $this->checkTemporaryLockout($email, $customerId, $lockedAccountData);
                    if ($lockoutResult !== null) {
                        // Add customer ID to the encrypted array if lockout was applied (only if not already in list)
                        if ($customerId && !$inTempLockoutList) {
                            $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password');
                        }
                        // Send email notification for temporary lockout (admin alert is already created in checkTemporaryLockout)
                        $this->sendTemporaryLockoutEmail($email, $customerEmailTemplate);
                        return $lockoutResult; // Return redirect if locked
                    }
                }
            } else {
                // Different parity - allow the request even though it's >= threshold
                $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Total attempts: $totalAttempts >= $maxAttemptsLockoutTemporary, but different parity (threshold: $thresholdParity, attempts: $totalAttemptsParity) - allowing request");
            }
        }
        
        // 2. SECOND: Check for login delay (only if not locked)
        // Also check delay if temp lockout is disabled and delay limit is not exceeded (apply delay forever with alternating pattern)
        if ($maxAttemptsDelay > 0) {
            $shouldCheckDelay = false;
            if ($maxAttemptsLockoutTemporary > 0) {
                // Temporary lockout is enabled
                if (!$inTempLockoutList && $tempLockoutLimitExceeded) {
                    // Temp lockout limit exceeded for new users - check delay even if temp lockout threshold is reached
                    // This allows delay to be applied instead of temp lockout
                    $shouldCheckDelay = ($totalAttempts >= $maxAttemptsDelay);
                } else {
                    // Normal case: check delay only if below temp lockout threshold
                    $shouldCheckDelay = ($totalAttempts >= $maxAttemptsDelay && $totalAttempts < $maxAttemptsLockoutTemporary);
                }
            } else {
                // Temporary lockout is disabled
                // Apply delay forever with alternating pattern if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    $shouldCheckDelay = ($totalAttempts >= $maxAttemptsDelay);
                } else {
                    // Delay limit exceeded and user not in delay list - skip delay
                    $shouldCheckDelay = false;
                }
            }
            
            if ($shouldCheckDelay) {
                $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Checking delay");
                // Check actual count to see if delay limit is exceeded (only if user not in list)
                if (!$inDelayList && $delayLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded. Skipping delay feature for customer ID: $customerId");
                    // Skip delay feature, but don't block - let request proceed
                    return null;
                }
                $delayResult = $this->checkLoginDelay($email, $delaySeconds, 'forgot_password', $maxAttemptsDelay);
                if ($delayResult !== null) {
                    // Add customer ID to the delay list if delay was applied (only if not already in list)
                    if ($customerId && !$inDelayList) {
                        $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password_delay');
                    }
                    return $delayResult; // Return redirect if delay is active
                }
            }
        } else if ($maxAttemptsLockoutTemporary > 0) {
            // Delay is disabled but temp lockout is enabled
            // If temp lockout limit is NOT exceeded, apply temp lockout forever with alternating pattern (for all attempts >= temp lockout threshold)
            if (!$inTempLockoutList && !$tempLockoutLimitExceeded && $totalAttempts >= $maxAttemptsLockoutTemporary) {
                // Check parity for alternating pattern
                $thresholdParity = $maxAttemptsLockoutTemporary % 2;
                $totalAttemptsParity = $totalAttempts % 2;
                
                if ($thresholdParity === $totalAttemptsParity) {
                    $this->bruteforceutility->log_debug("Customer Forgot Password BruteForce Protection - Delay disabled, applying TEMPORARY lockout forever (attempts: $totalAttempts >= $maxAttemptsLockoutTemporary, same parity)");
                    $lockoutResult = $this->checkTemporaryLockout($email, $customerId, $lockedAccountData);
                    if ($lockoutResult !== null) {
                        // Add customer ID to the encrypted array if lockout was applied (only if not already in list)
                        if ($customerId && !$inTempLockoutList) {
                            $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password');
                        }
                        // Send email notification for temporary lockout
                        $this->sendTemporaryLockoutEmail($email, $customerEmailTemplate);
                        return $lockoutResult; // Return redirect if locked
                    }
                }
            }
        }
        
        return null; // No action needed
    }

    /**
     * Check account lockout status from database
     * @param string $email
     * @param int $customerId
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkAccountLockoutFromDb($email, $customerId)
    {
        // Get website for current store
        $website = $this->getCurrentWebsite();
        $lockedAccountData = $this->getLockedAccountFromDb($customerId, $website);
        
        if (!$lockedAccountData) {
            return null; // No lockout data in DB
        }
        
        // Check temporary lockout (no permanent lockout for forgot password)
        if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['first_time_lockout'] && $lockedAccountData['lock_until']) {
            $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
            $currentTime = time();
            
            if ($currentTime < $lockoutEndTime) {
                $remainingTime = $lockoutEndTime - $currentTime;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $this->bruteforceutility->log_debug("Account is TEMPORARILY locked in DB. Remaining time: " . $minutes . " minutes and " . $seconds . " seconds.");
                
                if ($minutes > 0) {
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/forgotpassword');
                return $resultRedirect;
            } else {
                // Lockout has expired - check parity-based logic
                $dbFailedAttempts = $lockedAccountData['failed_attempts'];
                
                // Get temporary lockout threshold to check parity
                $storeId = $this->storeManager->getStore()->getId();
                $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 'stores', $storeId);
                $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                
                // Check parity with the NEXT attempt (DB + 1): if threshold and next attempt have same parity, block
                // If different parity, allow
                $nextAttempt = $dbFailedAttempts + 1;
                $thresholdParity = $maxAttemptsLockoutTemporary % 2; // 0 for even, 1 for odd
                $nextAttemptParity = $nextAttempt % 2; // 0 for even, 1 for odd
                
                if ($thresholdParity === $nextAttemptParity) {
                    // Same parity - block the request
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Threshold: $maxAttemptsLockoutTemporary (parity: $thresholdParity), DB attempts: $dbFailedAttempts, Next attempt: $nextAttempt (parity: $nextAttemptParity). Same parity - blocking request.");
                    
                    // Re-apply temporary lockout with next attempt count
                    $lockoutDurationMinutes = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_lockout_duration_minutes', 'stores', $storeId);
                    if ($lockoutDurationMinutes <= 0) {
                        $lockoutDurationMinutes = 30;
                    }
                    $lockoutDuration = date('Y-m-d H:i:s', time() + ($lockoutDurationMinutes * 60));
                    
                    // Get website for current store
                    $website = $this->getCurrentWebsite();
                    $this->saveLockedAccountToDb($customerId, $nextAttempt, 'temporary', $lockoutDuration, $email, 'customer', $website);
                    
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 minutes before trying again.', $lockoutDurationMinutes);
                    $this->messageManager->addError($message);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/forgotpassword');
                    return $resultRedirect;
                } else {
                    // Different parity - allow the request
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Threshold: $maxAttemptsLockoutTemporary (parity: $thresholdParity), DB attempts: $dbFailedAttempts, Next attempt: $nextAttempt (parity: $nextAttemptParity). Different parity - allowing request.");
                    
                    $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
                    // Set session to DB value (not incremented) so when request is processed, it will increment to nextAttempt
                    $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, $dbFailedAttempts);
                    
                    $dbAttemptsBaseKey = 'forgot_password_db_attempts_base_' . hash('sha256', $email . '_' . ($website ? hash('sha256', $website) : 'default'));
                    $this->bruteforceutility->setSessionValue($dbAttemptsBaseKey, $dbFailedAttempts);
                    
                    // Get website for current store
                    $website = $this->getCurrentWebsite();
                    
                    // Update the expired record to clear lockout status but keep failed_attempts
                    // Don't delete - cron will handle record cleanup
                    $this->updateExpiredLockoutRecord($customerId, $dbFailedAttempts, $website);
                    
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Allowed request. Set session attempts to DB value: $dbFailedAttempts. Updated expired record (kept failed_attempts).");
                    
                    // Allow the request to proceed
                    return null;
                }
            }
        }
        
        return null;
    }

    /**
     * Check for temporary/permanent lockout and delay (before processing)
     * @param string $email
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkTemporaryLockoutOnly($email)
    {
        // Get customer ID for database operations
        $customerId = $this->getCustomerIdByEmail($email);
        if (!$customerId) {
            // Customer not found, but still check for delay
            return $this->checkLoginDelayBeforeAuth($email, 'forgot_password');
        }
        
        // Get locked account data from database
        // Get website for current store
        $website = $this->getCurrentWebsite();
        $lockedAccountData = $this->getLockedAccountFromDb($customerId, $website);
        
        if (!$lockedAccountData) {
            // No lockout data in DB, but still check for delay
            return $this->checkLoginDelayBeforeAuth($email, 'forgot_password');
        }
        
        // Check temporary lockout (no permanent lockout for forgot password)
        if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['first_time_lockout'] && $lockedAccountData['lock_until']) {
            $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
            $currentTime = time();
            
            if ($currentTime < $lockoutEndTime) {
                $remainingTime = $lockoutEndTime - $currentTime;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $this->bruteforceutility->log_debug("Account is TEMPORARILY locked in DB. Remaining time: " . $minutes . " minutes and " . $seconds . " seconds.");
                
                if ($minutes > 0) {
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/forgotpassword');
                return $resultRedirect;
            } else {
                // Lockout has expired - check parity-based logic
                $dbFailedAttempts = $lockedAccountData['failed_attempts'];
                
                // Get temporary lockout threshold to check parity
                $storeId = $this->storeManager->getStore()->getId();
                $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 'stores', $storeId);
                $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                
                // Check parity with the NEXT attempt (DB + 1): if threshold and next attempt have same parity, block
                // If different parity, allow
                $nextAttempt = $dbFailedAttempts + 1;
                $thresholdParity = $maxAttemptsLockoutTemporary % 2; // 0 for even, 1 for odd
                $nextAttemptParity = $nextAttempt % 2; // 0 for even, 1 for odd
                
                if ($thresholdParity === $nextAttemptParity) {
                    // Same parity - block the request
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Threshold: $maxAttemptsLockoutTemporary (parity: $thresholdParity), DB attempts: $dbFailedAttempts, Next attempt: $nextAttempt (parity: $nextAttemptParity). Same parity - blocking request.");
                    
                    // Re-apply temporary lockout with next attempt count
                    $lockoutDurationMinutes = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_lockout_duration_minutes', 'stores', $storeId);
                    if ($lockoutDurationMinutes <= 0) {
                        $lockoutDurationMinutes = 30;
                    }
                    $lockoutDuration = date('Y-m-d H:i:s', time() + ($lockoutDurationMinutes * 60));
                    
                    // Get website for current store
                    $website = $this->getCurrentWebsite();
                    $this->saveLockedAccountToDb($customerId, $nextAttempt, 'temporary', $lockoutDuration, $email, 'customer', $website);
                    
                    $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 minutes before trying again.', $lockoutDurationMinutes);
                    $this->messageManager->addError($message);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/forgotpassword');
                    return $resultRedirect;
                } else {
                    // Different parity - allow the request
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Threshold: $maxAttemptsLockoutTemporary (parity: $thresholdParity), DB attempts: $dbFailedAttempts, Next attempt: $nextAttempt (parity: $nextAttemptParity). Different parity - allowing request.");
                    
                    $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
                    // Set session to DB value (not incremented) so when request is processed, it will increment to nextAttempt
                    $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, $dbFailedAttempts);
                    
                    $dbAttemptsBaseKey = 'forgot_password_db_attempts_base_' . hash('sha256', $email . '_' . ($website ? hash('sha256', $website) : 'default'));
                    $this->bruteforceutility->setSessionValue($dbAttemptsBaseKey, $dbFailedAttempts);
                    
                    // Get website for current store
                    $website = $this->getCurrentWebsite();
                    
                    $this->updateExpiredLockoutRecord($customerId, $dbFailedAttempts, $website);
                    
                    $this->bruteforceutility->log_debug("Temporary lockout expired. Allowed request. Set session attempts to DB value: $dbFailedAttempts. Updated expired record (kept failed_attempts).");
                    
                    // Allow the request to proceed
                    return null;
                }
            }
        }
        
        // Check for login delay before processing
        return $this->checkLoginDelayBeforeAuth($email, 'forgot_password');
    }

    /**
     * Check login delay before processing
     * @param string $email
     * @param string $type 'forgot_password' for forgot password attempts
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkLoginDelayBeforeAuth($email, $type = 'forgot_password')
    {
        // Get current store ID for store-specific configuration
        $storeId = $this->storeManager->getStore()->getId();
        // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay', 'stores', $storeId);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_delay_seconds', 'stores', $storeId) ?: 30;
        
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        }
        
        // Get customer ID for delay limit checking
        $customerId = $this->getCustomerIdByEmail($email);
        
        // Get session attempts (account-specific)
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        
        // Check if in delay period
        if ($sessionAttempts >= $maxAttemptsDelay) {
            $website = $this->getCurrentWebsite();
            $websiteId = $website ? hash('sha256', $website) : 'default';
            $delayKey = $type . '_delay_' . hash('sha256', $email . '_' . $websiteId);
            $delayStartTime = $this->bruteforceutility->getSessionValue($delayKey);
            
            if ($delayStartTime) {
                $currentTime = time();
                $elapsedTime = $currentTime - $delayStartTime;
                
                if ($elapsedTime < $delaySeconds) {
                    // Active delay period - check delay limit only if delay is active
                    $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
                    if ($customerId && $delayLimitExceeded) {
                        $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded during active delay. Skipping delay feature for customer ID: $customerId");
                        // Skip delay feature, allow request to proceed
                        $this->bruteforceutility->setSessionValue($delayKey, null);
                        return null;
                    }
                    
                    $remainingTime = $delaySeconds - $elapsedTime;
                    $this->bruteforceutility->log_debug("Login delay active. Remaining time: $remainingTime seconds.");
                    // Add customer ID to the delay list if delay is active
                    if ($customerId) {
                        $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password_delay');
                    }
                    $message = __('Please wait %1 seconds before trying again.', $remainingTime);
                    $this->messageManager->addError($message);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/forgotpassword');
                    return $resultRedirect;
                } else {
                    // Delay has expired - remove delay flag and set a flag to allow this one request
                    $this->bruteforceutility->setSessionValue($delayKey, null);
                    $allowOnceKey = $type . '_allow_once_' . hash('sha256', $email . '_' . $websiteId);
                    $this->bruteforceutility->setSessionValue($allowOnceKey, true);
                    $this->bruteforceutility->log_debug("Delay expired, allowing one request to proceed");
                }
            }
            // If no active delay, allow request to proceed (delay limit will be checked after failed attempt)
        }
        
        return null; // No delay, proceed with processing
    }

    /**
     * Check temporary lockout
     * @param string $email
     * @param int|null $customerId
     * @param array|null $lockedAccountData
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkTemporaryLockout($email, $customerId = null, $lockedAccountData = null)
    {
        // Calculate total attempts (account-specific)
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        
        // Get website for cumulative tracking
        $website = $this->getCurrentWebsite();
        $websiteId = $website ? hash('sha256', $website) : 'default';
        
        // Check if there's a DB base value stored (from previous lockout expiry)
        // This allows cumulative tracking across lockout cycles
        $dbAttemptsBaseKey = 'forgot_password_db_attempts_base_' . hash('sha256', $email . '_' . $websiteId);
        $dbAttemptsBase = $this->bruteforceutility->getSessionValue($dbAttemptsBaseKey) ?? 0;

        if ($dbAttemptsBase > 0) {
            // Session was set to DB value after expiry, so current session attempts = DB value + increments
            // Total attempts = current session attempts (which already includes DB base)
            $totalAttempts = $sessionAttempts;
            // Clear the DB base key after using it
            $this->bruteforceutility->setSessionValue($dbAttemptsBaseKey, null);
        } else {
            $totalAttempts = $sessionAttempts;
        }
        
        // Create admin dashboard notification (admin alert) - always create, even if customer doesn't exist
        $this->createAdminAlert($email, 'temporary');
        
        // Get lockout duration from configuration (needed for message even if customer doesn't exist)
        $storeId = $this->storeManager->getStore()->getId();
        $lockoutDurationMinutes = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_lockout_duration_minutes', 'stores', $storeId);
        if ($lockoutDurationMinutes <= 0) {
            $lockoutDurationMinutes = 0; // No lockout if not configured
        }
        
        // Save temporary lockout to database (only if customer exists)
        if ($customerId) {
            // Get website for current store
            $website = $this->getCurrentWebsite();
            
            $lockoutDuration = date('Y-m-d H:i:s', time() + ($lockoutDurationMinutes * 60));
            
            $this->saveLockedAccountToDb($customerId, $totalAttempts, 'temporary', $lockoutDuration, $email, 'customer', $website);
            
            // Clear session attempts (account-specific)
            $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, 0);
            
            $this->bruteforceutility->log_debug("Temporary lockout saved to DB for email: " . $email . " Total attempts: $totalAttempts, Website: $website");
        }
        
        $message = __('Your account is temporarily locked due to multiple failed password reset attempts. Please wait %1 minutes before trying again.', $lockoutDurationMinutes);
        $this->messageManager->addError($message);
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('customer/account/forgotpassword');
        return $resultRedirect;
    }

    /**
     * Check login delay
     * @param string $email
     * @param int $delaySeconds
     * @param string $type 'forgot_password' for forgot password attempts
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkLoginDelay($email, $delaySeconds, $type = 'forgot_password', $maxAttemptsDelay = null)
    {
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay !== null && $maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        }
        
        if ($delaySeconds > 0) {
            $website = $this->getCurrentWebsite();
            $websiteId = $website ? hash('sha256', $website) : 'default';
            $delayKey = $type . '_delay_' . hash('sha256', $email . '_' . $websiteId);
            $allowOnceKey = $type . '_allow_once_' . hash('sha256', $email . '_' . $websiteId);
            $allowOnce = $this->bruteforceutility->getSessionValue($allowOnceKey);
            
            // If delay just expired and we're allowing one request, don't start delay yet
            if ($allowOnce) {
                $this->bruteforceutility->setSessionValue($allowOnceKey, null); // Clear the flag
                $this->bruteforceutility->log_debug("Allowing this request to proceed (delay just expired)");
                return null; // Allow request to proceed, delay will start on next attempt
            }
            
            $delayStartTime = $this->bruteforceutility->getSessionValue($delayKey);
            
            if ($delayStartTime) {
        $currentTime = time();
        $elapsedTime = $currentTime - $delayStartTime;
        
        if ($elapsedTime < $delaySeconds) {
            $remainingTime = $delaySeconds - $elapsedTime;
            $this->bruteforceutility->log_debug("Login delay active. Remaining time: $remainingTime seconds.");
            // Add customer ID to the delay list if delay is active
            $customerId = $this->getCustomerIdByEmail($email);
            if ($customerId) {
                $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password_delay');
            }
            $message = __('Please wait %1 seconds before trying again.', $remainingTime);
            $this->messageManager->addError($message);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/forgotpassword');
            return $resultRedirect;
        } else {
            // Delay has expired, remove delay flag
            $this->bruteforceutility->setSessionValue($delayKey, null);
                }
            } else {
                // Check if delay limit has been reached before starting delay
                $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
                $customerId = $this->getCustomerIdByEmail($email);
                if ($customerId && $delayLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer Forgot Password - Delay limit exceeded. Skipping delay feature for customer ID: $customerId");
                    // Skip delay feature, don't block - let request proceed
                    return null;
                }
                
                // Start delay timer (only if we're not allowing one request)
                $this->bruteforceutility->setSessionValue($delayKey, time());
                // Add customer ID to the delay list when starting delay
                if ($customerId) {
                    $this->bruteforceutility->addUserToTempLockoutList($customerId, 'forgot_password_delay');
                }
                $message = __('Please wait %1 seconds before trying again.', $delaySeconds);
                $this->messageManager->addError($message);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/forgotpassword');
                return $resultRedirect;
            }
        }
        
        return null; // No delay, proceed with processing
    }

    /**
     * Get website name/ID for current store
     * @return string|null
     */
    protected function getCurrentWebsite()
    {
        try {
            $store = $this->storeManager->getStore();
            $website = $store->getWebsite();
            return $website ? $website->getName() : null;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting website: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get locked account data from database (forgot password table)
     * @param int $customerId
     * @param string|null $website
     * @return array|null
     */
    protected function getLockedAccountFromDb($customerId, $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from('mo_bruteforce_forgot_password_locked_accounts')
                ->where('customer_id = ?', $customerId);
            
            // Filter by website if provided
            if ($website !== null) {
                $select->where('website = ?', $website);
            }
            
            $select->limit(1);
            
            return $connection->fetchRow($select);
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting forgot password locked account from DB: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Save locked account to database (forgot password table)
     * @param int $customerId
     * @param int $failedAttempts
     * @param int $tempLockCount
     * @param string $lockType
     * @param string|null $lockUntil
     * @param string $email
     * @param string $userType
     * @return bool
     */
    protected function saveLockedAccountToDb($customerId, $failedAttempts, $lockType, $lockUntil = null, $email = null, $userType = 'customer', $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = 'mo_bruteforce_forgot_password_locked_accounts';
            
            // Get website for customers (from current store)
            if ($userType === 'customer' && $website === null) {
                $website = $this->getCurrentWebsite();
            }
            
            // Check if record exists
            $existingRecord = $this->getLockedAccountFromDb($customerId, $website);
            
            $data = [
                'customer_id' => $customerId,
                'email' => $email ?: $this->getEmailByCustomerId($customerId),
                'user_type' => $userType,
                'website' => $website,
                'failed_attempts' => $failedAttempts,
                'lock_type' => $lockType,
                'lock_until' => $lockUntil,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($lockType === 'temporary') {
                $data['first_time_lockout'] = date('Y-m-d H:i:s');
            }
            
            // Reset sent_email to 0 when lock_type changes (new lockout event)
            if ($existingRecord && $existingRecord['lock_type'] !== $lockType) {
                // Lock type changed, reset sent_email flag for new lockout event
                $data['sent_email'] = 0;
            } elseif (!$existingRecord) {
                // New record, set sent_email to 0 and created_at
                $data['sent_email'] = 0;
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            // If existing record and lock_type unchanged, don't modify sent_email
            
            $where = ['customer_id = ?' => $customerId];
            if ($website !== null) {
                $where['website = ?'] = $website;
            }
            
            if ($existingRecord) {
                // Update existing record
                $connection->update($tableName, $data, $where);
            } else {
                // Insert new record
                $connection->insert($tableName, $data);
            }
            
            $this->bruteforceutility->log_debug("Saved forgot password locked account to DB - Customer ID: $customerId, Email: $email, User Type: $userType, Website: $website, Type: $lockType");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error saving forgot password locked account to DB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update expired lockout record to clear lockout status but keep failed_attempts
     * Record will be removed by cron job, not here
     * @param int $customerId
     * @param int $failedAttempts
     * @param string|null $website
     */
    protected function updateExpiredLockoutRecord($customerId, $failedAttempts, $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = 'mo_bruteforce_forgot_password_locked_accounts';
            
            $where = ['customer_id = ?' => $customerId];
            if ($website !== null) {
                $where['website = ?'] = $website;
            }
            
            // Update record to clear lockout status but keep failed_attempts
            $data = [
                'lock_type' => 'none',
                'lock_until' => null,
                'failed_attempts' => $failedAttempts, // Keep the failed attempts value
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $updated = $connection->update($tableName, $data, $where);
            
            if ($updated) {
                $this->bruteforceutility->log_debug("Updated expired lockout record for customer ID: $customerId, website: " . ($website ?? 'NULL') . " - cleared lockout but kept failed_attempts: $failedAttempts");
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error updating expired lockout: " . $e->getMessage());
        }
    }

    /**
     * Get customer ID by email
     * @param string $email
     * @return int|null
     */
    protected function getCustomerIdByEmail($email)
    {
        try {
            $customer = $this->customerRepository->get($email);
            return $customer->getId();
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting customer ID for email $email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get email by customer ID
     * @param int $customerId
     * @return string|null
     */
    protected function getEmailByCustomerId($customerId)
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            return $customer->getEmail();
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting email for customer ID $customerId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send temporary lockout email notification
     * @param string $email
     * @param string $customerEmailTemplate
     */
    protected function sendTemporaryLockoutEmail($email, $customerEmailTemplate)
    {
        try {
            $this->bruteforceutility->log_debug("Sending TEMPORARY lockout notification email for forgot password: " . $email);
            
            // Note: Admin alert is already created in checkTemporaryLockout(), so we don't create it again here
            
            // Send customer notification email
            if (!$customerEmailTemplate) {
                $this->bruteforceutility->log_debug("Customer email template not configured for forgot password");
                return;
            }
            
            // Check if email has already been sent
            $customerId = $this->getCustomerIdByEmail($email);
            if ($customerId) {
                $website = $this->getCurrentWebsite();
                $lockedAccountData = $this->getLockedAccountFromDb($customerId, $website);
                if ($lockedAccountData && isset($lockedAccountData['sent_email']) && $lockedAccountData['sent_email'] == 1) {
                    $this->bruteforceutility->log_debug("Email notification already sent for: " . $email . ". Skipping.");
                    return;
                }
            }
            
            $storeId = $this->storeManager->getStore()->getId();
            
            // Get sender information from system configuration
            $senderName = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/name', 'stores', $storeId) ?: 'Store Owner';
            $senderEmail = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
            
            if (!$senderEmail) {
                $this->bruteforceutility->log_debug("Cannot send email: sender email not configured");
                return;
            }
            
            // Get customer name
            $customerName = '';
            if ($customerId) {
                try {
                    $customer = $this->customerRepository->getById($customerId);
                    $customerName = $customer->getFirstname() . ' ' . $customer->getLastname();
                } catch (\Exception $e) {
                    // Customer name is optional
                }
            }
            
            // Prepare template variables
            $templateVars = [
                'customer_email' => $email,
                'customer_name' => $customerName ?: $email,
                'lockout_type' => 'temporary',
                'store' => $this->storeManager->getStore(),
            ];
            
            // Check if email notification limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('email', 'customer', 'forgot')) {
                $this->bruteforceutility->log_debug("Email notification limit reached. Skipping email for forgot password: $email");
                return;
            }
            
            // Turn off inline translation to send email in default locale
            $this->inlineTranslation->suspend();
            
            try {
                // Build and send email
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($customerEmailTemplate)
                    ->setTemplateOptions([
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ])
                    ->setTemplateVars($templateVars)
                    ->setFrom([
                        'name' => $this->escaper->escapeHtml($senderName),
                        'email' => $this->escaper->escapeHtml($senderEmail),
                    ])
                    ->addTo($email)
                    ->getTransport();
                
                $transport->sendMessage();
                
                // Increment global email count
                $this->bruteforceutility->incrementNotificationCount('email', 'customer', 'forgot');
                
                // Mark email as sent after successful send
                if ($customerId) {
                    $this->markEmailSent($customerId, $email, 'customer');
                }
                
                $this->bruteforceutility->log_debug("Email notification sent successfully to $email for temporary lockout");
            } finally {
                // Resume inline translation
                $this->inlineTranslation->resume();
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending temporary lockout notification email: " . $e->getMessage());
            // Resume inline translation even on error
            try {
                $this->inlineTranslation->resume();
            } catch (\Exception $resumeException) {
                // Ignore resume errors
            }
        }
    }

    /**
     * Create admin dashboard notification (admin alert)
     * @param string $email Customer email
     * @param string $lockoutType 'temporary' (no permanent for forgot password)
     */
    protected function createAdminAlert($email, $lockoutType = 'temporary')
    {
        try {
            // Check if admin alert limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('admin_alert', 'customer', 'forgot')) {
                $this->bruteforceutility->log_debug("Admin alert limit reached. Skipping admin alert for forgot password: $email");
                return;
            }
            
            // Get current store ID for store-specific configuration
            $storeId = $this->storeManager->getStore()->getId();
            $adminAlertEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/forgot_password_admin_alert_enabled', 'stores', $storeId);
            $this->bruteforceutility->log_debug("Forgot Password Admin Alert Check - Store ID: $storeId, Enabled: " . ($adminAlertEnabled ? 'YES' : 'NO'));
            if (!$adminAlertEnabled) {
                $this->bruteforceutility->log_debug("Forgot Password Admin Alert is disabled, skipping notification");
                return;
            }
            
            // Prepare notification title and description
            $title = __('Security Alert!');
            
            $lockoutText = 'temporarily locked';
            $description = __(
                '%1 was %2 due to multiple failed password reset attempts. View security logs for details.',
                $email,
                $lockoutText
            );
            
            // Create admin notification using Inbox
            $inbox = $this->inboxFactory->create();
            $inbox->addNotice(
                (string)$title,
                (string)$description,
                '', // URL (optional - link to security logs)
                false // is_read (false = unread notification)
            );
            
            // Increment global admin alert count
            $this->bruteforceutility->incrementNotificationCount('admin_alert', 'customer', 'forgot');
            
            $this->bruteforceutility->log_debug("Admin alert notification created for forgot password: " . $email . " (Type: " . $lockoutType . ")");
            
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error creating admin alert notification: " . $e->getMessage());
        }
    }

    /**
     * Mark email notification as sent in database
     * @param int $customerId
     * @param string|null $email
     * @param string $userType
     * @return bool
     */
    protected function markEmailSent($customerId, $email = null, $userType = 'customer')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $website = $this->getCurrentWebsite();
            
            $where = [
                'customer_id = ?' => $customerId,
                'user_type = ?' => $userType
            ];
            
            // If email is provided, also filter by email
            if ($email) {
                $where['email = ?'] = $email;
            }
            
            // For customers, filter by website. For admins, website is NULL
            if ($userType === 'customer' && $website !== null) {
                $where['website = ?'] = $website;
            } elseif ($userType === 'admin') {
                // For admin, website is always NULL - use string WHERE clause for NULL check
                $whereString = "customer_id = " . (int)$customerId . " AND user_type = 'admin'";
                if ($email) {
                    $whereString .= " AND email = " . $connection->quote($email);
                }
                $whereString .= " AND website IS NULL";
                $connection->update('mo_bruteforce_forgot_password_locked_accounts', ['sent_email' => 1], $whereString);
                $this->bruteforceutility->log_debug("Marked email as sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
                return true;
            }
            
            if ($userType !== 'admin') {
                $connection->update('mo_bruteforce_forgot_password_locked_accounts', ['sent_email' => 1], $where);
            }
            
            $this->bruteforceutility->log_debug("Marked email as sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: $website");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error marking email as sent: " . $e->getMessage());
            return false;
        }
    }

}
