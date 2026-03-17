<?php

namespace MiniOrange\TwoFA\Helper;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Url;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\ResourceConnection;
use Magento\User\Model\UserFactory;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\Data;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;
use Magento\Framework\Stdlib\DateTime\dateTime;
use MiniOrange\TwoFA\Model\Resolver\B2BDependencyResolver;


/**
 * This class contains some common Utility functions
 * which can be called from anywhere in the module. This is
 * mostly used in the action classes to get any utility
 * function or data from the database.
 */
class TwoFAUtility extends Data
{
    protected $adminSession;
    protected $customerSession;
    protected $authSession;
    protected $cacheTypeList;
    protected $resource;
    protected $cacheFrontendPool;
    protected $fileSystem;
    protected $logger;
    protected $_logger;
    protected $reinitableConfig;
    protected $coreSession;
    private $userCollectionFactory;
    protected $productMetadata;
    protected $dateTime;
    protected $moduleReader;
    
    // Commerce/B2B-specific properties
    private $customerRepository;
    private $b2bResolver;



    public function __construct(
        ScopeConfigInterface $scopeConfig,
        UserFactory $adminFactory,
        CustomerFactory $customerFactory,
        UrlInterface $urlInterface,
        WriterInterface $configWriter,
        \Magento\Framework\App\ResourceConnection $resource,
        Repository $assetRepo,
        \Magento\Backend\Helper\Data $helperBackend,
        Url $frontendUrl,
        \Magento\Backend\Model\Session $adminSession,
        Session $customerSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\App\Config\ReinitableConfigInterface $reinitableConfig,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        UserCollectionFactory $userCollectionFactory,
        \Psr\Log\LoggerInterface $logger,
        File $fileSystem,
        \MiniOrange\TwoFA\Logger\Logger $logger_customlog,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        dateTime $dateTime,
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        B2BDependencyResolver $b2bResolver
    ) {
                        $this->adminSession = $adminSession;
                        $this->customerSession = $customerSession;
                        $this->authSession = $authSession;
                        $this->cacheTypeList = $cacheTypeList;
                        $this->resource = $resource;
                        $this->cacheFrontendPool = $cacheFrontendPool;
                        $this->fileSystem = $fileSystem;
                        $this->logger = $logger;
                        $this->_logger = $logger_customlog;
                        $this->reinitableConfig = $reinitableConfig;
                        $this->coreSession = $coreSession;
                        $this->userCollectionFactory = $userCollectionFactory;
                        $this->productMetadata = $productMetadata;
                        $this->dateTime=$dateTime;
                        $this->moduleReader = $moduleReader;
                        $this->customerRepository = $customerRepository;
                        $this->b2bResolver = $b2bResolver;

                        parent::__construct(
                            $scopeConfig,
                            $adminFactory,
                            $customerFactory,
                            $urlInterface,
                            $configWriter,
                            $assetRepo,
                            $helperBackend,
                            $frontendUrl
                        );
    }

    /**
     * This function returns phone number as a obfuscated
     * string which can be used to show as a message to the user.
     *
     * @param $phone references the phone number.
     * @return string
     */
    public function getHiddenPhone($phone)
    {
        $hidden_phone = 'xxxxxxx' . substr($phone, strlen($phone) - 3);
        return $hidden_phone;
    }

    /**
     * This function checks if a value is set or
     * empty. Returns true if value is empty
     *
     * @return True or False
     * @param $value //references the variable passed.
     */
    public function isBlank($value)
    {
        if (! isset($value) || empty($value)) {
            return true;
        }
        return false;
    }

    public function getCompleteSession() {
        $this->coreSession->start();
        $sessionValue = $this->coreSession->getMyTestValue();
        return $sessionValue !== null ? $sessionValue : array();
    }

    public function getSessionValue( $key ){
        $sessionValueArray = $this->getCompleteSession();
        return isset( $sessionValueArray[ $key ] ) ? $sessionValueArray[ $key ] : null ;
    }

    public function setSessionValue( $key, $value ){
        $sessionValueArray = $this->getCompleteSession();
        $sessionValueArray[ $key ] = $value;
        $this->coreSession->setMyTestValue( $sessionValueArray );
    }



   /** check if customer registered in magento or not
   *
   */
  public function isCustomerRegistered(){

    $details = $this->getCustomerDetails();
    return ! isset( $details['email'] ) && ( $details['email'] === NULL || empty($details['email']) ) ? false : true;
 }

  /** get registered customer details from DB
    *
    */
    public function get_admin_role_name()
    {   $collection = $this->userCollectionFactory->create();
       $userid= $this->getSessionValue('admin_user_id');
       if($userid==NULL && $this->authSession->isLoggedIn()) {
        $adminUser = $this->authSession->getUser();
        $userid = $adminUser->getId();
       }
        $collection->addFieldToFilter('main_table.user_id',  $userid);
        $userData = $collection->getFirstItem();
        $user_all_information= $userData->getData();
       $admin_user_role= $user_all_information['role_name'];
        return   $admin_user_role;
    }

