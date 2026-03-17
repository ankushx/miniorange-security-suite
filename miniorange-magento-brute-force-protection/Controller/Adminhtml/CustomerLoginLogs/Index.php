<?php

namespace MiniOrange\BruteForceProtection\Controller\Adminhtml\CustomerLoginLogs;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Framework\App\ResourceConnection;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use Psr\Log\LoggerInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Customer Login Logs Controller
 */
class Index extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'MiniOrange_BruteForceProtection::customer_login_logs';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var BruteForceUtility
     */
    protected $bruteforceutility;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param BruteForceUtility $bruteforceutility
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        BruteForceUtility $bruteforceutility,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        ResultFactory $resultFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->bruteforceutility = $bruteforceutility;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->resultFactory = $resultFactory;
    }

    /**
     * Customer Login Logs page
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        // Handle POST request for saving settings - DISABLED in free version
        if ($this->getRequest()->isPost()) {
            // Feature disabled in free version - settings cannot be saved
            $this->messageManager->addNoticeMessage(__('This feature is not available in the free version.'));
            
            // Check if it's an AJAX request
            $isAjax = $this->getRequest()->isAjax() || 
                     $this->getRequest()->getHeader('X-Requested-With') === 'XMLHttpRequest' ||
                     $this->getRequest()->getParam('isAjax') === '1';
            
            if ($isAjax) {
                $result = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON);
                $result->setData([
                    'success' => false, 
                    'message' => __('This feature is not available in the free version.')
                ]);
                return $result;
            }
        }

        /** @var \Magento\Framework\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MiniOrange_BruteForceProtection::customer_login_logs');
        $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));

        return $resultPage;
    }
}

