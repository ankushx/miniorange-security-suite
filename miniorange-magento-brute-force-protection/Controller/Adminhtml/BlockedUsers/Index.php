<?php

namespace MiniOrange\BruteForceProtection\Controller\Adminhtml\BlockedUsers;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use MiniOrange\BruteForceProtection\Controller\Actions\BaseAdminAction;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use MiniOrange\BruteForceProtection\Helper\BruteForceMessages;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Framework\App\ResourceConnection;


/**
 * Blocked Users Controller
 * Handles user blocking/unblocking and management features
 */
class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    protected $fileFactory;
    protected $resourceConnection;
    protected $resultRedirectFactory;
    protected $resultFactory;

    public function __construct(
        Context                                     $context,
        BruteForceUtility                           $bruteforceutility,
        \Magento\Framework\View\Result\PageFactory  $resultPageFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface                    $logger,
        FileFactory                                 $fileFactory,
        ResourceConnection                          $resourceConnection,
        RedirectFactory                             $resultRedirectFactory,
        ResultFactory                               $resultFactory
    )
    {
        parent::__construct($context, $resultPageFactory, $bruteforceutility, $messageManager, $logger);
        $this->fileFactory = $fileFactory;
        $this->resourceConnection = $resourceConnection;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
    }

    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams();
            $adminEmail = $this->bruteforceutility->getCurrentAdminUser()->getEmail();
            $this->bruteforceutility->isFirstPageVisit($adminEmail, 'Blocked Users');

            // Process unlock/block actions FIRST (before checking for settings save)
            // This prevents the settings save message from appearing when only unlocking
            if (isset($params['option']) && $params['option'] === 'manageUsers') {
                $successMessage = $this->processValuesAndSaveData($params);
                
                // After unlock/block action, redirect to prevent double submission and stale notifications
                if (isset($params['unblock_user']) || isset($params['block_user']) || isset($params['bulk_unblock_users'])) {
                    // Check if this is an AJAX request
                    $isAjax = $this->getRequest()->isAjax() || 
                              $this->getRequest()->getHeader('X-Requested-With') === 'XMLHttpRequest' ||
                              isset($params['isAjax']);
                    
                    if ($isAjax) {
                        // For AJAX requests, return JSON so JavaScript can reload the page
                        // The messageManager message is already saved to session and will show after reload
                        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                        $resultJson->setData([
                            'success' => true,
                            'reload' => true
                        ]);
                        return $resultJson;
                    } else {
                        // Regular redirect for non-AJAX requests
                        $resultRedirect = $this->resultRedirectFactory->create();
                        $resultRedirect->setPath('mobruteforce/blockedusers/index');
                        return $resultRedirect;
                    }
                }
            }
            
            // Only check for settings save if it's NOT a blocked users action
            if ($this->isFormOptionBeingSaved($params) && (!isset($params['option']) || $params['option'] !== 'manageUsers')) {
                $this->processValuesAndSaveData($params);
                $this->bruteforceutility->flushCache();
                $this->messageManager->addSuccessMessage(BruteForceMessages::SETTINGS_SAVED);
                $this->bruteforceutility->reinitConfig();
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));
        return $resultPage;
    }

    protected function processValuesAndSaveData($params)
    {
        $successMessage = null;
        
        if (isset($params['option']) && $params['option'] === 'manageUsers') {
            
            // FREE FEATURES - Basic blocked users management
            // Handle bulk unblock (multiple accounts in one request)
            if (isset($params['bulk_unblock_users']) && is_array($params['bulk_unblock_users'])) {
                $accounts = $params['bulk_unblock_users'];
                $successCount = 0;
                $failedCount = 0;
                $connection = $this->resourceConnection->getConnection();
                
                foreach ($accounts as $accountData) {
                    $customerId = isset($accountData['customer_id']) ? (int)$accountData['customer_id'] : null;
                    $email = isset($accountData['email']) ? $accountData['email'] : null;
                    $userType = isset($accountData['user_type']) ? $accountData['user_type'] : 'customer';
                    
                    if ($customerId !== null) {
                        try {
                            // For admin accounts, need to handle NULL website properly
                            if ($userType === 'admin') {
                                // Admin accounts have website = NULL
                                $whereString = "customer_id = " . (int)$customerId . " AND user_type = 'admin'";
                                if ($email) {
                                    $whereString .= " AND email = " . $connection->quote($email);
                                }
                                $whereString .= " AND website IS NULL";
                                $connection->delete('mo_bruteforce_locked_accounts', $whereString);
                            } else {
                                // For customers, use array WHERE clause (website will be set)
                                $where = [
                                    'customer_id = ?' => $customerId,
                                    'user_type = ?' => $userType
                                ];
                                
                                // If email is provided, also filter by email
                                if ($email) {
                                    $where['email = ?'] = $email;
                                }
                                
                                $connection->delete('mo_bruteforce_locked_accounts', $where);
                            }
                            
                            $successCount++;
                            $this->logger->info("Locked account unlocked - Customer ID: {$customerId}, Email: {$email}, User Type: {$userType}");
                        } catch (\Exception $e) {
                            $failedCount++;
                            $this->logger->error("Failed to unlock account - Customer ID: {$customerId}, Email: {$email}, User Type: {$userType}, Error: " . $e->getMessage());
                        }
                    }
                }
                
                // Add success message for bulk operation
                if ($successCount > 0) {
                    if ($successCount === 1) {
                        $successMessage = __('1 account has been unblocked successfully.');
                        $this->messageManager->addSuccessMessage($successMessage);
                    } else {
                        $successMessage = __('%1 accounts have been unblocked successfully.', $successCount);
                        $this->messageManager->addSuccessMessage($successMessage);
                    }
                }
                if ($failedCount > 0) {
                    $this->messageManager->addErrorMessage(__('Failed to unblock %1 account(s).', $failedCount));
                }
            }
            // Handle single unblock (for backward compatibility)
            elseif (isset($params['unblock_user'])) {
                $customerId = isset($params['user_id']) ? (int)$params['user_id'] : null;
                $email = isset($params['email']) ? $params['email'] : null;
                $userType = isset($params['user_type']) ? $params['user_type'] : 'customer';
                
                if ($customerId !== null) {
                    try {
                        $connection = $this->resourceConnection->getConnection();
                        
                        // For admin accounts, need to handle NULL website properly
                        if ($userType === 'admin') {
                            // Admin accounts have website = NULL
                            $whereString = "customer_id = " . (int)$customerId . " AND user_type = 'admin'";
                            if ($email) {
                                $whereString .= " AND email = " . $connection->quote($email);
                            }
                            $whereString .= " AND website IS NULL";
                            $connection->delete('mo_bruteforce_locked_accounts', $whereString);
                        } else {
                            // For customers, use array WHERE clause (website will be set)
                            $where = [
                                'customer_id = ?' => $customerId,
                                'user_type = ?' => $userType
                            ];
                            
                            // If email is provided, also filter by email
                            if ($email) {
                                $where['email = ?'] = $email;
                            }
                            
                            // For customers, also filter by website if provided (to match exact record)
                            // Note: Customer accounts should have website set, but we'll delete all matching records
                            // to handle edge cases
                            $connection->delete('mo_bruteforce_locked_accounts', $where);
                        }
                        
                        $successMessage = __('Account has been unlocked successfully.');
                        $this->messageManager->addSuccessMessage($successMessage);
                        $this->logger->info("Locked account unlocked - Customer ID: {$customerId}, Email: {$email}, User Type: {$userType}");
                    } catch (\Exception $e) {
                        $this->messageManager->addErrorMessage(__('Failed to unlock account: %1', $e->getMessage()));
                        $this->logger->error("Failed to unlock account - Customer ID: {$customerId}, Email: {$email}, User Type: {$userType}, Error: " . $e->getMessage());
                    }
                } else {
                    $this->messageManager->addErrorMessage(__('Invalid customer ID.'));
                }
            }

            if (isset($params['block_user'])) {
                $userId = $params['user_id'];
                if ($this->bruteforceutility->blockUser($userId)) {
                    $this->messageManager->addSuccessMessage('User has been blocked successfully.');
                } else {
                    $this->messageManager->addErrorMessage('Failed to block user.');
                }
            }

            // PREMIUM FEATURES - One-click unblock
            if (isset($params['one_click_unblock_all'])) {
                if ($this->bruteforceutility->unblockAllUsers()) {
                    $this->messageManager->addSuccessMessage('All users have been unblocked successfully.');
                } else {
                    $this->messageManager->addErrorMessage('Failed to unblock all users.');
                }
            }

            if (isset($params['bulk_actions'])) {
                $action = $params['bulk_action'];
                $userIds = $params['selected_users'] ?? [];
                
                if ($action === 'unblock' && !empty($userIds)) {
                    $this->bruteforceutility->bulkUnblockUsers($userIds);
                    $this->messageManager->addSuccessMessage('Selected users have been unblocked.');
                } elseif ($action === 'block' && !empty($userIds)) {
                    $this->bruteforceutility->bulkBlockUsers($userIds);
                    $this->messageManager->addSuccessMessage('Selected users have been blocked.');
                }
            }
        }
    }

    /**
     * Check if user is allowed to access Blocked Users
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(BruteForceConstants::MODULE_DIR . BruteForceConstants::BLOCKED_USERS);
    }
}