   public function getCustomerDetails(){

   $email = $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
   $customer_key= $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
   $api_key = $this->getStoreConfig(TwoFAConstants::API_KEY);
   $customer_token = $this->getStoreConfig(TwoFAConstants::TOKEN);

   $details = array (
   'email'=> $email,
   'customer_Key'=> $customer_key,
   'api_Key'=> $api_key,
   'token'=> $customer_token
    );

    return $details;
   }



    /**
     * This function checks if cURL has been installed
     * or enabled on the site.
     *
     * @return True or False
     */
    public function isCurlInstalled()
    {
        if (in_array('curl', get_loaded_extensions())) {
            return 1;
        } else {
            return 0;
        }
    }


    /**
     * This function checks if the phone number is in the correct format or not.
     *
     * @param $phone refers to the phone number entered
     * @return bool
     */
    public function validatePhoneNumber($phone)
    {
        if (!preg_match(MoIDPConstants::PATTERN_PHONE, $phone, $matches)) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * This function is used to obfuscate and return
     * the email in question.
     *
     * @param $email //refers to the email id to be obfuscated
     * @return string obfuscated email id.
     */
    public function getHiddenEmail($email)
    {
        if (!isset($email) || trim($email)==='') {
            return "";
        }

        $emailsize = strlen($email);
        $partialemail = substr($email, 0, 1);
        $temp = strrpos($email, "@");
        $endemail = substr($email, $temp-1, $emailsize);
        for ($i=1; $i<$temp; $i++) {
            $partialemail = $partialemail . 'x';
        }

        $hiddenemail = $partialemail . $endemail;

        return $hiddenemail;
    }
/***
 * @return \Magento\Backend\Model\Session
 */
    public function getAdminSession()
    {
        return $this->adminSession;
    }

    /**
     * set Admin Session Data
     *
     * @param $key
     * @param $value
     * @return
     */
    public function setAdminSessionData($key, $value)
    {
        return $this->adminSession->setData($key, $value);
    }


    public function getImageUrl($image)
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_IMAGES.$image);
    }

   /** 2fa methods for admin
   */
   public function tfaMethodArray(){
    return array(
        'OOS'=>array(
            "name"=>"OTP Over SMS",
            "description" => "Enter the One Time Passcode sent to your phone to login."
        ),
        'OOE'=>array(
            "name"=>"OTP Over Email",
            "description" => "Enter the One Time Passcode sent to your email to login."
        ),
        'OOSE'=>array(
            "name"=>"OTP Over SMS and Email",
            "description" => "Enter the One Time Passcode sent to your phone and email to login."
        ),
       'GoogleAuthenticator'=>array(
             "name"=>"Google Authenticator",
              "description" => "Enter the soft token from the account in your Google Authenticator App to login."
         ),
         'MicrosoftAuthenticator'=>array(
            "name"=>"Microsoft Authenticator",
             "description" => "You have to scan the QR code from Microsoft Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'OktaVerify'=>array(
            "name"=>"Okta Verify",
             "description" => "You have to scan the QR code from Okta Verify App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'DuoAuthenticator'=>array(
            "name"=>"Duo Authenticator",
             "description" => "You have to scan the QR code from Duo Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'AuthyAuthenticator'=>array(
            "name"=>"Authy Authenticator",
             "description" => "You have to scan the QR code from Authy Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'LastPassAuthenticator'=>array(
            "name"=>"LastPass Authenticator",
             "description" => "You have to scan the QR code from LastPass Authenticator App and enter code generated by app to login. Supported in Smartphones only."
        ),
        'QRCodeAuthenticator'=>array(
            "name"=>"QR Code Authentication",
             "description" => "You have to scan the QR Code from your phone using miniOrange Authenticator App to login. Supported in Smartphones only."
        ),
         'KBA'=>array(
            "name"=>"Security Questions (KBA)",
             "description" => "You have to answers some knowledge based security questions which are only known to you to authenticate yourself."
        ),
        'OOP'=>array(
            "name"=>"OTP Over Phone",
             "description" => "You will receive a one time passcode via phone call. You have to enter the otp on your screen to login. Supported in Smartphones, Feature Phones."
        ),
        'YubikeyHardwareToken'=>array(
            "name"=>"Yubikey Hardware Token",
             "description" => "You can press the button on your yubikey Hardware token which generate a random key. You can use that key to authenticate yourself."
        ),
        'PushNotificationsr'=>array(
            "name"=>"Push Notifications",
             "description" => "You will receive a push notification on your phone. You have to ACCEPT or DENY it to login. Supported in Smartphones only."
        ),
        'SoftToken'=>array(
            "name"=>"Soft Token",
             "description" => "You have to enter passcode generated by miniOrange Authenticator App to login. Supported in Smartphones only."
        ),
        'EmailVerification'=>array(
            "name"=>"Email Verification",
             "description" => "You will receive an email with link. You have to click the ACCEPT or DENY link to verify your email. Supported in Desktops, Laptops, Smartphones."
        ),
    );
    }

