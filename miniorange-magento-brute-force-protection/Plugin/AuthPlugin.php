<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Backend\Model\Auth;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\Plugin\AuthenticationException as PluginAuthenticationException;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\User\Model\UserFactory;
use Magento\AdminNotification\Model\InboxFactory;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;

/**
 * Plugin for Backend Auth model to add BruteForce protection
 */
class AuthPlugin
{
    protected $bruteForceUtility;
    protected $messageManager;
    protected $resultRedirectFactory;
    protected $resourceConnection;
    protected $storeManager;
    protected $actionFlag;
    protected $request;
    protected $url;
    protected $response;
    protected $eventManager;
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $escaper;
    protected $userFactory;
    protected $inboxFactory;
    
    public function __construct(
        BruteForceUtility $bruteForceUtility,
        ManagerInterface $messageManager,
        RedirectFactory $resultRedirectFactory,
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        ActionFlag $actionFlag,
        Request $request,
        UrlInterface $url,
        ResponseInterface $response,
        EventManager $eventManager,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        UserFactory $userFactory,
        InboxFactory $inboxFactory
    ) {
        $this->bruteForceUtility = $bruteForceUtility;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->actionFlag = $actionFlag;
        $this->request = $request;
        $this->url = $url;
        $this->response = $response;
        $this->eventManager = $eventManager;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->userFactory = $userFactory;
        $this->inboxFactory = $inboxFactory;
    }

