<?php

namespace MiniOrange\BruteForceProtection\Controller\Actions;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Base Admin Action Controller
 * Provides common functionality for all admin controllers
 */
abstract class BaseAdminAction extends Action
{
    protected $resultPageFactory;
    protected $bruteforceutility;
    protected $messageManager;
    protected $logger;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        PageFactory $resultPageFactory,
        BruteForceUtility $bruteforceutility,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->bruteforceutility = $bruteforceutility;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Check if form option is being saved
     * @param array $params
     * @return bool
     */
    protected function isFormOptionBeingSaved($params)
    {
        return isset($params['option']) && !empty($params['option']);
    }

    /**
     * Get request parameters
     * @return array
     */
    protected function getRequestParams()
    {
        return $this->getRequest()->getParams();
    }

    /**
     * Get specific request parameter
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getRequestParam($key, $default = null)
    {
        return $this->getRequest()->getParam($key, $default);
    }

    /**
     * Add success message
     * @param string $message
     */
    protected function addSuccessMessage($message)
    {
        $this->messageManager->addSuccessMessage($message);
    }

    /**
     * Add error message
     * @param string $message
     */
    protected function addErrorMessage($message)
    {
        $this->messageManager->addErrorMessage($message);
    }

    /**
     * Add warning message
     * @param string $message
     */
    protected function addWarningMessage($message)
    {
        $this->messageManager->addWarningMessage($message);
    }

    /**
     * Add notice message
     * @param string $message
     */
    protected function addNoticeMessage($message)
    {
        $this->messageManager->addNoticeMessage($message);
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
     * Check if user is allowed to access the resource
     * @param string $resource
     * @return bool
     */
    protected function isAllowed($resource)
    {
        return $this->_authorization->isAllowed($resource);
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
     * Get current admin user
     * @return \Magento\User\Model\User|null
     */
    protected function getCurrentAdminUser()
    {
        return $this->bruteforceutility->getCurrentAdminUser();
    }

    /**
     * Validate required parameters
     * @param array $params
     * @param array $required
     * @return bool
     */
    protected function validateRequiredParams($params, $required)
    {
        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                $this->addErrorMessage("Required field '{$field}' is missing or empty.");
                return false;
            }
        }
        return true;
    }

    /**
     * Sanitize input data
     * @param mixed $data
     * @return mixed
     */
    protected function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }

    /**
     * Validate email format
     * @param string $email
     * @return bool
     */
    protected function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate IP address
     * @param string $ip
     * @return bool
     */
    protected function isValidIp($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Get current timestamp
     * @return string
     */
    protected function getCurrentTimestamp()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Format date for display
     * @param string $date
     * @param string $format
     * @return string
     */
    protected function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        return date($format, strtotime($date));
    }

    /**
     * Check if request is POST
     * @return bool
     */
    protected function isPostRequest()
    {
        return $this->getRequest()->isPost();
    }

    /**
     * Check if request is GET
     * @return bool
     */
    protected function isGetRequest()
    {
        return $this->getRequest()->isGet();
    }

    /**
     * Get form key
     * @return string
     */
    protected function getFormKey()
    {
        return $this->getRequest()->getParam('form_key');
    }

    /**
     * Validate form key
     * @return bool
     */
    protected function validateFormKey()
    {
        $formKey = $this->getFormKey();
        $sessionFormKey = $this->_session->getData('_form_key');
        
        return $formKey && $sessionFormKey && $formKey === $sessionFormKey;
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
        $this->addErrorMessage('An error occurred. Please try again.');
    }
}
