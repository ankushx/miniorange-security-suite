<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\User\Controller\Adminhtml\Auth\Forgotpassword;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\User\Model\UserFactory;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\Action\Action;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Plugin for Admin Forgot Password controller to add BruteForce protection
 */
class AdminForgotPasswordPlugin
{
    protected $bruteforceutility;
    protected $resourceConnection;
    protected $userFactory;
    protected $inboxFactory;
    protected $resultRedirectFactory;
    protected $messageManager;
    protected $actionFlag;
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $escaper;
    protected $storeManager;

    public function __construct(
        BruteForceUtility $bruteforceutility,
        ResourceConnection $resourceConnection,
        UserFactory $userFactory,
        InboxFactory $inboxFactory,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        ActionFlag $actionFlag,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        StoreManagerInterface $storeManager
    ) {
        $this->bruteforceutility = $bruteforceutility;
        $this->resourceConnection = $resourceConnection;
        $this->userFactory = $userFactory;
        $this->inboxFactory = $inboxFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->actionFlag = $actionFlag;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->storeManager = $storeManager;
    }

    /**
     * Around execute plugin to add brute force protection
     * This runs BEFORE Magento validates the email and sends password reset email
     * 
     * @param Forgotpassword $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function aroundExecute($subject, \Closure $proceed)
    {
        $this->bruteforceutility->log_debug("=== ADMIN FORGOT PASSWORD PLUGIN EXECUTED ===");
        if (!$subject->getRequest()->isPost()) {
            $subject->getRequest()->setParam('key', null);
            return $proceed();
        }
       

        if ($subject->getRequest()->isPost()) {
            $email = trim((string)$subject->getRequest()->getPost('email'));
            
            // Only check brute force protection if email is provided
            // If email is empty, let the original controller handle validation

            if (!empty($email)) {
                // Get thresholds to check if both are disabled (0)
                $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay', 'default', 0);
                $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_lockout', 'default', 0);
                $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                
                // If both thresholds are 0 (disabled), skip all brute force protection
                if ($maxAttemptsDelay <= 0 && $maxAttemptsLockoutTemporary <= 0) {
                    $this->bruteforceutility->log_debug("Admin Forgot Password - Both delay and temp lockout thresholds are 0 (disabled). Skipping all brute force protection.");
                    return $proceed();
                }
                
                // Get user ID
                $userId = $this->getUserIdByEmail($email);
                
                if ($userId) {
                    // Check if user is already in restriction lists
                    $inDelayList = $this->bruteforceutility->isUserInRestrictionList($userId, 'admin_forgot_password_delay');
                    $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($userId, 'admin_forgot_password');
                    
                    // If user is already in either list, apply normal brute force protection
                    // Don't clear flags - we'll check actual counts in checkBruteForceProtection
                    if ($inDelayList || $inTempLockoutList) {
                        $this->bruteforceutility->log_debug("Admin Forgot Password - User already in restriction list. Applying normal brute force protection for user ID: $userId");
                        // Continue with normal brute force checks - limits will be checked in checkBruteForceProtection
                    } else {
                        // User not in list - check if limit is exceeded
                        // Check if limits are exceeded using helper functions
                        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($userId, 'admin_forgot_password_delay');
                        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($userId, 'admin_forgot_password');
                        
                        // If both limits exceeded, skip brute force protection entirely
                        if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                            $this->bruteforceutility->log_debug("Admin Forgot Password - User not in list and both limits exceeded. Skipping brute force protection for user ID: $userId");
                            // Let request proceed normally without brute force protection
                            return $proceed();
                        }
                        
                        // If delay limit is NOT exceeded but temp lockout limit IS exceeded
                        if (!$delayLimitExceeded && $tempLockoutLimitExceeded) {
                            // Get delay threshold to check if it's disabled (0)
                            $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay', 'default', 0);
                            $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                            
                            if ($maxAttemptsDelay <= 0) {
                                $this->bruteforceutility->log_debug("Admin Forgot Password - Temp lockout limit exceeded but delay threshold is 0 (disabled). Skipping brute force protection for user ID: $userId");
                                return $proceed();
                            } else {
                                $this->bruteforceutility->log_debug("Admin Forgot Password - Temp lockout limit exceeded but delay threshold is enabled. Allowing delay feature for user ID: $userId");
                            }
                        }
                        
                        // If temp lockout limit is NOT exceeded but delay limit IS exceeded
                        if ($delayLimitExceeded && !$tempLockoutLimitExceeded) {
                            // Get temp lockout threshold to check if it's disabled (0)
                            $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_lockout', 'default', 0);
                            $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                            
                            if ($maxAttemptsLockoutTemporary <= 0) {
                                $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded but temp lockout threshold is 0 (disabled). Skipping brute force protection for user ID: $userId");
                                return $proceed();
                            } else {
                                $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded but temp lockout threshold is enabled. Allowing temp lockout feature for user ID: $userId");
                            }
                        }
                        
                        // Store flags in session for use in brute force checks
                        $this->bruteforceutility->setSessionValue('delay_limit_exceeded', $delayLimitExceeded);
                        $this->bruteforceutility->setSessionValue('temp_lockout_limit_exceeded', $tempLockoutLimitExceeded);
                    }
                }

                // Count this attempt and enforce restrictions before processing
                $preCheck = $this->checkBruteForceProtection($email, $subject);
                if ($preCheck !== null) {
                    $this->bruteforceutility->log_debug("Admin Forgot Password - BruteForce protection triggered, returning redirect and preventing original controller execution");
                    
                    // Set action flag to prevent original controller from executing
                    // This ensures the password reset email is NOT sent when account is locked/delayed
                    $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                    
                    // Also set flag on the subject controller to ensure it doesn't execute
                    $subject->getActionFlag()->set('', Action::FLAG_NO_DISPATCH, true);
                    
                    // Return redirect WITHOUT calling $proceed()
                    $this->bruteforceutility->log_debug("Admin Forgot Password - Returning redirect, NOT calling proceed()");
                    return $preCheck;
                } else {
                    $this->bruteforceutility->log_debug("Admin Forgot Password - No brute force protection triggered, allowing normal execution");
                }
            } else {
                // Email is empty - let original controller handle validation
                // Don't check for delays or lockouts when email is empty
                $this->bruteforceutility->log_debug("Admin Forgot Password - Email is empty, skipping brute force check");
            }
        
        }
        // Only call $proceed() if brute force protection allows the request
        // This ensures the original controller executes normally when account is not locked/delayed
        $this->bruteforceutility->log_debug("Admin Forgot Password - Calling proceed() to execute original controller");
        return $proceed();

    }

    /**
     * Get account-specific session key for forgot password attempts
     * @param string $email
     * @return string
     */
    protected function getForgotPasswordAttemptsSessionKey($email)
    {
        return 'admin_forgot_password_attempts_' . hash('sha256', $email);
    }

