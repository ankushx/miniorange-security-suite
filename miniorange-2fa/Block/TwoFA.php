<?php
namespace MiniOrange\TwoFA\Block;

use MiniOrange\TwoFA\Helper\TwoFAConstants;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use MiniOrange\TwoFA\Helper\Curl;

/**
 * This class is used to denote our admin block for all our
 * backend templates. This class has certain commmon
 * functions which can be called from our admin template pages.
 */
class TwoFA extends Template
{


    private $twofautility;
    private $adminRoleModel;
    protected $authSession;
    protected $websiteCollectionFactory;
    protected $_websiteCollectionFactory;
    private $userGroupModel;
    private $request;
    private $deploymentConfig;

    public function __construct(
        Context $context,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
        \Magento\Authorization\Model\ResourceModel\Role\Collection $adminRoleModel,
        \Magento\Customer\Model\ResourceModel\Group\Collection $userGroupModel,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\App\DeploymentConfig $deploymentConfig,
        \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory,
        RequestInterface $request,
        array $data = []
    ) {
        $this->twofautility = $twofautility;
        $this->authSession = $authSession;
        $this->adminRoleModel = $adminRoleModel;
        $this->userGroupModel = $userGroupModel;
        $this->request = $request;
        $this->deploymentConfig = $deploymentConfig;
        $this->_websiteCollectionFactory = $websiteCollectionFactory;
        parent::__construct($context, $data);
    }
    public function getWebsiteCollection()
    {
        $collection = $this->_websiteCollectionFactory->create();
        return $collection;
    }
    public function get_enable_email_customgateway(){
        return $this->twofautility->getStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_EMAIL);
    }
    public function get_enable_sms_customgateway(){
        return $this->twofautility->getStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_SMS);
    }
    public function getUserManagementDetails(){
        $save_method_name=array(
            'OOSE'=>"OTP Over SMS and Email",
            'OOS'=>"OTP Over SMS",
            'OOE'=>"OTP Over Email",
            'GoogleAuthenticator'=> 'Google Authenticator'
    );
        $username=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME);
        $useremail=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL);
        $usercountrycode=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE);
        $userphone=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE);
        $userActiveMethod=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD);
        $userConfiguredmethod=$this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD);
        //Active method shortcut method name with complete name.
        if($userActiveMethod!=NULL && $userActiveMethod!=''){
            foreach($save_method_name as $method => $name) {
                $userActiveMethod=str_replace($method,$name,$userActiveMethod);
              }
        }
        //Configured method shortcut method name with complete name.
        if($userConfiguredmethod!=NULL && $userConfiguredmethod!=''){
            foreach($save_method_name as $method => $name) {
                $userConfiguredmethod=str_replace($method,$name,$userConfiguredmethod);
              }
        }
         //Add '+' sign in front of countrycode
        if($usercountrycode!=NULL && $usercountrycode!=''){
            $usercountrycode='+'.$usercountrycode;
        }

        $userDetails=array(
            'user_management_username'=>$username,
            'user_management_email'=>$useremail,
            'user_management_countrycode'=>$usercountrycode,
            'user_management_phone'=>$userphone,
            'user_management_activemethod'=>$userActiveMethod,
            'user_management_configuredmethod'=>$userConfiguredmethod
        );
        return $userDetails;
    }
