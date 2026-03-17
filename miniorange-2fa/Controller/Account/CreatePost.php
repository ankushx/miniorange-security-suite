<?php

namespace MiniOrange\TwoFA\Controller\Account;

use Magento\Customer\Api\CustomerRepositoryInterface as CustomerRepository;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Helper\Address;
use Magento\Framework\UrlFactory;
use Magento\Customer\Model\Metadata\FormFactory;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Customer\Model\Registration;
use Magento\Framework\Escaper;
use Magento\Customer\Model\CustomerExtractor;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Customer\Controller\AbstractAccount;
use Magento\Customer\Model\Customer;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use MiniOrange\TwoFA\Helper\Curl;

class CreatePost extends \Magento\Customer\Controller\Account\CreatePost
{
    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $accountManagement;

    /**
     * @var \Magento\Customer\Helper\Address
     */
    protected $addressHelper;

    /**
     * @var \Magento\Customer\Model\Metadata\FormFactory
     */
    protected $formFactory;

    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * @var \Magento\Customer\Api\Data\RegionInterfaceFactory
     */
    protected $regionDataFactory;

    /**
     * @var \Magento\Customer\Api\Data\AddressInterfaceFactory
     */
    protected $addressDataFactory;

    /**
     * @var \Magento\Customer\Model\Registration
     */
    protected $registration;

    /**
     * @var \Magento\Customer\Api\Data\CustomerInterfaceFactory
     */
    protected $customerDataFactory;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $customerUrl;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var \Magento\Customer\Model\CustomerExtractor
     */
    protected $customerExtractor;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlModel;

    /**
     * @var \Magento\Framework\Api\DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var AccountRedirect
     */
    private $accountRedirect;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;

    /**
     * @var Validator
     */
    private $formKeyValidator;
    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    protected $TwoFAUtility;
    protected $customerModel;
    /**
     * @param Context $context
     * @param Session $customerSession
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param AccountManagementInterface $accountManagement
     * @param Address $addressHelper
     * @param UrlFactory $urlFactory
     * @param FormFactory $formFactory
     * @param SubscriberFactory $subscriberFactory
     * @param RegionInterfaceFactory $regionDataFactory
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CustomerInterfaceFactory $customerDataFactory
     * @param CustomerUrl $customerUrl
     * @param Registration $registration
     * @param Escaper $escaper
     * @param CustomerExtractor $customerExtractor
     * @param DataObjectHelper $dataObjectHelper
     * @param AccountRedirect $accountRedirect
     * @param Validator $formKeyValidator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */

    private $cookieManager;

    private $url;
    private $moduleManager;
    protected $resultFactory;
    protected $response;
    public $customerSession;
    protected $storeManager;

    public function __construct(
        Context $context,
        Session $customerSession,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        AccountManagementInterface $accountManagement,
        Address $addressHelper,
        UrlFactory $urlFactory,
        FormFactory $formFactory,
        SubscriberFactory $subscriberFactory,
        RegionInterfaceFactory $regionDataFactory,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerUrl $customerUrl,
        Registration $registration,
        Escaper $escaper,
        CustomerExtractor $customerExtractor,
        DataObjectHelper $dataObjectHelper,
        AccountRedirect $accountRedirect,
        ?Validator $formKeyValidator,
        TwoFAUtility $TwoFAUtility,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\UrlInterface $url,
        CustomerRepository $customerRepository,
        Customer $customerModel,
    ) {
        $this->customerSession = $customerSession;
        $this->TwoFAUtility = $TwoFAUtility;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->moduleManager = $moduleManager;
        $this->url = $url;
        $this->resultFactory = $resultFactory;
        $this->customerModel = $customerModel;
        $this->storeManager = $storeManager;
        parent::__construct(
            $context,
            $customerSession,
            $scopeConfig,
            $storeManager,
            $accountManagement,
            $addressHelper,
            $urlFactory,
            $formFactory,
            $subscriberFactory,
            $regionDataFactory,
            $addressDataFactory,
            $customerDataFactory,
            $customerUrl,
            $registration,
            $escaper,
            $customerExtractor,
            $dataObjectHelper,
            $accountRedirect,
            $customerRepository,
            $formKeyValidator ?:
            ObjectManager::getInstance()->get(Validator::class)
        );
    }

    /**
     * Check if 2FA should be invoked for registration
     * @return bool
     */
    private function shouldInvoke2FAForRegistration()
    {
        $this->TwoFAUtility->log_debug("CreatePost: Inside shouldInvoke2FAForRegistration");
        
        // First check basic config
        $active_method = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
        $active_method_status = ($active_method == '[]' || $active_method == null) ? false : true;
        $invokeInline = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION);
        
