<?php

namespace MiniOrange\BruteForceProtection\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\AdminNotification\Model\InboxFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use MiniOrange\BruteForceProtection\Helper\AESEncryption;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\User\Model\UserFactory;

/**
 * BruteForce Service - Contains all brute force protection logic
 * This service can be reused across different plugins and controllers
 */
class BruteForceService
{
    protected $bruteforceutility;
    protected $resourceConnection;
    protected $customerRepository;
    protected $inboxFactory;
    protected $storeManager;
    protected $transportBuilder;
    protected $inlineTranslation;
    protected $escaper;
    protected $eventManager;
    protected $session;
    protected $resultRedirectFactory;
    protected $customerAccountManagement;
    protected $customerUrl;
    protected $formKeyValidator;
    protected $messageManager;
    protected $request;
    protected $remoteAddress;
    protected $filesystem;
    protected $productMetadata;
    protected $userFactory;

    public function __construct(
        BruteForceUtility $bruteforceutility,
        ResourceConnection $resourceConnection,
        CustomerRepositoryInterface $customerRepository,
        InboxFactory $inboxFactory,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        EventManager $eventManager,
        Session $session,
        RedirectFactory $resultRedirectFactory,
        AccountManagementInterface $customerAccountManagement,
        CustomerUrl $customerUrl,
        FormKeyValidator $formKeyValidator,
        ManagerInterface $messageManager,
        RequestInterface $request,
        RemoteAddress $remoteAddress,
        Filesystem $filesystem,
        ProductMetadataInterface $productMetadata,
        UserFactory $userFactory
    ) {
        $this->bruteforceutility = $bruteforceutility;
        $this->resourceConnection = $resourceConnection;
        $this->customerRepository = $customerRepository;
        $this->inboxFactory = $inboxFactory;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->eventManager = $eventManager;
        $this->session = $session;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->customerUrl = $customerUrl;
        $this->formKeyValidator = $formKeyValidator;
        $this->messageManager = $messageManager;
        $this->request = $request;
        $this->remoteAddress = $remoteAddress;
        $this->filesystem = $filesystem;
        $this->productMetadata = $productMetadata;
        $this->userFactory = $userFactory;
    }