public function get_admin_append_name(){
   return $this->deploymentConfig->get('backend/frontName');
}
public function get_sms_email_detail(){
    $customer_key= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
    $api_key= $this->twofautility->getStoreConfig(TwoFAConstants::API_KEY);
    if($customer_key==NULL || $api_key==NULL){
        return false;
    }
    return  Curl::get_email_sms_transactions($customer_key,$api_key);
}

    public function getRequestVariable(){
        return $this->request->getParams();
    }

    public function getStoreConfig(){
        $invoke_registration=$this->twofautility->getSessionValue( "mocreate_customer_register");
        
        // Check if 2FA for registration toggle is enabled
        $customer_2fa_for_registration = $this->twofautility->getStoreConfig(
            TwoFAConstants::CUSTOMER_2FA_FOR_REGISTRATION
        );
        
        if($invoke_registration) {
            // During registration
            if ($customer_2fa_for_registration) {
                return $this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
            } else {
                return $this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE);
            }
        } else {
            // During login
            return $this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD);
        }
    }


    public function getSessionUsername(){
        return $this->twofautility->getSessionValue( 'mousername');
    }

    /**
     * This function retrieves the miniOrange customer Email
     * from the database. To be used on our template pages.
     */
    public function getCustomerEmail()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_EMAIL);
    }


    public function isHeader()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::SEND_HEADER);
    }


    public function isBody()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::SEND_BODY);
    }

    /**
     * This function retrieves the miniOrange customer key from the
     * database. To be used on our template pages.
     */
    public function getCustomerKey()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
    }


    /**
     * This function retrieves the miniOrange API key from the database.
     * To be used on our template pages.
     */
    public function getApiKey()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::API_KEY);
    }


    /**
     * This function retrieves the token key from the database.
     * To be used on our template pages.
     */
    public function getToken()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::TOKEN);
    }

    /**
     * This function checks if TwoFA has been configured or not.
     */
    public function isTwoFAConfigured()
    {
        return $this->twofautility->isTwoFAConfigured();
    }

    /**
     * This function fetches the TwoFA App name saved by the admin
     */
    public function getAppName()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::APP_NAME);
    }


   public function getAllTfaMethods(){
      $allTfas = $this->twofautility->tfaMethodArray();
      return $allTfas;
   }

   //get mo 2fa user getMoTfaUserDetails

   public function getUserDetails(){
    $current_user     = $this->getCurrentAdminUser();
    $current_username = $current_user->getUsername();
    $tfaInfo     = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);
    return $tfaInfo;
  }

  public function getAdminEmailDetails(){
    $admin_email= $this->twofautility->getSessionValue('admin_inline_email_detail');
    if($admin_email==NULL || $admin_email=='')
    {
        return NULL;
    }else{
        return $admin_email;
    }

  }


  /**
  * Is 2fa methods method configured for admin
  * Return: true or false
  */
  public function isTfaMethodConfiguredorActive( $name ){
    $allTfaMethods = $this->twofautility->tfaMethodArray();
    $current_user     = $this->getCurrentAdminUser();
    $current_username = $current_user->getUsername();
    $tfaInfo     = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);

    $isCustomerRegistered =  $this->twofautility->isCustomerRegistered();
    $configureMethods = is_null( $tfaInfo ) || empty( $tfaInfo ) ? array() : explode(';',$tfaInfo[0]['configured_methods']);
    $activeMethod     = is_null( $tfaInfo ) || empty( $tfaInfo ) ? '' : $tfaInfo[0]['active_method'];
    $response = [
        'is_active' => false,
        'is_configured' => false
    ];
    if( $name === $activeMethod ){
        $this->twofautility->log_debug("isTfaMethodConfiguredorActive - in active oos ,ooe,oose");
        $response['is_active'] = true;
    }
    if( in_array( $name, $configureMethods ) ) {
        $this->twofautility->log_debug("isTfaMethodConfiguredorActive - in configured oos ,ooe,oose");
        $response['is_configured'] = true;
    }
    return $response;
  }

    /**
     * This function fetches the Client ID saved by the admin
     */
    public function getClientID()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::CLIENT_ID);
    }

    /**
     * This function fetches the Client secret saved by the admin
     */
    public function getClientSecret()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::CLIENT_SECRET);
    }

    /**
     * This function fetches the Scope saved by the admin
     */
    public function getScope()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::SCOPE);
    }

    /**
     * This function fetches the Authorize URL saved by the admin
     */
    public function getAuthorizeURL()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::AUTHORIZE_URL);
    }

    /**
     * This function fetches the AccessToken URL saved by the admin
     */
    public function getAccessTokenURL()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::ACCESSTOKEN_URL);
    }

    /**
     * This function fetches the GetUserInfo URL saved by the admin
     */
    public function getUserInfoURL()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::GETUSERINFO_URL);
    }


    public function getLogoutURL()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::TwoFA_LOGOUT_URL);
    }

    public function AuthenticatorIssuer()
    {
        return $this->twofautility->AuthenticatorIssuer();
    }

    public function AuthenticatorUrl()
    {
        return $this->twofautility->AuthenticatorUrl();
    }

    public function AuthenticatorCustomerUrl()
    {
        return $this->twofautility->AuthenticatorCustomerUrl();
    }

    /**
     * Get admin secret from session for Google Authenticator
     */
    public function getAdminSecret()
    {
        return $this->twofautility->getSessionValue(TwoFAConstants::ADMIN_SECRET);
    }