//get info if first user
	public static function isFirstUser($id){
		$details = self::getCustomerDetails();

		return $details['jid']==$id;
	}

    public function AuthenticatorUrl(){
        $this->log_debug("Inside authenticator url");
        if($this->getSessionValue(TwoFAConstants:: ADMIN_IS_INLINE)){
            $username = $this->getSessionValue(TwoFAConstants:: ADMIN_USERNAME);
        }else{
            $username = $this->getCurrentAdminUser()->getUsername();
        }



       //if admin is not created then create new user
       $row = $this->getMoTfaUserDetails('miniorange_tfa_users',$username);

       $secret = $this->getAuthenticatorSecret( $username );
       $secret_already_set=$this->getSessionValue(TwoFAConstants::PRE_SECRET);
       if($this->getSessionValue(TwoFAConstants:: ADMIN_IS_INLINE)){
        $secret_already_set = $this->getSessionValue(TwoFAConstants:: ADMIN_SECRET);
    }
       if( (!is_array( $row ) || sizeof( $row ) <= 0)) {
        if($secret_already_set==NULL){
            $secret =$this->generateRandomString();
            $this->setSessionValue(TwoFAConstants::PRE_SECRET,$secret);

        }else{
            $secret=$secret_already_set;
        }
       }
        $issuer = $this->AuthenticatorIssuer();
        $url = "otpauth://totp/";
	    $url .= $username."?secret=".$secret."&issuer=".$issuer;
	    return $url;
    }

    public function AuthenticatorCustomerUrl(){
        $this->log_debug("inside authenticator customer url");
        $email = $_COOKIE['mousername'];
        if( is_null( $email ) ) {
            return false;
        } else {
            $secret = false;
            
            // First, check if user exists in database and has a secret (for returning users)
            $row = $this->getMoTfaUserDetails('miniorange_tfa_users', $email);
            if( is_array( $row ) && sizeof( $row ) > 0 ) {
                $db_secret = isset( $row[0]['secret'] ) ? $row[0]['secret'] : false;
                if($db_secret !== false && !empty(trim($db_secret))){
                    $this->log_debug("AuthenticatorCustomerUrl: Using secret from database for returning user");
                    $secret = $db_secret;
                }
            }
            
            // If no database secret found, check if we're in inline registration mode
            if($secret === false || empty($secret)){
                $customer_inline = $this->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
                if($customer_inline){
                    $this->log_debug("AuthenticatorCustomerUrl: Inline registration mode, using session secret");
                    $secret_already_set = $this->getSessionValue('customer_inline_secret');
                    if($secret_already_set == NULL){
                        $secret = $this->generateRandomString();
                        $this->setSessionValue('customer_inline_secret', $secret);
                    } else {
                        $secret = $secret_already_set;
                    }
                } else {
                    // Not in inline mode and no database secret - this shouldn't happen for returning users
                    $this->log_debug("AuthenticatorCustomerUrl: WARNING - No secret found in database and not in inline mode");
                    $secret = $this->generateRandomString();
                    $this->setSessionValue('customer_inline_secret', $secret);
                }
            }

            $issuer = $this->AuthenticatorIssuer();
            $url = "otpauth://totp/";
            $url .= $email."?secret=".$secret."&issuer=".$issuer;
            return $url;
        }
    }

    public function AuthenticatorIssuer(){
        return TwoFAConstants::TwoFA_AUTHENTICATOR_ISSUER;
    }

    public function getAuthenticatorSecret( $current_username ){
        $this->log_debug("Inside getAuthenticatorSecret. generating secret for username: " . $current_username);
        $row = $this->getMoTfaUserDetails('miniorange_tfa_users',$current_username);

        if( is_array( $row ) && sizeof( $row ) > 0 ) {
            $secret = isset( $row[0]['secret'] ) ? $row[0]['secret'] : false;
            if($secret !== false && !empty(trim($secret))){
                $this->log_debug("Inside getAuthenticatorSecret: Secret found in database");
                return $secret;
            } else {
                $this->log_debug("Inside getAuthenticatorSecret: Secret is empty or null in database");
                return false;
            }
        } else {
            $this->log_debug("Inside getAuthenticatorSecret: No user found in database");
            return false;
        }
    }

    function generateRandomString($length = 16) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