        if (!$invokeInline || !$active_method_status) {
            $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Basic config check failed");
            return false;
        }
        
        // Check if there are site-specific rules
        $customerRulesJson = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::CURRENT_CUSTOMER_RULE);
        $customerRules = $customerRulesJson ? json_decode($customerRulesJson, true) : [];

        if (empty($customerRules)) {
            $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: No rules found, using legacy behavior");
            return true;
        }
        
        // Get current website info from the current store 
        $currentStore = $this->storeManager->getStore();
        $currentWebsiteId = $currentStore->getWebsiteId();
        $currentWebsite = $this->storeManager->getWebsite($currentWebsiteId);
        $currentWebsiteName = $currentWebsite ? $currentWebsite->getName() : '';
        $currentWebsiteCode = $currentWebsite ? $currentWebsite->getCode() : '';
        
        $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Current website ID: " . $currentWebsiteId . ", Name: " . $currentWebsiteName . ", Code: " . $currentWebsiteCode);
        
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
                if ($baseWebsite && ($baseWebsite->getId() == $currentWebsiteId || $baseWebsite->getCode() == $currentWebsiteCode)) {
                    $siteMatches = true;
                    $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Rule matches base website");
                }
            } elseif ($ruleSite === 'All Sites') {
                $siteMatches = true;
                $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Rule matches All Sites");
            } elseif ($ruleSite === $currentWebsiteName || $ruleSite === $currentWebsiteCode) {
                $siteMatches = true;
                $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Rule matches current website: " . $ruleSite);
            } elseif ($baseWebsite && ($ruleSite === $baseWebsiteName || $ruleSite === $baseWebsiteCode)) {
                if ($baseWebsite->getId() == $currentWebsiteId || $baseWebsite->getCode() == $currentWebsiteCode) {
                    $siteMatches = true;
                    $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Rule matches base website by name/code: " . $ruleSite);
                }
            }
            
            if ($siteMatches) {
                $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: Found matching rule, 2FA should be invoked for registration");
                return true;
            }
        }
        
        $this->TwoFAUtility->log_debug("CreatePost: shouldInvoke2FAForRegistration: No matching rule found for current site, 2FA should not be invoked for registration");
        return false;
    }

    public function execute()
    {
        //After registration of customer ,flow start's from here.
        $params = $this->getRequest()->getParams();
        $current_website_id = $this->storeManager->getStore()->getWebsiteId();

        $resultRedirect = $this->resultRedirectFactory->create();
        //check if 2FA for registration toggle is enabled 
        $customer_2fa_for_registration = $this->TwoFAUtility->getStoreConfig(
            TwoFAConstants::CUSTOMER_2FA_FOR_REGISTRATION
        );
        //Forcefully assign "general" role. If you want to add any code related to customer role during registration add here.
        $customer_role_name = "General";
        
        $shouldInvoke2FA = false;
        $useLoginMethods = false;

        if ($customer_2fa_for_registration) {
            $shouldInvoke2FA = $this->shouldInvoke2FAForRegistration();
            $useLoginMethods = true;
            $this->TwoFAUtility->log_debug("CreatePost: 2FA for registration toggle is ON, using login methods for registration. Should invoke: " . ($shouldInvoke2FA ? 'yes' : 'no'));
        } else {
            $customer_registration_twofa = $this->TwoFAUtility->getStoreConfig(
                TwoFAConstants::REGISTER_CHECKBOX
            );
            $active_method = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE);
            $active_method_status = $active_method == "[]" || $active_method == null ? false : true;
            $shouldInvoke2FA = $customer_registration_twofa && $active_method_status;
            $useLoginMethods = false;
            $this->TwoFAUtility->log_debug("CreatePost: 2FA for registration toggle is OFF, using premium registration methods");
        }

        // Invoke 2FA at registration based on the toggle
        if ($shouldInvoke2FA) {
            $number_of_customer_method = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
            if ($number_of_customer_method == NULL || $number_of_customer_method == 0) {
                $this->TwoFAUtility->log_debug("CreatePost.php: Customer 2FA is not configured, proceeding with default registration");
                parent::execute();
                $resultRedirect->setPath("customer/account");
                return $resultRedirect;
            }

            $count = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
            
            if ($count >= 10) {

                $subject = 'TwoFA user limit has been exceeded';
                $message = 'Trying to create frontend user using ' . $params["email"] . ' email';
                $isUserLimitEmailSent = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::USER_LIMIT_EMAIL_SENT);
                $this->TwoFAUtility->flushCache();
                if ($isUserLimitEmailSent == null) {

                    $timeStamp = $this->TwoFAUtility->getStoreConfig(TwoFAConstants::TIME_STAMP);
                    if($timeStamp == null){
                        $timeStamp = time();
                        $this->TwoFAUtility->setStoreConfig(TwoFAConstants::TIME_STAMP,$timeStamp);
                        $this->TwoFAUtility->flushCache();
                    }

                    $domain = $this->TwoFAUtility->getBaseUrl();
                    $environmentName = $this->TwoFAUtility->getEdition();
                    $environmentVersion = $this->TwoFAUtility->getProductVersion();
                    $miniorangeAccountEmail= $this->TwoFAUtility->getCustomerEmail();
                    $frontendMethod = '';
                    $backendMethod = '';
                    $freeInstalledDate = $this->TwoFAUtility->getCurrentDate();

                    
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

                    $this->TwoFAUtility->setStoreConfig(TwoFAConstants::USER_LIMIT_EMAIL_SENT, 1);
                }
                $this->TwoFAUtility->log_debug("CreatePost.php : execute: your user limit has been exceeded ");
                $this->messageManager->addError(__('Your user limit has been exceeded. Please contact your administrator to perform 2FA.'));
                $this->TwoFAUtility->log_debug(
                    "Execute CreatePost: Default Account Creation flow"
                );
                parent::execute();
                $resultRedirect->setPath("customer/account");
                return $resultRedirect;

            }

            $this->customerModel->setWebsiteId($current_website_id);
            $customer = $this->customerModel->loadByEmail($params["email"]);

            //check if customer id is null or not
            if (!is_null($customer->getId())) {
                $resultRedirect = $this->resultRedirectFactory->create();
                //parent::execute() function will continue event without loading further code
                parent::execute();
                $resultRedirect->setPath("customer/account");
                return $resultRedirect;
            }
            // Initiate MFA flow

            $current_username = $params["email"];
            $register_page_parameter = json_encode($params, true);
            $this->TwoFAUtility->setSessionValue(
                "mo_customer_page_parameters",
                $register_page_parameter
            );
            $this->TwoFAUtility->setSessionValue(
                "mousername",
                $params["email"]
            );
            $this->TwoFAUtility->setSessionValue(
                "mocreate_customer_register",
                1
            );
            // Setting up in the cookie for printing

            $publicCookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata();
            $publicCookieMetadata->setDurationOneYear();
            $publicCookieMetadata->setPath("/");
            $publicCookieMetadata->setHttpOnly(false);
            $this->cookieManager->setPublicCookie(
                "mousername",
                $current_username,
                $publicCookieMetadata
            );

            $redirectionUrl = "";

            $this->TwoFAUtility->log_debug(
                "Execute CreatePost: Customer going through Inline in createpost"
            );

            if ($useLoginMethods) {
                $number_of_activeMethod = $this->TwoFAUtility->getStoreConfig(
                    TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD
                );
                $method_config_key = TwoFAConstants::ACTIVE_METHOD;
            } else {
                $number_of_activeMethod = $this->TwoFAUtility->getStoreConfig(
                    TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD_AT_REGISTRATION
                );
                $method_config_key = TwoFAConstants::REGISTER_OTP_TYPE;
            }
            
            //check for number of active method. If only one method is active then redirect to that method without showing method dropdown.
            if ($number_of_activeMethod == 1) {
                $customer_active_method = $this->TwoFAUtility->getStoreConfig($method_config_key);
                
                $customer_active_method = trim($customer_active_method, '[""]');
                $params = [
                    "mopostoption" => "method",
                    "miniorangetfa_method" => $customer_active_method,
                    "inline_one_method" => "1",
                ];
                $resultRedirect->setPath("motwofa/mocustomer", $params);
            } elseif ($number_of_activeMethod > 1) {
                //If more than one methods are present then show dropdown to choose method
                $params = [
                    "mooption" => "invokeInline",
                    "step" => "ChooseMFAMethod",
                ];
                $resultRedirect->setPath("motwofa/mocustomer/index", $params);
            }

            return $resultRedirect;
        } else {
            //customer creation by default method
            $this->TwoFAUtility->log_debug(
                "Execute CreatePost: Default Account Creation flow"
            );
            parent::execute();
            $resultRedirect->setPath("customer/account");
            return $resultRedirect;
        }
    }
}