public function isGoogleAuthValidForAdmin()
{    $count=$this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
    $this->twofautility->log_debug("isGoogleAuthValidForAdmin : check google auth limit");

        if($count<=10){
            return 1;
        }else{
            return 0;
        }

}
    /**
     * This function gets the admin CSS URL to be appended to the
     * admin dashboard screen.
     */
    public function getAdminCssURL()
    {
        return $this->twofautility->getAdminCssUrl('adminSettings.css');
    }

      /**
       * This function gets the current version of the plugin
       * admin dashboard screen.
       */
      public function getCurrentVersion()
      {
          return $this->twofautility->getModuleVersion();
      }

    /**
     * This function gets the license plan level
     */
    public function getLicensePlan()
    {
        return TwoFAConstants::LICENSE_PLAN;
    }


    /**
     * This function gets the admin JS URL to be appended to the
     * admin dashboard pages for plugin functionality
     */
    public function getAdminJSURL()
    {
        return $this->twofautility->getAdminJSUrl('adminSettings.js');
    }

    /**
     * This function gets the admin JS URL to be appended to the
     * admin dashboard pages for plugin functionality
     */
    public function getQrCodeJS()
    {
        return $this->twofautility->getAdminJSUrl('jquery-qrcode.js');
    }


    /**
     * This function gets the IntelTelInput JS URL to be appended
     * to admin pages to show country code dropdown on phone number
     * fields.
     */
    public function getIntlTelInputJs()
    {
        return $this->twofautility->getAdminJSUrl('intlTelInput.min.js');
    }


    /**
     * This function fetches/creates the TEST Configuration URL of the
     * Plugin.
     */
    public function getTestUrl()
    {
        return $this->getSPInitiatedUrl(TwoFAConstants::TEST_RELAYSTATE);
    }


    /**
     * Get/Create Base URL of the site
     */
    public function getBaseUrl()
    {
        return $this->twofautility->getBaseUrl();
    }

    /**
     * Get/Create Base URL of the site
     */
    public function getCallBackUrl()
    {
        return $this->twofautility->getBaseUrl() . TwoFAConstants::CALLBACK_URL;
    }


    /**
     * Create the URL for one of the SAML SP plugin
     * sections to be shown as link on any of the
     * template files.
     */
    public function getExtensionPageUrl($page)
    {
        return $this->twofautility->getAdminUrl('motwofa/'.$page.'/index');
    }


    /**
     * Reads the Tab and retrieves the current active tab
     * if any.
     */
    public function getCurrentActiveTab()
    {
        $page = $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => false]);
              $start = strpos($page, '/motwofa')+9;
        $end = strpos($page, '/index/key');
        $tab = substr($page, $start, $end-$start);
        return $tab;
    }

        /**
     * Just check and return if the user has verified his
     * license key to activate the plugin. Mostly used
     * on the account page to show the verify license key
     * screen.
     */
    public function isVerified()
    {
        return $this->twofautility->mclv();
    }


    /**
     * Is the option to show SSO link on the Admin login page enabled
     * by the admin.
     */
    public function showAdminLink()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::SHOW_ADMIN_LINK);
    }


    /**
     * Is the option to show SSO link on the Customer login page enabled
     * by the admin.
     */
    public function showCustomerLink()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::SHOW_CUSTOMER_LINK);
    }


    /**
     * Create/Get the SP initiated URL for the site.
     */
    public function getSPInitiatedUrl($relayState = null)
    {
        return $this->twofautility->getSPInitiatedUrl($relayState);
    }


    /**
     * This fetches the setting saved by the admin which decides if the
     * account should be mapped to username or email in Magento.
     */
    public function getAccountMatcher()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::MAP_MAP_BY);
    }

    /**
     * This fetches the setting saved by the admin which doesn't allow
     * roles to be assigned to unlisted users.
     */
    public function getDisallowUnlistedUserRole()
    {
        $disallowUnlistedRole = $this->twofautility->getStoreConfig(TwoFAConstants::UNLISTED_ROLE);
        return !$this->twofautility->isBlank($disallowUnlistedRole) ?  $disallowUnlistedRole : '';
    }


    /**
     * This fetches the setting saved by the admin which doesn't allow
     * users to be created if roles are not mapped based on the admin settings.
     */
    public function getDisallowUserCreationIfRoleNotMapped()
    {
        $disallowUserCreationIfRoleNotMapped = $this->twofautility->getStoreConfig(TwoFAConstants::CREATEIFNOTMAP);
        return !$this->twofautility->isBlank($disallowUserCreationIfRoleNotMapped) ?  $disallowUserCreationIfRoleNotMapped : '';
    }


    /**
     * This fetches the setting saved by the admin which decides what
     * attribute in the SAML response should be mapped to the Magento
     * user's userName.
     */
    public function getUserNameMapping()
    {
        $amUserName = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_USERNAME);
        return !$this->twofautility->isBlank($amUserName) ?  $amUserName : '';
    }


    public function getGroupMapping()
    {
        $amGroupName = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_GROUP);
        return !$this->twofautility->isBlank($amGroupName) ?  $amGroupName : '';
    }

    /**
     * This fetches the setting saved by the admin which decides what
     * attribute in the SAML response should be mapped to the Magento
     * user's Email.
     */
    public function getUserEmailMapping()
    {
        $amEmail = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_EMAIL);
        return !$this->twofautility->isBlank($amEmail) ?  $amEmail : '';
    }

    /**
     * This fetches the setting saved by the admin which decides what
     * attribute in the SAML response should be mapped to the Magento
     * user's firstName.
     */
    public function getFirstNameMapping()
    {
        $amFirstName = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_FIRSTNAME);
        return !$this->twofautility->isBlank($amFirstName) ?  $amFirstName : '';
    }


    /**
     * This fetches the setting saved by the admin which decides what
     * attributein the SAML resposne should be mapped to the Magento
     * user's lastName
     */
    public function getLastNameMapping()
    {
        $amLastName = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_LASTNAME);
        return !$this->twofautility->isBlank($amLastName) ?  $amLastName : '';
    }


    /**
     * Get all admin roles set by the admin on his site.
     */
    public function getAllRoles()
    {
         //Apply a filter to only include roles of a certain type ('G' in this case)
         $rolesCollection = $this->adminRoleModel->addFieldToFilter('role_type', 'G');
         // Convert the filtered collection to an options array
         $rolesOptionsArray = $rolesCollection->toOptionArray();
         return $rolesOptionsArray;
    }

    /**
     * Get all admin rules set by the admin on his site.
     */
    public function getExistingRules()
    {
        $configValue = $this->twofautility->getStoreConfig(TwoFAConstants::CURRENT_ADMIN_RULE);
        return $configValue ? json_decode($configValue, true) : [];
    }

    /**
     * Get all customer rules set by the admin on his site.
     */
    public function getCustomerExistingRules()
    {
        $configValue = $this->twofautility->getStoreConfig(TwoFAConstants::CURRENT_CUSTOMER_RULE);
        return $configValue ? json_decode($configValue, true) : [];
    }

    /**
     * This function fetches the X509 cert saved by the admin for the IDP
     * in the plugin settings.
     */
    public function getX509Cert()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::X509CERT);
    }


    /**
     * Get all customer groups set by the admin on his site.
     */
    public function getAllGroups()
    {
        return $this->userGroupModel->toOptionArray();
    }


    /**
     * Get the default role to be set for the user if it
     * doesn't match any of the role/group mappings
     */
    public function getDefaultRole()
    {
        $defaultRole = $this->twofautility->getStoreConfig(TwoFAConstants::MAP_DEFAULT_ROLE);
        return !$this->twofautility->isBlank($defaultRole) ?  $defaultRole : TwoFAConstants::DEFAULT_ROLE;
    }


    /**
     * This fetches the registration status in the plugin.
     * Used to detect at what stage is the user at for
     * registration with miniOrange
     */
    public function getRegistrationStatus()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::REG_STATUS);
    }


    /**
     * Get the Current Admin user from session
     */
    public function getCurrentAdminUser()
    {
        return $this->twofautility->getCurrentAdminUser();
    }


    /**
     * Fetches/Creates the text of the button to be shown
     * for SP inititated login from the admin / customer
     * login pages.
     */
    public function getSSOButtonText()
    {
        $buttonText = $this->twofautility->getStoreConfig(TwoFAConstants::BUTTON_TEXT);
        $idpName = $this->twofautility->getStoreConfig(TwoFAConstants::APP_NAME);
        return !$this->twofautility->isBlank($buttonText) ?  $buttonText : 'Login with ' . $idpName;
    }


     /**
      * Get base url of miniorange
      */
    public function getMiniOrangeUrl()
    {
        return $this->twofautility->getMiniOrangeUrl();
    }


    /**
     * Get Admin Logout URL for the site
     */
    public function getAdminLogoutUrl()
    {
        return $this->twofautility->getLogoutUrl();
    }

    /**
     * Get Admin Page URL
     */
    public function getAdminPageUrl()
    {
        return $this->twofautility->getAdminPageUrl();
    }

    /**
     * Is Test Configuration clicked?
     */
    public function getIsTestConfigurationClicked()
    {
        return $this->twofautility->getIsTestConfigurationClicked();
    }

    /**
     * Is the option to show SSO link on the Customer login page enabled
     * by the admin.
     */
    public function invokeInline()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION);
    }

    /**
     * Is the option to show SSO link on the Admin login page enabled
     * by the admin.
     */
    public function TFAModule()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::MODULE_TFA);
    }
    public function checkcustomerID(){
        return $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_KEY);
    }
        /*
        if otp is enabled for user
        */

        public function getOTP(){

            $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD)):" ";

                    if(isset($activeMethods) && is_array( $activeMethods ) && in_array( "OOS", $activeMethods ) )
                     return "1";

                     else
                         return "0";

                 }

             /*
             if email is enabled for user */
                public function getEmail(){

            $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD)):" ";

                     if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOE", $activeMethods ))
                     {
                      return "1";



                      }
                     else
                       return "0";

                  }

              /*
              if oose is enabled for user */
                   public function getOOSE(){

            $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD)):" ";

                      if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOSE", $activeMethods ) )
                       return "1";

                       else
                        return "0";

                   }

          public function getGA(){

            $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ACTIVE_METHOD)):" ";

                      if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "GoogleAuthenticator", $activeMethods ) )
                       return "1";

                       else
                        return "0";

            }





    /* ===================================================================================================
                THE FUNCTIONS BELOW ARE FREE PLUGIN SPECIFIC AND DIFFER IN THE PREMIUM VERSION
       ===================================================================================================
     */


    /**
     * This function checks if the user has completed the registration
     * and verification process. Returns TRUE or FALSE.
     */
    public function isEnabled()
    {
        return $this->twofautility->micr();
    }

    public function getLkStatus(){
        return $this->twofautility->getStoreConfig(TwoFAConstants::LK_VERIFY);
    }
    public function getLkCustomer(){
        return $this->twofautility->getStoreConfig(TwoFAConstants::LK_NO_OF_USERS);
    }

    public function check_avaliable_customer(){

        $count= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
        if($count>=10 ){
            return false;
        }
       return true;
    }

    public function no_of_user_left(){
        $this->twofautility->flushCache();
        $count= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
        $user_left=10-$count;
        if($user_left>=0){
            return $user_left;
        }else{
            return 0;
        }

    }

    public function check_lk()
    {
        return $this->twofautility->getStoreConfig(TwoFAConstants::LK_VERIFY);
    }

    public function admin_getOOS(){
        $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE)):" ";

        if(isset($activeMethods) && is_array( $activeMethods ) && in_array( "OOS", $activeMethods ) )
         return "1";

         else
             return "0";
    }
    public function admin_getOOE(){
        $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE)):" ";

        if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOE", $activeMethods ))
        {
         return "1";

         }
        else
          return "0";
    }
    public function admin_getOOSE(){
        $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE)):" ";

        if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOSE", $activeMethods ) )
         return "1";

         else
          return "0";
    }
    public function admin_getGoogleAuth(){
        $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE)):" ";

        if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "GoogleAuthenticator", $activeMethods ) )
         return "1";

         else
          return "0";
    }

    public function get_admin_active_method(){

        $admin_adtive_method= $this->twofautility->getStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE);
        if($admin_adtive_method==NULL){
            return '';
        }else{
            return $admin_adtive_method;
        }

    }
    public function get_numberOf_admin_methods(){
        $numberOF_admin_method= $this->twofautility->getStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD);
        return $numberOF_admin_method;
    }
    public function get_number_of_activemethod_customer(){
        $invoke_registration=$this->twofautility->getSessionValue( "mocreate_customer_register");
        
        // Check if 2FA for registration toggle is enabled
        $customer_2fa_for_registration = $this->twofautility->getStoreConfig(
            TwoFAConstants::CUSTOMER_2FA_FOR_REGISTRATION
        );
        
        if($invoke_registration) {
            // During registration
            if ($customer_2fa_for_registration) {
                return $this->twofautility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
            } else {
                return $this->twofautility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD_AT_REGISTRATION);
            }
        } else {
            // During login
            return $this->twofautility->getStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD);
        }
    }

    /**
     * Get customer TFA user details
     */
    public function getCustomerMoTfaUserDetails($tableName, $email)
    {
        return $this->twofautility->getMoTfaUserDetails($tableName, $email);
    }

    /**
     * Get session value
     */
    public function getSessionValue($key)
    {
        return $this->twofautility->getSessionValue($key);
    }

    /**
     * Check if customer is a returning user 
     */
    public function isReturningCustomerUser($activeMethod)
    {
        $email = $this->twofautility->getSessionValue('mousername');
        if (!$email) {
            return false;
        }
        
        $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $email);
        if (is_array($row) && sizeof($row) > 0) {
            $userActiveMethod = isset($row[0]['active_method']) ? $row[0]['active_method'] : '';
            return $userActiveMethod === $activeMethod;
        }
        
        return false;
    }

    /**
     * Get user details for customer reset
     */
    public function getUserDetailsforcustomerreset()
    {
        $current_username = $this->twofautility->getSessionValue('mousername');
        $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);
        return $row;
    }

    public function get_admin_details(){
        $current_user     = $this->getCurrentAdminUser();
        $current_email = $current_user->getEmail();
        $current_username= $current_user->getUsername();
        $admin_detail_array=array();
        $admin_detail_array['current_admin_username']=$current_username;
        $admin_detail_array['current_admin_email']=$current_email;
        return $admin_detail_array;
    }

    public function get_current_admin_role(){
       $current_admin_role_twofsetting= $this->twofautility->get_admin_role_name();
       if($current_admin_role_twofsetting=='Administrators'){
        return 1;
       }else{
        return NULL;
       }
    }


    /*
get customer skip twofa enabled/disabled
*/
public function do_customer_skip_twofa(){
    return  $this->twofautility->getStoreConfig(TwoFAConstants::SKIP_TWOFA);
}
/*
get admin skip twofa enabled/disabled
*/
public function do_admin_skip_twofa(){
    return  $this->twofautility->getStoreConfig(TwoFAConstants::SKIP_TWOFA_ADMIN);
}