public function getCustomerKeys($isMiniorange=false){
    $keys=array();
    if($isMiniorange){

        $keys['customer_key']= "16555";
        $keys['apiKey']      = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";
    }
    else{
        $details=self::getCustomerDetails();
        $keys['customer_key']= $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
        $keys['apiKey']  = $api_key = $this->getStoreConfig(TwoFAConstants::API_KEY);
    }
    return $keys;
}


  static function getTransactionName(){
		return 'Magento 2 Factor Authentication Plugin';
	 }

    static function getApiUrls(){
        $hostName = TwoFAConstants::HOSTNAME;
        return array(
            'challange'=>$hostName.'/moas/api/auth/challenge',
            'update'=>$hostName.'/moas/api/admin/users/update',
            'validate'=>$hostName.'/moas/api/auth/validate',
            'googleAuthService'=>$hostName.'/moas/api/auth/google-auth-secret',
            'googlevalidate'=>$hostName.'/moas/api/auth/validate-google-auth-secret',
            'createUser'=>$hostName.'/moas/api/admin/users/create',
            'kbaRegister'=>$hostName.'/moas/api/auth/register',
            'getUserInfo'=>$hostName.'/moas/api/admin/users/get',
             'feedback'   => $hostName.'/moas/api/notify/send'
        );
    }


    /**
     * get Admin Session data based of on the key
     *
     * @param $key
     * @param $remove
     * @return mixed
     */
    public function getAdminSessionData($key, $remove = false)
    {
        return $this->adminSession->getData($key, $remove);
    }



    /**
     * set customer Session Data
     *
     * @param $key
     * @param $value
     * @return
     */
    public function setSessionData($key, $value)
    {
        return $this->customerSession->setData($key, $value);
    }


    /**
     * Get customer Session data based off on the key
     *
     * @param $key
     * @param $remove
     */
    public function getSessionData($key, $remove = false)
    {
        return $this->customerSession->getData($key, $remove);
    }


    /**
     * Set Session data for logged in user based on if he/she
     * is in the backend of frontend. Call this function only if
     * you are not sure where the user is logged in at.
     *
     * @param $key
     * @param $value
     */
    public function setSessionValueForCurrentUser($key, $value)
    {
        if ($this->customerSession->isLoggedIn()) {
            $this->setSessionValue($key, $value);
        } elseif ($this->authSession->isLoggedIn()) {
            $this->setAdminSessionData($key, $value);
        }
    }


    /**
     * Check if the admin has configured the plugin with
     * the Identity Provier. Returns true or false
     */
    public function isTwoFAConfigured()
    {
        $loginUrl = $this->getStoreConfig(TwoFAConstants::AUTHORIZE_URL);
        return $this->isBlank($loginUrl) ? false : true;
    }


    /**
     * This function is used to check if customer has completed
     * the registration process. Returns TRUE or FALSE. Checks
     * for the email and customerkey in the database are set
     * or not.
     */
    public function micr()
    {
              $email = $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
        $key = $this->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
        return !$this->isBlank($email) && !$this->isBlank($key) ? true : false;
    }


    /**
     * Check if there's an active session of the user
     * for the frontend or the backend. Returns TRUE
     * or FALSE
     */
    public function isUserLoggedIn()
    {
        return $this->customerSession->isLoggedIn()
                || $this->authSession->isLoggedIn();
    }

    /**
     * Get the Current Admin User who is logged in
     */
    public function getCurrentAdminUser()
    {
        return $this->authSession->getUser();
    }


    /**
     * Get the Current Admin User who is logged in
     */
    public function getCurrentUser()
    {
        return $this->customerSession->getCustomer();
    }


    /**
     * Get the admin login url
     */
    public function getAdminLoginUrl()
    {
        return $this->getAdminUrl('adminhtml/auth/login');
    }

    /**
     * Get the admin page url
     */
    public function getAdminPageUrl()
    {
            return $this->getAdminBaseUrl();
    }

    /**
     * Get the customer login url
     */
    public function getCustomerLoginUrl()
    {
        return $this->getUrl('customer/account/login');
    }

    /**
     * Get is Test Configuration clicked
     */
    public function getIsTestConfigurationClicked()
    {
        return $this->getStoreConfig(TwoFAConstants::IS_TEST);
    }


    /**
     * Flush Magento Cache. This has been added to make
     * sure the admin/user has a smooth experience and
     * doesn't have to flush his cache over and over again
     * to see his changes.
     */
    public function flushCache($from = "")
    {

        $types = ['db_ddl']; // we just need to clear the database cache

        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }


    /**
     * Get data in the file specified by the path
     */
    public function getFileContents($file)
    {
        return $this->fileSystem->fileGetContents($file);
    }


    /**
     * Put data in the file specified by the path
     */
    public function putFileContents($file, $data)
    {
        $this->fileSystem->filePutContents($file, $data);
    }


    /**
     * Get the Current User's logout url
     */
    public function getLogoutUrl()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->getUrl('customer/account/logout');
        }
        if ($this->authSession->isLoggedIn()) {
            return $this->getAdminUrl('adminhtml/auth/logout');
        }
        return '/';
    }


    /**
     * Get/Create Callback URL of the site
     */
    public function getCallBackUrl()
    {
        return $this->getBaseUrl() . TwoFAConstants::CALLBACK_URL;
    }

    public function removeSignInSettFings()
    {
            $this->setStoreConfig(TwoFAConstants::SHOW_CUSTOMER_LINK, 0);
            $this->setStoreConfig(TwoFAConstants::SHOW_ADMIN_LINK, 0);
    }
    public function reinitConfig(){

            $this->reinitableConfig->reinit();
    }

        /**
     * This function is used to check if customer has completed
     * the registration process. Returns TRUE or FALSE. Checks
     * for the email and customerkey in the database are set
     * or not. Then checks if license key has been verified.
     */
	public function mclv()
	{
        return true;
		// $token = $this->getStoreConfig(TwoFAConstants::TOKEN);
		// $isVerified = AESEncryption::decrypt_data($this->getStoreConfig(TwoFAConstants::SAMLSP_CKL),$token);
		// $licenseKey = $this->getStoreConfig(TwoFAConstants::SAMLSP_LK);
		// return $isVerified == "true" ? TRUE : FALSE;
	}


    /**
     *Common Log Method .. Accessible in all classes through
     **/
    public function log_debug($msg = "", $obj = null)
    {
        if (is_object($msg)) {
            $this->customlog("MO TwoFA: " . print_r($obj, true));
        } else {
            $this->customlog("MO TwoFA: " . $msg);
        }

        if ($obj != null) {
            $this->customlog("MO TwoFA : " . var_export($obj, true));
        }
    }

    /**
     * Print custom log entries in var/log/mo_twofa.log file.
     *
     * @param string $txt Log message
     * @return void
     */
    public function customlog($txt)
    {
        $this->isLogEnable() ? $this->_logger->debug($txt) : null;
    }

    /**
     * Check if debug logging is enabled.
     *
     * @return bool True if debug logging is enabled, false otherwise
     */
    public function isLogEnable()
    {
        return $this->getStoreConfig(TwoFAConstants::ENABLE_DEBUG_LOG);
    }

    /**
     * Check if custom log file exists.
     *
     * @return int 1 if log file exists, 0 otherwise
     */
    public function isCustomLogExist()
    {
        if ($this->fileSystem->isExists("../var/log/mo_twofa.log")) {
            return 1;
        } elseif ($this->fileSystem->isExists("var/log/mo_twofa.log")) {
            return 1;
        }
        return 0;
    }

    /**
     * Delete custom log file.
     *
     * @return void
     */
    public function deleteCustomLogFile()
    {
        if ($this->fileSystem->isExists("../var/log/mo_twofa.log")) {
            $this->fileSystem->deleteFile("../var/log/mo_twofa.log");
        } elseif ($this->fileSystem->isExists("var/log/mo_twofa.log")) {
            $this->fileSystem->deleteFile("var/log/mo_twofa.log");
        }
    }

    /**
   ****DATABASE Querying Methods
     * @param $table
    * @param $data
    */

    //Insert a row in any table
    public function insertRowInTable($table,$data){
    $this->log_debug("insert row ");
       $this->resource->getConnection()->insertMultiple($table, $data);
    }

    //Update a column in any table
    public function updateColumnInTable($table, $colName, $colValue, $idKey, $idValue, $website_id = null){
       $this->log_debug("updateColumnInTable");
       $whereConditions = [$idKey." = ?" => $idValue];
       
       // If website_id is provided, add it to where conditions
       if ($website_id !== null) {
           try {
               $tableInfo = $this->resource->getConnection()->describeTable($table);
               if (isset($tableInfo['website_id'])) {
                   $whereConditions['website_id = ?'] = $website_id;
               }
           } catch (\Exception $e) {
               $this->log_debug("website_id column doesn't exist: " . $e->getMessage());
           }
       }
       
       $this->resource->getConnection()->update(
           $table,  [ $colName => $colValue],
           $whereConditions
       );
}

    //fetch user details
    public function getMoTfaUserDetails($table,$username=false){
       // $this->log_debug("getMOTfaUserDetails");
        $query = $this->resource->getConnection()->select()
            ->from($table,['username','active_method','configured_methods','email','phone','transactionId','secret','id','countrycode','disable_motfa'])->where(
            "username='".$username."'"
            );
        $fetchData = $this->resource->getConnection()->fetchAll($query);
        return $fetchData;
    }

    //fetch user details with website_id support
    public function getAllMoTfaUserDetails($table, $username = false, $website_id = false)
    {
        return $this->getMoTfaUserDetails($table, $username);
    }



   //Update a set of values of a row in any table
   public function updateRowInTable($table, $valArray, $idKey, $idValue){
     $this->log_debug("updateRowInTable");
     $this->resource->getConnection()->update(
     $table, $valArray , [$idKey." = ?" => $idValue]
 );
}