    /**
     * Check if admin user exists
     * @param string $email
     * @return bool
     */
    protected function adminExists($email)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('admin_user');
            
            $select = $connection->select()
                ->from($tableName, ['user_id'])
                ->where('email = ?', $email)
                ->limit(1);
            
            $userId = $connection->fetchOne($select);
            return $userId ? true : false;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error checking if admin exists for email $email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check BruteForce protection for admin forgot password
     * @param string $email
     * @param Forgotpassword $controller
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkBruteForceProtection($email, $controller)
    {
        // Get admin forgot password BruteForce settings (admin settings are always global)
        // Allow 0 values to disable individual features - use null coalescing only for null/empty, not for 0
        $enabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_enabled', 'default', 0);
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay', 'default', 0);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_delay_seconds', 'default', 0) ?: 30;
        $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_lockout', 'default', 0);
        $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
        $lockoutDurationMinutes = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_lockout_duration_minutes', 'default', 0) ?: 30;
        $adminEmailTemplate = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_admin_email_template', 'default', '');
        
        $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - Enabled: " . $enabled);
        $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - Email: " . $email);
        
        if (!$enabled) {
            $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection is disabled. Skipping protection.");
            return null;
        }
       
        // Check if admin exists before applying brute force protection
        if (!$this->adminExists($email)) {
            $this->bruteforceutility->log_debug("Admin not found for email: " . $email);
            return null;
        }
        
