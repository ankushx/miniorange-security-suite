<?php

namespace MiniOrange\IpRestriction\Controller\Adminhtml\Upgrade;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\IpRestriction\Controller\Actions\BaseAdminAction;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;

/**
 * This class handles the action for endpoint: moratelimit/upgrade/Index
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
     * It's called when you visit the moratelimit/upgrade/Index
     * URL. It prepares all the values required on the license upgrade
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        try {
            // Add any initialization logic here if needed
        } catch (\Exception $e) {
            $this->handleException($e, 'Upgrade controller');
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(IpRestrictionConstants::MODULE_TITLE));
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
        return $this->_authorization->isAllowed(IpRestrictionConstants::MODULE_DIR . 'upgrade');
    }
}