    /**
     * Process customer login with brute force protection
     * 
     * @param object $subject - Controller instance
     * @param \Closure $proceed - Original controller method
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function processLogin($subject, \Closure $proceed)
    {
        $this->bruteforceutility->log_debug("=== BRUTEFORCE LOGIN POST PLUGIN EXECUTED ===");

        // Use injected dependencies
        $request = $subject->getRequest();

        if ($this->session->isLoggedIn() || !$this->formKeyValidator->validate($request)) {
            /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account');
            return $resultRedirect;
        }
      
        if ($request->isPost()) {
            $login = $request->getPost('login');
            $resultRedirect = $this->resultRedirectFactory->create();

            // Check if username/password is empty or not
            if (!empty($login['username']) && !empty($login['password'])) {
                // Get thresholds to check if both are disabled (0)
                $storeId = $this->storeManager->getStore()->getId();
                $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
                $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_lockout', 'stores', $storeId);
                $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                
                // If both thresholds are 0 (disabled), skip all brute force protection
                if ($maxAttemptsDelay <= 0 && $maxAttemptsLockoutTemporary <= 0) {
                    $this->bruteforceutility->log_debug("Customer Login - Both delay and temp lockout thresholds are 0 (disabled). Skipping all brute force protection.");
                    return $proceed();
                }
                
                // Get customer ID
                $customerId = $this->getCustomerIdByEmail($login['username']);
                
                if ($customerId) {
                    // Check if user is already in restriction lists
                    $inDelayList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'customer_delay');
                    $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'customer');
                    
                    // If user is already in either list, apply normal brute force protection
                    // Don't clear flags - we'll check actual counts in checkBruteForceProtection
                    if ($inDelayList || $inTempLockoutList) {
                        $this->bruteforceutility->log_debug("Customer Login - User already in restriction list. Applying normal brute force protection for customer ID: $customerId");
                        // Continue with normal brute force checks - limits will be checked in checkBruteForceProtection
                    } else {
                        // User not in list - check if limit is exceeded
                        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($customerId, 'customer_delay');
                        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($customerId, 'customer');
                        
                        // If both limits exceeded, skip brute force protection entirely
                        if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                            $this->bruteforceutility->log_debug("Customer Login - User not in list and both limits exceeded. Skipping brute force protection for customer ID: $customerId");
                            // Let request proceed normally without brute force protection
                            return $proceed();
                        }
                        
                        // If delay limit is NOT exceeded but temp lockout limit IS exceeded
                        if (!$delayLimitExceeded && $tempLockoutLimitExceeded) {
                            // Get delay threshold to check if it's disabled (0)
                            $storeId = $this->storeManager->getStore()->getId();
                            $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
                            $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
                            
                            if ($maxAttemptsDelay <= 0) {
                                $this->bruteforceutility->log_debug("Customer Login - Temp lockout limit exceeded but delay threshold is 0 (disabled). Skipping brute force protection for customer ID: $customerId");
                                return $proceed();
                            } else {
                                $this->bruteforceutility->log_debug("Customer Login - Temp lockout limit exceeded but delay threshold is enabled. Allowing delay feature for customer ID: $customerId");
                            }
                        }
                        
                        // If temp lockout limit is NOT exceeded but delay limit IS exceeded
                        if ($delayLimitExceeded && !$tempLockoutLimitExceeded) {
                            // Get temp lockout threshold to check if it's disabled (0)
                            $storeId = $this->storeManager->getStore()->getId();
                            $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_lockout', 'stores', $storeId);
                            $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
                            
                            if ($maxAttemptsLockoutTemporary <= 0) {
                                $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded but temp lockout threshold is 0 (disabled). Skipping brute force protection for customer ID: $customerId");
                                return $proceed();
                            } else {
                                $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded but temp lockout threshold is enabled. Allowing temp lockout feature for customer ID: $customerId");
                            }
                        }
                        
                        // Store flags in session for use in brute force checks
                        $this->bruteforceutility->setSessionValue('delay_limit_exceeded', $delayLimitExceeded);
                        $this->bruteforceutility->setSessionValue('temp_lockout_limit_exceeded', $tempLockoutLimitExceeded);
                    }
                }
                
                // Check if account is temporarily locked BEFORE authentication
                $lockoutResult = $this->checkTemporaryLockoutOnly($login['username']);
                if ($lockoutResult !== null) {
                    // Log locked attempt (account is already locked)
                    $customerId = $this->getCustomerIdByEmail($login['username']);
                    $loginAttemptsKey = $this->getLoginAttemptsSessionKey($login['username'], $this->storeManager->getStore()->getId());
                    $attempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
                    if ($customerId) {
                        $website = $this->getCurrentWebsite();
                        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                        $attempts = $lockedAccountData ? $lockedAccountData['failed_attempts'] : $attempts;
                    }
                    return $lockoutResult; // Return redirect if temporarily locked
                }
                
                try {
                    // Only authenticate if account is not locked
                    $customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
                    
                    // Reset failed attempts and clear all protection flags on successful login (account-specific)
                    $loginAttemptsKey = $this->getLoginAttemptsSessionKey($login['username'], $this->storeManager->getStore()->getId());
                    $this->bruteforceutility->setSessionValue($loginAttemptsKey, 0);
                    $this->bruteforceutility->setSessionValue('account_locked', 0);
                    $this->bruteforceutility->setSessionValue('lockout_type', null);
                    $this->bruteforceutility->setSessionValue('twofa_required', 0);
                    // Clear limit flags
                    $this->bruteforceutility->setSessionValue('delay_limit_exceeded', null);
                    $this->bruteforceutility->setSessionValue('temp_lockout_limit_exceeded', null);
                    
                    // Check if account had temporary lockout before clearing it (for 2FA trigger)
                    $customerId = $this->getCustomerIdByEmail($login['username']);
                    $hadTemporaryLockout = false;
                    if ($customerId) {
                        // Get website for current store
                        $website = $this->getCurrentWebsite();
                        // Get locked account data BEFORE deleting to check if temporary lockout occurred
                        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                        
                        // Check if account had a temporary lockout (first_time_lockout indicates temporary lockout was triggered)
                        if ($lockedAccountData && !empty($lockedAccountData['first_time_lockout'])) {
                            $hadTemporaryLockout = true;
                            $this->bruteforceutility->log_debug("Account had temporary lockout previously (first_time_lockout: " . $lockedAccountData['first_time_lockout'] . "). Will trigger 2FA if enabled.");
                        }
                        
                        // Clear locked account from database
                        $this->deleteLockedAccountFromDb($customerId, null, 'customer', $website);

                    }
                    
                    // Clear login delay
                    $website = $this->getCurrentWebsite();
                    $websiteId = $website ? hash('sha256', $website) : 'default';
                    $delayKey = 'login_delay_' . hash('sha256', $login['username'] . '_' . $websiteId);
                    $this->bruteforceutility->setSessionValue($delayKey, null);

                } catch (EmailNotConfirmedException $e) {
                    $value = $this->customerUrl->getEmailConfirmationUrl($login['username']);
                    $message = __(
                            'This account is not confirmed.' .
                            ' <a href="%1">Click here</a> to resend confirmation email.', $value
                    );
                    $this->messageManager->addError($message);
                    $this->session->setUsername($login['username']);
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;

                } catch (AuthenticationException $e) {
                    $storeId = $this->storeManager->getStore()->getId();
                    // Get current attempt count before checking brute force protection (account-specific)
                    $loginAttemptsKey = $this->getLoginAttemptsSessionKey($login['username'], $storeId);
                    $attempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
                    $attempts++; // Increment for this failed attempt
                    
                    // Check BruteForce protection on authentication failure
                    $bruteforceResult = $this->checkBruteForceProtection($login['username'], $storeId);
                    
                    // Determine if account was locked after this attempt
                    $lockStatus = 0;
                    $customerId = $this->getCustomerIdByEmail($login['username']);
                    if ($customerId) {
                        $website = $this->getWebsiteFromStoreId($storeId);
                        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                        if ($lockedAccountData && $lockedAccountData['lock_type'] !== 'none') {
                            $lockStatus = 1;
                        }
                    }
                    
                    if ($bruteforceResult !== null) {
                        return $bruteforceResult; // Return redirect if blocked
                    }
                    
                    $message = __('The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.');
                    $this->messageManager->addError($message);
                    $this->session->setUsername($login['username']);
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;

                } catch (\Exception $e) {
                    $this->messageManager->addError(__('The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.'));
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;
                }
            } else {
                $this->messageManager->addError(__('A login and a password are required.'));
                $resultRedirect->setPath('customer/account/login');
                return $resultRedirect;
            }
        }

        // If not POST, proceed with original controller logic
        return $proceed();
    }

    /**
     * Process customer API token creation with brute force protection
     * This is for REST API / GraphQL token creation endpoints
     * 
     * @param object $subject - CustomerTokenServiceInterface instance
     * @param \Closure $proceed - Original method
     * @param string $username - Customer email/username
     * @param string $password - Customer password
     * @return string - Access token
     * @throws AuthenticationException
     */
    public function processApiTokenCreation($subject, \Closure $proceed, $username, $password)
    {
        $this->bruteforceutility->log_debug("=== BRUTEFORCE API TOKEN CREATION PLUGIN EXECUTED ===");
        $this->bruteforceutility->log_debug("API Token Creation - Username: " . $username);

        // Check if username/password is empty
        if (empty($username) || empty($password)) {
            throw new AuthenticationException(__('A login and a password are required.'));
        }

        // Extract store code from request URL (e.g., /default/rest/all/V1/integration/customer/token)
        $storeId = $this->getStoreIdFromRequest();
        $this->bruteforceutility->log_debug("API Token Creation - Store ID from request: " . ($storeId ?? 'NULL'));

        // Check if account is temporarily/permanently locked BEFORE authentication
        $lockoutException = $this->checkApiLockout($username, $storeId);
        if ($lockoutException !== null) {
            // Log locked attempt (account is already locked)
            $customerId = $this->getCustomerIdByEmail($username);
            $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
            $attempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
            if ($customerId) {
                $website = $this->getWebsiteFromStoreId($storeId);
                $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                $attempts = $lockedAccountData ? $lockedAccountData['failed_attempts'] : $attempts;
            }
            throw $lockoutException;
        }

        try {
            // Attempt to create the token
            $token = $proceed($username, $password);
            
            // Reset failed attempts and clear all protection flags on successful login (account-specific)
            $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
            $this->bruteforceutility->setSessionValue($loginAttemptsKey, 0);
            $this->bruteforceutility->setSessionValue('account_locked', 0);
            $this->bruteforceutility->setSessionValue('lockout_type', null);
            $this->bruteforceutility->setSessionValue('twofa_required', 0);
            
            // Clear locked account from database
            $customerId = $this->getCustomerIdByEmail($username);
            if ($customerId) {
                $website = $this->getWebsiteFromStoreId($storeId);
                $this->deleteLockedAccountFromDb($customerId, null, 'customer', $website);
            }
            
            // Clear login delay
            $website = $this->getWebsiteFromStoreId($storeId);
            $websiteId = $website ? hash('sha256', $website) : 'default';
            $delayKey = 'login_delay_' . hash('sha256', $username . '_' . $websiteId);
            $this->bruteforceutility->setSessionValue($delayKey, null);

            $this->bruteforceutility->log_debug("API Token created successfully for user: " . $username);
            return $token;

        } catch (AuthenticationException $e) {
            // Get current attempt count before checking brute force protection (account-specific)
            $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
            $attempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
            $attempts++; // Increment for this failed attempt
            
            // Check BruteForce protection on authentication failure (use store ID from request)
            $bruteforceException = $this->checkApiBruteForceProtection($username, $storeId);
            
            // Determine if account was locked after this attempt
            $lockStatus = 0;
            $customerId = $this->getCustomerIdByEmail($username);
            if ($customerId) {
                $website = $this->getWebsiteFromStoreId($storeId);
                $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                if ($lockedAccountData && $lockedAccountData['lock_type'] !== 'none') {
                    $lockStatus = 1;
                }
            }
            
            // If brute force protection triggered, throw that exception instead
            if ($bruteforceException !== null) {
                throw $bruteforceException;
            }
            
            // Otherwise, throw the original authentication exception
            throw $e;

        } catch (\Exception $e) {
            throw new AuthenticationException(__('Invalid login or password.'));
        }
    }