        // Get admin user ID for database operations
        $userId = $this->getUserIdByEmail($email);
        
        // Check if user is already in restriction lists
        $inDelayList = false;
        $inTempLockoutList = false;
        if ($userId) {
            $inDelayList = $this->bruteforceutility->isUserInRestrictionList($userId, 'admin_forgot_password_delay');
            $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($userId, 'admin_forgot_password');
        }
        
        // Always check actual counts to determine if limits are exceeded (don't rely on session flags)
        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($userId, 'admin_forgot_password_delay');
        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($userId, 'admin_forgot_password');
        
        // If user is NOT in either list, check if both limits exceeded
        if (!$inDelayList && !$inTempLockoutList && $userId) {
            // If both limits exceeded, skip brute force protection entirely
            if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                $this->bruteforceutility->log_debug("Admin Forgot Password - User not in list and both limits exceeded. Skipping brute force protection for user ID: $userId");
                return null; // Don't interfere, let Magento handle
            }
        }
        
        // Check if account is already locked (using database) - check by userId or email
        $lockoutResult = $this->checkAccountLockoutFromDb($email, $userId);
        if ($lockoutResult !== null) {
            return $lockoutResult; // Return redirect if locked
        }

        $delayResult = $this->checkLoginDelayBeforeAuth($email, 'admin_forgot_password');
        if ($delayResult !== null) {
            return $delayResult; // Return redirect if delay is active
        }

        // Get current session attempts (account-specific)
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        $sessionAttempts++;
        
        // Get locked account data from database (by email and userId if available)
        $lockedAccountData = $this->getLockedAccountFromDb($email, $userId);
        
        // Calculate total attempts
        // If there's a DB base (from previous lockout expiry), use it for cumulative tracking
        $dbAttemptsBaseKey = 'admin_forgot_password_db_attempts_base_' . hash('sha256', $email);
        $dbAttemptsBase = $this->bruteforceutility->getSessionValue($dbAttemptsBaseKey) ?? 0;
        
        if ($dbAttemptsBase > 0) {
            // Session was set to DB value after expiry, so total = DB base + (current session - DB base) = current session
            // But we need to account for the fact that session was set to DB value, so total = session
            $totalAttempts = $sessionAttempts;
        } else {
            // No DB base, use session attempts as is
            $totalAttempts = $sessionAttempts;
        }
        
        $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - Session Attempts: $sessionAttempts, DB Attempts: " . ($lockedAccountData['failed_attempts'] ?? 0) . ", Total: $totalAttempts");
        
        $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, $sessionAttempts);
        $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - Thresholds - Delay: $maxAttemptsDelay, Temp Lockout: $maxAttemptsLockoutTemporary (NO permanent for forgot password)");
        
        // 1. FIRST: Check for TEMPORARY lockout (NO permanent lockout for forgot password)
        // Only check if temporary lockout threshold is > 0 (feature enabled)
        if ($maxAttemptsLockoutTemporary > 0 && $totalAttempts >= $maxAttemptsLockoutTemporary) {
            // Check actual count to see if temp lockout limit is exceeded (only if user not in list)
            if (!$inTempLockoutList && $tempLockoutLimitExceeded) {
                $this->bruteforceutility->log_debug("Admin Forgot Password - Temporary lockout limit exceeded. Skipping temp lockout, checking delay instead for user ID: $userId");
                // Apply delay if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    // Apply delay instead of lockout when temp lockout limit is reached
                    $delayResult = $this->checkLoginDelay($email, $delaySeconds, 'admin_forgot_password', $maxAttemptsDelay);
                    if ($delayResult !== null) {
                        return $delayResult; // Return redirect if delay is active
                    }
                    return null; // Delay expired, allow attempt
                } else {
                    // Delay limit exceeded and user not in delay list - skip brute force protection
                    $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded and user not in delay list. Skipping brute force protection.");
                    return null;
                }
            }
            
            // User in list or limit not exceeded - apply normal temp lockout
            // Only apply if user is in temp lockout list OR temp lockout limit is NOT exceeded
            if ($inTempLockoutList || !$tempLockoutLimitExceeded) {
                $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - TEMPORARY lockout triggered (attempts: $totalAttempts >= $maxAttemptsLockoutTemporary)");
                $lockoutResult = $this->checkTemporaryLockout($email, $userId, $lockedAccountData);
                if ($lockoutResult !== null) {
                    // Add user ID to the encrypted array if lockout was applied (only if not already in list)
                    if ($userId && !$inTempLockoutList) {
                        $this->bruteforceutility->addUserToTempLockoutList($userId, 'admin_forgot_password');
                    }
                    // Send email notification for temporary lockout (admin alert is already created in checkTemporaryLockout)
                    $this->sendTemporaryLockoutEmail($email, $adminEmailTemplate);
                    return $lockoutResult; // Return redirect if locked
                }
            }
        }
        
        // 2. SECOND: Check for login delay (only if not locked)
        // Also check delay if temp lockout is disabled and delay limit is not exceeded (apply delay forever)
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
                // Apply delay forever if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    $shouldCheckDelay = ($totalAttempts >= $maxAttemptsDelay);
                } else {
                    // Delay limit exceeded and user not in delay list - skip delay
                    $shouldCheckDelay = false;
                }
            }
            
            if ($shouldCheckDelay) {
                $this->bruteforceutility->log_debug("Admin Forgot Password BruteForce Protection - Checking delay");
                // Check actual count to see if delay limit is exceeded (only if user not in list)
                if (!$inDelayList && $delayLimitExceeded) {
                    $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded. Skipping delay feature for user ID: $userId");
                    // Skip delay feature, but don't block - let request proceed
                    return null;
                }
                $delayResult = $this->checkLoginDelay($email, $delaySeconds, 'admin_forgot_password', $maxAttemptsDelay);
                if ($delayResult !== null) {
                    // Add user ID to the delay list if delay was applied (only if not already in list)
                    if ($userId && !$inDelayList) {
                        $this->bruteforceutility->addUserToTempLockoutList($userId, 'admin_forgot_password_delay');
                    }
                    return $delayResult; // Return redirect if delay is active
                }
            }
        }
        
        return null; // No action needed
    }

    /**
     * Check account lockout status from database
     * @param string $email
     * @param int|null $userId
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkAccountLockoutFromDb($email, $userId)
    {
        $lockedAccountData = $this->getLockedAccountFromDb($email, $userId);
        
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
                
                $message = __('Too many failed password reset attempts. Your account has been temporarily locked. Please try again after %1 minutes and %2 seconds.', $minutes, $seconds);
                $this->messageManager->addErrorMessage($message);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('admin/auth/forgotpassword');
                return $resultRedirect;
            } else {
                // Lockout period has expired, set session to (DB failed attempts - 2) to allow one attempt after expiry
                $dbFailedAttempts = $lockedAccountData['failed_attempts'];
                $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
                // Set to (DB value - 2) so next attempt will be below threshold, and the one after will reach/exceed it
                $sessionValue = max(0, $dbFailedAttempts - 2);
                $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, $sessionValue);
                
                $dbAttemptsBaseKey = 'admin_forgot_password_db_attempts_base_' . hash('sha256', $email);
                $this->bruteforceutility->setSessionValue($dbAttemptsBaseKey, $dbFailedAttempts);
              
                $this->updateExpiredLockoutRecord($email, $dbFailedAttempts, $userId);
                
                $this->bruteforceutility->log_debug("Temporary lockout expired. Set session attempts to $sessionValue (DB value: $dbFailedAttempts - 2). Stored DB base: $dbFailedAttempts. Updated expired record (kept failed_attempts). Next attempt will be allowed, then lockout will apply again.");
            }
        }
        
        return null; // No active lockout
    }

    /**
     * Get locked account data from database
     * @param string $email
     * @param int|null $userId
     * @return array|null
     */
    protected function getLockedAccountFromDb($email, $userId = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('mo_bruteforce_forgot_password_locked_accounts');
            
            $select = $connection->select()
                ->from($tableName)
                ->where('user_type = ?', 'admin')
                ->where('email = ?', $email);
            
            if ($userId) {
                $select->where('customer_id = ?', $userId);
            } else {
                // If userId is null, search by email only (for non-existent admin users)
                $select->where('customer_id IS NULL');
            }
            
            $select->limit(1);
            
            $result = $connection->fetchRow($select);
            
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting locked account from DB: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Check login delay before authentication
     * @param string $email
     * @param string $type
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkLoginDelayBeforeAuth($email, $type)
    {
        // Don't check delay if email is empty
        if (empty($email)) {
            return null;
        }
        
        // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay', 'default', 0);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_delay_seconds', 'default', 0) ?: 30;
        
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        }
        
        // Get user ID for delay limit checking
        $userId = $this->getUserIdByEmail($email);
        
        // Get session attempts
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        
        // Check if in delay period
        if ($sessionAttempts >= $maxAttemptsDelay && $delaySeconds > 0) {
            $delayKey = $type . '_delay_' . hash('sha256', $email);
            $delayStartTime = $this->bruteforceutility->getSessionValue($delayKey);
            
            if ($delayStartTime) {
                $currentTime = time();
                $elapsedTime = $currentTime - $delayStartTime;
                
                if ($elapsedTime < $delaySeconds) {
                    // Active delay period - check delay limit only if delay is active
                    $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
                    if ($userId && $delayLimitExceeded) {
                        $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded during active delay. Skipping delay feature for user ID: $userId");
                        // Skip delay feature, allow request to proceed
                        $this->bruteforceutility->setSessionValue($delayKey, null);
                        return null;
                    }
                    
                    $remainingTime = $delaySeconds - $elapsedTime;
                    $this->bruteforceutility->log_debug("Login delay active. Remaining time: $remainingTime seconds.");
                    // Add user ID to the delay list if delay is active
                    if ($userId) {
                        $this->bruteforceutility->addUserToTempLockoutList($userId, 'admin_forgot_password_delay');
                    }
                    $message = __('Please wait %1 seconds before trying again.', $remainingTime);
                    $this->messageManager->addError($message);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('admin/auth/forgotpassword');
                    return $resultRedirect;
                } else {
                    // Delay has expired - remove delay flag and set a flag to allow this one request
                    $this->bruteforceutility->setSessionValue($delayKey, null);
                    $allowOnceKey = $type . '_allow_once_' . hash('sha256', $email);
                    $this->bruteforceutility->setSessionValue($allowOnceKey, true);
                    $this->bruteforceutility->log_debug("Delay expired, allowing one request to proceed");
                }
            }
            // If no active delay, allow request to proceed (delay limit will be checked after failed attempt)
        }
        
        return null; // No delay, proceed with processing
    }

    /**
     * Check login delay
     * @param string $email
     * @param int $delaySeconds
     * @param string $type
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkLoginDelay($email, $delaySeconds, $type, $maxAttemptsDelay = null)
    {
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay !== null && $maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        }
        
        if ($delaySeconds > 0) {
            $delayKey = $type . '_delay_' . hash('sha256', $email);
            $allowOnceKey = $type . '_allow_once_' . hash('sha256', $email);
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
                    // Add user ID to the delay list if delay is active
                    $userId = $this->getUserIdByEmail($email);
                    if ($userId) {
                        $this->bruteforceutility->addUserToTempLockoutList($userId, 'admin_forgot_password_delay');
                    }
                    $message = __('Please wait %1 seconds before trying again.', $remainingTime);
                    $this->messageManager->addError($message);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('admin/auth/forgotpassword');
                    return $resultRedirect;
                } else {
                    // Delay has expired, remove delay flag
                    $this->bruteforceutility->setSessionValue($delayKey, null);
                }
            } else {
                // Check if delay limit has been reached before starting delay
                $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
                $userId = $this->getUserIdByEmail($email);
                if ($userId && $delayLimitExceeded) {
                    $this->bruteforceutility->log_debug("Admin Forgot Password - Delay limit exceeded. Skipping delay feature for user ID: $userId");
                    // Skip delay feature, don't block - let request proceed
                    return null;
                }
                
                // Start delay timer (only if we're not allowing one request)
                $this->bruteforceutility->setSessionValue($delayKey, time());
                // Add user ID to the delay list when starting delay
                if ($userId) {
                    $this->bruteforceutility->addUserToTempLockoutList($userId, 'admin_forgot_password_delay');
                }
                $message = __('Please wait %1 seconds before trying again.', $delaySeconds);
                $this->messageManager->addError($message);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('admin/auth/forgotpassword');
                return $resultRedirect;
            }
        }
        
        return null; // No delay, proceed with processing
    }

    /**
     * Check temporary lockout
     * @param string $email
     * @param int|null $userId
     * @param array|null $lockedAccountData
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    protected function checkTemporaryLockout($email, $userId = null, $lockedAccountData = null)
    {
        // Calculate total attempts (account-specific)
        $forgotPasswordAttemptsKey = $this->getForgotPasswordAttemptsSessionKey($email);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($forgotPasswordAttemptsKey) ?? 0;
        
        // Check if there's a DB base value stored (from previous lockout expiry)
        // This allows cumulative tracking across lockout cycles
        $dbAttemptsBaseKey = 'admin_forgot_password_db_attempts_base_' . hash('sha256', $email);
        $dbAttemptsBase = $this->bruteforceutility->getSessionValue($dbAttemptsBaseKey) ?? 0;
        
        // Calculate cumulative attempts: DB base + (current session - (DB base - 2))
        if ($dbAttemptsBase > 0) {
            $totalAttempts = $sessionAttempts + 2;
            // Clear the base value after using it
            $this->bruteforceutility->setSessionValue($dbAttemptsBaseKey, null);
        } else {
            // No previous DB base, use session attempts as is (first lockout cycle)
            $totalAttempts = $sessionAttempts;
        }
        
        // Create admin dashboard notification (admin alert) - always create, even if admin doesn't exist
        $this->createAdminAlert($email);
        
        $lockoutDurationMinutes = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_lockout_duration_minutes', 'default', 0);
        if ($lockoutDurationMinutes <= 0) {
            $lockoutDurationMinutes = 0; // No lockout if not configured
        }
        
        $lockoutEndTime = date('Y-m-d H:i:s', strtotime("+{$lockoutDurationMinutes} minutes"));
        
        // Save to database (even if userId is null, we track by email)
        $this->saveLockoutToDb($userId, $email, $totalAttempts, $lockoutEndTime);
        
        // Clear session attempts (they're now in DB)
        $this->bruteforceutility->setSessionValue($forgotPasswordAttemptsKey, 0);
        
        $message = __('Too many failed password reset attempts. Your account has been temporarily locked for %1 minutes.', $lockoutDurationMinutes);
        $this->messageManager->addErrorMessage($message);
        
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('admin/auth/forgotpassword');
        return $resultRedirect;
    }

    /**
     * Save lockout to database
     * @param int|null $userId
     * @param string $email
     * @param int $failedAttempts
     * @param string $lockoutEndTime
     */
    protected function saveLockoutToDb($userId, $email, $failedAttempts, $lockoutEndTime)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('mo_bruteforce_forgot_password_locked_accounts');
            
            // Check if record exists - search by userId if available, otherwise by email
            $select = $connection->select()
                ->from($tableName)
                ->where('user_type = ?', 'admin')
                ->where('email = ?', $email);
            
            if ($userId) {
                $select->where('customer_id = ?', $userId);
            } else {
                // If userId is null, search by email only (for non-existent admin users)
                $select->where('customer_id IS NULL');
            }
            
            $select->limit(1);
            
            $existing = $connection->fetchRow($select);
            
            $data = [
                'failed_attempts' => $failedAttempts,
                'lock_type' => 'temporary',
                'first_time_lockout' => date('Y-m-d H:i:s'),
                'lock_until' => $lockoutEndTime,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Reset sent_email to 0 when lock_type changes (new lockout event)
            if ($existing && $existing['lock_type'] !== 'temporary') {
                // Lock type changed, reset sent_email flag for new lockout event
                $data['sent_email'] = 0;
            } elseif (!$existing) {
                // New record, set sent_email to 0
                $data['sent_email'] = 0;
            }
            // If existing record and lock_type unchanged, don't modify sent_email
            
            if ($existing) {
                // Update existing record
                $this->bruteforceutility->log_debug("Updating existing lockout record for email: $email, userId: " . ($userId ?? 'NULL'));
                $connection->update(
                    $tableName,
                    $data,
                    ['id = ?' => $existing['id']]
                );
            } else {
                // Insert new record - admin users have NULL website
                $data['customer_id'] = $userId; // Can be null if admin doesn't exist
                $data['email'] = $email;
                $data['user_type'] = 'admin';
                $data['website'] = null; // Admin users have NULL website
                $data['created_at'] = date('Y-m-d H:i:s');
                
                $this->bruteforceutility->log_debug("Inserting new lockout record for email: $email, userId: " . ($userId ?? 'NULL') . ", failedAttempts: $failedAttempts");
                $connection->insert($tableName, $data);
                $this->bruteforceutility->log_debug("Lockout record inserted successfully");
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error saving lockout to DB: " . $e->getMessage());
            $this->bruteforceutility->log_debug("Error stack trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Update expired lockout record to clear lockout status but keep failed_attempts
     * Record will be removed by cron job, not here
     * @param string $email
     * @param int $failedAttempts
     * @param int|null $userId
     */
    protected function updateExpiredLockoutRecord($email, $failedAttempts, $userId = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('mo_bruteforce_forgot_password_locked_accounts');
            
            $where = ['user_type = ?' => 'admin', 'email = ?' => $email];
            if ($userId) {
                $where['customer_id = ?'] = $userId;
            } else {
                $where[] = 'customer_id IS NULL';
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
                $this->bruteforceutility->log_debug("Updated expired lockout record for email: $email, userId: " . ($userId ?? 'NULL') . " - cleared lockout but kept failed_attempts: $failedAttempts");
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error updating expired lockout: " . $e->getMessage());
        }
    }

    /**
     * Create admin dashboard notification
     * @param string $email
     */
    protected function createAdminAlert($email)
    {
        try {
            // Check if admin alert limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('admin_alert', 'admin', 'forgot')) {
                $this->bruteforceutility->log_debug("Admin alert limit reached. Skipping admin alert for admin forgot password: $email");
                return;
            }
            
            $adminAlertEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_admin_alert_enabled', 'default', 0);
            
            if (!$adminAlertEnabled) {
                $this->bruteforceutility->log_debug("Admin alert is disabled for admin forgot password");
                return;
            }
            
            $this->bruteforceutility->log_debug("Creating admin alert for admin forgot password: " . $email);
            
            $inbox = $this->inboxFactory->create();
            $inbox->addNotice(
                1,
                __('Security Alert! %1 was temporarily locked for forgot password attempts. View security logs for details.', $email)
            );
            
            // Increment global admin alert count
            $this->bruteforceutility->incrementNotificationCount('admin_alert', 'admin', 'forgot');
            
            $this->bruteforceutility->log_debug("Admin alert created successfully for admin forgot password: " . $email);
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error creating admin alert: " . $e->getMessage());
        }
    }

    /**
     * Send temporary lockout email notification
     * @param string $email
     * @param string $adminEmailTemplate
     */
    protected function sendTemporaryLockoutEmail($email, $adminEmailTemplate)
    {
        try {
            $emailNotificationsEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/forgot_password_email_notifications_enabled', 'default', 0);
            
            if (!$emailNotificationsEnabled) {
                $this->bruteforceutility->log_debug("Email notifications are disabled for admin forgot password");
                return;
            }
            
            $this->bruteforceutility->log_debug("Sending TEMPORARY lockout notification email for admin forgot password: " . $email);
            
            // Note: Admin alert is already created in checkTemporaryLockout(), so we don't create it again here
            
            // Check if email has already been sent
            $userId = $this->getUserIdByEmail($email);
            if ($userId) {
                $lockedAccountData = $this->getLockedAccountFromDb($email, $userId);
                if ($lockedAccountData && isset($lockedAccountData['sent_email']) && $lockedAccountData['sent_email'] == 1) {
                    $this->bruteforceutility->log_debug("Email notification already sent for: " . $email . ". Skipping.");
                    return;
                }
            }
            
            // Check if email notification limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('email', 'admin', 'forgot')) {
                $this->bruteforceutility->log_debug("Email notification limit reached. Skipping email for admin forgot password: $email");
                return;
            }
            
            // Send admin user notification email (to the locked admin user)
            if ($adminEmailTemplate) {
                $this->sendAdminUserEmail($email, $adminEmailTemplate, $userId);
            } else {
                $this->bruteforceutility->log_debug("Admin email template not configured for forgot password. Email will not be sent.");
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending temporary lockout notification email: " . $e->getMessage());
        }
    }

    /**
     * Send email to admin user
     * @param string $email
     * @param string $templateId
     * @param int|null $userId
     */
    protected function sendAdminUserEmail($email, $templateId, $userId = null)
    {
        try {
            if (!$templateId) {
                $this->bruteforceutility->log_debug("Admin user email template not configured for forgot password");
                return;
            }
            
            // Use default store (0) for admin emails
            $storeId = 0;
            
            // Get admin user details
            $adminName = '';
            if ($userId) {
                try {
                    $adminUser = $this->userFactory->create()->load($userId);
                    if ($adminUser->getId()) {
                        $adminName = $adminUser->getFirstname() . ' ' . $adminUser->getLastname();
                        if (trim($adminName) === '') {
                            $adminName = $adminUser->getUsername();
                        }
                    }
                } catch (\Exception $e) {
                    // Admin name is optional
                }
            }
            
            // Get sender information from system configuration
            $senderName = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/name', 'stores', $storeId) ?: 'Store Owner';
            $senderEmail = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
            
            if (!$senderEmail) {
                $this->bruteforceutility->log_debug("Cannot send email: sender email not configured");
                return;
            }
            
            // Prepare template variables
            $templateVars = [
                'admin_email' => $email,
                'admin_name' => $adminName ?: $email,
                'lockout_type' => 'temporary',
                'store' => $this->storeManager->getStore($storeId),
            ];
            
            // Turn off inline translation to send email in default locale
            $this->inlineTranslation->suspend();
            
            try {
                // Build and send email
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($templateId)
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
                $this->bruteforceutility->incrementNotificationCount('email', 'admin', 'forgot');
                
                // Mark email as sent after successful send
                if ($userId) {
                    $this->markEmailSent($userId, $email, 'admin');
                }
                
                $this->bruteforceutility->log_debug("Email notification sent successfully to $email for temporary lockout");
            } finally {
                // Resume inline translation
                $this->inlineTranslation->resume();
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending admin user email: " . $e->getMessage());
            // Resume inline translation even on error
            try {
                $this->inlineTranslation->resume();
            } catch (\Exception $resumeException) {
                // Ignore resume errors
            }
        }
    }

    /**
     * Mark email notification as sent in database
     * @param int $userId
     * @param string|null $email
     * @param string $userType
     * @return bool
     */
    protected function markEmailSent($userId, $email = null, $userType = 'admin')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // For admin, website is always NULL - use string WHERE clause for NULL check
            $whereString = "customer_id = " . (int)$userId . " AND user_type = 'admin'";
            if ($email) {
                $whereString .= " AND email = " . $connection->quote($email);
            }
            $whereString .= " AND website IS NULL";
            
            $connection->update('mo_bruteforce_forgot_password_locked_accounts', ['sent_email' => 1], $whereString);
            $this->bruteforceutility->log_debug("Marked email as sent for customer ID: $userId, Email: $email, User Type: $userType, Website: NULL");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error marking email as sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get admin user ID by email
     * @param string $email
     * @return int|null
     */
    protected function getUserIdByEmail($email)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('admin_user');
            
            $select = $connection->select()
                ->from($tableName, ['user_id'])
                ->where('email = ?', $email)
                ->limit(1);
            
            $userId = $connection->fetchOne($select);
            return $userId ? (int)$userId : null;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting admin user ID for email $email: " . $e->getMessage());
        }
        return null;
    }

}

