<?php
namespace MiniOrange\SecuritySuite\Controller\Adminhtml\Redirect;

class Index extends \Magento\Backend\App\Action
{
    protected $moduleManager;
    protected $backendUrl;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Backend\Model\UrlInterface $backendUrl
    ) {
        parent::__construct($context);
        $this->moduleManager = $moduleManager;
        $this->backendUrl = $backendUrl;
    }

    /**
     * Execute the action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        // Get the module parameter from the request
        $module = $this->getRequest()->getParam('module');
        
        switch ($module) {
            case 'twofa':
                return $this->redirectToTwoFA();
            case 'ip-restriction':
                return $this->redirectToIpRestriction();
            case 'brute-force-protection':
                return $this->redirectToBruteForceProtection();
            case 'admin-logs':
                return $this->redirectToAdminLogs();
            default:
                $this->messageManager->addError(__('Invalid module specified.'));
                return $this->_redirect('adminhtml/dashboard/index');
        }
    }

    /**
     * Redirect to the Two-Factor Authentication module
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectToTwoFA()
    {
        // Check if MiniOrange TwoFA module is installed and enabled
        if ($this->moduleManager->isEnabled('MiniOrange_TwoFA')) {
            $miniorangeUrl = $this->backendUrl->getUrl('motwofa/account/index');
            return $this->_redirect($miniorangeUrl);
        } else {
            $this->messageManager->addError(__('Two-Factor Authentication module is not installed.'));
            return $this->_redirect('adminhtml/dashboard/index');
        }
    }

    /**
     * Redirect to the IP Restriction module
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectToIpRestriction()
    {
        // Check if MiniOrange IP Restriction module is installed and enabled
        if ($this->moduleManager->isEnabled('MiniOrange_IpRestriction')) {
            $miniorangeUrl = $this->backendUrl->getUrl('iprestriction/iprestrict/index');
            return $this->_redirect($miniorangeUrl);
        } else {
            $this->messageManager->addError(__('IP Restriction module is not installed.'));
            return $this->_redirect('adminhtml/dashboard/index');
        }
    }

    /**
     * Redirect to the Brute Force Protection module
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectToBruteForceProtection()
    {
        // Check if MiniOrange Brute Force Protection module is installed and enabled
        if ($this->moduleManager->isEnabled('MiniOrange_BruteForceProtection')) {
            $miniorangeUrl = $this->backendUrl->getUrl('mobruteforce/bruteforcesettings/index');
            return $this->_redirect($miniorangeUrl);
        } else {
            $this->messageManager->addError(__('Brute Force Protection module is not installed.'));
            return $this->_redirect('adminhtml/dashboard/index');
        }
    }

    /**
     * Redirect to the Admin Logs module
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    protected function redirectToAdminLogs()
    {
        // Check if MiniOrange Admin Logs module is installed and enabled
        if ($this->moduleManager->isEnabled('MiniOrange_AdminLogs')) {
            $miniorangeUrl = $this->backendUrl->getUrl('adminlogs/configuration/index');
            return $this->_redirect($miniorangeUrl);
        } else {
            $this->messageManager->addError(__('Admin Logs module is not installed.'));
            return $this->_redirect('adminhtml/dashboard/index');
        }
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MiniOrange_SecuritySuite::SecuritySuite');
    }
}
