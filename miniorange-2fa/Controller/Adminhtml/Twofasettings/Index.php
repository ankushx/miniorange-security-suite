<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Twofasettings;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;

/**
 * This class handles the action for endpoint: motwofa/TwoFAsettings/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */


class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{

   protected $request;
protected $data_array;
   protected $resultFactory;
public static $var1;
public static $uname;
public static $uemail;
public static $uphone;
public static $uActive;
public static $uConfig;
public static $utID;
public static $uSecret;
public static $uid;
   public function __construct(
       \Magento\Framework\App\RequestInterface $request,
       \Magento\Backend\App\Action\Context $context,
       \Magento\Framework\View\Result\PageFactory $resultPageFactory,
       \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
       \Magento\Framework\Message\ManagerInterface $messageManager,
       \Psr\Log\LoggerInterface $logger,
       \Magento\Framework\Controller\ResultFactory $resultFactory
   ) {
		$this->resultFactory = $resultFactory;

       parent::__construct($context,$resultPageFactory,$twofautility,$messageManager,$logger);
       $this->request = $request;
   }

     /**
      * The first function to be called when a Controller class is invoked.
      * Usually, has all our controller logic. Returns a view/page/template
      * to be shown to the users.
      *
      * This function gets and prepares all our OAuth config data from the
      * database. It's called when you visis the motwofa/TwoFAsettings/Index
      * URL. It prepares all the values required on the SP setting
      * page in the backend and returns the block to be displayed.
      *
      * @return \Magento\Framework\View\Result\Page
      */
    public function execute()
    {
      $postValue = $this->request->getPostValue();
		if( isset( $postValue['option'] ) ) {

			$isCustomerRegistered = $this->twofautility->isCustomerRegistered();
			if( !$isCustomerRegistered ) {
				$resultRedirect = $this->resultRedirectFactory->create();
				$resultRedirect->setPath('motwofa/account/index');
				return $resultRedirect;
			} else {
				$url = '';

				if( 'TfaMethodConfigure' === $postValue['option']) {
					$url = $this->configure();
				} else if( 'TfaMethodConfigureValidate' === $postValue['option']) {
					$url = $this->configure_step_two();
				} else if( 'TfaMethodTestConfiguration' === $postValue['option'] ) {
					$url = $this->test_configuration();
				} else if( 'TfaMethodActivate' === $postValue['option'] ) {
					$url = $this->activate_method();
				}
				$redirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
				$redirect->setUrl( $url );
				return $redirect;
			}
		}
		// generate page
		$resultPage = $this->resultPageFactory->create();
		$resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
		return $resultPage;
	}

