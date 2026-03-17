<?php

namespace MiniOrange\TwoFA\Controller\Account;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Helper\Curl;


/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LoginPost extends \Magento\Customer\Controller\Account\LoginPost
{
    protected $context;
    protected $customerSession;
    protected $customerAccountManagement;
    protected $customerUrl;
    protected $formKeyValidator;
    protected $accountRedirect;
    protected $twofaUtility;
    protected $cookieManager;
    protected $cookieMetadataFactory;
    protected $moduleManager;
    protected $storeManager;

    public function __construct(
        Context                    $context,
        Session                    $customerSession,
        AccountManagementInterface $customerAccountManagement,
        CustomerUrl                $customerHelperData,
        Validator                  $formKeyValidator,
        AccountRedirect            $accountRedirect,
        TwoFAUtility               $twofaUtility,
        CookieManagerInterface     $cookieManager,
        CookieMetadataFactory      $cookieMetadataFactory,
        ModuleManager              $moduleManager,
        StoreManagerInterface      $storeManager
    )
    {
        $this->customerSession = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->customerUrl = $customerHelperData;
        $this->formKeyValidator = $formKeyValidator;
        $this->accountRedirect = $accountRedirect;
        $this->twofaUtility = $twofaUtility;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->moduleManager = $moduleManager;
        $this->storeManager = $storeManager;
        parent::__construct(
            $context,
            $customerSession,
            $customerAccountManagement,
            $customerHelperData,
            $formKeyValidator,
            $accountRedirect
        );
    }

    /**
     * Execute login action with 2FA logic
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $this->twofaUtility->log_debug("Execute LoginPost");

        if ($this->customerSession->isLoggedIn() || !$this->formKeyValidator->validate($this->getRequest())) {
            $this->twofaUtility->log_debug("If Customer Already logged in Magento");
            return $this->resultRedirectFactory->create((array)\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT)->setPath('home');
        }

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if ($this->isLoginDataValid($login)) {
                return $this->processLogin($login);
            }

            $this->messageManager->addErrorMessage(__('Username and password are required.'));
        }

        return $this->resultRedirectFactory->create((array)\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT)->setPath('home');
    }

    /**
     * Validate login data
     *
     * @param array $login
     * @return bool
     */
    private function isLoginDataValid($login)
    {
        $this->twofaUtility->log_debug("Inside isLoginDataValid");
        return (!empty($login['username']) && !empty($login['password']));
    }

    /**
     * Process login data
     *
     * @param array $login
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function processLogin($login)
    {
        $this->twofaUtility->log_debug("Inside processLogin");
        $resultRedirect = $this->resultRedirectFactory->create((array)\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        try {

            $customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
            $this->handleTwoFactorAuthentication($login['username'], $customer, $resultRedirect);

        } catch (EmailNotConfirmedException $e) {
            $this->handleEmailNotConfirmedException($e, $login['username'], $resultRedirect);
        } catch (AuthenticationException $e) {
            $this->handleAuthenticationException($e, $login['username'], $resultRedirect);
        } catch (\Exception $e) {
            $this->handleGenericException($resultRedirect);
        }

        return $resultRedirect;
    }

    /**
     * Handle two factor authentication logic
     *
     * @param string $username
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleTwoFactorAuthentication($username, $customer, $resultRedirect)
    {
        $this->twofaUtility->log_debug("Inside handleTwoFactorAuthentication");

        $licenseCheckResult = $this->checkCustomerLicense($username,$customer,$resultRedirect);

        if ($licenseCheckResult instanceof \Magento\Framework\Controller\Result\Redirect) {
            return $licenseCheckResult;
        }

        if ($licenseCheckResult) {

            if ($this->shouldInvokeInlineTwoFA()) {
                $userDetails = $this->twofaUtility->getMoTfaUserDetails('miniorange_tfa_users', $username);
                if (is_array($userDetails) && sizeof($userDetails) > 0 && $this->twofaUtility->isTwoFADisabled($userDetails)) {
                    $this->twofaUtility->log_debug("Execute LoginPost: 2FA is disabled for this user, proceeding with default login");
                    return $this->defaultLoginFlow($username, $customer, $resultRedirect, $user_limit=false);
                }

                $this->initiateTwoFactorChallenge($username, $customer, $resultRedirect);
                return $resultRedirect;
            } else{

                return $this->defaultLoginFlow($username, $customer, $resultRedirect,$user_limit=false);

            }
        }
        else{
            return $this->defaultLoginFlow($username, $customer, $resultRedirect,$user_limit=false);
        }
    }

    /**
     * Check if inline 2FA should be invoked
     *
     * @return bool
     */
    private function shouldInvokeInlineTwoFA()
    {
        $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA");
        
        // Check if 2FA for registration toggle is enabled
        $customer_2fa_for_registration = $this->twofaUtility->getStoreConfig(
            TwoFAConstants::CUSTOMER_2FA_FOR_REGISTRATION
        );
        
        if ($customer_2fa_for_registration) {
            $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: 2FA for registration toggle is ON, skipping 2FA at login");
            return false;
        }
        
        // First check basic config
        $active_method = $this->twofaUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
        $active_method_status= ($active_method=='[]' || $active_method==NULL) ? false : true ;
        $invokeInline = $this->twofaUtility->getStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION);
        
        if (!$invokeInline || !$active_method_status) {
            $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Basic config check failed");
            return false;
        }
        
        // Check if there are site-specific rules
        $customerRulesJson = $this->twofaUtility->getStoreConfig(TwoFAConstants::CURRENT_CUSTOMER_RULE);
        $customerRules = $customerRulesJson ? json_decode($customerRulesJson, true) : [];
        
        // If no rules exist, use legacy behavior (check basic config only)
        if (empty($customerRules)) {
            $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: No rules found, using legacy behavior");
            return true;
        }
        
        // Get current website info from the current store (more reliable for multi-site)
        $currentStore = $this->storeManager->getStore();
        $currentWebsiteId = $currentStore->getWebsiteId();
        $currentWebsite = $this->storeManager->getWebsite($currentWebsiteId);
        $currentWebsiteName = $currentWebsite ? $currentWebsite->getName() : '';
        $currentWebsiteCode = $currentWebsite ? $currentWebsite->getCode() : '';
        
        $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Current website ID: " . $currentWebsiteId . ", Name: " . $currentWebsiteName . ", Code: " . $currentWebsiteCode);
        
        // Check if any rule applies to current website
        foreach ($customerRules as $rule) {
            if (!isset($rule['site'])) {
                continue;
            }
            
            $ruleSite = $rule['site'];
            
            // Rule applies if:
            // 1. Site is 'base' (main website) - check if current website is base
            // 2. Site is 'All Sites'
            // 3. Site matches current website name
            // 4. Site matches current website code
            // 5. Site is empty (legacy rule)
            $siteMatches = false;
            
            // Get base website info for comparison
            $baseWebsite = $this->storeManager->getWebsite(1);
            $baseWebsiteName = $baseWebsite ? $baseWebsite->getName() : '';
            $baseWebsiteCode = $baseWebsite ? $baseWebsite->getCode() : '';
            
            if ($ruleSite === 'base' || $ruleSite === '' || empty($ruleSite)) {
                // Check if current website is the base website (usually ID 1)
                if ($baseWebsite && ($baseWebsite->getId() == $currentWebsiteId || $baseWebsite->getCode() == $currentWebsiteCode)) {
                    $siteMatches = true;
                    $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Rule matches base website");
                }
            } elseif ($ruleSite === 'All Sites') {
                $siteMatches = true;
                $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Rule matches All Sites");
            } elseif ($ruleSite === $currentWebsiteName || $ruleSite === $currentWebsiteCode) {
                // Direct match with current website name or code
                $siteMatches = true;
                $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Rule matches current website: " . $ruleSite);
            } elseif ($baseWebsite && ($ruleSite === $baseWebsiteName || $ruleSite === $baseWebsiteCode)) {
                // Rule site matches base website name/code, check if current website is base
                if ($baseWebsite->getId() == $currentWebsiteId || $baseWebsite->getCode() == $currentWebsiteCode) {
                    $siteMatches = true;
                    $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Rule matches base website by name/code: " . $ruleSite);
                }
            }
            
            if ($siteMatches) {
                $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: Found matching rule, 2FA should be invoked");
                return true;
            }
        }
        
        $this->twofaUtility->log_debug("Inside shouldInvokeInlineTwoFA: No matching rule found for current site, 2FA should not be invoked");
        return false;
    }

    /**
     * Initiate two factor challenge
     *
     * @param string $username
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function initiateTwoFactorChallenge($username, $customer, $resultRedirect)
    {

        $this->twofaUtility->log_debug("Execute LoginPost: Inside initiateTwoFactorChallenge : Inline Invoked and found active method");

        $this->twofaUtility->setSessionValue('mousername', $username);
        $this->setCookie('mousername', $username);

         $userDetails = $this->twofaUtility->getMoTfaUserDetails('miniorange_tfa_users', $username);
         if ($userDetails) {
             if ($this->twofaUtility->isTwoFADisabled($userDetails)) {
                 $this->twofaUtility->log_debug("Execute LoginPost: 2FA is disabled for this user (duplicate check in initiateTwoFactorChallenge)");
                 return $this->defaultLoginFlow($username, $customer, $resultRedirect, $user_limit=false);
             }
             $this->handleRegisteredUser($username, $userDetails[0]['active_method'], $resultRedirect);
         } else {
             $this->handleInlineUserRegistration($username, $resultRedirect);
         }
    }

    /**
     * Set a public cookie
     *
     * @param string $name
     * @param string $value
     */
    private function setCookie($name, $value)
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDurationOneYear()
            ->setPath('/')
            ->setHttpOnly(false);
        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }

    /**
     * Handle registered user
     *
     * @param string $username
     * @param string $authType
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleRegisteredUser($username, $authType, $resultRedirect)
    {

        $this->twofaUtility->log_debug("Execute LoginPost:Inside handleRegisteredUser :Customer has already registered in TwoFA method");

        if ($authType !== "GoogleAuthenticator") {
            $miniOrangeUser = new MiniOrangeUser();
            $response = json_decode($miniOrangeUser->challenge($username, $this->twofaUtility, $authType, true));
            if ($response->status === 'SUCCESS') {
                $this->twofaUtility->updateColumnInTable('miniorange_tfa_users', 'transactionId' , $response->txId, 'username', $username);
                // Reset resend count when initial OTP is sent
                $this->twofaUtility->setSessionValue('otp_resend_count', 0);
                $this->twofaUtility->setSessionValue('last_otp_sent_time', time());
                $this->twofaUtility->setSessionValue('last_otp_send_time', null);
                
                // Get user details to extract phone and countrycode if needed
                $row = $this->twofaUtility->getMoTfaUserDetails('miniorange_tfa_users', $username);
                $phone = '';
                $countrycode = '';
                if (is_array($row) && sizeof($row) > 0) {
                    $phone = isset($row[0]['phone']) ? $row[0]['phone'] : '';
                    $countrycode = isset($row[0]['countrycode']) ? $row[0]['countrycode'] : '';
                }
                
                // Map authType to the appropriate step
                $step = '';
                $params = [
                    'mooption' => 'invokeInline',
                    'message' => $response->message,
                    'r_status' => $response->status,
                    'active_method' => $authType,
                    'showdiv' => 'showdiv' 
                ];
                
                if ($authType === 'OOS') {
                    $step = 'OOSMethodValidation';
                    $params['step'] = $step;
                    $params['savestep'] = 'OOS';
                    $params['deleteSet'] = 'deleteSet';
                    if ($phone) {
                        $params['phone'] = $phone;
                    }
                    if ($countrycode) {
                        $params['countrycode'] = $countrycode;
                    }
                } elseif ($authType === 'OOE') {
                    $step = 'OOEMethodValidation';
                    $params['step'] = $step;
                    $params['savestep'] = 'OOE';
                    $params['deleteSet'] = 'deleteSet';
                } elseif ($authType === 'OOSE') {
                    $step = 'OOSEMethodValidation';
                    $params['step'] = $step;
                    $params['savestep'] = 'OOSE';
                    $params['deleteSet'] = 'deleteSet';
                    $params['useremail'] = $username;
                    if ($phone) {
                        $params['phone'] = $phone;
                    }
                    if ($countrycode) {
                        $params['countrycode'] = $countrycode;
                    }
                } else {
                    // Fallback to old UI for unknown methods
                    $params = [
                        'mooption' => 'invokeTFA',
                        'message' => $response->message,
                        'r_status' => $response->status,
                        'active_method' => $authType
                    ];
                }
                
                $resultRedirect->setPath('motwofa/mocustomer/index', $params);
            } else {
                $this->handleTwoFactorChallengeFailure($response->message, $resultRedirect);
            }
        } else {
            // Google Authenticator
            $resultRedirect->setPath('motwofa/mocustomer/index', [
                'mooption' => 'invokeInline',
                'step' => 'GAMethodValidation',
                'savestep' => 'GoogleAuthenticator',
                'addPasscode' => 'true',
                'deleteSet' => 'deleteSet',
                'active_method' => $authType
            ]);
        }
    }

    /**
     * Handle two factor challenge failure
     *
     * @param string $message
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleTwoFactorChallengeFailure($message, $resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost: Inside handleTwoFactorChallengeFailure");

        if (empty($message)) {
            // Handle the case where $message is null or empty
            $this->messageManager->addErrorMessage(__('An unexpected error occurred during 2FA. Please try again later.'));
        } elseif ($message === "The transaction limit has been exceeded.") {
            $this->twofaUtility->log_debug("Transaction limit is exceeded");
            $this->messageManager->addErrorMessage(__('OTP Transaction limit has been exceeded for your 2FA extension. Please contact your administrator to perform 2FA.'));
        } else {
            $this->messageManager->addErrorMessage($message);
        }

        $resultRedirect->setPath('customer/account/login');
    }

    /**
     * Handle inline user registration
     *
     * @param string $username
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleInlineUserRegistration($username, $resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost:Inside handleInlineUserRegistration: Customer going through Inline");

        $this->setActiveMethodRedirection($resultRedirect);
    }

    private function checkCustomerLicense($username, $customer,$resultRedirect){

        if( $this->twofaUtility->isCustomerRegistered()) {
            // First check if customer 2FA is actually configured
            $number_of_customer_method = $this->twofaUtility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
            $active_method = $this->twofaUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
            $active_method_status = ($active_method == '[]' || $active_method == NULL) ? false : true;
            
            // If customer 2FA is not configured, skip 2FA logic and proceed with default login
            if ($number_of_customer_method == NULL || $number_of_customer_method == 0 || !$active_method_status) {
                $this->twofaUtility->log_debug("LoginPost.php: Customer 2FA is not configured, proceeding with default login");
                return $this->defaultLoginFlow($username, $customer, $resultRedirect, $user_limit=false);
            }

            $count = $this->twofaUtility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
            if ($count <= 10) {
                return true;
            }else{
                //User limit Exceed redirecting to default flow
                return $this->defaultLoginFlow($username,$customer,$resultRedirect,$user_limit=true);
            }

        }
        else{
            // Customer not Registered in plugin
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('motwofa/account/index');
            return $resultRedirect;

        }

    }

    /**
     * Set active method redirection
     *
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function setActiveMethodRedirection($resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost:Inside setActiveMethodRedirection: Set active method");
        $number_of_activeMethod=$this->twofaUtility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
        if($number_of_activeMethod==1){
            $customer_active_method=$this->twofaUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
            $customer_active_method = trim($customer_active_method,'[""]');
            $params = array('mopostoption' => 'method', 'miniorangetfa_method' => $customer_active_method,'deleteSet'=>'deleteSet','inline_one_method'=>'1');
            $resultRedirect->setPath('motwofa/mocustomer', $params);
        }elseif($number_of_activeMethod>1){

            $params = array('mooption' => 'invokeInline', 'step' => 'ChooseMFAMethod');
            $resultRedirect->setPath('motwofa/mocustomer/index', $params);

        }

    }

    /**
     * Handle email not confirmed exception
     *
     * @param EmailNotConfirmedException $e
     * @param string $username
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleEmailNotConfirmedException($e, $username, $resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost:Inside handleEmailNotConfirmedException: Email not verified");
        $confirmationUrl = $this->customerUrl->getEmailConfirmationUrl($username);
        $message = __('This account is not confirmed. <a href="%1">Click here</a> to resend confirmation email.', $confirmationUrl);
        $this->messageManager->addErrorMessage($message);
        $this->customerSession->setUsername($username);
        $resultRedirect->setPath('customer/account/login');
    }

    /**
     * Handle authentication exception
     *
     * @param AuthenticationException $e
     * @param string $username
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleAuthenticationException($e, $username, $resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost:Inside handleAuthenticationException: Authentication error");
        $this->messageManager->addErrorMessage(__('Invalid login or password.'));
        $this->customerSession->setUsername($username);
        $resultRedirect->setPath('customer/account/login');
    }

    /**
     * Handle generic exception
     *
     * @param \Magento\Framework\Controller\Result\Redirect $resultRedirect
     */
    private function handleGenericException($resultRedirect)
    {
        $this->twofaUtility->log_debug("Execute LoginPost:Inside handleGenericException: Generic error");
        $this->messageManager->addErrorMessage(__('Invalid login or password.'));
        $resultRedirect->setPath('customer/account/login');
    }

    private function defaultLoginFlow($username,$customer, $resultRedirect,$user_limit)
    {

        if($user_limit){

        $subject='TwoFA user limit has been exceeded';
        $message='Trying to create frontend user using '.$username.' email';
        $isUserLimitEmailSent = $this->twofaUtility->getStoreConfig(TwoFAConstants::USER_LIMIT_EMAIL_SENT);
        $this->twofaUtility->flushCache();
        if($isUserLimitEmailSent == NULL)
        {

            $timeStamp = $this->twofaUtility->getStoreConfig(TwoFAConstants::TIME_STAMP);
            if($timeStamp == null){
                $timeStamp = time();
                $this->twofaUtility->setStoreConfig(TwoFAConstants::TIME_STAMP,$timeStamp);
                $this->twofaUtility->flushCache();
            }
            
            $domain = $this->twofaUtility->getBaseUrl();
            $environmentName = $this->twofaUtility->getEdition();
            $environmentVersion = $this->twofaUtility->getProductVersion();
            $miniorangeAccountEmail= $this->twofaUtility->getCustomerEmail();
            $trackingDate = $this->twofaUtility->getCurrentDate();
            $frontendMethod = '';
            $backendMethod = '';
            $freeInstalledDate = $this->twofaUtility->getCurrentDate();
            
            Curl::submit_to_magento_team($timeStamp,
            '',
            $domain,
            $miniorangeAccountEmail,
            '',
            $environmentName,
            $environmentVersion,
            $freeInstalledDate,
            $backendMethod,
            $frontendMethod,
            '',
            'Yes');

            $this->twofaUtility->setStoreConfig(TwoFAConstants::USER_LIMIT_EMAIL_SENT,1);
        }
       
        $this->twofaUtility->log_debug("LoginPost.php : execute: your user limit has been exceeded ");
        $this->messageManager->addErrorMessage(__('User limit has been exceeded for your 2fa users.Please contact your administrator to perform 2FA'));


        //continue normal flow
        $this->twofaUtility->log_debug("Users limit is exceeded");
        }
        
         // Continue the flow

         $this->customerSession->setCustomerDataAsLoggedIn($customer);
         $this->customerSession->regenerateId();

         // Redirect to account page, not login page
         $resultRedirect->setPath('customer/account');
         return $resultRedirect;

    }

}
