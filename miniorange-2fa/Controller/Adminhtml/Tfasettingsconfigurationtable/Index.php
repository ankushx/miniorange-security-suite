<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Tfasettingsconfigurationtable;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\View\Result\PageFactory;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;

class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
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
                
                $this->processSignInSettings($params);
                $this->messageManager->addSuccessMessage(TwoFAMessages::SETTINGS_SAVED);
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred: %1', $e->getMessage()));
            $this->logger->debug($e->getMessage());
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
        return $resultPage;
    }

    private function processSignInSettings(array $params)
    {
        // Handle delete operations from the table page
        if (isset($params['option']) && $params['option'] === 'delete_existing_rule') {
            if (isset($params['delete_role_admin'])) {
                // Delete admin rule
                $this->twofautility->setStoreConfig(TwoFAConstants::CURRENT_ADMIN_RULE, json_encode([]));
                $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_ADMIN_METHOD, NULL);
                $this->twofautility->setStoreConfig(TwoFAConstants::ADMIN_ACTIVE_METHOD_INLINE, NULL);
            } elseif (isset($params['delete_role_customer'])) {
                // Delete customer rule
                $this->twofautility->setStoreConfig(TwoFAConstants::CURRENT_CUSTOMER_RULE, json_encode([]));
                $this->twofautility->setStoreConfig(TwoFAConstants::ACTIVE_METHOD, NULL);
                $this->twofautility->setStoreConfig(TwoFAConstants::NUMBER_OF_CUSTOMER_METHOD, NULL);
                $this->twofautility->setStoreConfig(TwoFAConstants::INVOKE_INLINE_REGISTERATION, 0);
            }
        }

        $this->twofautility->flushCache();
        $this->twofautility->reinitConfig();
    }

    /**
     * Is the user allowed to view the 2FA Settings Configuration Table.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR . TwoFAConstants::MODULE_TFA_SETTINGS_CONFIGURATION_TABLE);
    }
}
