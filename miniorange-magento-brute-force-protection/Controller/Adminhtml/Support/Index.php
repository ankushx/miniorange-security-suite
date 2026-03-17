<?php

namespace MiniOrange\BruteForceProtection\Controller\Adminhtml\Support;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use MiniOrange\BruteForceProtection\Helper\BruteForceMessages;
use MiniOrange\BruteForceProtection\Helper\Curl;
use MiniOrange\BruteForceProtection\Helper\Data;
use MiniOrange\BruteForceProtection\Controller\Actions\BaseAdminAction;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * This class handles the action for endpoint: mobruteforce/support/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 *
 * This class handles processing and sending or support request
 */

 
class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    protected $dataHelper;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MiniOrange\BruteForceProtection\Helper\BruteForceUtility $bruteforceutility,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper
    ) {
        parent::__construct($context, $resultPageFactory, $bruteforceutility, $messageManager, $logger);
        $this->dataHelper = $dataHelper;
    }

    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our SP config data from the
     * database. It's called when you visis the mobruteforce/support/Index
     * URL. It prepares all the values required on the Support setting
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams(); //get params

            if ($this->isFormOptionBeingSaved($params)) {
                $this->checkIfSupportQueryFieldsEmpty(['email'=>$params,'query'=>$params]);
                $email = $params['email'];
                $query = $params['query'];
                $companyName = $this->dataHelper->getBaseUrl();
                Curl::submit_contact_us($email, $query);
                $this->messageManager->addSuccessMessage(BruteForceMessages::QUERY_SENT);
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);        
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());
        
        return $resultRedirect;
    }


    /**
     * Is the user allowed to view the Support settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(BruteForceConstants::MODULE_DIR . BruteForceConstants::SUPPORT);
    }

    /**
     * Check if support query forms are empty. If empty, throw
     * an exception. This is an extension of the requiredFields
     * function.
     *
     * @param array $array
     * @throws \Exception
     */
    protected function checkIfSupportQueryFieldsEmpty($array)
    {
        foreach ($array as $key => $value) {
            if ((is_array($value) && (!array_key_exists($key, $value) || empty($value[$key])))
                || empty($value)
            ) {
                throw new \Exception('Please fill in the required fields.');
            }
        }
    }
}

