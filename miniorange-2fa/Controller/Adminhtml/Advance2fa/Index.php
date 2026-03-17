<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Advance2fa;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;

/**
 * This class handles the action for endpoint: motwofa/advance2fa/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */
class Index extends BaseAdminAction implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function displays the Advanced 2FA Settings page with all fields
     * disabled to show premium features.
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams(); 
            
            if ($this->isFormOptionBeingSaved($params)) {
                $isCustomerRegistered = $this->twofautility->isCustomerRegistered();
                $isEnabled = $this->twofautility->micr();
                
                if (!$isCustomerRegistered || !$isEnabled) {
                    $resultPage = $this->resultPageFactory->create();
                    $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
                    return $resultPage;
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        // generate page
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
        return $resultPage;
    }

    /**
     * Is the user allowed to view the Advance 2FA Settings.
     * This is based on the ACL set by the admin in the backend.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR . TwoFAConstants::MODULE_ADVANCE_2FA);
    }
}