	// Activate the method
	public function activate_method(){

		$isCustomerRegistered = $this->twofautility->isCustomerRegistered();
		if(!$isCustomerRegistered){
			$this->messageManager->addSuccessMessage(TwoFAMessages::NOT_REGISTERED);
			return;
		}

		$params = $this->getRequest()->getParams();
		$current_user = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();
		$username = $current_username;
		$method = $params['ActivateMethodName'];

		// get the id
		// check if the table has the row corresponding to the user id
		$row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);
		$url = '';
		if( is_array( $row ) && sizeof( $row ) > 0 )
		{
			$configuredMethod = $row[0]['configured_methods'];
			if( ! str_contains($row[0]['configured_methods'], $method) ) {
				$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=error';
			} else {
				// Update the active Method
				$data = [
					'active_method' => $method
				];
				$this->twofautility->updateRowInTable( 'miniorange_tfa_users', $data, 'username', $username );
				$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=success';
			}
		} else {
			$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=error';
		}
		return $url;
	}

	// Test configuration POST request handle
	public function test_configuration(){
		$this->twofautility->log_debug("TwoFAsettings: test configuration: execute");
		$isCustomerRegistered = $this->twofautility->isCustomerRegistered();
		if(!$isCustomerRegistered){
			$this->messageManager->addSuccessMessage(TwoFAMessages::NOT_REGISTERED);
			return;
		}

		$params = $this->getRequest()->getParams();
		$current_user = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();
		$username = $current_username;
		$method = $params['TestConfigMethodName'];

		// get the id
		// check if the table has the row corresponding to the user id
		$row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);
		$url = '';
		if( is_array( $row ) && sizeof( $row ) > 0 )
		{
			$configuredMethod = $row[0]['configured_methods'];
			if( ! str_contains($row[0]['configured_methods'], $method) ) {
				$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=error';
			} else {
				// challange the user
				if( 'GoogleAuthenticator' !== $method ){
					$this->twofautility->log_debug("TwoFAsettings: test configuration: execute: method: ".$method);
					$mouser = new MiniOrangeUser();
					$response = json_decode( $mouser->challenge( $username, $this->twofautility, $method, true), true );
					$this->twofautility->setSessionValue(TwoFAConstants::PRE_TRANSACTIONID,$response['txId']);
					if(isset($response['message'])){
						$response_message=$response['message'];
					}else{
						$response_message=NULL;
					}


					if( isset( $response['status'] ) && $response['status'] === 'SUCCESS' ){
						$this->twofautility->updateColumnInTable( 'miniorange_tfa_users', 'transactionId' , $response['txId'] , 'username' , $current_username);
					}
				}else{
					$url = strtok($_SERVER["REQUEST_URI"], '?').'?configure='.$method.'&action=validateotp&testconfig=testconfig';
				}
				if(isset( $response['status'] ) && $response['status'] === 'FAILED' ){
					$this->twofautility->log_debug("TwoFAsettings: test configuration: Failed");
					$url= strtok($_SERVER["REQUEST_URI"], '?').'?message='.$response_message.'&r_status=FAILED&testconfig=testconfig';
				}
				if(isset( $response['status'] ) && $response['status'] === 'SUCCESS' ){
					$this->twofautility->log_debug("TwoFAsettings: test configuration: success");
					$url = strtok($_SERVER["REQUEST_URI"], '?').'?configure='.$method.'&action=validateotp&testconfig=testconfig&message='.$response_message.'&r_status=SUCCESS';
				}

			}
		} else {
			$this->twofautility->log_debug("TwoFAsettings: test configuration: row not register");
			$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=error&testconfig=testconfig';
		}

		return $url;
	}

	//configure 2fa method
	public function configure(){
		$this->twofautility->log_debug("TwoFAsettings: Configuration: execute");

		$isCustomerRegistered = $this->twofautility->isCustomerRegistered();
		if(!$isCustomerRegistered){
			$this->messageManager->addSuccessMessage(TwoFAMessages::NOT_REGISTERED);
			return;
		}
        $params = $this->getRequest()->getParams();


		$current_user = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();
		$username = $current_username;
		$email = isset( $params['email'] ) ? $params['email'] : '';
		$phone = isset( $params['phone'] ) ? $params['phone'] : '';
		$countrycode =isset($params['countrycode'] )? $params['countrycode'] : '';
		$authType = isset( $params['authType'] ) ? $params['authType'] : '';
		$mouser = new MiniOrangeUser();
		$isInline = 0;
		// get the id
		// check if the table has the row corresponding to the user id
		$row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);

		if( is_array( $row ) && sizeof( $row ) > 0 )
		{
		//gather old data;
			$current_user     = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();
		$tfaInfo     = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);

		$this->twofautility->log_debug("TwoFAsettings: Configuration: userfound: set prevoius configuration in session");
			$configuredMethod = $row[0]['configured_methods'];
			$email = empty( $email ) ? ( empty( $row[0]['email'] ) ? '' : $row[0]['email'] ) : $email;
			$phone = empty( $phone ) ? ( empty( $row[0]['phone'] ) ? '' : $row[0]['phone'] ) : $phone;
			$countrycode = empty( $countrycode ) ? ( empty( $row[0]['countrycode'] ) ? '' : $row[0]['countrycode'] ) : $countrycode;
			if( ! str_contains($row[0]['configured_methods'], $authType) ) {
				$configuredMethod = $configuredMethod . ';' . $authType;
			}

			$data_array= array(
				'active_method' => $authType,
				'configured_methods' => $configuredMethod,
				'email' => $email,
				'phone' => $phone,
				'countrycode' => $countrycode
			);
			$this->twofautility->setSessionValue(TwoFAConstants::PRE_USERNAME, $current_username);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_EMAIL,$email);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_PHONE,$phone);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_COUNTRY_CODE,$countrycode);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_ACTIVE_METHOD,$authType);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_CONFIG_METHOD,  $configuredMethod);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_IS_INLINE,1);
		} else {
			$current_user     = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();
		$isInline=1;
		$this->twofautility->log_debug("TwoFAsettings: Configuration: user not found : set previous data in session");

$this->twofautility->setSessionValue(TwoFAConstants::PRE_USERNAME, $username);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_EMAIL,$email);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_PHONE, $phone);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_COUNTRY_CODE,$countrycode);
$Secret_GAuth=$this->twofautility->getSessionValue(TwoFAConstants::PRE_SECRET);
if($Secret_GAuth!=NULL){
	$set_secret=$Secret_GAuth;
}else{
	$set_secret=$this->twofautility->generateRandomString();
	$this->twofautility->setSessionValue(TwoFAConstants::PRE_SECRET,$set_secret);
}

