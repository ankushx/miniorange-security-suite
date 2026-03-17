<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Usermanagement;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;



/**
 * This class handles the action for endpoint: motwofa/TwoFAsettings/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */


class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{

   protected $request;

   protected $resultFactory;

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

   public function execute()
   {
    $postValue = $this->request->getPostValue();

    if (!empty($postValue)) {
        $isCustomerRegistered = $this->twofautility->isCustomerRegistered();
        $isEnabled = $this->twofautility->micr();
        
        if (!$isCustomerRegistered || !$isEnabled) {
            $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
            $resultRedirect->setPath('motwofa/usermanagement/index');
            return $resultRedirect;
        }
    }
    if(isset($postValue['search'])){
   if(isset($postValue['user_username']) ){
    $username=$postValue['user_username'];
    $row=$this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $username);
    if( is_array( $row ) && sizeof( $row ) > 0 ){
        $user_username=$row[0]['username'];
        $user_email=$row[0]['email'];
        $user_countrycode=$row[0]['countrycode'];
        $user_phone=$row[0]['phone'];
        $user_active_method=$row[0]['active_method'];
        $user_configured_methods=$row[0]['configured_methods'];

        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,$user_username);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,$user_email);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,$user_countrycode);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE, $user_phone);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,$user_active_method);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,$user_configured_methods);
        $this->twofautility->flushCache();
        $this->twofautility->reinitConfig();
       // $this->messageManager->addSuccessMessage('User Found');
    }else{
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
        $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
        $this->twofautility->flushCache();
        $this->twofautility->reinitConfig();
        $this->messageManager->addErrorMessage('The user has not set up any Two-Factor Authentication (TwoFA) method yet. !');
    }
   }
}elseif(isset($postValue['reset'])){
    if (isset($postValue['email']) && isset($postValue['website_id'])) {
        $email = $postValue['email'];
        $websiteId = $postValue['website_id'];
        $row = $this->twofautility->getAllMoTfaUserDetails('miniorange_tfa_users', $email, $websiteId);
        if (!is_array($row) || sizeof($row) == 0) {
            $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $email);
        }
    } else {
        $username = $this->twofautility->getStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME);
        $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $username);
    }

    if( is_array( $row ) && sizeof( $row ) > 0 )
    {
     $idvalue=$row[0]['id'];
     $this->twofautility->deleteRowInTable('miniorange_tfa_users', 'id', $idvalue);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
     $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
     $this->twofautility->flushCache();
     $this->twofautility->reinitConfig();
     $this->messageManager->addSuccessMessage('Your User Details has been reset successfully');
     
     // Redirect to prevent form resubmission on refresh
     $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
     $resultRedirect->setPath('motwofa/usermanagement/index');
     return $resultRedirect;
  }else{
    $this->messageManager->addErrorMessage('Failed to reset User');
  }
 } else if (isset($postValue['disable_selected_users'])) {
    $usersJson = $postValue['all_users,website_id'] ?? $postValue['all users,website id'] ?? $postValue['all users,website_id'] ?? $postValue['all_users,website id'] ?? null;
    
    if (!$usersJson) {
        $this->messageManager->addErrorMessage('No users data found in request');
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('motwofa/usermanagement/index');
        return $resultRedirect;
    }
    
    $users = json_decode($usersJson, true); // Decode JSON into PHP array

    if (is_array($users) && !empty($users)) {
        foreach ($users as $user) {
            // Get the email/username and website_id for each user
            $email = $user['email'] ?? '';
            $websiteId = isset($user['website_id']) ? $user['website_id'] : null;

            if (empty($email)) {
                continue;
            }

            // Fetch the user row based on the email and website ID
            $row = $this->twofautility->getAllMoTfaUserDetails('miniorange_tfa_users', $email, $websiteId);

            if (is_array($row) && sizeof($row) > 0) {
                $username = isset($row[0]['username']) ? $row[0]['username'] : $email;
                $userId = isset($row[0]['id']) ? $row[0]['id'] : null;

                $disable_2fa_settings = false;
                if (isset($row[0]['disable_motfa'])) {
                    $disableValue = $row[0]['disable_motfa'];
                    $disable_2fa_settings = ($disableValue === '1' || $disableValue === 1 || $disableValue === true);
                } 

                $newDisableValue = !$disable_2fa_settings;

                $valueToSave = $newDisableValue ? '1' : '0';

                if ($userId) {
                    $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'disable_motfa', $valueToSave, 'id', $userId, null);
                } else {
                    $this->twofautility->updateColumnInTable('miniorange_tfa_users', 'disable_motfa', $valueToSave, 'username', $username, null);
                }

                $this->twofautility->flushCache();
                $this->twofautility->reinitConfig();

            }

        }
    }
    $this->messageManager->addSuccessMessage('2FA settings for the selected users have been change successful');
    
    // Redirect to prevent form resubmission on refresh
    $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
    $resultRedirect->setPath('motwofa/usermanagement/index');
    return $resultRedirect;
} else if (isset($postValue['reset_selected_users'])) {
    $usersJson = $postValue['all_users,website_id'] ?? $postValue['all users,website id'] ?? $postValue['all users,website_id'] ?? $postValue['all_users,website id'] ?? null;
    
    if (!$usersJson) {
        $this->messageManager->addErrorMessage('No users data found in request');
        $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setPath('motwofa/usermanagement/index');
        return $resultRedirect;
    }
    
    $users = json_decode($usersJson, true); // Decode JSON into PHP array

    if (is_array($users) && !empty($users)) {
        $resetCount = 0;
        foreach ($users as $user) {
            // Get the email/username and website_id for each user
            $email = $user['email'] ?? '';
            $websiteId = isset($user['website_id']) ? $user['website_id'] : null;

            if (empty($email)) {
                continue;
            }

            // Fetch the user row based on the email and website ID
            $row = $this->twofautility->getAllMoTfaUserDetails('miniorange_tfa_users', $email, $websiteId);

            if (is_array($row) && sizeof($row) > 0) {
                $idvalue = $row[0]['id'];
                $this->twofautility->deleteRowInTable('miniorange_tfa_users', 'id', $idvalue);
                $resetCount++;
            }
        }
        
        if ($resetCount > 0) {
            $this->twofautility->flushCache();
            $this->twofautility->reinitConfig();
            $this->messageManager->addSuccessMessage('2FA settings for the selected users have been reset successfully');
        } else {
            $this->messageManager->addErrorMessage('Failed to reset selected users');
        }
    } else {
        $this->messageManager->addErrorMessage('Invalid users data');
    }
    
    // Redirect to prevent form resubmission on refresh
    $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
    $resultRedirect->setPath('motwofa/usermanagement/index');
    return $resultRedirect;
}else{
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_USERNAME,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_EMAIL,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_COUNTRYCODE,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_PHONE,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_ACTIVEMETHOD,NULL);
    $this->twofautility->setStoreConfig(TwoFAConstants::USER_MANAGEMENT_CONFIGUREDMETHOD,NULL);
    $this->twofautility->flushCache();
    $this->twofautility->reinitConfig();
}
       // generate page
       $resultPage = $this->resultPageFactory->create();
       $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
       return $resultPage;
   }

    /**
     * Check if the user is allowed to access User Management.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR . TwoFAConstants::MODULE_USER_MANAGEMENT);
    }

}
