<?php

namespace MiniOrange\AdminLogs\Controller\Adminhtml\Support;

use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use MiniOrange\AdminLogs\Helper\AdminLogsMessages;
use MiniOrange\AdminLogs\Helper\Curl;
use MiniOrange\AdminLogs\Controller\Actions\BaseAdminAction;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Response\Http;

/**
 * This class handles the action for endpoint: AdminLogs/AdminLogs/support/Submit
 * Extends the BaseAdminAction for Admin Actions which
 * handles support form submission via GET request and returns JSON response
 *
 * This class handles processing and sending support request via popup form
 */
class Index extends BaseAdminAction
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \MiniOrange\AdminLogs\Helper\AdminLogsUtility $adminLogsUtility
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Psr\Log\LoggerInterface $logger
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MiniOrange\AdminLogs\Helper\AdminLogsUtility $adminLogsUtility,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context, $resultPageFactory, $adminLogsUtility, $messageManager, $logger);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * Handle GET request for support form submission
     * Extracts parameters, validates, calls API, and returns JSON response
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        
        // Extract GET parameters with null coalescing for defaults
        $email = $this->getRequest()->getParam('email') ?? '';
        $phone = $this->getRequest()->getParam('phone') ?? '';
        $query = $this->getRequest()->getParam('query') ?? '';
        
        // Validate required fields
        if (empty($email) || empty($query)) {
            return $resultJson->setData([
                'success' => false,
                'message' => 'Email and query are required fields.'
            ]);
        }
        
        // Call external API
        try {
            Curl::submit_contact_us($email, $phone, $query);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        
        return $resultJson->setData([
            'success' => true,
            'message' => 'Your query has been submitted successfully. We will get back to you soon.'
        ]);
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
        return $this->_authorization->isAllowed(AdminLogsConstants::MODULE_DIR . AdminLogsConstants::MODULE_SUPPORT);
    }
}