$this->twofautility->setSessionValue(TwoFAConstants::PRE_ACTIVE_METHOD, $authType);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_CONFIG_METHOD,$authType);
$this->twofautility->setSessionValue(TwoFAConstants::PRE_IS_INLINE,1);
$this->twofautility->flushCache();

			$this->twofautility->log_debug("TwoFAsettings: Configuration: adding new row");
			$data_array= array(
				'username' => $username,
					'active_method' => $authType,
					'configured_methods' => $authType,
					'email' => $email,
					'phone' => $phone,
					'countrycode' => $countrycode,
					'secret' => $set_secret
			);

		}
		// for now let's say email is not
		// challange the user
		$response_message=NULL;
		if( $authType !== "GoogleAuthenticator" ) {
			$response = json_decode( $mouser->setUserInfoData($data_array)->challenge( $username,$this->twofautility, $authType, true), true );

			if(isset($response['message'])){
				$response_message =$response['message'];
			}

			if( isset( $response['status'] ) && $response['status'] === 'SUCCESS' ){
				$data_array['transactionId']=$response['txId'];
				$this->twofautility->setSessionValue(TwoFAConstants::PRE_TRANSACTIONID,$response['txId']);
			}
		}
		if(isset( $response['status'] ) && $response['status'] === 'FAILED' ){
			$this->twofautility->flushCache();
			$url= strtok($_SERVER["REQUEST_URI"], '?').'?message='.$response_message.'&r_status=FAILED';
		}
		else{
			$url = strtok($_SERVER["REQUEST_URI"], '?').'?configure='.$authType.'&action=validateotp&message='.$response_message.'&r_status=SUCCESS';
		}


		return $url;
	}

 	public function configure_step_two(){
		$this->twofautility->log_debug("TwoFAsettings: Configuration setp_two: execute");
		$data_array=$this->goBack_to_PreviousConfig();
		$isCustomerRegistered = $this->twofautility->isCustomerRegistered();
   		if(!$isCustomerRegistered){
   			$this->messageManager->addSuccessMessage(TwoFAMessages::NOT_REGISTERED);
   				return;
   		}
 		$params = $this->getRequest()->getParams();

 		$mouser = new miniOrangeUser();
 		$current_user = $this->twofautility->getCurrentAdminUser();
 		$current_username = $current_user->getUsername();
		if( 'GoogleAuthenticator' === $params['authType'] ){
			$response = $this->twofautility->verifyGauthCode( $params['one-time-otp-token'], $current_username );
			$this->twofautility->log_debug("TwoFAsettings: Configuration: execute: google auth response fetched");

		} else {

			$response = $mouser->setUserInfoData($data_array)->validate($current_username, $params['one-time-otp-token'], $params['authType'], $this->twofautility, NULL, true);
			$this->twofautility->log_debug("TwoFAsettings: Configuration: validating response");
		}
 		$response = json_decode($response, true);

 		$url = '';
		 $isInline=$this->twofautility->getSessionValue(TwoFAConstants::PRE_IS_INLINE);
 		if( isset( $response['status'] ) && $response['status'] == 'SUCCESS' ){
			 // update configured methods in miniOrange and local db
 			$row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $current_username);
			if(!isset($params['testconfig'])){
			 if(  is_array( $row ) && sizeof( $row ) > 0 )
			 {



				if($data_array['active_method']!=NULL){
					$authType=$data_array['active_method'];
				}else{
					$authType = empty( $authType ) ? ( empty( $row[0]['active_method'] ) ? '' : $row[0]['active_method'] ) : $authType;
				}
				$configuredMethod = $row[0]['configured_methods'];
				if( ! str_contains($row[0]['configured_methods'], $authType) ) {
					$configuredMethod = $configuredMethod . ';' . $authType;
				}
				if($data_array['email']!=NULL){
					$email=$data_array['email'];
				}else{
					$email = empty( $email ) ? ( empty( $row[0]['email'] ) ? '' : $row[0]['email'] ) : $email;
				}
				if($data_array['phone']!=NULL){
					$phone=$data_array['phone'];
				}else{
					$phone = empty( $phone ) ? ( empty( $row[0]['phone'] ) ? '' : $row[0]['phone'] ) : $phone;
				}
				if($data_array['countrycode']!=NULL){
					$countrycode =$data_array['countrycode'];
				}else{
					$countrycode = empty( $countrycode ) ? ( empty( $row[0]['countrycode'] ) ? '' : $row[0]['countrycode'] ) : $countrycode;
				}

			if($data_array['transactionId']!=NULL){
				$transactionID=$data_array['transactionId'];
			}elseif($row[0]['transactionId']!=NULL){
				$transactionID=$row[0]['transactionId'];
			}else{
				$transactionID=1;
			}


			$data = [
				'active_method' => $authType,
				'configured_methods' => $configuredMethod,
				'email' => $email,
				'phone' => $phone,
				'countrycode' => $countrycode,
				'transactionId' => $transactionID
			];

			$this->twofautility->updateRowInTable( 'miniorange_tfa_users', $data, 'username', $data_array['username'] );
			if($data_array['secret'] != NULL){
				$this->twofautility->updateColumnInTable('miniorange_tfa_users', 'secret' ,  $data_array['secret'], 'username', $data_array['username']);

			}

				} else {
				if($data_array['transactionId']==NULL){
					$data_array['transactionId']=1;
				}
				//update customer count.

                         $customer_count= $this->twofautility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
                         $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_COUNT, 1 + $customer_count);
					$this->twofautility->insertRowInTable('miniorange_tfa_users', $data_array );
				}
			}

				//update user info at miniorange
				$response = json_decode($mouser->mo2f_update_userinfo($this->twofautility,$data_array['email'], $params['authType'], $data_array['phone'],$data_array['countrycode']));

				 if( is_null( $response ) ) {
					 $response = json_decode($mouser->mo_create_user($this->twofautility, $data_array['email'], $params['authType'], $data_array['phone'],$data_array['countrycode']));
					}
					//redirect url
					$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=success';
			}
		else{
			$this->twofautility->flushCache();
			$url = strtok($_SERVER["REQUEST_URI"], '?').'?status=error';

 		}
		//remove value set in session:
		unset($data_array);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_IS_INLINE,0);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_USERNAME, NULL);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_EMAIL,NULL);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_PHONE, NULL);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_COUNTRY_CODE,NULL);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_ACTIVE_METHOD,NULL);
		$this->twofautility->setSessionValue(TwoFAConstants::PRE_CONFIG_METHOD,NULL);

		return $url;
 	}


     /**
      * Is the user allowed to view the Service Provider settings.
      * This is based on the ACL set by the admin in the backend.
      * Works in conjugation with acl.xml
      *
      * @return bool
      */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_TWOFASETTINGS);
    }

	public function goBack_to_PreviousConfig()
	{				$current_user     = $this->twofautility->getCurrentAdminUser();
		$current_username = $current_user->getUsername();

		$uname=$this->twofautility->getSessionValue(TwoFAConstants::PRE_USERNAME);
		$uemail=$this->twofautility->getSessionValue(TwoFAConstants::PRE_EMAIL);
		$uphone=$this->twofautility->getSessionValue(TwoFAConstants::PRE_PHONE);
		$utID=$this->twofautility->getSessionValue(TwoFAConstants::PRE_TRANSACTIONID);
		$uSecret=$this->twofautility->getSessionValue(TwoFAConstants::PRE_SECRET);
		$uActive=$this->twofautility->getSessionValue(TwoFAConstants::PRE_ACTIVE_METHOD);
		$uconfig=$this->twofautility->getSessionValue(TwoFAConstants::PRE_CONFIG_METHOD);
		$ucountry=$this->twofautility->getSessionValue(TwoFAConstants::PRE_COUNTRY_CODE);

		$data = [
			'username' => $uname,
			'active_method' => $uActive,
			'configured_methods' => $uconfig,
			'email' => $uemail,
			'phone' => $uphone,
			'countrycode' => $ucountry,
			'transactionId' => $utID,
			'secret' => $uSecret
				];
			return $data;
	}

}
