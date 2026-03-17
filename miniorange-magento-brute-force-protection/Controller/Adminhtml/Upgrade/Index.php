<?php

namespace MiniOrange\BruteForceProtection\Controller\Adminhtml\Upgrade;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\BruteForceProtection\Controller\Actions\BaseAdminAction;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;

/**
 * This class handles the action for endpoint: mobruteforce/upgrade/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */
class Index extends BaseAdminAction implements HttpGetActionInterface
{
    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our upgrade /license page.
     * It's called when you visit the mobruteforce/upgrade/Index
     * URL. It prepares all the values required on the license upgrade
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        try {
            $adminEmail = $this->bruteforceutility->getCurrentAdminUser()->getEmail();
            $this->bruteforceutility->isFirstPageVisit($adminEmail, 'Upgrade');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));
        return $resultPage;
    }

    /**
     * Is the user allowed to view the Upgrade settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(BruteForceConstants::MODULE_DIR . BruteForceConstants::UPGRADE);
    }
}

