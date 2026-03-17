<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Customgateway;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\MiniOrangeUser;
use Exception;
use MiniOrange\TwoFA\Helper\MoCurl;
//use ZendMail;
// use ZendMimeMessage as MimeMessage;
// use ZendMimePart as MimePart;
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
   protected $logger;

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
      $this->logger = $logger;
       parent::__construct($context,$resultPageFactory,$twofautility,$messageManager,$logger);
       $this->request = $request;
   }
   public function execute()
   {
      $postValue = $this->request->getPostValue();

      if(isset($postValue['enable_Emailcustomgateway'])){
       $enable_customgateway_forEmail = (isset($postValue['enable_customgateway_forEmail'])) ? 1 : 0;
       $this->twofautility->setStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_EMAIL,$enable_customgateway_forEmail);
       $this->twofautility->flushCache();
       $this->twofautility->reinitConfig();
        }
        if(isset($postValue['enable_SMScustomgateway'])){
           $enable_customgateway_forSMS = (isset($postValue['enable_customgateway_forSMS'])) ? 1 : 0;
           $this->twofautility->setStoreConfig(TwoFAConstants::ENABLE_CUSTOMGATEWAY_SMS,$enable_customgateway_forSMS);
           $this->twofautility->flushCache();
           $this->twofautility->reinitConfig();
            }
     $resultPage = $this->resultPageFactory->create();
     $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
     return $resultPage;
   }


}
