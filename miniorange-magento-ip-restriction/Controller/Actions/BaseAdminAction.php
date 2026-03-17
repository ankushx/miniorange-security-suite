<?php

namespace MiniOrange\IpRestriction\Controller\Actions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use MiniOrange\IpRestriction\Helper\Data;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Base Admin Action
 * Common functionality for admin controllers
 */
abstract class BaseAdminAction extends Action
{
    protected $resultPageFactory;
    protected $dataHelper;
    protected $messageManager;
    protected $logger;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Data $dataHelper,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->dataHelper = $dataHelper;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Add success message
     * @param string|\Magento\Framework\Phrase $message
     */
    protected function addSuccessMessage($message)
    {
        $this->messageManager->addSuccessMessage($message);
    }

    /**
     * Add error message
     * @param string|\Magento\Framework\Phrase $message
     */
    protected function addErrorMessage($message)
    {
        $this->messageManager->addErrorMessage($message);
    }

    /**
     * Add warning message
     * @param string|\Magento\Framework\Phrase $message
     */
    protected function addWarningMessage($message)
    {
        $this->messageManager->addWarningMessage($message);
    }

    /**
     * Log debug message
     * @param string $message
     */
    protected function logDebug($message)
    {
        $this->logger->debug($message);
    }

    /**
     * Log error message
     * @param string $message
     */
    protected function logError($message)
    {
        $this->logger->error($message);
    }

    /**
     * Log info message
     * @param string $message
     */
    protected function logInfo($message)
    {
        $this->logger->info($message);
    }

    /**
     * Redirect to specific path
     * @param string $path
     * @param array $params
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectToPath($path, $params = [])
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path, $params);
        return $resultRedirect;
    }

    /**
     * Handle exception
     * @param \Exception $e
     * @param string $context
     */
    protected function handleException(\Exception $e, $context = '')
    {
        $message = $context ? "Error in {$context}: " . $e->getMessage() : $e->getMessage();
        $this->logError($message);
        $this->addErrorMessage(__('An error occurred. Please try again.'));
    }
}