    /**
     * Around login plugin to add brute force protection
     * 
     * @param Auth $subject
     * @param \Closure $proceed
     * @param string $username
     * @param string $password
     * @return void
     * @throws PluginAuthenticationException
     * @throws AuthenticationException
     */
    public function aroundLogin(Auth $subject, \Closure $proceed, $username, $password)
    {
        if (empty($username) || empty($password)) {
            throw new PluginAuthenticationException(__('You did not sign in correctly or your account is temporarily disabled.'));
        }

        try { 
            $this->bruteForceUtility->log_debug("AuthPlugin : execute :admin login flow");

            // Get thresholds to check if both are disabled (0)
            $maxAttemptsDelayConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
            $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
            $maxAttemptsLockoutTemporaryConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_lockout', 'default', 0);
            $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
            
            // If both thresholds are 0 (disabled), skip all brute force protection
            if ($maxAttemptsDelay <= 0 && $maxAttemptsLockoutTemporary <= 0) {
                $this->bruteForceUtility->log_debug("Admin Login - Both delay and temp lockout thresholds are 0 (disabled). Skipping all brute force protection.");
                return $proceed($username, $password);
            }

            // Get user ID
            $userId = $this->getUserIdByUsername($username);
            
            if ($userId) {
                // Check if user is already in restriction lists
                $inDelayList = $this->bruteForceUtility->isUserInRestrictionList($userId, 'admin_delay');
                $inTempLockoutList = $this->bruteForceUtility->isUserInRestrictionList($userId, 'admin');
                
                // If user is already in either list, apply normal brute force protection
                // Don't clear flags - we'll check actual counts in checkBruteForceProtectionAdmin
                if ($inDelayList || $inTempLockoutList) {
                    $this->bruteForceUtility->log_debug("Admin Login - User already in restriction list. Applying normal brute force protection for user ID: $userId");
                    // Continue with normal brute force checks - limits will be checked in checkBruteForceProtectionAdmin
                } else {
                    // User not in list - check if limit is exceeded
                    // Check if limits are exceeded using helper functions
                    $delayLimitExceeded = !$this->bruteForceUtility->canApplyDelay($userId, 'admin_delay');
                    $tempLockoutLimitExceeded = !$this->bruteForceUtility->canApplyTemporaryLockout($userId, 'admin');

                    // If both limits exceeded, skip brute force protection entirely
                    if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                        $this->bruteForceUtility->log_debug("Admin Login - User not in list and both limits exceeded. Skipping brute force protection for user ID: $userId");
                        // Let request proceed normally without brute force protection
                        return $proceed($username, $password);
                    }
                    
                    // If delay limit is NOT exceeded but temp lockout limit IS exceeded
                    if (!$delayLimitExceeded && $tempLockoutLimitExceeded) {
                        // Get delay threshold to check if it's disabled (0)
                        $maxAttemptsDelayConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
                        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                        
                        if ($maxAttemptsDelay <= 0) {
                            $this->bruteForceUtility->log_debug("Admin Login - Temp lockout limit exceeded but delay threshold is 0 (disabled). Skipping brute force protection for user ID: $userId");
                            return $proceed($username, $password);
                        } else {
                            $this->bruteForceUtility->log_debug("Admin Login - Temp lockout limit exceeded but delay threshold is enabled. Allowing delay feature for user ID: $userId");
                        }
                    }
                    
                    // If temp lockout limit is NOT exceeded but delay limit IS exceeded
                    if ($delayLimitExceeded && !$tempLockoutLimitExceeded) {
                        // Get temp lockout threshold to check if it's disabled (0)
                        $maxAttemptsLockoutTemporaryConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_lockout', 'default', 0);
                        $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                        
                        if ($maxAttemptsLockoutTemporary <= 0) {
                            $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded but temp lockout threshold is 0 (disabled). Skipping brute force protection for user ID: $userId");
                            return $proceed($username, $password);
                        } else {
                            $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded but temp lockout threshold is enabled. Allowing temp lockout feature for user ID: $userId");
                        }
                    }
                    
                    // Store flags in session for use in brute force checks
                    $this->bruteForceUtility->setSessionValue('delay_limit_exceeded', $delayLimitExceeded);
                    $this->bruteForceUtility->setSessionValue('temp_lockout_limit_exceeded', $tempLockoutLimitExceeded);
                }
            }
            
            // 1) Pre-checks: temporary lockout and delay BEFORE authentication
            $shouldBlock = $this->preCheckAdminLockoutAndDelay($username);
            if ($shouldBlock) {
                // Set action flag to prevent further dispatch
                $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                // Set redirect in response
                if ($this->response instanceof \Magento\Framework\App\Response\Http) {
                    $this->response->setRedirect($this->url->getUrl('adminhtml/auth/login'));
                }
                return; // Exit early, don't proceed with login
            }
           
            // Call original login method - it will throw an exception if authentication fails
            try {
                $proceed($username, $password);
            } catch (PluginAuthenticationException $e) {
                // Authentication failed - handle brute force protection
                $shouldBlock = $this->checkBruteForceProtectionAdmin($username);
                if ($shouldBlock) {
                    // Set action flag to prevent further dispatch
                    $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                    // Set redirect in response
                    if ($this->response instanceof \Magento\Framework\App\Response\Http) {
                        $this->response->setRedirect($this->url->getUrl('adminhtml/auth/login'));
                    }
                    return; // Exit early, don't re-throw the exception
                }
                // No block needed, re-throw the original exception
                throw $e;
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                
                // Authentication failed - handle brute force protection
                $shouldBlock = $this->checkBruteForceProtectionAdmin($username);
                if ($shouldBlock) {
                    // Set action flag to prevent further dispatch
                    $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                    // Set redirect in response
                    if ($this->response instanceof \Magento\Framework\App\Response\Http) {
                        $this->response->setRedirect($this->url->getUrl('adminhtml/auth/login'));
                    }
                    return; // Exit early, don't re-throw the exception
                }
                // No block needed, throw PluginAuthenticationException
                throw new PluginAuthenticationException(__('You did not sign in correctly or your account is temporarily disabled.'));
            }
           
            // After successful login - only reached if $proceed() didn't throw an exception
            if ($subject->getCredentialStorage()->getId()) {
                // Get the details and set in the session
                $user = $subject->getCredentialStorage();
                $this->bruteForceUtility->setSessionValue('admin_user_id', $user->getId());
                $this->actionFlag->set('', Action::FLAG_NO_DISPATCH, true);
                $subject->getAuthStorage()->setData('user', $user);

                // Admin BruteForce settings are always global (default scope) - no website/store level configuration
                // Check if admin BruteForce protection is enabled
                $enableBFP = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/enabled', 'default', 0);
                $this->bruteForceUtility->log_debug("Admin brute force protection is enabled: " . $enableBFP . " (global settings)");

                // (Post-auth) nothing to do here; pre-check handled lock/delay already

                // Reset attempts and clear lock/delay on successful login
                $loginAttemptsKey = $this->getAdminLoginAttemptsSessionKey($username);
                $this->bruteForceUtility->setSessionValue($loginAttemptsKey, 0);
                
                // Clear admin lockout from session
                $lockoutKey = 'admin_locked_' . hash('sha256', $username);
                $this->bruteForceUtility->setSessionValue($lockoutKey, null);

                // Clear delay flag
                $delayKey = 'admin_login_delay_' . hash('sha256', $username);
                $this->bruteForceUtility->setSessionValue($delayKey, null);
                
                // Clear limit flags
                $this->bruteForceUtility->setSessionValue('delay_limit_exceeded', null);
                $this->bruteForceUtility->setSessionValue('temp_lockout_limit_exceeded', null);
                
                // Clear admin lockout from database
                $this->deleteAdminLockoutFromDb($username);
                
                // Proceed with normal login flow
                $this->normalLoginFlow($subject);
            }

            if (!$subject->getAuthStorage()->getUser()) {
                $exception = new PluginAuthenticationException(__('You did not sign in correctly or your account is temporarily disabled.'));

                $this->eventManager->dispatch(

                    'backend_auth_user_login_failed',

                    ['user_name' => $username, 'exception' => $exception]

                );

                throw $exception;
            }
        } catch (PluginAuthenticationException $e) {
            throw $e;
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            throw new PluginAuthenticationException(__('You did not sign in correctly or your account is temporarily disabled.'));
        }
       
    }

    /**
     * Normal login flow after successful authentication
     * @param Auth $subject
     * @return void
     */
    protected function normalLoginFlow(Auth $subject)
    {
        $this->bruteForceUtility->log_debug("AuthPlugin : execute: NormalLoginFlow");
        // Login process for admin
        $subject->getAuthStorage()->setUser($subject->getCredentialStorage());
        $subject->getAuthStorage()->processLogin();
      
        // Dispatch login success event
        $this->eventManager->dispatch(
            'backend_auth_user_login_success',
            ['user' => $subject->getCredentialStorage()]
        );
    }

    /**
     * Get account-specific session key for admin login attempts
     * @param string $username
     * @return string
     */
    public function getAdminLoginAttemptsSessionKey($username)
    {
        return 'admin_login_attempts_' . hash('sha256', $username);
    }

    /**
     * Check if admin user exists
     * @param string $username
     * @return bool
     */
    public function adminExists($username)
    {
        try {
            $user = $this->userFactory->create();
            $user->loadByUsername($username);
            return $user->getId() ? true : false;
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error checking if admin exists for username $username: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Pre-check admin lockout and delay before authentication (mirrors customer flow)
     * @param string $username
     * @return bool True if lockout/delay is active and should block login, false otherwise
     */
    public function preCheckAdminLockoutAndDelay($username)
    {
        // Check database for existing lockouts (temporary only - no permanent in free version)
        $lockedAccountData = $this->getAdminLockedAccountFromDb($username);
        
        if ($lockedAccountData) {
            // Check temporary lockout from DB
            if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['lock_until']) {
                $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
                $currentTime = time();
                
                if ($currentTime < $lockoutEndTime) {
                    $remainingTime = $lockoutEndTime - $currentTime;
                    $minutes = floor($remainingTime / 60);
                    $seconds = $remainingTime % 60;

                    if ($minutes > 0) {
                        $message = __('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 minutes and %2 seconds.', $minutes, $seconds);
                    } else {
                        $message = __('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 seconds.', $seconds);
                    }

                    $this->messageManager->addError($message);
                    return true; // Block login
                } else {
                    // Lockout expired - clear from DB and session, restore session attempts
                    $this->saveAdminLockoutToDb($username, $lockedAccountData['failed_attempts'], 'none', null);
                    $loginAttemptsKey = $this->getAdminLoginAttemptsSessionKey($username);
                    $this->bruteForceUtility->setSessionValue($loginAttemptsKey, $lockedAccountData['failed_attempts']);
                }
            }
        }
        
        // Temporary lockout check (session-based, backward compatibility)
        $lockoutKey = 'admin_locked_' . hash('sha256', $username);
        $lockoutUntil = $this->bruteForceUtility->getSessionValue($lockoutKey);
        if ($lockoutUntil && $lockoutUntil > time()) {
            $remainingTime = $lockoutUntil - time();
            $minutes = floor($remainingTime / 60);
            $seconds = $remainingTime % 60;

            if ($minutes > 0) {
                $message = __('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 minutes and %2 seconds.', $minutes, $seconds);
            } else {
                $message = __('Your admin account is temporarily locked due to multiple failed login attempts. Please try again in %1 seconds.', $seconds);
            }

            $this->messageManager->addError($message);
            return true; // Block login
        } elseif ($lockoutUntil) {
            // Expired - clear
            $this->bruteForceUtility->setSessionValue($lockoutKey, null);
        }

        // Admin BruteForce settings are always global (default scope)
        // Delay check - using global admin config
        // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
        $maxAttemptsDelayConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/delay_seconds', 'default', 0) ?: 30;
        
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay <= 0) {
            return false; // No delay, allow login
        }
        
        $loginAttemptsKey = $this->getAdminLoginAttemptsSessionKey($username);
        $attempts = $this->bruteForceUtility->getSessionValue($loginAttemptsKey) ?? 0;
        if ($attempts >= $maxAttemptsDelay) {
            $delayKey = 'admin_login_delay_' . hash('sha256', $username);
            $delayStart = $this->bruteForceUtility->getSessionValue($delayKey);
            if ($delayStart) {
                $elapsed = time() - $delayStart;
                if ($elapsed < $delaySeconds) {
                    // Active delay period - check delay limit only if delay is active
                    $delayLimitExceeded = $this->bruteForceUtility->getSessionValue('delay_limit_exceeded') ?? false;
                    $userId = $this->getUserIdByUsername($username);
                    if ($userId && $delayLimitExceeded) {
                        $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded during active delay. Skipping delay feature for user ID: $userId");
                        // Skip delay feature, allow authentication to proceed
                        $this->bruteForceUtility->setSessionValue($delayKey, null);
                        return false;
                    }
                    
                    $remaining = $delaySeconds - $elapsed;
                    $minutes = floor($remaining / 60);
                    $seconds = $remaining % 60;
                    if ($minutes > 0) {
                        $message = __('Too many failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                    } else {
                        $message = __('Too many failed login attempts. Please wait %1 seconds before trying again.', $seconds);
                    }
                    $this->messageManager->addError($message);
                    return true; // Block login
                } else {
                    // Delay has expired, remove delay flag - allow authentication to proceed
                    $this->bruteForceUtility->setSessionValue($delayKey, null);
                    $this->bruteForceUtility->log_debug("Admin login delay expired. Allowing authentication to proceed.");
                }
            }
            // If no active delay, allow authentication to proceed (delay limit will be checked after failed attempt)
        }

        return false; // No lockout/delay, allow login
    }

    /**
     * Check brute force protection for admin login
     * Returns true if delay/lock is applied and login should be blocked; otherwise false.
     */
    public function checkBruteForceProtectionAdmin($username)
    {
        // Admin BruteForce settings are always global (default scope) - no website/store level configuration
        // Check if admin BruteForce protection is enabled
        $enableBFP = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/enabled', 'default', 0);
        $this->bruteForceUtility->log_debug("Admin BruteForce protection enabled: " . $enableBFP . " (global settings)");
        
        if (!$enableBFP) {
            return false;
        }

        // Check if admin exists before applying brute force protection
        if (!$this->adminExists($username)) {
            $this->bruteForceUtility->log_debug("Admin not found for username: " . $username);
            return false;
        }

        // Account-based only (no IP): remove IP dependency
        $this->bruteForceUtility->log_debug("Admin login attempt for user: " . $username);

        // Get admin BruteForce settings from default scope (global) only
        // Allow 0 values to disable individual features - use null coalescing only for null/empty, not for 0
        $maxAttemptsDelayConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 'default', 0);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/delay_seconds', 'default', 0) ?: 30;
        $maxAttemptsWarning = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_warning', 'default', 0) ?: 2;
        $maxAttemptsLockoutTemporaryConfig = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/max_attempts_lockout', 'default', 0);
        $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
        $emailNotificationTiming = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/email_notification_timing', 'default', 0) ?: 'both';
        $adminEmailTemplateTemporary = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/admin_email_template_temporary', 'default', 0);

        // Get user ID
        $userId = $this->getUserIdByUsername($username);
        
        // Check if user is already in restriction lists
        $inDelayList = false;
        $inTempLockoutList = false;
        if ($userId) {
            $inDelayList = $this->bruteForceUtility->isUserInRestrictionList($userId, 'admin_delay');
            $inTempLockoutList = $this->bruteForceUtility->isUserInRestrictionList($userId, 'admin');
        }
        
        // Always check actual counts to determine if limits are exceeded (don't rely on session flags)
        $delayLimitExceeded = !$this->bruteForceUtility->canApplyDelay($userId, 'admin_delay');
        $tempLockoutLimitExceeded = !$this->bruteForceUtility->canApplyTemporaryLockout($userId, 'admin');
        
        // If user is NOT in either list, check if both limits exceeded
        if (!$inDelayList && !$inTempLockoutList && $userId) {
            // If both limits exceeded, skip brute force protection entirely
            if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                $this->bruteForceUtility->log_debug("Admin Login - User not in list and both limits exceeded. Skipping brute force protection for user ID: $userId");
                return false; // Don't interfere, let Magento handle
            }
        }

        // Get locked account data from database
        $lockedAccountData = $this->getAdminLockedAccountFromDb($username);

        // Get current attempts (email-based session key)
        $loginAttemptsKey = $this->getAdminLoginAttemptsSessionKey($username);
        $attempts = $this->bruteForceUtility->getSessionValue($loginAttemptsKey) ?? 0;
        $attempts++;
        $this->bruteForceUtility->setSessionValue($loginAttemptsKey, $attempts);
        
        $this->bruteForceUtility->log_debug("Admin login attempts: " . $attempts);
        $this->bruteForceUtility->log_debug("Admin BruteForce Protection - Thresholds - Delay: $maxAttemptsDelay, Temp Lockout: $maxAttemptsLockoutTemporary (NO permanent for free version)");

        // 1. FIRST: Check for TEMPORARY lockout (NO permanent lockout in free version)
        // Only check if temporary lockout threshold is > 0 (feature enabled)
        if ($maxAttemptsLockoutTemporary > 0 && $attempts >= $maxAttemptsLockoutTemporary) {
            // Check actual count to see if temp lockout limit is exceeded (only if user not in list)
            if (!$inTempLockoutList && $tempLockoutLimitExceeded) {
                $this->bruteForceUtility->log_debug("Admin Login - Temporary lockout limit exceeded. Skipping temp lockout, checking delay instead for user ID: $userId");
                // Apply delay if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    // Apply delay instead of lockout when temp lockout limit is reached
                    // Always apply delay (will start new delay if previous one expired)
                    $shouldBlock = $this->handleAdminLoginDelay($username, $delaySeconds, $maxAttemptsDelay);
                    if ($shouldBlock) {
                        return true; // Block login with delay
                    }
                    // If delay just expired and was allowed once, continue to allow this request
                    // Otherwise, delay will be applied again on next attempt
                    return false;
                } else {
                    // Delay limit exceeded and user not in delay list - skip brute force protection
                    $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded and user not in delay list. Skipping brute force protection.");
                    return false;
                }
            }
            
            // User in list or limit not exceeded - apply normal temp lockout
            // Only apply if user is in temp lockout list OR temp lockout limit is NOT exceeded
            if ($inTempLockoutList || !$tempLockoutLimitExceeded) {
                $this->bruteForceUtility->log_debug("Admin BruteForce Protection - TEMPORARY lockout triggered (attempts: $attempts >= $maxAttemptsLockoutTemporary)");
                $this->handleAdminAccountLockout($username, $adminEmailTemplateTemporary, $emailNotificationTiming);
                
                // Add user ID to the encrypted array if lockout was applied (only if not already in list)
                if ($userId && !$inTempLockoutList) {
                    $this->bruteForceUtility->addUserToTempLockoutList($userId, 'admin');
                }
                
                return true; // Block login
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
                    $shouldCheckDelay = ($attempts >= $maxAttemptsDelay);
                } else {
                    // Normal case: check delay only if below temp lockout threshold
                    $shouldCheckDelay = ($attempts >= $maxAttemptsDelay && $attempts < $maxAttemptsLockoutTemporary);
                }
            } else {
                // Temporary lockout is disabled
                // Apply delay forever if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    $shouldCheckDelay = ($attempts >= $maxAttemptsDelay);
                } else {
                    // Delay limit exceeded and user not in delay list - skip delay
                    $shouldCheckDelay = false;
                }
            }
            
            if ($shouldCheckDelay) {
                $this->bruteForceUtility->log_debug("Admin BruteForce Protection - Checking login delay");
                // Check actual count to see if delay limit is exceeded (only if user not in list)
                if (!$inDelayList && $delayLimitExceeded) {
                    $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded. Skipping delay feature for user ID: $userId");
                    // Skip delay feature, but don't block - let request proceed
                    return false;
                }
                $shouldBlock = $this->handleAdminLoginDelay($username, $delaySeconds, $maxAttemptsDelay);
                if ($shouldBlock) {
                    // Add user ID to the delay list if delay was applied (only if not already in list)
                    if ($userId && !$inDelayList) {
                        $this->bruteForceUtility->addUserToTempLockoutList($userId, 'admin_delay');
                    }
                    return true; // Block login
                }
            }
        } else if ($maxAttemptsLockoutTemporary > 0) {
            // Delay is disabled but temp lockout is enabled
            // If temp lockout limit is NOT exceeded, apply temp lockout forever (for all attempts >= temp lockout threshold)
            if (!$inTempLockoutList && !$tempLockoutLimitExceeded && $attempts >= $maxAttemptsLockoutTemporary) {
                $this->bruteForceUtility->log_debug("Admin BruteForce Protection - Delay disabled, applying TEMPORARY lockout forever (attempts: $attempts >= $maxAttemptsLockoutTemporary)");
                $this->handleAdminAccountLockout($username, $adminEmailTemplateTemporary, $emailNotificationTiming);
                
                // Add user ID to the encrypted array if lockout was applied (only if not already in list)
                if ($userId && !$inTempLockoutList) {
                    $this->bruteForceUtility->addUserToTempLockoutList($userId, 'admin');
                }
                
                return true; // Block login
            }
        }

        // Check for warning email
        if ($attempts == $maxAttemptsWarning) {
            // Use temporary template for warning emails, or pass null if not configured
            $warningTemplate = ($emailNotificationTiming === 'temporary' || $emailNotificationTiming === 'both') ? $adminEmailTemplateTemporary : null;
            $this->sendAdminWarningEmail($username, $warningTemplate);
        }

        return false; // No block needed
    }

    /**
     * Handle admin login delay
     * @param string $username
     * @param int $delaySeconds
     * @param int $maxAttemptsDelay
     * @return bool True if delay is active and should block login, false otherwise
     */
    protected function handleAdminLoginDelay($username, $delaySeconds, $maxAttemptsDelay = null)
    {
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay !== null && $maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        } 

        $delayKey = 'admin_login_delay_' . hash('sha256', $username);
        $allowOnceKey = 'admin_login_allow_once_' . hash('sha256', $username);
        $allowOnce = $this->bruteForceUtility->getSessionValue($allowOnceKey);
        
        // If delay just expired and we're allowing one request, don't start delay yet
        if ($allowOnce) {
            $this->bruteForceUtility->setSessionValue($allowOnceKey, null); // Clear the flag
            $this->bruteForceUtility->log_debug("Allowing this admin login request to proceed (delay just expired)");
            return false; // Allow request to proceed, delay will start on next attempt
        }
        
        $delayStart = $this->bruteForceUtility->getSessionValue($delayKey);
        
            if ($delayStart) {
                $elapsed = time() - $delayStart;
                if ($elapsed < $delaySeconds) {
                    // Active delay period - check delay limit only if delay is active
                    $delayLimitExceeded = $this->bruteForceUtility->getSessionValue('delay_limit_exceeded') ?? false;
                    $userId = $this->getUserIdByUsername($username);
                    if ($userId && $delayLimitExceeded) {
                        $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded during active delay. Skipping delay feature for user ID: $userId");
                        // Skip delay feature, allow authentication to proceed
                        $this->bruteForceUtility->setSessionValue($delayKey, null);
                        return false;
                    }
                    
                    $remaining = $delaySeconds - $elapsed;
                $minutes = floor($remaining / 60);
                $seconds = $remaining % 60;
                
                if ($minutes > 0) {
                    $message = __('Too many failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Too many failed login attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $this->bruteForceUtility->log_debug("Admin login delayed. Remaining: " . $remaining . " seconds");
                return true; // Block login
            } else {
                // Delay has expired - remove delay flag and set a flag to allow this one request
                $this->bruteForceUtility->setSessionValue($delayKey, null);
                $this->bruteForceUtility->setSessionValue($allowOnceKey, true);
                $this->bruteForceUtility->log_debug("Delay expired, allowing one admin login request to proceed");
                return false; // Allow this one request
            }
        } else {
            // Check if delay limit has been reached before starting delay
            $delayLimitExceeded = $this->bruteForceUtility->getSessionValue('delay_limit_exceeded') ?? false;
            $userId = $this->getUserIdByUsername($username);
            if ($userId && $delayLimitExceeded) {
                $this->bruteForceUtility->log_debug("Admin Login - Delay limit exceeded. Skipping delay feature for user ID: $userId");
                // Skip delay feature, don't block - let request proceed
                return false;
            }
            
            // Start delay timer (only if we're not allowing one request)
            $this->bruteForceUtility->setSessionValue($delayKey, time());
            // Add user ID to the delay list when starting delay
            if ($userId) {
                $this->bruteForceUtility->addUserToTempLockoutList($userId, 'admin_delay');
            }
            $message = __('Too many failed login attempts. Please wait %1 seconds before trying again.', $delaySeconds);
            $this->messageManager->addError($message);
            $this->bruteForceUtility->log_debug("Admin login delay started for user: " . $username);
            return true; // Block login
        }
    }

    /**
     * Handle admin account lockout
     * @param string $username
     * @param string $adminEmailTemplateTemporary
     * @param string $emailNotificationTiming
     */
    protected function handleAdminAccountLockout($username, $adminEmailTemplateTemporary, $emailNotificationTiming)
    {
        // Get lockout duration from configuration
        $lockoutDurationMinutes = (int)$this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/lockout_duration_minutes', 'default', 0);
        if ($lockoutDurationMinutes <= 0) {
            $lockoutDurationMinutes = 0; // No lockout if not configured
        }
        
        // Set lockout duration in seconds
        $lockoutDuration = $lockoutDurationMinutes * 60;
        $lockoutUntil = time() + $lockoutDuration;
        
        // Store lockout information using username-based key (global lockout)
        $lockoutKey = 'admin_locked_' . hash('sha256', $username);
        $this->bruteForceUtility->setSessionValue($lockoutKey, $lockoutUntil);
        
        $this->bruteForceUtility->log_debug("Admin account locked for user: " . $username . " until: " . date('Y-m-d H:i:s', $lockoutUntil));

        // Get locked account data
        $lockedAccountData = $this->getAdminLockedAccountFromDb($username);
        $loginAttemptsKey = $this->getAdminLoginAttemptsSessionKey($username);
        $attempts = (int)($this->bruteForceUtility->getSessionValue($loginAttemptsKey) ?? 0);
        
        // Persist lock to DB (shared table with customer login)
        $this->saveAdminLockoutToDb(
            $username,
            $attempts,
            'temporary',
            date('Y-m-d H:i:s', $lockoutUntil)
        );
        
        // Create admin dashboard notification (admin alert) - will check limit internally
        $this->createAdminAlert($username, 'temporary');
        
        // Get appropriate template based on timing setting
        $templateToUse = null;
        if ($emailNotificationTiming === 'temporary' || $emailNotificationTiming === 'both') {
            $templateToUse = $adminEmailTemplateTemporary;
        }
        
        // Send admin notification email
        $this->sendTemporaryLockoutEmail($username, $templateToUse);
        
        $message = __('Your admin account is temporarily locked due to multiple failed login attempts. Please wait %1 minutes before trying again.', $lockoutDurationMinutes);
        $this->messageManager->addError($message);
    }

    /**
     * Save admin lockout in mo_bruteforce_locked_accounts
     * Uses customer_id=0, user_type='admin', email=<username>, website=NULL (admins are global)
     */
    protected function saveAdminLockoutToDb($username, $failedAttempts, $lockType, $lockUntil = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = 'mo_bruteforce_locked_accounts';
            
            // Admin accounts use NULL for website (admins are global, not website-specific)
            $website = null;

            // Try to load existing admin row for this username using composite key (customer_id + email + user_type + website)
            $select = $connection->select()
                ->from($table)
                ->where('customer_id = ?', 0)
                ->where('user_type = ?', 'admin')
                ->where('email = ?', $username)
                ->where('website IS NULL')
                ->limit(1);
            $existing = $connection->fetchRow($select);

            $data = [
                'customer_id'    => 0,
                'email'          => $username,
                'user_type'      => 'admin',
                'website'        => $website,
                'failed_attempts'=> $failedAttempts,
                'lock_type'      => $lockType,
                'lock_until'     => $lockUntil,
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            if ($lockType === 'temporary') {
                $data['first_time_lockout'] = date('Y-m-d H:i:s');
            }
            
            // Reset sent_email to 0 when lock_type changes (new lockout event)
            if ($existing && $existing['lock_type'] !== $lockType) {
                $data['sent_email'] = 0;
            } elseif (!$existing) {
                $data['sent_email'] = 0;
            }

            if ($existing) {
                // Update using composite key (customer_id + email + user_type + website)
                // Use string WHERE clause for NULL check (Magento's update() doesn't handle NULL in array syntax)
                $where = "customer_id = 0 AND email = " . $connection->quote($username) . " AND user_type = 'admin' AND website IS NULL";
                $affectedRows = $connection->update($table, $data, $where);
                $this->bruteForceUtility->log_debug("Updated admin lockout in DB - Username: {$username}, Affected rows: {$affectedRows}");
            } else {
                // Insert new record
                try {
                    $connection->insert($table, $data);
                    $insertId = $connection->lastInsertId($table);
                    $this->bruteForceUtility->log_debug("Inserted new admin lockout in DB - Username: {$username}, Insert ID: {$insertId}");
                } catch (\Exception $insertException) {
                    $this->bruteForceUtility->log_debug("Failed inserting admin lockout to DB: " . $insertException->getMessage());
                    throw $insertException; // Re-throw to be caught by outer catch
                }
            }

            $this->bruteForceUtility->log_debug("Saved admin lockout to DB for username: {$username}, Type: {$lockType}, Website: NULL");
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Failed saving admin lockout to DB: " . $e->getMessage());
            $this->bruteForceUtility->log_debug("Exception trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Delete admin lockout from database
     * @param string $username Admin email/username
     * @return bool
     */
    public function deleteAdminLockoutFromDb($username)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            // Use string WHERE clause for NULL check (Magento's delete() doesn't handle NULL in array syntax)
            $where = "customer_id = 0 AND email = " . $connection->quote($username) . " AND user_type = 'admin' AND website IS NULL";
            
            $connection->delete('mo_bruteforce_locked_accounts', $where);
            $this->bruteForceUtility->log_debug("Deleted admin lockout from DB for username: {$username}");
            return true;
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error deleting admin lockout from DB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get admin locked account data from database
     * @param string $username
     * @return array|null
     */
    public function getAdminLockedAccountFromDb($username)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from('mo_bruteforce_locked_accounts')
                ->where('user_type = ?', 'admin')
                ->where('email = ?', $username)
                ->where('website IS NULL')
                ->limit(1);
            
            return $connection->fetchRow($select);
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error getting admin locked account from DB: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send temporary lockout email notification
     * @param string $username
     * @param string $adminEmailTemplate
     */
    protected function sendTemporaryLockoutEmail($username, $adminEmailTemplate)
    {
        try {
            $this->bruteForceUtility->log_debug("Sending TEMPORARY lockout notification email for admin: " . $username);
            
            $storeId = $this->storeManager->getStore()->getId();
            $emailNotificationsEnabled = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/email_notifications_enabled', 'default', 0);
            
            // If email notifications are disabled or template is not set, mark sent_email as 0
            if (!$emailNotificationsEnabled || !$adminEmailTemplate) {
                $this->markEmailNotSent(0, $username, 'admin');
                return;
            }
            
            // Send admin notification email
            // sendEmailNotification will check sent_email flag and skip if already sent
            if ($adminEmailTemplate) {
                $this->bruteForceUtility->log_debug("Sending admin temporary lockout email for user: " . $username);
                $this->sendEmailNotification($adminEmailTemplate, 'admin', $username, 'temporary');
            }
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error sending temporary lockout notification email: " . $e->getMessage());
        }
    }

    /**
     * Get admin user email by username
     * @param string $username
     * @return string|null
     */
    protected function getAdminUserEmail($username)
    {
        try {
            $user = $this->userFactory->create();
            $user->loadByUsername($username);
            if ($user->getId()) {
                $email = $user->getEmail();
                $this->bruteForceUtility->log_debug("Found admin user email for username $username: $email");
                return $email;
            }
            // If username is already an email, return it
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $this->bruteForceUtility->log_debug("Username $username appears to be an email address");
                return $username;
            }
            $this->bruteForceUtility->log_debug("Admin user not found for username: $username");
            return null;
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error getting admin user email for username $username: " . $e->getMessage());
            // Fallback: if username looks like an email, use it
            if (filter_var($username, FILTER_VALIDATE_EMAIL)) {
                return $username;
            }
            return null;
        }
    }

    /**
     * Send email notification using Magento email template
     * @param string $templateId Email template ID
     * @param string $recipientType 'admin' or 'customer'
     * @param string $username Admin email/username
     * @param string $lockoutType 'temporary' or 'permanent'
     */
    protected function sendEmailNotification($templateId, $recipientType, $username, $lockoutType)
    {
        try {
            // Check if email notification limit has been reached (global count)
            if (!$this->bruteForceUtility->canSendNotification('email', 'admin', 'login')) {
                $this->bruteForceUtility->log_debug("Email notification limit reached. Skipping email for: $username");
                return;
            }
            
            // Check if email has already been sent for this lockout
            $lockedAccountData = $this->getAdminLockedAccountFromDb($username);
            if ($lockedAccountData && isset($lockedAccountData['sent_email']) && $lockedAccountData['sent_email'] == 1) {
                $this->bruteForceUtility->log_debug("Email notification already sent for: " . $username . ". Skipping.");
                return;
            }
            
            // For admin emails, use default store (0) since admin settings are global
            $storeId = 0;
            
            // Get recipient email
            $recipientEmail = null;
            if ($recipientType === 'admin') {
                // Get admin user's actual email address
                $recipientEmail = $this->getAdminUserEmail($username);
                if (!$recipientEmail) {
                    $this->bruteForceUtility->log_debug("Cannot send email: admin user email not found for username: $username");
                    return;
                }
            } else {
                // Get admin email from system configuration
                $recipientEmail = $this->bruteForceUtility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
                if (!$recipientEmail) {
                    $recipientEmail = $this->bruteForceUtility->getStoreConfig('trans_email/ident_sales/email', 'stores', $storeId);
                }
            }
            
            if (!$recipientEmail || !$templateId) {
                $this->bruteForceUtility->log_debug("Cannot send email: recipient or template missing. Recipient: $recipientEmail, Template: $templateId");
                return;
            }
            
            // Get sender information from system configuration (use default store for admin)
            $senderName = $this->bruteForceUtility->getStoreConfig('trans_email/ident_general/name', 'stores', $storeId) ?: 'Store Owner';
            $senderEmail = $this->bruteForceUtility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
            
            if (!$senderEmail) {
                $this->bruteForceUtility->log_debug("Cannot send email: sender email not configured");
                return;
            }
            
            // Get admin user name for template variables
            $adminName = $username;
            try {
                $user = $this->userFactory->create();
                $user->loadByUsername($username);
                if ($user->getId()) {
                    $adminName = $user->getFirstName() . ' ' . $user->getLastName();
                    if (trim($adminName) === '') {
                        $adminName = $user->getUserName();
                    }
                }
            } catch (\Exception $e) {
                // Use username as fallback
            }
            
            // Prepare template variables
            $templateVars = [
                'admin_email' => $recipientEmail,
                'admin_name' => $adminName,
                'lockout_type' => $lockoutType,
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
                    ->addTo($recipientEmail)
                    ->getTransport();
                
                $transport->sendMessage();
                
                // Increment global email count
                $this->bruteForceUtility->incrementNotificationCount('email', 'admin', 'login');
                
                // Mark email as sent after successful send
                $this->markEmailSent(0, $username, 'admin');
                
                $this->bruteForceUtility->log_debug("Email notification sent successfully to $recipientEmail for $lockoutType lockout");
            } finally {
                // Resume inline translation
                $this->inlineTranslation->resume();
            }
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error sending email notification: " . $e->getMessage());
            $this->bruteForceUtility->log_debug("Exception trace: " . $e->getTraceAsString());
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
     * @param int $customerId (0 for admin)
     * @param string $email
     * @param string $userType
     * @return bool
     */
    protected function markEmailSent($customerId, $email, $userType = 'admin')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // For admin, website is always NULL - use string WHERE clause for NULL check
            $whereString = "customer_id = " . (int)$customerId . " AND user_type = 'admin'";
            $whereString .= " AND email = " . $connection->quote($email);
            $whereString .= " AND website IS NULL";
            $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 1], $whereString);
            $this->bruteForceUtility->log_debug("Marked email as sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
            return true;
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error marking email as sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark email notification as not sent in database
     * @param int $customerId (0 for admin)
     * @param string $email
     * @param string $userType
     * @return bool
     */
    protected function markEmailNotSent($customerId, $email, $userType = 'admin')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // For admin, website is always NULL - use string WHERE clause for NULL check
            $whereString = "customer_id = " . (int)$customerId . " AND user_type = 'admin'";
            $whereString .= " AND email = " . $connection->quote($email);
            $whereString .= " AND website IS NULL";
            $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 0], $whereString);
            $this->bruteForceUtility->log_debug("Marked email as not sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
            return true;
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error marking email as not sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create admin dashboard notification (admin alert)
     * @param string $username Admin email/username
     * @param string $lockoutType 'temporary' or 'permanent'
     */
    protected function createAdminAlert($username, $lockoutType = 'temporary')
    {
        try {
            // Check if admin alert limit has been reached (global count)
            if (!$this->bruteForceUtility->canSendNotification('admin_alert', 'admin', 'login')) {
                $this->bruteForceUtility->log_debug("Admin alert limit reached. Skipping admin alert for: $username");
                return;
            }
            
            // Admin BruteForce settings are always global (default scope)
            $adminAlertEnabled = $this->bruteForceUtility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/admin_alert_enabled', 'default', 0);
            if (!$adminAlertEnabled) {
                $this->bruteForceUtility->log_debug("Admin alert is disabled. Skipping notification for: " . $username);
                return;
            }
            
            // Prepare notification title and description
            $title = __('Security Alert!');
            
            $lockoutText = ($lockoutType === 'temporary') ? 'temporarily locked' : 'permanently locked';
            $description = __(
                'Admin account %1 was %2 due to multiple failed login attempts. View security logs for details.',
                $username,
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
            $this->bruteForceUtility->incrementNotificationCount('admin_alert', 'admin', 'login');
            
            // Note: sent_email flag is only for emails, not admin alerts
            // Admin alerts are dashboard notifications, not emails
            
            $this->bruteForceUtility->log_debug("Admin alert notification created for: " . $username . " (Type: " . $lockoutType . ")");
            
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error creating admin alert notification: " . $e->getMessage());
            $this->bruteForceUtility->log_debug("Exception trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Get admin user ID by username
     * @param string $username
     * @return int|null
     */
    protected function getUserIdByUsername($username)
    {
        try {
            $user = $this->userFactory->create();
            $user->loadByUsername($username);
            if ($user->getId()) {
                return $user->getId();
            }
        } catch (\Exception $e) {
            $this->bruteForceUtility->log_debug("Error getting admin user ID for username $username: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Send admin warning email
     * @param string $username
     * @param string $adminEmailTemplate
     */
    protected function sendAdminWarningEmail($username, $adminEmailTemplate)
    {
        if ($adminEmailTemplate) {
            $this->bruteForceUtility->log_debug("Sending admin warning email for user: " . $username);
            // TODO: Implement email sending logic
        }
    }

    /**
     * Get BruteForceUtility instance (for use by other plugins)
     * @return BruteForceUtility
     */
    public function getBruteForceUtility()
    {
        return $this->bruteForceUtility;
    }
}

