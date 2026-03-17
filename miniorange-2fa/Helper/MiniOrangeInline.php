<?php

namespace MiniOrange\TwoFA\Helper;

use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\Exception\InvalidPasscodeException;
use MiniOrange\TwoFA\Helper\Exception\OtpSentFailureException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\RequestInterface;

/**
 * @package     Joomla.Site
 * @subpackage  com_miniorange_twofa
 *
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
Class MiniOrangeInline extends BaseAdminAction{


    private $relayState;
    private $user;
    private $adminSession;
    private $cookieManager;
    private $adminConfig;
    private $cookieMetadataFactory;
    protected $_customer;
    private $customerModel;
    protected $_customerSession;
    private $adminSessionManager;
    private $urlInterface;

    private $userFactory;
    protected $_resultPage;
    private $request;
    private $postValue;
    protected $storeManager;
    private $url;
    protected $twofaCustomerRegistration;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $TwoFAUtility,
        \Magento\Customer\Model\Customer $customer,
         \Magento\Customer\Model\Session $customerSession,
         StoreManagerInterface $storeManager,
         \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Customer\Model\Customer $customerModel,
        RequestInterface $request,
       \Magento\Framework\UrlInterface $url,
       \MiniOrange\TwoFA\Helper\TwoFACustomerRegistration $twofaCustomerRegistration

    ) {
        //You can use dependency injection to get any class this observer may need.
     $this->_customer = $customer;
      $this->storeManager = $storeManager;
      $this->customerModel = $customerModel;
     $this->_customerSession = $customerSession;
      $this->request = $request;
      $this->cookieManager = $cookieManager;
      $this->url = $url;
      $this->twofaCustomerRegistration = $twofaCustomerRegistration;

     $this->postValue = $this->request->getPostValue();
    }

public function execute(){
}
public function testing($TwoFAUtility){
    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute :testing");
      $email = $this->postValue['miniorange_registered_email'];

		 // current user who started the flow
		$current_username =  $TwoFAUtility->getSessionValue( 'mousername');

     	$data = [
                  [
                     'username'=>$current_username,
                     'email'=> $email ,
                  ]
              ];
            $TwoFAUtility->insertRowInTable('miniorange_tfa_users',$data);
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: testing : row inserted");
            $user = new miniOrangeUser();

            $response=json_decode($user->challenge($current_username,$TwoFAUtility, 'OOE',true));
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: testing :response fetched");
            if($response->status=='SUCCESS'){
                $TwoFAUtility->updateColumnInTable('miniorange_tfa_users','transactionId',$response->txId,'username',$current_username);
                $TwoFAUtility->updateColumnInTable('miniorange_tfa_users','status_of_motfa','one','username',$current_username);
            }
		else{ $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: testing :response fetched:failed");
		      $this->setRedirect('index.php?option=com_miniorange_twofa&view=miniorange_twoFA');
			  return;
	   }
}

  public function pageTwoSubmit($TwoFAUtility){
    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageTwoSubmit");
		$user = new miniOrangeUser();
		$current_user=$TwoFAUtility->getSessionValue( 'mousername');

		$response= $user->validate($current_user,$this->postValue['Passcode'],'OOE',$TwoFAUtility,NULL,true);
		$response=json_decode($response);
		if($response->status=='SUCCESS'){
		   // $user_create_response = json_decode($user->mo_create_user($current_user->id,$current_user->name));
			$TwoFAUtility->updateColumnInTable('miniorange_tfa_users','status_of_motfa','one','username',$current_user);
            }
        else{
			throw new InvalidPasscodeException;
			}
  }
 public function thirdStepSubmit($TwoFAUtility){

    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: thirdsetpSubmit");
    // current user who started the flow
   $current_username =  $TwoFAUtility->getSessionValue( 'mousername');

    $data = [
             [
                'username'=>$current_username

             ]
         ];
         $row = $TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);


         if( !is_array( $row ) || sizeof( $row ) <= 0 )
         {

       $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: thirdsetpSubmit: row inserted");
         }
    		$user = new miniOrangeUser();
    		$current_user= $TwoFAUtility->getSessionValue( 'mousername');

    	      if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                          }

            $request =  $this->request->getParams();
            if(isset($this->postValue['miniorangetfa_method'])){
             $method=$this->postValue['miniorangetfa_method'];
            }elseif(isset($request['inline_one_method'])){
                $method=$request['miniorangetfa_method'];
            }


            //set data in session
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: thirdsetpSubmit: setting data into session");
           $temp_secret= $TwoFAUtility->generateRandomString();
            $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_INLINE, 1);
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_USERNAME, $current_username);
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_STATUS_OF_MOTFA, 'one');
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_ACTIVE_METHOD, $method);
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_CONFIG_METHOD, $method);
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_SECRET, $temp_secret);
            $redirect_url = '';
    		if(isset($method) && !empty($method)){
                  $TwoFAUtility->setSessionValue( 'step3method', $method);

    			if($method=='OOE'){
                    $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=OOEMethodValidation";
    			}
    			else if($method=='OOS'){
                    $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=OOSMethodValidation";
    			}
                else if($method=='GoogleAuthenticator'){
                    $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=GAMethodValidation";
    			}
    			else {
                    $redirect_url = $this->url->getUrl('motwofa/mocustomer') . "?mooption=invokeInline&step=OOSEMethodValidation&useremail=".$current_user;
    			}
    		}
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: thirdsetpSubmit :sending URL");
            return $redirect_url;
   }

    	public function pageFourChallenge($TwoFAUtility){
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourChallenge :send otp");
      		$user    = new miniOrangeUser();
      		$current_user = $TwoFAUtility->getSessionValue( 'mousername');
      		$method  = $TwoFAUtility->getSessionValue( 'step3method');
              $request =  $this->request->getParams();

              if(isset($request['inline_one_method'])){
                $this->postValue=$request;
              }

            if(isset($this->postValue['phone'])){
                $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourChallenge:phone set");
      		$phone   = $this->postValue['phone'];
            $countrycode =  $this->postValue['countrycode'];
            $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER__PHONE, $phone);
            $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE, $countrycode);
           }
             if(isset($this->postValue['email']) && $this->postValue['email']!=NULL){
                $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourChallenge:email set");
             $email   = $this->postValue['email'];
             $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER__EMAIL, $email);
    }elseif(isset($this->postValue['miniorangetfa_method']) && $this->postValue['miniorangetfa_method']=='OOE'){
        $email   = $current_user;
        $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER__EMAIL, $email);
    }
      		$send_otp_response = json_decode($user->challenge($current_user,$TwoFAUtility ,$method,true));
              $r_status= $send_otp_response->status;
              $message = $send_otp_response->message;
              $return_response= array(
                'status'=>$r_status,
                'message'=>$message
              );

      		if($send_otp_response->status == 'SUCCESS'){
                $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourChallenge:response success");
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_TRANSACTIONID, $send_otp_response->txId);

}elseif($send_otp_response->status == 'FAILED'){
    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourChallenge: response failed");
}
      		else{
      			   throw new OtpSentFailureException;
      		}

     return $return_response;
}

   public function pageFourValidate($TwoFAUtility) {
    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourValidate");
        $user    = new miniOrangeUser();
        $current_user = $TwoFAUtility->getSessionValue( 'mousername');
        $method  = $TwoFAUtility->getSessionValue( 'step3method');

        if( "GoogleAuthenticator" === $method ) {

            $response = json_decode($TwoFAUtility->verifyGauthCode( $this->postValue['Passcode'] , $current_user ));
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourValidate: Google auth response");

        } else {
            $customerUser = $TwoFAUtility->getCurrentUser();
            $response= $user->validate($current_user,$this->postValue['Passcode'],$method,$TwoFAUtility,NULL,true);
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: PageFourValidate: method response");
            $response=json_decode($response);
        }
        //save data after succesful inline process.
        if($response->status === 'SUCCESS'){
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: page four validate succesfull > saving data into db");
            $current_username =  $TwoFAUtility->getSessionValue( 'mousername');
            $TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_INLINE);
            $phone=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER__PHONE);
            $email=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER__EMAIL);
            $countrycode=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE);
            $transactionID=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_TRANSACTIONID);
            $status_of_motfa= $TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_STATUS_OF_MOTFA);
            $active_method=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_ACTIVE_METHOD);
            $config_method=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_CONFIG_METHOD);
            $secret=$TwoFAUtility->getSessionValue(TwoFAConstants::CUSTOMER_SECRET);

            $data = [
                     [
                        'username'=>$current_username,
                        'configured_methods'=> $config_method,
                        'active_method'=> $active_method,
                        'status_of_motfa'=> $status_of_motfa,
                        'secret'=> $secret
                     ]
                 ];
				//update customer count.
                $TwoFAUtility->flushCache();

                    $customer_count=  $TwoFAUtility->getStoreConfig(TwoFAConstants::CUSTOMER_COUNT);
                    $customer_count= 1 + $customer_count;
                    $TwoFAUtility->setStoreConfig(TwoFAConstants::CUSTOMER_COUNT, $customer_count);

                   //create new customer if going through registration process
                   $is_inside_registration=$TwoFAUtility->getSessionValue( 'mocreate_customer_register');
                   if($is_inside_registration){
                       $this->twofaCustomerRegistration->createNewCustomerAtRegistration();
                       $TwoFAUtility->setSessionValue( 'mocreate_customer_register', NULL );
                       $TwoFAUtility->log_debug("MiniOrangeInline.php: Creating a new customer account during registration process without saving details to the database. Session flag 'mocreate_customer_register' reset.");           
                  }else{                     
                      //insert row for new customer
                      
                      $TwoFAUtility->insertRowInTable('miniorange_tfa_users',$data);
                      
                      if($active_method=='OOS' || $active_method=='OOSE'){
                          if($phone!=NULL){
                              $TwoFAUtility->updateColumnInTable('miniorange_tfa_users','phone',$phone,'username',$current_username);
                              $TwoFAUtility->updateColumnInTable('miniorange_tfa_users','countrycode',$countrycode,'username',$current_username);
                        }
                    }
                    if($active_method=='OOE'  || $active_method=='OOSE'){
                        if($email!=NULL){
                            $TwoFAUtility->updateColumnInTable('miniorange_tfa_users','email',$email,'username',$current_username);
                        }
                    }
                    
                    
                    if($transactionID!=NULL){
                        $TwoFAUtility->updateColumnInTable('miniorange_tfa_users', 'transactionId',$transactionID,'username',$current_username);
                    }
                }
                $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: page four validate :clearing session value ");
                //remove data from session
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_INLINE,NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_SECRET,NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_TRANSACTIONID, NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_USERNAME, NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_ACTIVE_METHOD,NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_CONFIG_METHOD,NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_STATUS_OF_MOTFA, NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER__PHONE, NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE, NULL);
                $TwoFAUtility->setSessionValue(TwoFAConstants::CUSTOMER__EMAIL, NULL);
        }




        return $response->status === 'SUCCESS' ? true : false;
}


   public function TFAValidate($TwoFAUtility) {
    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate");
        $user    = new miniOrangeUser();
        $current_username = $TwoFAUtility->getSessionValue( 'mousername');
        $row=$TwoFAUtility->getMoTfaUserDetails('miniorange_tfa_users',$current_username);
        
        if(empty($row) || !is_array($row) || sizeof($row) == 0 || !isset($row[0]['active_method'])){
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate: ERROR - User not found in database or active_method not set");
            return false;
        }
        
        $method  = $row[0]['active_method'];
        $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate: Active method: " . $method);

        if( "GoogleAuthenticator" === $method ) {
            if(!isset($this->postValue['Passcode']) || empty($this->postValue['Passcode'])){
                $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate: ERROR - Passcode not provided");
                return false;
            }
            $response = json_decode($TwoFAUtility->verifyGauthCode( $this->postValue['Passcode'] , $current_username ), true);
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate: google auth response status: " . (isset($response['status']) ? $response['status'] : 'UNKNOWN'));
        } else {
            $response= $user->validate($current_username,$this->postValue['Passcode'],$method,$TwoFAUtility,NULL,true);
            $response=json_decode($response, true);
            $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: TFAValidate: Method response status: " . (isset($response['status']) ? $response['status'] : 'UNKNOWN'));
        }

        return isset($response['status']) && $response['status'] === 'SUCCESS' ? true : false;
}


   private function getCustomerFromAttributes($user_email)
   {    $TwoFAUtility->log_debug("MiniOrangeInline.php : execute: getcustomer from attribute");
       $this->customerModel->setWebsiteId($this->storeManager->getStore()->getWebsiteId());
       $customer = $this->customerModel->loadByEmail($user_email);
       return !is_null($customer->getId()) ? $customer : false;
   }

 }