    /**
     * Check API lockout (reuses existing methods, converts redirects to exceptions)
     * @param string $username
     * @param int|null $storeId Optional store ID from request
     * @return AuthenticationException|null
     */
    protected function checkApiLockout($username, $storeId = null)
    {
        // Reuse existing checkTemporaryLockoutOnly method
        $redirectResult = $this->checkTemporaryLockoutOnly($username, $storeId);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username, $storeId);
        }
        
        return null;
    }

    /**
     * Check API login delay (reuses existing method, converts redirects to exceptions)
     * @param string $username
     * @return AuthenticationException|null
     */
    protected function checkApiLoginDelay($username)
    {
        // Reuse existing checkLoginDelayBeforeAuth method
        $redirectResult = $this->checkLoginDelayBeforeAuth($username);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username);
        }
        
        return null;
    }

    /**
     * Check BruteForce protection for API token creation (reuses existing method, converts redirects to exceptions)
     * @param string $username
     * @param int|null $storeId Optional store ID from request
     * @return AuthenticationException|null
     */
    protected function checkApiBruteForceProtection($username, $storeId = null)
    {
        // Reuse existing checkBruteForceProtection method with store ID from request
        $redirectResult = $this->checkBruteForceProtection($username, $storeId);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username, $storeId);
        }
        
        return null;
    }

    /**
     * Check API account lockout from database (reuses existing method, converts redirects to exceptions)
     * @param string $username
     * @param int $customerId
     * @return AuthenticationException|null
     */
    protected function checkApiAccountLockoutFromDb($username, $customerId)
    {
        // Reuse existing checkAccountLockoutFromDb method
        $redirectResult = $this->checkAccountLockoutFromDb($username, $customerId);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username);
        }
        
        return null;
    }

    /**
     * Check API login delay after failure (reuses existing method, converts redirects to exceptions)
     * @param string $username
     * @param int $delaySeconds
     * @return AuthenticationException|null
     */
    protected function checkApiLoginDelayAfterFailure($username, $delaySeconds, $storeId = null)
    {
        // Get delay threshold to pass to checkLoginDelay
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        // Reuse existing checkLoginDelay method
        $redirectResult = $this->checkLoginDelay($username, $delaySeconds, $storeId, $maxAttemptsDelay);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username);
        }
        
        return null;
    }

    /**
     * Check API temporary lockout (reuses existing method, converts redirects to exceptions)
     * @param string $username
     * @param int $customerId
     * @param array|null $lockedAccountData
     * @return AuthenticationException|null
     */
    protected function checkApiTemporaryLockout($username, $customerId, $lockedAccountData = null)
    {
        // Reuse existing checkTemporaryLockout method
        $redirectResult = $this->checkTemporaryLockout($username, $customerId, $lockedAccountData);
        
        // If redirect is returned, convert to exception
        if ($redirectResult !== null) {
            return $this->convertRedirectToException($redirectResult, $username);
        }
        
        return null;
    }

    /**
     * Convert redirect result to AuthenticationException for API calls
     * Extracts lockout information from database and creates appropriate exception message
     * @param \Magento\Framework\Controller\Result\Redirect $redirect
     * @param string $username
     * @param int|null $storeId Optional store ID from request
     * @return AuthenticationException
     */
    protected function convertRedirectToException($redirect, $username, $storeId = null)
    {
        // Try to get the last error message from messageManager
        $messages = $this->messageManager->getMessages();
        $errorMessages = $messages->getErrors();
        
        if (!empty($errorMessages)) {
            $lastMessage = end($errorMessages);
            $messageText = $lastMessage->getText();
            // Clear the message from messageManager to avoid it being sent in API response
            $this->messageManager->getMessages()->clear();
            return new AuthenticationException(__($messageText));
        }
        
        // Fallback: Extract lockout information from database and create appropriate message
        $customerId = $this->getCustomerIdByEmail($username);
        if ($customerId) {
            $website = $this->getWebsiteFromStoreId($storeId);
            $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
            
            if ($lockedAccountData) {
                if ($lockedAccountData['lock_type'] === 'permanent') {
                    return new AuthenticationException(
                        __('Your account has been permanently locked due to multiple failed login attempts. Please contact administrator.')
                    );
                }
                
                if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['lock_until']) {
                    $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
                    $currentTime = time();
                    
                    if ($currentTime < $lockoutEndTime) {
                        $remainingTime = $lockoutEndTime - $currentTime;
                        $minutes = floor($remainingTime / 60);
                        $seconds = $remainingTime % 60;
                        
                        if ($minutes > 0) {
                            return new AuthenticationException(
                                __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds)
                            );
                        } else {
                            return new AuthenticationException(
                                __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 seconds before trying again.', $seconds)
                            );
                        }
                    }
                }
            }
        }
        
        // Fallback: Use a generic message
        return new AuthenticationException(__('Access denied. Please try again later.'));
    }

    /**
     * Get account-specific session key for login attempts
     * Includes website identifier to avoid conflicts when same email exists in multiple websites
     * @param string $username
     * @param int|null $storeId Optional store ID (for API calls to get correct website)
     * @return string
     */
    public function getLoginAttemptsSessionKey($username, $storeId = null)
    {
        $website = $this->getWebsiteFromStoreId($storeId);
        $websiteId = $website ? hash('sha256', $website) : 'default';
        return 'login_attempts_' . hash('sha256', $username . '_' . $websiteId);
    }

    /**
     * Check BruteForce protection for customer login
     * @param string $username
     * @param int|null $storeId Optional store ID (for API calls)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkBruteForceProtection($username, $storeId = null)
    {
        // If store ID is not provided, use current store
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        
        // Get customer BruteForce settings for current store (will inherit from website → default if not set)
        // Allow 0 values to disable individual features - use null coalescing only for null/empty, not for 0
        $enabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/enabled', 'stores', $storeId);
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/delay_seconds', 'stores', $storeId) ?: 30;
        $maxAttemptsLockoutTemporaryConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_lockout', 'stores', $storeId);
        $maxAttemptsLockoutTemporary = ($maxAttemptsLockoutTemporaryConfig !== null && $maxAttemptsLockoutTemporaryConfig !== '') ? (int)$maxAttemptsLockoutTemporaryConfig : 5;
        $lockoutDurationMinutes = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/lockout_duration_minutes', 'stores', $storeId) ?: 30;
        $maxAttemptsLockoutPermanentConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_permanent_lockout', 'stores', $storeId);
        $maxAttemptsLockoutPermanent = ($maxAttemptsLockoutPermanentConfig !== null && $maxAttemptsLockoutPermanentConfig !== '') ? (int)$maxAttemptsLockoutPermanentConfig : 10;
        $EmailNotificationsEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/email_notifications_enabled', 'stores', $storeId);
        $emailNotificationTiming = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/email_notification_timing', 'stores', $storeId) ?: 'both';
        $AdminAlertEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/admin_alert_enabled', 'stores', $storeId);
        $customerEmailTemplateTemporary = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/customer_email_template_temporary', 'stores', $storeId);
        $customerEmailTemplatePermanent = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/customer_email_template_permanent', 'stores', $storeId);
        
        $this->bruteforceutility->log_debug("Customer BruteForce Protection - Enabled: " . $enabled);
        $this->bruteforceutility->log_debug("Customer BruteForce Protection - Username: " . $username);
        $this->bruteforceutility->log_debug("Customer BruteForce Protection - Thresholds - Delay: $maxAttemptsDelay, Temp Lockout: $maxAttemptsLockoutTemporary, Permanent Lockout: $maxAttemptsLockoutPermanent");
        
        if (!$enabled) {
            $this->bruteforceutility->log_debug("Customer BruteForce Protection is disabled. Skipping protection.");
            return null;
        }
        
        // Get customer ID for database operations
        $customerId = $this->getCustomerIdByEmail($username);
        if (!$customerId) {
            $this->bruteforceutility->log_debug("Customer not found for email: " . $username);
            return null;
        }
        
        // Check if user is already in restriction lists
        $inDelayList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'customer_delay');
        $inTempLockoutList = $this->bruteforceutility->isUserInRestrictionList($customerId, 'customer');
        
        // Always check actual counts to determine if limits are exceeded (don't rely on session flags)
        $delayLimitExceeded = !$this->bruteforceutility->canApplyDelay($customerId, 'customer_delay');
        $tempLockoutLimitExceeded = !$this->bruteforceutility->canApplyTemporaryLockout($customerId, 'customer');
        
        // If user is NOT in either list, check if both limits exceeded
        if (!$inDelayList && !$inTempLockoutList) {
            // If both limits exceeded, skip brute force protection entirely
            if ($delayLimitExceeded && $tempLockoutLimitExceeded) {
                $this->bruteforceutility->log_debug("Customer Login - User not in list and both limits exceeded. Skipping brute force protection for customer ID: $customerId");
                return null; // Don't interfere, let Magento handle
            }
        }
        
        // Check if account is already locked (using database)
        $lockoutResult = $this->checkAccountLockoutFromDb($username, $customerId, $storeId);
        if ($lockoutResult !== null) {
            return $lockoutResult; // Return redirect if locked
        }
        
        // Get current session attempts (account-specific)
        // Use store ID to get correct website for session key
        $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
        $sessionAttempts++;
        
        // Get website from store ID (for API calls) or current store
        $website = $this->getWebsiteFromStoreId($storeId);
        
        // Get locked account data from database
        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
        
        // Calculate total attempts (session attempts only, DB attempts are already stored)
        $totalAttempts = $sessionAttempts;
        
        $this->bruteforceutility->log_debug("Customer BruteForce Protection - Session Attempts: $sessionAttempts, DB Attempts: " . ($lockedAccountData['failed_attempts'] ?? 0) . ", Total: $totalAttempts");
        
        $this->bruteforceutility->setSessionValue($loginAttemptsKey, $sessionAttempts);
        
        // 2. SECOND: Check for TEMPORARY lockout
        if ($maxAttemptsLockoutTemporary > 0) {
            $shouldCheckTempLockout = false;
            $shouldCheckTempLockout = ($totalAttempts >= $maxAttemptsLockoutTemporary);
           
            if ($shouldCheckTempLockout) {
                // Check actual count to see if temp lockout limit is exceeded (only if user not in list)
                if (!$inTempLockoutList && $tempLockoutLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer Login - Temporary lockout limit exceeded. Skipping temp lockout, checking delay instead for customer ID: $customerId");
                    // Apply delay if user is in delay list OR delay limit is NOT exceeded
                    if ($inDelayList || !$delayLimitExceeded) {
                        // Apply delay instead of lockout when temp lockout limit is reached
                        // Get delay seconds from configuration
                        if ($storeId === null) {
                            $storeId = $this->storeManager->getStore()->getId();
                        }
                        $delaySeconds = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/delay_seconds', 'stores', $storeId) ?: 30;
                        // Always apply delay (will start new delay if previous one expired)
                        $delayResult = $this->checkLoginDelay($username, $delaySeconds, $storeId, $maxAttemptsDelay);
                        
                        if ($delayResult !== null) {
                            return $delayResult; // Return redirect if delay is active
                        }
                       
                        // If delay just expired and was allowed once, continue to allow this request
                        // Otherwise, delay will be applied again on next attempt
                        return null;
                    } else {
                        // Delay limit exceeded and user not in delay list - skip brute force protection
                        $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded and user not in delay list. Skipping brute force protection.");
                        return null;
                    }
                }
                
                // User in list or limit not exceeded - apply normal temp lockout
                // Only apply if user is in temp lockout list OR temp lockout limit is NOT exceeded
                if ($inTempLockoutList || !$tempLockoutLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer BruteForce Protection - TEMPORARY lockout triggered (attempts: $totalAttempts >= $maxAttemptsLockoutTemporary)");
                    $lockoutResult = $this->checkTemporaryLockout($username, $customerId, $lockedAccountData, $storeId);
                    if ($lockoutResult !== null) {
                        // Add customer ID to the encrypted array if lockout was applied (only if not already in list)
                        if ($customerId && !$inTempLockoutList) {
                            $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer');
                        }
                        // Get appropriate template based on timing setting
                        $templateToUse = null;
                        if ($emailNotificationTiming === 'temporary' || $emailNotificationTiming === 'both') {
                            $templateToUse = $customerEmailTemplateTemporary;
                        }
                        $this->sendTemporaryLockoutEmail($username, $templateToUse, $storeId);
                        return $lockoutResult; // Return redirect if locked
                    }
                }
            }
        }
       
        // 3. THIRD: Check for login delay (only if not locked)
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
                // Both temp and permanent lockouts are disabled
                // Apply delay forever if user is in delay list OR delay limit is NOT exceeded
                if ($inDelayList || !$delayLimitExceeded) {
                    $shouldCheckDelay = ($totalAttempts >= $maxAttemptsDelay);
                } else {
                    // Delay limit exceeded and user not in delay list - skip delay
                    $shouldCheckDelay = false;
                }
            }
            if ($shouldCheckDelay) {
                $this->bruteforceutility->log_debug("Customer BruteForce Protection - Checking login delay");
                // Check actual count to see if delay limit is exceeded (only if user not in list)
                if (!$inDelayList && $delayLimitExceeded) {
                    $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded. Skipping delay feature for customer ID: $customerId");
                    // Skip delay feature, but don't block - let request proceed
                    return null;
                }
                
                $delayResult = $this->checkLoginDelay($username, $delaySeconds, $storeId, $maxAttemptsDelay);
                if ($delayResult !== null) {
                    // Add customer ID to the delay list if delay was applied (only if not already in list)
                    if ($customerId && !$inDelayList) {
                        $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer_delay');
                    }
                    return $delayResult; // Return redirect if delay is active
                }
            }
        } else if ($maxAttemptsLockoutTemporary > 0) {
            // Delay is disabled but temp lockout is enabled
            // If temp lockout limit is NOT exceeded, apply temp lockout forever (for all attempts >= temp lockout threshold)
            if (!$inTempLockoutList && !$tempLockoutLimitExceeded && $totalAttempts >= $maxAttemptsLockoutTemporary) {
                $this->bruteforceutility->log_debug("Customer BruteForce Protection - Delay disabled, applying TEMPORARY lockout forever (attempts: $totalAttempts >= $maxAttemptsLockoutTemporary)");
                $lockoutResult = $this->checkTemporaryLockout($username, $customerId, $lockedAccountData, $storeId);
                if ($lockoutResult !== null) {
                    // Add customer ID to the encrypted array if lockout was applied (only if not already in list)
                    if ($customerId && !$inTempLockoutList) {
                        $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer');
                    }
                    // Get appropriate template based on timing setting
                    $templateToUse = null;
                    if ($emailNotificationTiming === 'temporary' || $emailNotificationTiming === 'both') {
                        $templateToUse = $customerEmailTemplateTemporary;
                    }
                    $this->sendTemporaryLockoutEmail($username, $templateToUse, $storeId);
                    return $lockoutResult; // Return redirect if locked
                }
            }
        }
        
        return null; // No action needed
    }

    /**
     * Get locked account data from database
     * @param int $customerId
     * @return array|null
     */
    public function getLockedAccountFromDb($customerId, $email = null, $userType = 'customer', $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()
                ->from('mo_bruteforce_locked_accounts')
                ->where('customer_id = ?', $customerId)
                ->where('user_type = ?', $userType);
            
            // If email is provided, also filter by email (for admin accounts)
            if ($email) {
                $select->where('email = ?', $email);
            }
            
            // For customers, filter by website to differentiate same user from different websites
            // For admins, website is NULL (admins are global, not website-specific)
            if ($userType === 'customer' && $website !== null) {
                $select->where('website = ?', $website);
            } elseif ($userType === 'admin') {
                // For admin, website is always NULL
                $select->where('website IS NULL');
            }
            
            return $connection->fetchRow($select);
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting locked account from DB: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get website name/ID for current store
     * @return string|null
     */
    public function getCurrentWebsite()
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
     * Get website name from store ID
     * @param int|null $storeId Store ID (if null, uses current store)
     * @return string|null
     */
    protected function getWebsiteFromStoreId($storeId = null)
    {
        try {
            if ($storeId !== null) {
                $store = $this->storeManager->getStore($storeId);
            } else {
                $store = $this->storeManager->getStore();
            }
            $website = $store->getWebsite();
            return $website ? $website->getName() : null;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error getting website from store ID: " . ($storeId ?? 'current') . " - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert or update locked account in database
     * @param int $customerId
     * @param int $failedAttempts
     * @param string $lockType
     * @param int|null $lockUntil
     * @param string $email
     * @param string $userType
     * @param string|null $website
     * @return bool
     */
    public function saveLockedAccountToDb($customerId, $failedAttempts, $lockType, $lockUntil = null, $email = null, $userType = 'customer', $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = 'mo_bruteforce_locked_accounts';
            
            // Get email if not provided
            if (!$email) {
                $email = $this->getEmailByCustomerId($customerId);
            }
            
            // Get website for customers (from current store), use NULL for admin accounts
            if ($userType === 'customer' && $website === null) {
                $website = $this->getCurrentWebsite();
            } elseif ($userType === 'admin') {
                $website = null; // Admins are global, not website-specific
            }
            
            // Check if record exists using composite key (customer_id + email + user_type + website)
            $existingRecord = $this->getLockedAccountFromDb($customerId, $email, $userType, $website);
            
            $data = [
                'customer_id' => $customerId,
                'email' => $email,
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
                // New record, set sent_email to 0
                $data['sent_email'] = 0;
            }
            // If existing record and lock_type unchanged, don't modify sent_email
            
            if ($existingRecord) {
                // Update existing record using composite key (customer_id + email + user_type + website)
                if ($userType === 'admin' && $website === null) {
                    // For admin with NULL website, use string WHERE clause (Magento's update() doesn't handle NULL in array syntax)
                    $whereString = "customer_id = " . (int)$customerId . " AND email = " . $connection->quote($email) . " AND user_type = 'admin' AND website IS NULL";
                    $affectedRows = $connection->update($tableName, $data, $whereString);
                    $this->bruteforceutility->log_debug("Updated admin lockout - Customer ID: $customerId, Email: $email, Affected rows: $affectedRows");
                } else {
                    // For customers, use array WHERE clause
                    $connection->update(
                        $tableName, 
                        $data, 
                        [
                            'customer_id = ?' => $customerId,
                            'email = ?' => $email,
                            'user_type = ?' => $userType,
                            'website = ?' => $website
                        ]
                    );
                }
            } else {
                // Insert new record
                try {
                    $connection->insert($tableName, $data);
                    $insertId = $connection->lastInsertId($tableName);
                    $this->bruteforceutility->log_debug("Inserted new lockout - Customer ID: $customerId, Email: $email, User Type: $userType, Website: " . ($website ?? 'NULL') . ", Insert ID: $insertId");
                } catch (\Exception $insertException) {
                    $this->bruteforceutility->log_debug("Failed inserting lockout to DB: " . $insertException->getMessage());
                    $this->bruteforceutility->log_debug("Insert exception trace: " . $insertException->getTraceAsString());
                    throw $insertException; // Re-throw to be caught by outer catch
                }
            }
            
            $this->bruteforceutility->log_debug("Saved locked account to DB - Customer ID: $customerId, Email: $email, User Type: $userType, Website: " . ($website ?? 'NULL') . ", Type: $lockType");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error saving locked account to DB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete locked account from database
     * @param int $customerId
     * @param string|null $email
     * @param string $userType
     * @return bool
     */
    public function deleteLockedAccountFromDb($customerId, $email = null, $userType = 'customer', $website = null)
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $where = [
                'customer_id = ?' => $customerId,
                'user_type = ?' => $userType
            ];
            
            // If email is provided, also filter by email (for admin accounts)
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
                $connection->delete('mo_bruteforce_locked_accounts', $whereString);
                $this->bruteforceutility->log_debug("Deleted locked account from DB - Customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
                return true;
            }
            
            if ($userType !== 'admin') {
                $connection->delete('mo_bruteforce_locked_accounts', $where);
            }
            $this->bruteforceutility->log_debug("Deleted locked account from DB - Customer ID: $customerId, Email: $email, User Type: $userType, Website: $website");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error deleting locked account from DB: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get customer ID by email
     * @param string $email
     * @return int|null
     */
    public function getCustomerIdByEmail($email)
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
    public function getEmailByCustomerId($customerId)
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
     * Check for temporary/permanent lockout and delay (before authentication)
     * @param string $username
     * @param int|null $storeId Optional store ID (for API calls to get correct website)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkTemporaryLockoutOnly($username, $storeId = null)
    {
        // Get customer ID for database operations
        $customerId = $this->getCustomerIdByEmail($username);
        if (!$customerId) {
            // Customer not found, but still check for delay
            return $this->checkLoginDelayBeforeAuth($username, $storeId);
        }
        
        // Get website from store ID (for API calls) or current store
        $website = $this->getWebsiteFromStoreId($storeId);
        
        // Get locked account data from database
        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
        
        if (!$lockedAccountData) {
            // No lockout data in DB, but still check for delay
            return $this->checkLoginDelayBeforeAuth($username, $storeId);
        }
        
        // Check if it's a permanent lockout
        if ($lockedAccountData['lock_type'] === 'permanent') {
            $this->bruteforceutility->log_debug("Account is PERMANENTLY locked in DB (failed attempts: " . $lockedAccountData['failed_attempts'] . ").");
            $message = __('Your account has been permanently locked due to multiple failed login attempts. Please contact administrator.');
            $this->messageManager->addError($message);
            $this->session->setUsername($username);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }
        
        // Check temporary lockout
        if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['first_time_lockout'] && $lockedAccountData['lock_until']) {
            $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
            $currentTime = time();
            
            if ($currentTime < $lockoutEndTime) {
                $remainingTime = $lockoutEndTime - $currentTime;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $this->bruteforceutility->log_debug("Account is TEMPORARILY locked in DB. Remaining time: " . $minutes . " minutes and " . $seconds . " seconds.");
                
                if ($minutes > 0) {
                    $message = __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $this->session->setUsername($username);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/login');
                return $resultRedirect;
            } else {
                // Lockout period has expired, set session to DB failed attempts (account-specific)
                $dbFailedAttempts = $lockedAccountData['failed_attempts'];
                $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
                $this->bruteforceutility->setSessionValue($loginAttemptsKey, $dbFailedAttempts);
                
                // Get website from store ID (for API calls) or current store
                $website = $this->getWebsiteFromStoreId($storeId);
                
                // Update database: reset lock_type to 'none', and clear lock_until
                // This allows user to try again while keeping track of failed attempts
                $this->saveLockedAccountToDb($customerId, $dbFailedAttempts, 'none', null, $username, 'customer', $website);
                
                $this->bruteforceutility->log_debug("Temporary lockout expired. Set session attempts to DB value: $dbFailedAttempts. Lock cleared, user can try again.");
            }
        }
        
        // Check for login delay before authentication (pass store ID if available)
        return $this->checkLoginDelayBeforeAuth($username, $storeId ?? null);
    }

    /**
     * Check login delay before authentication
     * @param string $username
     * @param int|null $storeId Optional store ID (for API calls)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkLoginDelayBeforeAuth($username, $storeId = null)
    {
        // If store ID is not provided, use current store
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        // Allow 0 values to disable delay feature - use null coalescing only for null/empty, not for 0
        $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
        $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
        $delaySeconds = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/delay_seconds', 'stores', $storeId) ?: 30;
        
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay <= 0) {
            return null;
        }
        
        // Get customer ID for delay limit checking
        $customerId = $this->getCustomerIdByEmail($username);
        
        // Get session attempts (account-specific)
        // Use store ID to get correct website for session key
        $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
        
        // Check if in delay period
        if ($sessionAttempts >= $maxAttemptsDelay) {
            $website = $this->getWebsiteFromStoreId($storeId);
            $websiteId = $website ? hash('sha256', $website) : 'default';
            $delayKey = 'login_delay_' . hash('sha256', $username . '_' . $websiteId);
            $delayStartTime = $this->bruteforceutility->getSessionValue($delayKey);
            
            if ($delayStartTime) {
                $currentTime = time();
                $elapsedTime = $currentTime - $delayStartTime;
                
                if ($elapsedTime < $delaySeconds) {
                    // Active delay period - check delay limit only if delay is active
                    $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
                    if ($customerId && $delayLimitExceeded) {
                        $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded during active delay. Skipping delay feature for customer ID: $customerId");
                        // Skip delay feature, allow authentication to proceed
                        $this->bruteforceutility->setSessionValue($delayKey, null);
                        return null;
                    }
                    
                    $remainingTime = $delaySeconds - $elapsedTime;
                    $this->bruteforceutility->log_debug("Login delay active. Remaining time: $remainingTime seconds.");
                    // Add customer ID to the delay list if delay is active
                    if ($customerId) {
                        $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer_delay');
                    }
                    $message = __('Please wait %1 seconds before trying again.', $remainingTime);
                    $this->messageManager->addError($message);
                    $this->session->setUsername($username);
                    $resultRedirect = $this->resultRedirectFactory->create();
                    $resultRedirect->setPath('customer/account/login');
                    return $resultRedirect;
                } else {
                    // Delay has expired, remove delay flag - allow authentication to proceed
                    $this->bruteforceutility->setSessionValue($delayKey, null);
                    $this->bruteforceutility->log_debug("Login delay expired. Allowing authentication to proceed.");
                }
            }
            // If no active delay, allow authentication to proceed (delay limit will be checked after failed attempt)
        }
        
        return null; // No delay, proceed with authentication
    }

    /**
     * Check account lockout from database
     * @param string $username
     * @param int $customerId
     * @param int|null $storeId Optional store ID (for API calls to get correct website)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkAccountLockoutFromDb($username, $customerId, $storeId = null)
    {
        // Get website from store ID (for API calls) or current store
        $website = $this->getWebsiteFromStoreId($storeId);
        
        $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
        
        if (!$lockedAccountData) {
            return null; // No lockout data in DB
        }
        
        // Check if it's a permanent lockout
        if ($lockedAccountData['lock_type'] === 'permanent') {
            $this->bruteforceutility->log_debug("Account is PERMANENTLY locked in DB (failed attempts: " . $lockedAccountData['failed_attempts'] . ").");
            $message = __('Your account has been permanently locked due to multiple failed login attempts. Please contact administrator.');
            $this->messageManager->addError($message);
            $this->session->setUsername($username);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }
        
        // Check temporary lockout
        if ($lockedAccountData['lock_type'] === 'temporary' && $lockedAccountData['first_time_lockout'] && $lockedAccountData['lock_until']) {
            $lockoutEndTime = strtotime($lockedAccountData['lock_until']);
            $currentTime = time();
            
            if ($currentTime < $lockoutEndTime) {
                $remainingTime = $lockoutEndTime - $currentTime;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $this->bruteforceutility->log_debug("Account is TEMPORARILY locked in DB. Remaining time: " . $minutes . " minutes and " . $seconds . " seconds.");
                
                if ($minutes > 0) {
                    $message = __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $this->session->setUsername($username);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/login');
                return $resultRedirect;
            } else {
                // Lockout period has expired, set session to DB failed attempts (account-specific)
                $dbFailedAttempts = $lockedAccountData['failed_attempts'];
                $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
                $this->bruteforceutility->setSessionValue($loginAttemptsKey, $dbFailedAttempts);
                
                // Get website from store ID (for API calls) or current store
                $website = $this->getWebsiteFromStoreId($storeId);
                
                // Update database: reset lock_type to 'none', and clear lock_until
                // This allows user to try again while keeping track of failed attempts
                $this->saveLockedAccountToDb($customerId, $dbFailedAttempts, 'none', null, $username, 'customer', $website);
                
                $this->bruteforceutility->log_debug("Temporary lockout expired. Set session attempts to DB value: $dbFailedAttempts. Lock cleared, user can try again.");
            }
        }
        
        return null;
    }

    /**
     * Check login delay
     * @param string $username
     * @param int $delaySeconds
     * @param int|null $storeId Optional store ID (for API calls)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkLoginDelay($username, $delaySeconds, $storeId = null, $maxAttemptsDelay = null)
    {
        // If delay threshold is 0, delay feature is disabled - skip delay check
        if ($maxAttemptsDelay !== null && $maxAttemptsDelay <= 0) {
            return null; // No delay, proceed with processing
        }
        
        $website = $this->getWebsiteFromStoreId($storeId);
        $websiteId = $website ? hash('sha256', $website) : 'default';
        $delayKey = 'login_delay_' . hash('sha256', $username . '_' . $websiteId);
        $delayStartTime = $this->bruteforceutility->getSessionValue($delayKey);
        if ($delayStartTime) {
            $currentTime = time();
            $elapsedTime = $currentTime - $delayStartTime;
            
            if ($elapsedTime < $delaySeconds) {
                $remainingTime = $delaySeconds - $elapsedTime;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $this->bruteforceutility->log_debug("Login delay active. Remaining time: " . $minutes . " minutes and " . $seconds . " seconds");
                
                if ($minutes > 0) {
                    $message = __('Too many failed login attempts. Please wait %1 minutes and %2 seconds before trying again.', $minutes, $seconds);
                } else {
                    $message = __('Too many failed login attempts. Please wait %1 seconds before trying again.', $seconds);
                }
                
                $this->messageManager->addError($message);
                $this->session->setUsername($username);
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setPath('customer/account/login');
                return $resultRedirect;
            } else {
                // Delay period has expired, clear it
                $this->bruteforceutility->setSessionValue($delayKey, null);
                $this->bruteforceutility->log_debug("Login delay period expired. Delay cleared.");
            }
        } else {
            // About to start a new delay - check delay limit before starting
            $delayLimitExceeded = $this->bruteforceutility->getSessionValue('delay_limit_exceeded') ?? false;
            $customerId = $this->getCustomerIdByEmail($username);
            if ($customerId && $delayLimitExceeded) {
                $this->bruteforceutility->log_debug("Customer Login - Delay limit exceeded. Skipping delay feature for customer ID: $customerId");
                // Skip delay feature, don't block - let request proceed
                return null;
            }
            
            // Set delay start time for future attempts
            $this->bruteforceutility->setSessionValue($delayKey, time());
            $this->bruteforceutility->log_debug("Login delay started for user: " . $username);
            
            // Add customer ID to the delay list when starting delay
            if ($customerId) {
                $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer_delay');
            }
            
            $message = __('Too many failed login attempts. Please wait %1 seconds before trying again.', $delaySeconds);
            $this->messageManager->addError($message);
            $this->session->setUsername($username);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }
        
        return null;
    }

    /**
     * Check temporary lockout
     * @param string $username
     * @param int $customerId
     * @param array|null $lockedAccountData
     * @param int|null $storeId Optional store ID (for API calls)
     * @return null|\Magento\Framework\Controller\Result\Redirect
     */
    public function checkTemporaryLockout($username, $customerId, $lockedAccountData = null, $storeId = null)
    {
        // Check if temporary lockout limit has been reached
        if (!$this->bruteforceutility->canApplyTemporaryLockout($customerId, 'customer')) {
            $this->bruteforceutility->log_debug("Temporary lockout limit reached. Applying delay instead of lockout for customer ID: $customerId");
            // Get delay seconds from configuration
            if ($storeId === null) {
                $storeId = $this->storeManager->getStore()->getId();
            }
            $delaySeconds = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/delay_seconds', 'stores', $storeId) ?: 30;
            // Get delay threshold to pass to checkLoginDelay
            $maxAttemptsDelayConfig = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 'stores', $storeId);
            $maxAttemptsDelay = ($maxAttemptsDelayConfig !== null && $maxAttemptsDelayConfig !== '') ? (int)$maxAttemptsDelayConfig : 3;
            // Apply delay instead of lockout when limit is reached
            return $this->checkLoginDelay($username, $delaySeconds, $storeId, $maxAttemptsDelay);
        }
        
        // Calculate total attempts (account-specific)
        $loginAttemptsKey = $this->getLoginAttemptsSessionKey($username, $storeId);
        $sessionAttempts = $this->bruteforceutility->getSessionValue($loginAttemptsKey) ?? 0;
        $totalAttempts = $sessionAttempts;
        
        // Get website for current store
        $website = $this->getWebsiteFromStoreId($storeId);
        
        // Get lockout duration from configuration
        if ($storeId === null) {
            $storeId = $this->storeManager->getStore()->getId();
        }
        $lockoutDurationMinutes = (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/lockout_duration_minutes', 'stores', $storeId);
        if ($lockoutDurationMinutes <= 0) {
            $lockoutDurationMinutes = 0; // No lockout if not configured
        }
        
        // Save temporary lockout to database
        $lockoutDuration = date('Y-m-d H:i:s', time() + ($lockoutDurationMinutes * 60));
        
        $this->saveLockedAccountToDb($customerId, $totalAttempts, 'temporary', $lockoutDuration, $username, 'customer', $website);

        // Add customer ID to the encrypted array
                $this->bruteforceutility->addUserToTempLockoutList($customerId, 'customer');

         // Create admin dashboard notification (admin alert)
         $this->createAdminAlert($username, 'temporary', $storeId);
        
        // Clear session attempts (account-specific)
        $this->bruteforceutility->setSessionValue($loginAttemptsKey, 0);
        
        $this->bruteforceutility->log_debug("Temporary lockout saved to DB for user: " . $username . " Total attempts: $totalAttempts");
        
        $message = __('Your account is temporarily locked due to multiple failed login attempts. Please wait %1 minutes before trying again.', $lockoutDurationMinutes);
        $this->messageManager->addError($message);
        $this->session->setUsername($username);
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('customer/account/login');
        return $resultRedirect;
    }

    /**
     * Send temporary lockout email notification
     * @param string $username
     * @param string $customerEmailTemplate
     * @param int|null $storeId Optional store ID (for API calls)
     */
    public function sendTemporaryLockoutEmail($username, $customerEmailTemplate, $storeId = null)
    {
        try {
            $this->bruteforceutility->log_debug("Sending TEMPORARY lockout notification email for user: " . $username);
            
            $emailNotificationsEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/email_notifications_enabled', 'stores', $storeId);
            
            $customerId = $this->getCustomerIdByEmail($username);
            
            // If email notifications are disabled or template is not set, mark sent_email as 0
            if (!$emailNotificationsEnabled || !$customerEmailTemplate) {
                if ($customerId) {
                    $this->markEmailNotSent($customerId, $username, 'customer', $storeId);
                }
                return;
            }
            
            // Send customer notification email
            // sendEmailNotification will check sent_email flag and skip if already sent
            if ($customerEmailTemplate) {
                $this->bruteforceutility->log_debug("Sending customer temporary lockout email for user: " . $username);
                $this->sendEmailNotification($customerEmailTemplate, 'customer', $username, 'temporary', $storeId);
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending temporary lockout notification email: " . $e->getMessage());
        }
    }

    /**
     * Send permanent lockout email notification
     * @param string $username
     * @param string $customerEmailTemplate
     * @param int|null $storeId Optional store ID (for API calls)
     */
    public function sendPermanentLockoutEmail($username, $customerEmailTemplate, $storeId = null)
    {
        try {
            $this->bruteforceutility->log_debug("Sending PERMANENT lockout notification email for user: " . $username);
            
            $emailNotificationsEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/email_notifications_enabled', 'stores', $storeId);
            
            $customerId = $this->getCustomerIdByEmail($username);
            
            // If email notifications are disabled or template is not set, mark sent_email as 0
            if (!$emailNotificationsEnabled || !$customerEmailTemplate) {
                if ($customerId) {
                    $this->markEmailNotSent($customerId, $username, 'customer', $storeId);
                }
                return;
            }
            
            // Send customer notification email
            if ($customerEmailTemplate) {
                $this->bruteforceutility->log_debug("Sending customer permanent lockout email for user: " . $username);
                $this->sendEmailNotification($customerEmailTemplate, 'customer', $username, 'permanent', $storeId);
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending permanent lockout notification email: " . $e->getMessage());
        }
    }

    /**
     * Send email notification using Magento email template
     * @param string $templateId Email template ID
     * @param string $recipientType 'admin' or 'customer'
     * @param string $username Customer email/username
     * @param string $lockoutType 'warning', 'temporary', or 'permanent'
     * @param int|null $storeId Optional store ID (for API calls)
     */
    public function sendEmailNotification($templateId, $recipientType, $username, $lockoutType, $storeId = null)
    {
        try {
            // Check if email notification limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('email', 'customer', 'login')) {
                $this->bruteforceutility->log_debug("Email notification limit reached. Skipping email for: $username");
                return;
            }
            
            // Get customer ID to check sent_email flag
            $customerId = $this->getCustomerIdByEmail($username);
            if ($customerId) {
                // Check if email has already been sent for this lockout (sent_email flag only tracks emails, not admin alerts)
                // Get website for current store
                $website = $this->getWebsiteFromStoreId($storeId);
                $lockedAccountData = $this->getLockedAccountFromDb($customerId, null, 'customer', $website);
                if ($lockedAccountData && isset($lockedAccountData['sent_email']) && $lockedAccountData['sent_email'] == 1) {
                    $this->bruteforceutility->log_debug("Email notification already sent for: " . $username . ". Skipping.");
                    return;
                }
            }
            
            if ($storeId === null) {
                $storeId = $this->storeManager->getStore()->getId();
            }
            
            // Get recipient email
            $recipientEmail = null;
            if ($recipientType === 'admin') {
                // Get admin email from system configuration
                $recipientEmail = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
                if (!$recipientEmail) {
                    $recipientEmail = $this->bruteforceutility->getStoreConfig('trans_email/ident_sales/email', 'stores', $storeId);
                }
            } else {
                // Customer email is the username
                $recipientEmail = $username;
            }
            
            if (!$recipientEmail || !$templateId) {
                $this->bruteforceutility->log_debug("Cannot send email: recipient or template missing. Recipient: $recipientEmail, Template: $templateId");
                return;
            }
            
            // Get sender information from system configuration
            $senderName = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/name', 'stores', $storeId) ?: 'Store Owner';
            $senderEmail = $this->bruteforceutility->getStoreConfig('trans_email/ident_general/email', 'stores', $storeId);
            
            if (!$senderEmail) {
                $this->bruteforceutility->log_debug("Cannot send email: sender email not configured");
                return;
            }
            
            // Get customer ID for additional template variables
            $customerId = $this->getCustomerIdByEmail($username);
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
                'customer_email' => $username,
                'customer_name' => $customerName ?: $username,
                'lockout_type' => $lockoutType,
                'store' => $this->storeManager->getStore(),
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
                $this->bruteforceutility->incrementNotificationCount('email', 'customer', 'login');
                
                // Mark email as sent after successful send (only for emails, not admin alerts)
                if ($customerId) {
                    $this->markEmailSent($customerId, $username, 'customer');
                }
                
                $this->bruteforceutility->log_debug("Email notification sent successfully to $recipientEmail for $lockoutType lockout");
            } finally {
                // Resume inline translation
                $this->inlineTranslation->resume();
            }
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error sending email notification: " . $e->getMessage());
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
     * @param int $customerId
     * @param string|null $email
     * @param string $userType
     * @return bool
     */
    public function markEmailSent($customerId, $email = null, $userType = 'customer')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Get website for current store
            $website = $this->getCurrentWebsite();
            
            $where = [
                'customer_id = ?' => $customerId,
                'user_type = ?' => $userType
            ];
            
            // If email is provided, also filter by email (for admin accounts)
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
                $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 1], $whereString);
                $this->bruteforceutility->log_debug("Marked email as sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
                return true;
            }
            
            if ($userType !== 'admin') {
                $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 1], $where);
            }
            $this->bruteforceutility->log_debug("Marked email as sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: $website");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error marking email as sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark email notification as not sent in database
     * @param int $customerId
     * @param string|null $email
     * @param string $userType
     * @return bool
     */
    public function markEmailNotSent($customerId, $email = null, $userType = 'customer')
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Get website for current store
            $website = $this->getCurrentWebsite();
            
            $where = [
                'customer_id = ?' => $customerId,
                'user_type = ?' => $userType
            ];
            
            // If email is provided, also filter by email (for admin accounts)
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
                $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 0], $whereString);
                $this->bruteforceutility->log_debug("Marked email as not sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: NULL");
                return true;
            }
            
            if ($userType !== 'admin') {
                $connection->update('mo_bruteforce_locked_accounts', ['sent_email' => 0], $where);
            }
            $this->bruteforceutility->log_debug("Marked email as not sent for customer ID: $customerId, Email: $email, User Type: $userType, Website: $website");
            return true;
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error marking email as not sent: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create admin dashboard notification (admin alert)
     * @param string $username Customer email/username
     * @param string $lockoutType 'temporary' or 'permanent'
     */
    public function createAdminAlert($username, $lockoutType = 'temporary')
    {
        try {
            // Check if admin alert limit has been reached (global count)
            if (!$this->bruteforceutility->canSendNotification('admin_alert', 'customer', 'login')) {
                $this->bruteforceutility->log_debug("Admin alert limit reached. Skipping admin alert for: $username");
                return;
            }
            
            // Get customer ID (optional - for logging purposes)
            $customerId = $this->getCustomerIdByEmail($username);
            
            // Get current store ID for store-specific configuration
            $storeId = $this->storeManager->getStore()->getId();
            $adminAlertEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/admin_alert_enabled', 'stores', $storeId);
            if (!$adminAlertEnabled) {
                return;
            }
            
            // Prepare notification title and description
            $title = __('Security Alert!');
            
            $lockoutText = ($lockoutType === 'temporary') ? 'temporarily locked' : 'permanently locked';
            $description = __(
                '%1 was %2. View security logs for details.',
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
            $this->bruteforceutility->incrementNotificationCount('admin_alert', 'customer', 'login');
            
            // Note: sent_email flag is only for emails, not admin alerts
            // Admin alerts are dashboard notifications, not emails
            
            $this->bruteforceutility->log_debug("Admin alert notification created for: " . $username . " (Type: " . $lockoutType . ")");
            
        } catch (\Exception $e) {
            $this->bruteforceutility->log_debug("Error creating admin alert notification: " . $e->getMessage());
        }
    }

    /**
     * Get store ID from request URL (for API calls)
     * Extracts store code from URL like /index.php/default/rest/all/V1/integration/customer/token
     * @return int|null Store ID or null if not found
     */
    protected function getStoreIdFromRequest()
    {
        try {
            // Get the full request URI (includes store code)
            $requestUri = $this->request->getRequestUri();
            $this->bruteforceutility->log_debug("API Token Creation - Request URI: " . $requestUri);
        
            $uriParts = parse_url($requestUri);
            $path = $uriParts['path'] ?? '';
            
            // Remove /index.php if present
            $path = preg_replace('#^/index\.php#', '', $path);
            
            // Split by /rest/ to get the part before it
            if (preg_match('#^/([^/]+)/rest/#', $path, $matches)) {
                $storeCode = $matches[1];
                $this->bruteforceutility->log_debug("API Token Creation - Extracted store code from URI: " . $storeCode);
                
                // Get store by code
                $store = $this->storeManager->getStore($storeCode);
                if ($store && $store->getId()) {
                    $storeId = $store->getId();
                    $this->bruteforceutility->log_debug("API Token Creation - Store ID from code '$storeCode': $storeId");
                    return $storeId;
                }
            }
            
            // Fallback: try to get store from current store manager context
            // Magento might have already set the store based on the URL
            $storeId = $this->storeManager->getStore()->getId();
            $this->bruteforceutility->log_debug("API Token Creation - Store code not found in URI, using current store: $storeId");
            return $storeId;
            
        } catch (\Exception $e) {
            // Fallback to current store if error
            $storeId = $this->storeManager->getStore()->getId();
            $this->bruteforceutility->log_debug("API Token Creation - Error extracting store from request, using current store: $storeId - " . $e->getMessage());
            return $storeId;
        }
    }
}