public function deleteRowInTable($table, $idKey, $idValue){
    $this->log_debug("deleteRowIntable");
    $conn = $this->resource->getConnection();
   $sql = "DELETE FROM ".$table." WHERE ".$idKey."=".$idValue;

 //enter log here to know about deletion of row
    $conn->exec($sql);
//enter log here
}
    /**

     * Get value of any column from a table.

     * @param $table

     * @param $col

     * @param $idKey

     * @param $idValue

     * @return

     */

    public function getValueFromTableSQL($table, $col, $idKey, $idValue)

    {

        $connection = $this->resource->getConnection();

        //Select Data from table

        $sqlQuery = "SELECT ".$col. " FROM ".$table." WHERE ".$idKey. " = " .$idValue;

        $this->log_debug("SQL: ".$sqlQuery);

        $result = $connection->fetchOne($sqlQuery);

        $this->log_debug("result sql: ".$result);

        return $result;

    }

    public function verifyGauthCode( $code, $current_username, $discrepancy = 3, $currentTimeSlice = null ) {
        $this->log_debug("TwoFAUtlity: verifyGauthCode: execute for username: " . $current_username);

        // First, try to get secret from database (for returning users)
        // This should be the primary source for users who have already registered
        $secret = $this->getAuthenticatorSecret($current_username);
        $this->log_debug("TwoFAUtlity: verifyGauthCode: Secret from database: " . ($secret !== false && !empty($secret) ? "Found (length: " . strlen($secret) . ")" : "Not found"));
        
        // If database doesn't have secret, try PRE_SECRET from session
        if($secret === false || empty($secret)){
            $this->log_debug("TwoFAUtlity: verifyGauthCode: Secret not in database, trying PRE_SECRET from session");
            $secret = $this->getSessionValue(TwoFAConstants::PRE_SECRET);
        }
        
        // Check if we're in inline registration mode (for first-time setup)
        // Only use session secret if we're actually in inline mode AND database doesn't have secret
        $customer_inline = $this->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
        if($customer_inline && ($secret === false || empty($secret))){
            $this->log_debug("TwoFAUtlity: verifyGauthCode: Customer inline mode detected, using session secret");
            $secret = $this->getSessionValue('customer_inline_secret');
            $this->setSessionValue(TwoFAConstants::CUSTOMER_SECRET, $secret);
        }
        
        // Check if admin inline mode
        $admin_inline = $this->getSessionValue(TwoFAConstants::ADMIN_IS_INLINE);
        if($admin_inline && ($secret === false || empty($secret))){
            $this->log_debug("TwoFAUtlity: verifyGauthCode: Admin inline mode detected, using session secret");
            $secret = $this->getSessionValue(TwoFAConstants::ADMIN_SECRET);
        }
        
        // Validate that we have a secret
        if($secret === false || empty($secret)){
            $this->log_debug("TwoFAUtlity: verifyGauthCode: ERROR - Secret is empty or false for username: " . $current_username);
            $response = array("status" => 'FALSE');
            return json_encode($response);
        }
        
        $this->log_debug("TwoFAUtlity: verifyGauthCode: Secret found, length: " . strlen($secret));
        
		$response = array("status"=>'FALSE');
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }

        if (strlen($code) != 6) {
            $this->log_debug("TwoFAUtlity: verifyGauthCode: Invalid OTP code length: " . strlen($code));
            return json_encode($response);
        }
        
        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($this->timingSafeEquals($calculatedCode, $code)) {
                $this->log_debug("TwoFAUtlity: verifyGauthCode: OTP validation SUCCESS");
                $response['status']='SUCCESS';
                return json_encode($response);
            }
        }
        
        $this->log_debug("TwoFAUtlity: verifyGauthCode: OTP validation FAILED - code does not match");
        return json_encode($response);
    }

    function timingSafeEquals($safeString, $userString)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($safeString, $userString);
        }
        $safeLen = strlen($safeString);
        $userLen = strlen($userString);

        if ($userLen != $safeLen) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < $userLen; ++$i) {
            $result |= (ord($safeString[$i]) ^ ord($userString[$i]));
        }

        // They are only identical strings if $result is exactly 0...
        return $result === 0;
    }

    function getCode($secret, $timeSlice = null)
    {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretkey = $this->_base32Decode($secret);
        // Pack time into binary string
        $time = chr(0).chr(0).chr(0).chr(0).	pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = hash_hmac('SHA1', $time, $secretkey, true);

        // Use last nipple of result as index/offset
        $offset = ord(substr($hm, -1)) & 0x0F;

        // grab 4 bytes of the result
        $hashpart = substr($hm, $offset, 4);
        // Unpak binary value
        $value = unpack('N', $hashpart);
        $value = $value[1];
        // Only 32 bits
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    function _base32Decode($secret)
    {
        if (empty($secret)) {
            return '';
        }
        $base32chars = $this->_getBase32LookupTable();
        $base32charsFlipped = array_flip($base32chars);

        $paddingCharCount = substr_count($secret, $base32chars[32]);
        $allowedValues = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }


        for ($i = 0; $i < 4; ++$i) {
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($secret, -($allowedValues[$i])) != str_repeat($base32chars[32], $allowedValues[$i])) {
                return false;
            }
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], $base32chars)) {
                return false;
            }
            for ($j = 0; $j < 8; ++$j) {

                 $x .= str_pad(base_convert($base32charsFlipped[$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); ++$z) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';

			}
        }

        return $binaryString;
    }

    function _getBase32LookupTable()
    {
        return array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
            'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
            '=',  // padding char
        );
    }

    public function getCustomerPhoneFromEmail($email=false){

        $getID_table='customer_entity';
         $query1 = $this->resource->getConnection()->select()
             ->from($getID_table,['entity_id'])->where(
             "email='".$email."'"
             );
         $fetchIDData = $this->resource->getConnection()->fetchAll($query1);
         if(($fetchIDData)==array()){
           return false;
         }
$entity_id=$fetchIDData[0]['entity_id'];

        $getPhone_table='customer_address_entity';
        $query2 = $this->resource->getConnection()->select()
        ->from($getPhone_table,['telephone'])->where(
        "entity_id='".$entity_id."'"
        );
    $fetchPhoneData = $this->resource->getConnection()->fetchAll($query2);
    if(($fetchPhoneData)==array()){
return false;

    }else{
        $phone_no=$fetchPhoneData[0]['telephone'];
        return $phone_no;
    }

     }

     public function getCustomerCountryFromEmail($email=false){

        $getID_table='customer_entity';
         $query1 = $this->resource->getConnection()->select()
             ->from($getID_table,['entity_id'])->where(
             "email='".$email."'"
             );
         $fetchIDData = $this->resource->getConnection()->fetchAll($query1);
         if(($fetchIDData)==array()){
           return false;
         }
$entity_id=$fetchIDData[0]['entity_id'];

        $getcountryID_table='customer_address_entity';
        $query2 = $this->resource->getConnection()->select()
        ->from( $getcountryID_table,['country_id'])->where(
        "entity_id='".$entity_id."'"
        );
    $fetchCountryIDData = $this->resource->getConnection()->fetchAll($query2);
    if(($fetchCountryIDData)==array()){
return false;

    }else{
        $country_id=$fetchCountryIDData[0]['country_id'];
        return $country_id;
    }

     }

    public function getCustomerEmail()
    {
        return $this->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
    }

    public function getProductVersion(){
        return  $this->productMetadata->getVersion(); 
    }

    public function getEdition(){
        return $this->productMetadata->getEdition() == 'Community' ? 'Magento Open Source':'Adobe Commerce Enterprise/Cloud';
    }

    public function getCurrentDate(){
        $dateTimeZone = new \DateTimeZone('Asia/Kolkata'); 
        $dateTime = new \DateTime('now', $dateTimeZone);
        return $dateTime->format('n/j/Y, g:i:s a');
    }

    /**
     * Get module version from composer.json
     * @return string|null
     */
    public static function getModuleVersion()
    {
        try {
            // Get the module directory 
            $moduleDir = dirname(__DIR__);
            $composerJsonPath = $moduleDir . '/composer.json';
            
            if (file_exists($composerJsonPath)) {
                $composerJson = file_get_contents($composerJsonPath);
                $composerData = json_decode($composerJson, true);
                
                if (isset($composerData['version'])) {
                    return 'v' . $composerData['version'];
                }
            }
        } catch (\Exception $e) {
        }
        return null;
    }

    /**
     * Checks if 2FA is disabled for the user.
     * Returns true if 2FA is disabled, false otherwise.
     * 
     * @param array $row User row data from miniorange_tfa_users table
     */
    public function isTwoFADisabled($row)
    { 
        if (isset($row[0]['disable_motfa'])) {
            $disableValue = $row[0]['disable_motfa'];
            if ($disableValue === '1' || $disableValue === 1 || $disableValue === true) {
                $this->log_debug("2FA is disabled for this user (disable_motfa = 1)");
                return true;
            }
        }

        return false;
    }

    /**
     * Get all users with 2FA configured from the database
     * Helper method for Block class to access all configured users
     */
    public function getAllConfiguredUsers()
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('miniorange_tfa_users');

        $query = "SELECT * FROM $tableName";
        $result = $connection->fetchAll($query);
        $processedUsers = [];

        foreach ($result as $user) {
            if (!isset($user['username']) || $user['username'] === null || $user['username'] === '') {
                if (isset($user['email']) && $user['email'] !== null && $user['email'] !== '') {
                    $user['username'] = $user['email'];
                } else {
                    continue;
                }
            }

            if (!isset($user['email']) || $user['email'] === null || $user['email'] === '') {
                $user['email'] = $user['username'];
            }

            $usernameToCheck = $user['username'];
            $emailToCheck = $user['email'];

            $adminTable = $this->resource->getTableName('admin_user');
            $adminQueryByUsername = $connection->select()
                ->from($adminTable, ['user_id'])
                ->where("username = ?", $usernameToCheck);
            $adminResult = $connection->fetchOne($adminQueryByUsername);
            
            // If not found by username, check by email
            if (!$adminResult) {
                $adminQueryByEmail = $connection->select()
                    ->from($adminTable, ['user_id'])
                    ->where("email = ?", $emailToCheck);
                $adminResult = $connection->fetchOne($adminQueryByEmail);
            }
            
            if ($adminResult) {
                $originalWebsiteId = isset($user['website_id']) ? $user['website_id'] : null;
                $user['website_id'] = -1;

                if ($originalWebsiteId !== null && $originalWebsiteId !== '' && (int)$originalWebsiteId !== -1) {
                    try {
                        // Check if website_id column exists in table
                        $tableInfo = $connection->describeTable($tableName);
                        if (isset($tableInfo['website_id'])) {
                            $connection->update(
                                $tableName,
                                ['website_id' => '-1'],
                                ['username = ?' => $usernameToCheck]
                            );
                        }
                    } catch (\Exception $e) {
                        $this->log_debug("website_id column doesn't exist yet: " . $e->getMessage());
                    }
                }
            } else {
                if (!isset($user['website_id']) || $user['website_id'] === null || $user['website_id'] === '') {
                    // Check customer_entity table for website_id
                    $customerTable = $this->resource->getTableName('customer_entity');
                    $customerQuery = $connection->select()
                        ->from($customerTable, ['website_id'])
                        ->where("email = ?", $emailToCheck)
                        ->limit(1);
                    $customerWebsiteId = $connection->fetchOne($customerQuery);
                    
                    if ($customerWebsiteId !== false && $customerWebsiteId !== null) {
                        $user['website_id'] = (int)$customerWebsiteId;
                    } else {
                        $user['website_id'] = 1;
                    }
                } else {
                    $websiteIdInt = (int)$user['website_id'];
                    if ($websiteIdInt === -1) {
                        $recheckAdmin = $connection->fetchOne(
                            $connection->select()
                                ->from($adminTable, ['user_id'])
                                ->where("username = ? OR email = ?", $usernameToCheck, $emailToCheck)
                        );
                        if (!$recheckAdmin) {
                            $user['website_id'] = 1;
                        } else {
                            $user['website_id'] = -1;
                        }
                    } else {
                        $user['website_id'] = $websiteIdInt;
                    }
                }
            }

            if (isset($user['disable_motfa'])) {
                $disableValue = $user['disable_motfa'];
                if (is_string($disableValue)) {
                    $user['disable_2fa'] = ($disableValue === '1' || $disableValue === 'true' || $disableValue === 'True');
                } else {
                    $user['disable_2fa'] = (bool)$disableValue;
                }
            } elseif (isset($user['disable_2fa'])) {
                $user['disable_2fa'] = (bool)$user['disable_2fa'];
            } else {
                $user['disable_2fa'] = false;
            }

            if (!isset($user['active_method'])) {
                $user['active_method'] = '';
            }
            
            // Ensure all required fields for template are present and properly sanitized
            if (!isset($user['configured_methods'])) {
                $user['configured_methods'] = '';
            } else {
                $user['configured_methods'] = (string)$user['configured_methods'];
            }
            if (!isset($user['phone'])) {
                $user['phone'] = '';
            } else {
                $user['phone'] = (string)$user['phone'];
            }
            if (!isset($user['countrycode'])) {
                $user['countrycode'] = '';
            } else {
                $user['countrycode'] = (string)$user['countrycode'];
            }
            
            // Ensure username and email are strings
            $user['username'] = (string)$user['username'];
            $user['email'] = (string)$user['email'];
            $user['active_method'] = (string)$user['active_method'];
            
            $processedUsers[] = $user;
        }

        return $processedUsers;
    }

    /**
     * Check if this is running on Adobe Commerce/B2B edition
     *
     * @return bool
     */
    public function isCommerceEdition()
    {
        return $this->b2bResolver->isCommerceEdition();
    }

    /**
     * This function sets the user as B2C user by appending data in company_advanced_customer_entity table
     */
    public function saveCustomerAsB2CUser($customerData, $customerId)
    {
        if (!$this->b2bResolver->isCommerceEdition()) {
            return $customerData;
        }
        
        $companyCustomerFactory = $this->b2bResolver->getCompanyCustomerFactory();
        if (!$companyCustomerFactory) {
            return $customerData;
        }
        
        try {
            // Create company customer extension attributes
            $companyAttributes = $companyCustomerFactory->create();
            $companyAttributes->setCustomerId($customerId);
            $companyAttributes->setCompanyId(TwoFAConstants::NO_COMPANY_ID);
            $companyAttributes->setIsCompanyAdmin(false);
            $companyAttributes->setStatus(TwoFAConstants::COMPANY_ACTIVE); // Active status
        
            // Set the extension attributes
            $extensionAttributes = $customerData->getExtensionAttributes();
            if ($extensionAttributes === null) {
                $customerExtensionFactory = $this->b2bResolver->getCustomerExtensionFactory();
                if ($customerExtensionFactory) {
                    $extensionAttributes = $customerExtensionFactory->create();
                }
            }
            
            if ($extensionAttributes !== null) {
                $extensionAttributes->setCompanyAttributes($companyAttributes);
                $customerData->setExtensionAttributes($extensionAttributes);
            }
            
            return $customerData;
        } catch (\Exception $e) {
            $this->log_debug("Error in saveCustomerAsB2CUser: " . $e->getMessage());
            return $customerData;
        }
    }

    /**
     * Send OTP using MiniOrange gateway API call
     */
    public function send_otp_using_miniOrange_gateway_usingApicall($authType, $username, $phone)
    {
        $customerKeys = $this->getCustomerKeys(false);
        if (!$customerKeys) {
            //inline 2fa is disable.
            $this->log_debug(
                "Execute headapi: login with miniorange Account first"
            );
            return [
                "status" => "ERROR",
                "message" => "Please login with miniorange Account first",
            ];
        }
        $customerKey = $customerKeys["customer_key"];
        $apiKey = $customerKeys["apiKey"];
        $authCodes = [
            "OOE" => "EMAIL",
            "OOS" => "SMS",
            "OOSE" => "SMS AND EMAIL",
            "KBA" => "KBA",
        ];

        if ($authType == "OOS") {
            $phone_set = $phone;
            $email_set = "";
            $this->log_debug(
                "HeadlessApi.php : auth type SMS"
            );
        }
        if ($authType == "OOE") {
            $phone_set = "";
            $email_set = $username;
            $this->log_debug(
                "HeadlessApi.php : auth type Email"
            );
        }
        if ($authType == "OOSE") {
            $phone_set = $phone;
            $email_set = $username;
            $this->log_debug(
                "HeadlessApi.php :auth type SMS AND EMAIL"
            );
        }

        $fields = [
            "customerKey" => $customerKey,
            "username" => '',
            "phone" => $phone,
            "email" => $email_set,
            "authType" => $authCodes[$authType],
            "transactionName" => $this->getTransactionName(),
        ];
        $urls = $this->getApiUrls();
        $url = $urls['challange'];
        $sendOtpResponse = Curl::challenge(
            $customerKey,
            $apiKey,
            $url,
            $fields
        );

        $sendOtpResponse = json_decode($sendOtpResponse);
        $returnResponse = [
            "status" => $sendOtpResponse->status,
            "message" => $sendOtpResponse->message,
            "txId" => $sendOtpResponse->txId,
        ];

        return $returnResponse;
    }
}