// opt verification on registration
public function registration_checkbox(){
    return $this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_CHECKBOX);
}

// 2FA for registration toggle 
public function customer_2fa_for_registration(){
    return $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_2FA_FOR_REGISTRATION);
}
// opt verification on registration selected_method
public function registration_checkbox_selected_method(){
    return $this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE);
}

// registration
public function registration_getOOE(){
    $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE)):" ";

    if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOE", $activeMethods ))
    {
     return "1";

     }
    else
      return "0";
}
public function registration_getOOSE(){
    $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE)):" ";

    if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOSE", $activeMethods ) )
     return "1";

     else
      return "0";
}
public function registration_getOOS(){
    $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE)):" ";

    if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "OOS", $activeMethods ) )
     return "1";

     else
      return "0";
}
public function registration_getGoogleAuth(){
    $activeMethods = !is_null($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE))?json_decode($this->twofautility->getStoreConfig(TwoFAConstants::REGISTER_OTP_TYPE)):" ";

    if(isset($activeMethods)  && is_array( $activeMethods ) && in_array( "GoogleAuthenticator", $activeMethods ) )
     return "1";

     else
      return "0";
}

public function getProductVersion(){
    return  $this->twofautility->getProductVersion(); 
}

public function getEdition(){
    return $this->twofautility->getEdition();
}

public function getCurrentDate(){
    return $this->twofautility->getCurrentDate();

}
public function dataAdded(){
    $this->twofautility->setStoreConfig(TwoFAConstants::DATA_ADDED,1);
    $this->twofautility->flushCache() ;

}

public function checkDataAdded(){
    return $this->twofautility->getStoreConfig(TwoFAConstants::DATA_ADDED);

}

public function getTimeStamp(){
    if($this->twofautility->getStoreConfig(TwoFAConstants::TIME_STAMP) == null){
        $this->twofautility->setStoreConfig(TwoFAConstants::TIME_STAMP,time());
        $this->twofautility->flushCache();
        return time();
    }
    return $this->twofautility->getStoreConfig(TwoFAConstants::TIME_STAMP);
}

/**
 * Get all users with 2FA configured from the database
 */
public function getAllConfiguredUsers()
{
    return $this->twofautility->getAllConfiguredUsers();
}

/**
 * Check if debug log is enabled.
 */
public function isDebugLogEnable()
{
    return $this->twofautility->getStoreConfig(TwoFAConstants::ENABLE_DEBUG_LOG);
}

}
