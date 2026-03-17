<?php

namespace MiniOrange\BruteForceProtection\Controller\Adminhtml\BruteForceSettings;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use MiniOrange\BruteForceProtection\Helper\BruteForceMessages;
use MiniOrange\BruteForceProtection\Controller\Actions\BaseAdminAction;
use Magento\Framework\View\Element\BlockFactory;

class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    protected $request;
    protected $resultFactory;
    protected $blockFactory;

    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MiniOrange\BruteForceProtection\Helper\BruteForceUtility $bruteforceutility,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Controller\ResultFactory $resultFactory,
        BlockFactory $blockFactory
    ) {
        $this->resultFactory = $resultFactory;
        $this->blockFactory = $blockFactory;
        parent::__construct($context, $resultPageFactory, $bruteforceutility, $messageManager, $logger);
        $this->request = $request;
    }

    public function execute()
    {
        try {
            // Get params - but for AJAX requests, prioritize POST data over GET (URL params)
            // This is important because URL might have old scope from previous redirects
            $params = $this->getRequest()->getParams();
            
            // For AJAX loadSettingsForScope, ensure scope comes from POST (what JavaScript sends)
            if (isset($params['option']) && $params['option'] === 'loadSettingsForScope') {
                // Override scope in params with POST value if it exists
                // POST has the correct new scope that JavaScript sends
                if ($this->getRequest()->isPost()) {
                    $postScope = $this->getRequest()->getPostValue('scope');
                    if ($postScope !== null) {
                        $params['scope'] = $postScope;
                        $this->logger->debug("Overriding params scope with POST scope: '{$postScope}'");
                    }
                }
                // This is an AJAX request to load settings - return JSON
                return $this->loadSettingsForScope($params);
            }
            
            
            
            if($this->isFormOptionBeingSaved($params)) // check if form options are being saved
            {
                // Save configurations based on type (admin/customer)
                $this->saveConfigurations($params);
                
                // Comprehensive cache flushing
                $this->bruteforceutility->flushCache();
                $this->bruteforceutility->reinitConfig();
                
                $this->messageManager->addSuccessMessage(BruteForceMessages::SETTINGS_SAVED);
                
                // Redirect to the same page - scope is always default (scope selection is disabled)
                $type = $this->getRequest()->getParam('type', 'admin');
                
                // Build redirect URL with type only (no scope parameter - always default)
                $redirectParams = ['type' => $type];
                
                $redirectUrl = $this->getUrl('mobruteforce/bruteforcesettings/index', $redirectParams);
                $resultRedirect = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_REDIRECT);
                $resultRedirect->setUrl($redirectUrl);
                // Use replace instead of redirect to avoid adding to browser history
                $resultRedirect->setHttpResponseCode(302);
                // Note: Magento's redirect doesn't support replace, so we'll handle it via JavaScript
                return $resultRedirect;
            }

        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }

        // generate page
        $resultPage = $this->resultPageFactory->create();
        
        // Set different page titles based on type parameter
        $type = $this->getRequest()->getParam('type', 'general');
        if ($type === 'customer') {
            $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));
        } elseif ($type === 'admin') {
            $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));
        } else {
        $resultPage->getConfig()->getTitle()->prepend(__(BruteForceConstants::MODULE_TITLE));
        }
        
        return $resultPage;
    }

    /**
     * Load settings for a specific scope (AJAX endpoint)
     *
     * @param array $params
     * @return \Magento\Framework\Controller\Result\Json
     */
    protected function loadSettingsForScope($params)
    {
        $this->logger->debug("loadSettingsForScope - Starting execution with params: " . json_encode($params));
        $type = $this->getRequest()->getParam('type', 'admin');
        
        // Get scope from params (from AJAX POST) - this is the NEW scope being selected
        $scopeValue = isset($params['scope']) ? $params['scope'] : $this->getRequest()->getParam('scope', '');
        
        $this->logger->debug("loadSettingsForScope - Received scopeValue: '{$scopeValue}', type: '{$type}'");
        $this->logger->debug("loadSettingsForScope - All params: " . json_encode($params));
        
        // Always use default scope - scope selection is disabled
        $websiteId = null;
        $storeId = null;
        $scope = 'default';
        $scopeId = 0;
        
        $this->logger->debug("loadSettingsForScope - Forced to DEFAULT scope (scope selection is disabled)");
        
        $this->logger->debug("loadSettingsForScope - Final values before calling block: websiteId={$websiteId}, storeId={$storeId}, scope={$scope}, scopeId={$scopeId}");
        
        // Get block instance to call settings method using dependency injection
        $block = $this->blockFactory->createBlock(\MiniOrange\BruteForceProtection\Block\BruteForce::class);
        
        if ($type === 'customer') {
            $settings = $block->getCustomerBruteForceSettings($websiteId, $storeId);
        } else {
            $settings = $block->getAdminBruteForceSettings($websiteId, $storeId);
        }
        
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_JSON)
            ->setData(['success' => true, 'settings' => $settings]);
    }

    /**
     * Handle auto-save for individual toggle changes
     *
     * @param array $params
     * @return void
     */
    protected function handleAutoSave($params)
    {
        $type = $this->getRequest()->getParam('type', 'admin');
        
        // Always use default scope - scope selection is disabled
        $scope = 'default';
        $scopeId = 0;
        
        // Handle individual toggle saves
        if (isset($params['email_notifications_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/email_notifications_enabled', (int)$params['email_notifications_enabled'], $scope, $scopeId);
        }
        
        if (isset($params['admin_alert_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/admin_alert_enabled', (int)$params['admin_alert_enabled'], $scope, $scopeId);
        }
        
        if (isset($params['forgot_password_email_notifications_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_email_notifications_enabled', (int)$params['forgot_password_email_notifications_enabled'], $scope, $scopeId);
        }
        
        if (isset($params['forgot_password_admin_alert_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_admin_alert_enabled', (int)$params['forgot_password_admin_alert_enabled'], $scope, $scopeId);
        }

        // Note: Customer Login Logs settings are now managed in the Customer Login Logs tab
        // This setting has been moved from BruteForce Settings to the Customer Login Logs page
        
        // Flush cache for immediate effect
        $this->bruteforceutility->flushCache();
    }

    /**
     * Save configuration settings to the database.
     *
     * @param array $params
     * @return void
     */
    protected function saveConfigurations($params)
    {
        // Get the type (admin/customer) for dynamic config paths
        $type = $this->getRequest()->getParam('type', 'admin');
        $isCustomer = ($type === 'customer');
        
        // All settings are always saved at default scope (global) - scope selection is disabled
        $scope = 'default';
        $scopeId = 0;
        
        // Force default scope for all settings (admin and customer)
        $this->logger->debug("Saving {$type} configuration at Default Config scope (global - scope selection is disabled)");
        
        // All setStoreConfig calls below use $scope and $scopeId to save at the correct level
        
        // Handle enabled checkbox (set to 0 if not present)
        if (isset($params['enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/enabled', (int)$params['enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/enabled', 0, $scope, $scopeId);
        }

        // Custom Threshold for Delay on Successive Logins
        // Use array_key_exists to ensure 0 values are saved
        if (array_key_exists('max_attempts_delay', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/max_attempts_delay', (int)$params['max_attempts_delay'], $scope, $scopeId);
        }

        if (array_key_exists('delay_seconds', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/delay_seconds', (int)$params['delay_seconds'], $scope, $scopeId);
        }

        // Account Lockout Settings
        if (array_key_exists('max_attempts_lockout', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/max_attempts_lockout', (int)$params['max_attempts_lockout'], $scope, $scopeId);
        }

        if (array_key_exists('lockout_duration_minutes', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/lockout_duration_minutes', (int)$params['lockout_duration_minutes'], $scope, $scopeId);
        }

        if (array_key_exists('max_attempts_permanent_lockout', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/max_attempts_permanent_lockout', (int)$params['max_attempts_permanent_lockout'], $scope, $scopeId);
        }

        // TwoFA Integration
        // COMMENTED OUT: TwoFA Invoking settings saving
        // if (isset($params['max_attempts_twofa'])) {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/max_attempts_twofa', (int)$params['max_attempts_twofa'], $scope, $scopeId);
        // }

        // // Handle only_invoke_twofa_on_failed_attempts checkbox (set to 0 if not present)
        // if (isset($params['only_invoke_twofa_on_failed_attempts'])) {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/only_invoke_twofa_on_failed_attempts', (int)$params['only_invoke_twofa_on_failed_attempts'], $scope, $scopeId);
        // } else {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/only_invoke_twofa_on_failed_attempts', 0, $scope, $scopeId);
        // }

        // Email Notifications
        // Handle email_notifications_enabled checkbox (set to 0 if not present)
        if (isset($params['email_notifications_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/email_notifications_enabled', (int)$params['email_notifications_enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/email_notifications_enabled', 0, $scope, $scopeId);
        }

        // Handle admin_alert_enabled checkbox (set to 0 if not present)
        if (isset($params['admin_alert_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/admin_alert_enabled', (int)$params['admin_alert_enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/admin_alert_enabled', 0, $scope, $scopeId);
        }

        // Email notification timing for both customer and admin
        if (isset($params['email_notification_timing'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/email_notification_timing', $params['email_notification_timing'], $scope, $scopeId);
        }

        // Customer email templates only for customer type
        if ($isCustomer) {
            if (isset($params['customer_email_template_temporary'])) {
                $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/customer_email_template_temporary', $params['customer_email_template_temporary'], $scope, $scopeId);
            }
            if (isset($params['customer_email_template_permanent'])) {
                $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/customer_email_template_permanent', $params['customer_email_template_permanent'], $scope, $scopeId);
            }
        }

        // Admin email templates only for admin type
        if (!$isCustomer) {
            if (isset($params['admin_email_template_temporary'])) {
                $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/admin_email_template_temporary', $params['admin_email_template_temporary'], $scope, $scopeId);
            }
            if (isset($params['admin_email_template_permanent'])) {
                $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/admin_email_template_permanent', $params['admin_email_template_permanent'], $scope, $scopeId);
            }
        }

        // COMMENTED OUT: Away Mode Settings (Admin Only) - Moved to separate file
        // if ($type === 'admin') {
        //     // Handle away_mode_enabled checkbox (set to 0 if not present)
        //     if (isset($params['away_mode_enabled'])) {
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_enabled', (int)$params['away_mode_enabled'], 'default', 0);
        //     } else {
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_enabled', 0, 'default', 0);
        //     }
        //
        //     if (isset($params['away_mode_from_time'])) {
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_from_time', $params['away_mode_from_time'], 'default', 0);
        //     }
        //
        //     if (isset($params['away_mode_to_time'])) {
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_to_time', $params['away_mode_to_time'], 'default', 0);
        //     }
        //
        //     // Handle days selection (array to comma-separated string)
        //     if (isset($params['away_mode_days']) && is_array($params['away_mode_days'])) {
        //         $daysString = implode(',', $params['away_mode_days']);
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_days', $daysString, 'default', 0);
        //     } elseif (isset($params['away_mode_days'])) {
        //         // If it's already a string (from form submission)
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_days', $params['away_mode_days'], 'default', 0);
        //     } else {
        //         // Clear days if not provided
        //         $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_days', '', 'default', 0);
        //     }
        // }

        // Forgot Password Protection Settings
        // Handle forgot_password_enabled checkbox (set to 0 if not present)
        if (isset($params['forgot_password_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_enabled', (int)$params['forgot_password_enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_enabled', 0, $scope, $scopeId);
        }

        // Forgot Password Custom Threshold for Delay
        // Use array_key_exists to ensure 0 values are saved
        if (array_key_exists('forgot_password_max_attempts_delay', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_max_attempts_delay', (int)$params['forgot_password_max_attempts_delay'], $scope, $scopeId);
        }

        if (array_key_exists('forgot_password_delay_seconds', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_delay_seconds', (int)$params['forgot_password_delay_seconds'], $scope, $scopeId);
        }

        // Forgot Password Account Lockout Settings
        if (array_key_exists('forgot_password_max_attempts_lockout', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_max_attempts_lockout', (int)$params['forgot_password_max_attempts_lockout'], $scope, $scopeId);
        }

        if (array_key_exists('forgot_password_lockout_duration_minutes', $params)) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_lockout_duration_minutes', (int)$params['forgot_password_lockout_duration_minutes'], $scope, $scopeId);
        }

        // COMMENTED OUT: Forgot Password TwoFA Integration
        // // Forgot Password TwoFA Integration
        // if (isset($params['forgot_password_max_attempts_twofa'])) {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_max_attempts_twofa', (int)$params['forgot_password_max_attempts_twofa'], $scope, $scopeId);
        // }

        // COMMENTED OUT: TwoFA Invoking settings for forgot password
        // // Handle forgot_password_invoke_twofa_logged_in checkbox (set to 0 if not present)
        // if (isset($params['forgot_password_invoke_twofa_logged_in'])) {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_invoke_twofa_logged_in', (int)$params['forgot_password_invoke_twofa_logged_in'], $scope, $scopeId);
        // } else {
        //     $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_invoke_twofa_logged_in', 0, $scope, $scopeId);
        // }

        // Forgot Password Email Notifications
        // Handle forgot_password_email_notifications_enabled checkbox (set to 0 if not present)
        if (isset($params['forgot_password_email_notifications_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_email_notifications_enabled', (int)$params['forgot_password_email_notifications_enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_email_notifications_enabled', 0, $scope, $scopeId);
        }

        // Handle forgot_password_admin_alert_enabled checkbox (set to 0 if not present)
        if (isset($params['forgot_password_admin_alert_enabled'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_admin_alert_enabled', (int)$params['forgot_password_admin_alert_enabled'], $scope, $scopeId);
        } else {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_admin_alert_enabled', 0, $scope, $scopeId);
        }

        // Admin email template only for admin type, not for customer
        if (!$isCustomer && isset($params['forgot_password_admin_email_template'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_admin_email_template', $params['forgot_password_admin_email_template'], $scope, $scopeId);
        }

        // Customer email template only for customer type
        if ($isCustomer && isset($params['forgot_password_customer_email_template'])) {
            $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/bruteforce/' . $type . '/forgot_password_customer_email_template', $params['forgot_password_customer_email_template'], $scope, $scopeId);
        }

        // CAPTCHA Settings (also use scope)
            if (isset($params['captcha_enabled'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::CAPTCHA_ENABLED, (int)$params['captcha_enabled'], $scope, $scopeId);
            }

            if (isset($params['captcha_type'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::CAPTCHA_TYPE, $params['captcha_type'], $scope, $scopeId);
            }

            if (isset($params['warning_emails'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::WARNING_EMAILS, (int)$params['warning_emails'], $scope, $scopeId);
            }

            // PREMIUM FEATURES - Save settings (also use scope)
            if (isset($params['customer_delay_enabled'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::CUSTOMER_DELAY_ENABLED, (int)$params['customer_delay_enabled'], $scope, $scopeId);
            }

            if (isset($params['one_click_unblock'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::ONE_CLICK_UNBLOCK, (int)$params['one_click_unblock'], $scope, $scopeId);
            }

            if (isset($params['per_store_configuration'])) {
                $this->bruteforceutility->setStoreConfig(BruteForceConstants::PER_STORE_CONFIG, (int)$params['per_store_configuration'], $scope, $scopeId);
            }

        // Note: Customer Login Logs settings are now managed in the Customer Login Logs tab
        // This setting has been moved from BruteForce Settings to the Customer Login Logs page
    }

    /**
     * Check if the user is allowed to perform this action.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(BruteForceConstants::MODULE_DIR . BruteForceConstants::BRUTEFORCE_SETTINGS);
    }

    /**
     * Check if form is being saved in the backend other just
     * show the page. Checks if the request parameter has
     * an option key. All our forms need to have a hidden option
     * key.
     *
     * @param params
     * @return bool
     */
    protected function isFormOptionBeingSaved($params)
    {
        // Check if the 'option' parameter is set and equals 'saveBruteForceSettings'
        return isset($params['option']) && $params['option'] === 'saveBruteForceSettings';
    }
}