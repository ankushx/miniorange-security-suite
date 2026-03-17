<?php

namespace MiniOrange\BruteForceProtection\Block;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory as StoreCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use Magento\Framework\Module\Manager as ModuleManager;
use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Magento\Framework\App\ProductMetadataInterface;

/**
 * This class is used to denote our admin block for all our
 * backend templates. This class has certain common
 * functions which can be called from our admin template pages.
 */
class BruteForce extends Template
{
    protected $bruteforceutility;
    protected $request;
    protected $connection;
    protected $storeManager;
    protected $websiteCollectionFactory;
    protected $storeCollectionFactory;
    protected $formKey;
    protected $logger;
    protected $moduleManager;
    protected $productMetadata;

    public function __construct(
        Context $context,
        BruteForceUtility $bruteforceutility,
        RequestInterface $request,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,
        CollectionFactory $websiteCollectionFactory,
        StoreCollectionFactory $storeCollectionFactory,
        FormKey $formKey,
        LoggerInterface $logger,
        ModuleManager $moduleManager,
        ProductMetadataInterface $productMetadata,
        array $data = []
    ) {
        $this->bruteforceutility = $bruteforceutility;
        $this->request = $request;
        $this->connection = $resource->getConnection();
        $this->storeManager = $storeManager;
        $this->websiteCollectionFactory = $websiteCollectionFactory;
        $this->storeCollectionFactory = $storeCollectionFactory;
        $this->formKey = $formKey;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->productMetadata = $productMetadata;
        parent::__construct($context, $data);
    }

    /**
     * Get current admin user
     * @return \Magento\User\Model\User|null
     */
    public function getCurrentAdminUser()
    {
        return $this->bruteforceutility->getCurrentAdminUser();
    }

    /**
     * Get current active tab
     * @return string
     */
    public function getCurrentActiveTab()
    {
        $request = $this->request;
        $controller = $request->getControllerName();
        $action = $request->getActionName();
        
        $tabMap = [
            'bruteforcesettings' => 'bruteforce_settings',
            'customerloginlogs' => 'customer_login_logs',
            'blockedusers' => 'blocked_users',
            'upgrade' => 'upgrade',
            'account' => 'account'
        ];
        
        return $tabMap[$controller] ?? 'bruteforce_settings';
    }

    /**
     * Get extension page URL
     * @param string $page
     * @return string
     */
    public function getExtensionPageUrl($page)
    {
        $baseUrl = $this->getUrl('mobruteforce/' . $page . '/index');
        return $baseUrl;
    }

    /**
     * Get form key
     * @return string
     */
    public function getBlockHtml($blockName)
    {
        if ($blockName === 'formkey') {
            return '<input name="form_key" type="hidden" value="' . $this->getFormKey() . '" />';
        }
        return '';
    }

    /**
     * Get form key
     * @return string
     */
    public function getFormKey()
    {
        $formKey = $this->formKey->getFormKey();
        return $formKey;
    }

    /**
     * Check if account is verified
     * @return bool
     */
    public function checkaccountVerified()
    {
        // Implementation for account verification check
        return true;
    }

    /**
     * Check if license is verified
     * @return bool
     */
    public function isLicenseKeyVerified()
    {
        $licenseType = $this->bruteforceutility->getLicenseType();
        return $licenseType !== BruteForceConstants::LICENSE_FREE;
    }

    /**
     * Check if trial is activated
     * @return bool
     */
    public function isTrialActivated()
    {
        // Implementation for trial check
        return false;
    }

    /**
     * Check if trial is expired
     * @return bool
     */
    public function isTrialExpired()
    {
        // Implementation for trial expiry check
        return false;
    }

    /**
     * Get customer email
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/account/customer_email') ?: 'admin@example.com';
    }

    /**
     * Get customer key
     * @return string
     */
    public function getCustomerKey()
    {
        return $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/account/customer_key') ?: 'CUST123';
    }

    /**
     * Get BruteForce settings for customer
     * @param int|null $websiteId
     * @param int|null $storeId
     * @return array
     */
    public function getCustomerBruteForceSettings($websiteId = null, $storeId = null)
    {
        // Determine scope and scopeId based on Magento's hierarchy
        // Priority: Store View > Website > Default Config
        $scope = 'default';
        $scopeId = 0;
        
        if ($storeId) {
            // Store View scope - will inherit from Website → Default if not set at store level
            $scope = 'stores';
            $scopeId = $storeId;
        } elseif ($websiteId) {
            // Website scope - will inherit from Default if not set at website level
            $scope = 'websites';
            $scopeId = $websiteId;
        }
        // else: Default Config scope - no inheritance, applies to all
        
        // Debug: Log which scope is being used
        $this->logger->debug("getCustomerBruteForceSettings - storeId: {$storeId}, websiteId: {$websiteId}, scope: {$scope}, scopeId: {$scopeId}");

        $getValue = function($path, $default = null) use ($scope, $scopeId) {
            $value = $this->bruteforceutility->getStoreConfig($path, $scope, $scopeId);
    
            if ($value === null || $value === false || $value === '') {
                return $default;
            }
            return $value;
        };
        
        return [
            'enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/enabled', 0),
            'max_attempts_delay' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay', 3),
            'delay_seconds' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/delay_seconds', 30),
            'max_attempts_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/max_attempts_lockout', 5),
            'lockout_duration_minutes' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/lockout_duration_minutes', 30),
            'max_attempts_permanent_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/max_attempts_permanent_lockout', 10),
            'email_notifications_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/email_notifications_enabled', 0),
            'email_notification_timing' => $getValue('miniorange/SecuritySuite/bruteforce/customer/email_notification_timing', 'temporary'),
            'admin_alert_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/admin_alert_enabled', 0),
            'customer_email_template_temporary' => $getValue('miniorange/SecuritySuite/bruteforce/customer/customer_email_template_temporary', ''),
            'customer_email_template_permanent' => $getValue('miniorange/SecuritySuite/bruteforce/customer/customer_email_template_permanent', ''),
            // Forgot Password Settings
            'forgot_password_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_enabled', 0),
            'forgot_password_max_attempts_delay' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay', 3),
            'forgot_password_delay_seconds' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_delay_seconds', 30),
            'forgot_password_max_attempts_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout', 5),
            'forgot_password_lockout_duration_minutes' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_lockout_duration_minutes', 30),
            'forgot_password_max_attempts_permanent_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_permanent_lockout', 10),
            'forgot_password_email_notifications_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_email_notifications_enabled', 0),
            'forgot_password_admin_alert_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_admin_alert_enabled', 0),
            'forgot_password_customer_email_template' => $getValue('miniorange/SecuritySuite/bruteforce/customer/forgot_password_customer_email_template', '')
        ];
    }

    /**
     * Get BruteForce settings for admin
     * @param int|null $websiteId (Admin settings are typically global, but keeping for consistency)
     * @param int|null $storeId
     * @return array
     */
    public function getAdminBruteForceSettings()
    {
        
        $getValue = function($path, $default = null) {
            $value = $this->bruteforceutility->getStoreConfig($path);
            if ($value === null || $value === false || $value === '') {
                return $default;
            }
            return $value;
        };
      
        return [
            'enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/enabled', 0),
            'max_attempts_delay' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay', 3),
            'delay_seconds' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/delay_seconds', 30),
            'max_attempts_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/max_attempts_lockout', 5),
            'lockout_duration_minutes' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/lockout_duration_minutes', 30),
            'max_attempts_permanent_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/max_attempts_permanent_lockout', 10),
            'email_notifications_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/email_notifications_enabled', 0),
            'email_notification_timing' => $getValue('miniorange/SecuritySuite/bruteforce/admin/email_notification_timing', 'temporary'),
            'admin_alert_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/admin_alert_enabled', 0),
            'admin_email_template_temporary' => $getValue('miniorange/SecuritySuite/bruteforce/admin/admin_email_template_temporary', ''),
            'admin_email_template_permanent' => $getValue('miniorange/SecuritySuite/bruteforce/admin/admin_email_template_permanent', ''),
            // Forgot Password Settings
            'forgot_password_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_enabled', 0),
            'forgot_password_max_attempts_delay' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay', 3),
            'forgot_password_delay_seconds' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_delay_seconds', 30),
            'forgot_password_max_attempts_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_lockout', 5),
            'forgot_password_lockout_duration_minutes' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_lockout_duration_minutes', 30),
            'forgot_password_max_attempts_permanent_lockout' => (int)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_permanent_lockout', 10),
            'forgot_password_email_notifications_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_email_notifications_enabled', 0),
            'forgot_password_admin_alert_enabled' => (bool)$getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_admin_alert_enabled', 0),
            'forgot_password_admin_email_template' => $getValue('miniorange/SecuritySuite/bruteforce/admin/forgot_password_admin_email_template', ''),
        ];
    }

    /**
     * Get stores for a specific website
     * @param int $websiteId
     * @return array
     */
    public function getStoresByWebsite($websiteId)
    {
        $storeCollection = $this->storeCollectionFactory->create();
        $storeCollection->addFieldToFilter('website_id', $websiteId);
        
        $result = [];
        foreach ($storeCollection as $store) {
            $result[] = [
                'id' => $store->getId(),
                'name' => $store->getName(),
                'code' => $store->getCode(),
                'website_id' => $store->getWebsiteId()
            ];
        }
        
        return $result;
    }

    /**
     * Get all stores
     * @return array
     */
    public function getAllStores()
    {
        $storeCollection = $this->storeCollectionFactory->create();
        
        $result = [];
        foreach ($storeCollection as $store) {
            try {
                $website = $store->getWebsite();
                $websiteName = $website ? $website->getName() : '';
            } catch (\Exception $e) {
                $websiteName = '';
            }
            
            $result[] = [
                'id' => $store->getId(),
                'name' => $store->getName(),
                'code' => $store->getCode(),
                'website_id' => $store->getWebsiteId(),
                'website_name' => $websiteName
            ];
        }
        
        return $result;
    }

    /**
     * Get hierarchical scope data for dropdown (like Magento System Configuration)
     * Structure: Default Config → Websites → Store Views (under each website)
     * @return array
     */
    public function getScopeOptions()
    {
        $options = [];
        
        // 1. Default Config option (applies to all)
        $options[] = [
            'value' => '',
            'label' => 'Default Config',
            'type' => 'default'
        ];
        
        // 2. Get all websites
        $websites = $this->websiteCollectionFactory->create();
        
        // 3. Get all stores and group by website
        $storeCollection = $this->storeCollectionFactory->create();
        $storesByWebsite = [];
        
        foreach ($storeCollection as $store) {
            $websiteId = $store->getWebsiteId();
            if (!isset($storesByWebsite[$websiteId])) {
                $storesByWebsite[$websiteId] = [];
            }
            $storesByWebsite[$websiteId][] = [
                'id' => $store->getId(),
                'name' => $store->getName(),
                'code' => $store->getCode(),
                'website_id' => $websiteId
            ];
        }
        
        // 4. Add websites with their store views
        foreach ($websites as $website) {
            $websiteId = $website->getId();
            
            // Website option (selectable)
            $options[] = [
                'value' => 'website_' . $websiteId,
                'label' => $website->getName(),
                'type' => 'website',
                'website_id' => $websiteId
            ];
            
            // Store Views under this website (indented, but selectable)
            if (isset($storesByWebsite[$websiteId]) && !empty($storesByWebsite[$websiteId])) {
                foreach ($storesByWebsite[$websiteId] as $store) {
                    $options[] = [
                        'value' => 'store_' . $store['id'],
                        'label' => '  └─ ' . $store['name'], // Indented with tree indicator
                        'type' => 'store',
                        'store_id' => $store['id'],
                        'website_id' => $websiteId,
                        'is_child' => true // Flag for CSS styling (indentation)
                    ];
                }
            }
        }
        
        return $options;
    }

    /**
     * Get website ID from store ID
     * @param int $storeId
     * @return int|null
     */
    public function getWebsiteIdByStoreId($storeId)
    {
        try {
            $store = $this->storeManager->getStore($storeId);
            return $store->getWebsiteId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get email templates from Magento (same as TwoFA)
     * @return array
     */
    public function getEmailTemplateList()
    {
        $query = $this->connection->select()
            ->from('email_template', ['template_code', 'template_id']);

        $fetchData = $this->connection->fetchAll($query);
        return $fetchData;
    }

    /**
     * Get available email templates (for backward compatibility)
     * @return array
     */
    public function getAvailableEmailTemplates()
    {
        $templates = $this->getEmailTemplateList();
        $result = [];
        
        foreach ($templates as $template) {
            $result[$template['template_id']] = $template['template_code'];
        }
        
        return $result;
    }
    
    /**
     * Get all locked accounts from database
     * @return array
     */
    public function getLockedAccounts()
    {
        try {
            $select = $this->connection->select()
                ->from(['main_table' => 'mo_bruteforce_locked_accounts'])
                ->order('main_table.updated_at DESC');
            
            $lockedAccounts = $this->connection->fetchAll($select);
            
            // Format the data for display
            $formattedAccounts = [];
            foreach ($lockedAccounts as $account) {
                $formattedAccounts[] = [
                    'id' => $account['id'],
                    'customer_id' => $account['customer_id'],
                    'email' => $account['email'],
                    'user_type' => $account['user_type'],
                    'lock_type' => $account['lock_type'],
                    'lock_until' => $account['lock_until'],
                    'failed_attempts' => $account['failed_attempts'],
                    'website' => $account['website'] ?? null,
                    'first_time_lockout' => $account['first_time_lockout'],
                    'sent_email' => isset($account['sent_email']) ? $account['sent_email'] : 0,
                    'updated_at' => $account['updated_at']
                ];
            }
            
            return $formattedAccounts;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching locked accounts: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Format lock type for display
     * @param string $lockType
     * @return string
     */
    public function formatLockType($lockType)
    {
        $types = [
            'none' => __('None'),
            'temp' => __('Temporary'),
            'temporary' => __('Temporary'),
            'permanent' => __('Permanent')
        ];
        
        return $types[$lockType] ?? ucfirst($lockType);
    }
    
    /**
     * Format user type for display
     * @param string $userType
     * @return string
     */
    public function formatUserType($userType)
    {
        return $userType === 'admin' ? __('Admin') : __('Customer');
    }
    
    /**
     * Get lockout status text
     * @param array $account
     * @return string
     */
    public function getLockoutStatus($account)
    {
        if ($account['lock_type'] === 'permanent') {
            return __('Permanently Locked');
        } elseif ($account['lock_type'] === 'temporary' || $account['lock_type'] === 'temp') {
            if ($account['lock_until']) {
                $lockUntil = strtotime($account['lock_until']);
                $now = time();
                if ($lockUntil > $now) {
                    $remainingMinutes = ceil(($lockUntil - $now) / 60);
                    return __('Temporarily Locked (expires in %1 minutes)', $remainingMinutes);
                } else {
                    return __('Lock Expired');
                }
            }
            return __('Temporarily Locked');
        }
        return __('Not Locked');
    }
    
    /**
     * Get URL for unlocking an account
     * @param int $customerId
     * @param string|null $email
     * @param string $userType
     * @return string
     */
    public function getUnlockUrl($customerId, $email = null, $userType = 'customer')
    {
        $params = [
            'option' => 'manageUsers',
            'unblock_user' => 1,
            'user_id' => $customerId,
            'user_type' => $userType
        ];
        
        if ($email) {
            $params['email'] = $email;
        }
        
        return $this->getUrl('mobruteforce/blockedusers/index', $params);
    }

    /**
     * Check if customer BruteForce is enabled
     * @return bool
     */
    public function isCustomerBruteForceEnabled()
    {
        return (bool)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer/enabled');
    }

    /**
     * Check if admin BruteForce is enabled
     * @return bool
     */
    public function isAdminBruteForceEnabled()
    {
        return (bool)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/enabled');
    }

    /**
     * Get base URL (domain)
     * @return string
     */
    public function getBaseUrl()
    {
        try {
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            // Extract domain from URL
            $parsedUrl = parse_url($baseUrl);
            $domain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            if (isset($parsedUrl['port'])) {
                $domain .= ':' . $parsedUrl['port'];
            }
            return $domain;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Check if first page visit
     * @param string $email
     * @param string $page
     * @return bool
     */
    public function isFirstPageVisit($email, $page)
    {
        return true;
    }

    /**
     * Get existing rules for customer
     * @return array
     */
    public function get_customerexistingRules()
    {
        return $this->getCustomerBruteForceSettings();
    }

    /**
     * Get existing rules for admin
     * @return array
     */
    public function get_existingRules()
    {
        return $this->getAdminBruteForceSettings();
    }

    public function isTwoFaAvailable(): bool
    {
        return $this->moduleManager->isEnabled('MiniOrange_TwoFA');
    }

    /**
     * Check if customer login logs feature is enabled (global setting)
     * @return bool
     */
    public function isCustomerLoginLogsEnabled()
    {
        return (bool)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer_login_logs/enabled', 'default', 0);
    }

    /**
     * Get customer login logs retention days
     * @return int
     */
    public function getCustomerLoginLogsRetentionDays()
    {
        return (int)$this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/customer_login_logs/retention_days', 'default', 0) ?: 90;
    }

    /**
     * Check if customer login logs retention days has been explicitly configured
     * @return bool
     */
    public function isCustomerLoginLogsRetentionConfigured()
    {
        // Check if the value exists in database (not just using default)
        $value = $this->bruteforceutility->getStoreConfigDirect('miniorange/SecuritySuite/bruteforce/customer_login_logs/retention_days', 'default', 0);
        return $value !== null && $value !== false;
    }

    /**
     * Truncate string for display
     * @param string $string
     * @param int $length
     * @return string
     */
    public function truncateString($string, $length = 50)
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length) . '...';
    }

    /**
     * Get current module version
     * @return string
     */
    public function getCurrentVersion()
    {
        return BruteForceConstants::MODULE_VERSION;
    }

    /**
     * COMMENTED OUT: Get Away Mode settings for display - Moved to separate file
     * @return array|null
     */
    // public function getAwayModeSettings()
    // {
    //     $awayModeEnabled = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_enabled', 'default', 0);
    //     if (!$awayModeEnabled) {
    //         return null;
    //     }
    //
    //     $fromTime = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_from_time', 'default', 0) ?: '00:00:00';
    //     $toTime = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_to_time', 'default', 0) ?: '23:59:59';
    //     $days = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_days', 'default', 0) ?: '';
    //
    //     return [
    //         'from_time' => $fromTime,
    //         'to_time' => $toTime,
    //         'days' => $days
    //     ];
    // }

    /**
     * COMMENTED OUT: Check if Away Mode is currently active - Moved to separate file
     * @return bool
     */
    // public function isAwayModeActive()
    // {
    //     return $this->bruteforceutility->isAwayModeActive();
    // }

    /**
     * COMMENTED OUT: Override toHtml to check if Away Mode is active before rendering - Moved to separate file
     * This ensures the block only renders when Away Mode is actually active
     * 
     * @return string
     */
    // public function toHtml()
    // {
    //     // If this is the away mode block, check if it's actually active
    //     if ($this->getNameInLayout() === 'admin_login_away_mode') {
    //         if (!$this->isAwayModeActive()) {
    //             return ''; // Return empty string if not active - don't render anything
    //         }
    //     }
    //     
    //     return parent::toHtml();
    // }

    /**
     * Check if any free plan limit has been exceeded
     * @return bool
     */
    public function isAnyLimitExceeded()
    {
        // Check email notification limits
        $emailLimitCustomer = $this->checkNotificationLimit('email', 'customer', 'login');
        $emailLimitAdmin = $this->checkNotificationLimit('email', 'admin', 'login');
        $emailLimitForgot = $this->checkNotificationLimit('email', 'customer', 'forgot');
        $emailLimitAdminForgot = $this->checkNotificationLimit('email', 'admin', 'forgot');
        
        // Check admin alert limits
        $alertLimitCustomer = $this->checkNotificationLimit('admin_alert', 'customer', 'login');
        $alertLimitAdmin = $this->checkNotificationLimit('admin_alert', 'admin', 'login');
        $alertLimitForgot = $this->checkNotificationLimit('admin_alert', 'customer', 'forgot');
        $alertLimitAdminForgot = $this->checkNotificationLimit('admin_alert', 'admin', 'forgot');
        
        // Check delay limits (10 unique users)
        $delayLimitCustomer = $this->checkTempLockoutLimit('customer_delay');
        $delayLimitAdmin = $this->checkTempLockoutLimit('admin_delay');
        $delayLimitForgot = $this->checkTempLockoutLimit('forgot_password_delay');
        $delayLimitAdminForgot = $this->checkTempLockoutLimit('admin_forgot_password_delay');
        
        // Check temp lockout limits (10 unique users)
        $lockoutLimitCustomer = $this->checkTempLockoutLimit('customer');
        $lockoutLimitAdmin = $this->checkTempLockoutLimit('admin');
        $lockoutLimitForgot = $this->checkTempLockoutLimit('forgot_password');
        $lockoutLimitAdminForgot = $this->checkTempLockoutLimit('admin_forgot_password');
        
        // Return true if any limit is exceeded
        return $emailLimitCustomer || $emailLimitAdmin || $emailLimitForgot || $emailLimitAdminForgot ||
               $alertLimitCustomer || $alertLimitAdmin || $alertLimitForgot || $alertLimitAdminForgot ||
               $delayLimitCustomer || $delayLimitAdmin || $delayLimitForgot || $delayLimitAdminForgot ||
               $lockoutLimitCustomer || $lockoutLimitAdmin || $lockoutLimitForgot || $lockoutLimitAdminForgot;
    }

    /**
     * Check if notification limit is exceeded
     * @param string $type 'admin_alert' or 'email'
     * @param string $context 'admin' or 'customer'
     * @param string $action 'login' or 'forgot'
     * @return bool
     */
    protected function checkNotificationLimit($type, $context, $action)
    {
        try {
            $config = $this->bruteforceutility->getNotificationLimitConfig($type, $context, $action);
            $maxLimit = (int)$config['max_limit'];
            $count = (int)$config['count'];
            
            // Free plan limit is 10, so if count >= maxLimit and maxLimit <= 10, limit is exceeded
            return ($count >= $maxLimit && $maxLimit <= 10);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if temporary lockout/delay limit is exceeded
     * @param string $context
     * @return bool
     */
    protected function checkTempLockoutLimit($context)
    {
        try {
            $config = $this->bruteforceutility->getTempLockoutLimitConfig($context);
            $maxLimit = (int)$config['max_limit'];
            $userIds = $config['user_ids'] ?? [];
            
            // Free plan limit is 10 unique users, so if count >= maxLimit and maxLimit <= 10, limit is exceeded
            return (count($userIds) >= $maxLimit && $maxLimit <= 10);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if tracking data has been added (data_added flag)
     * @return int|null Returns 1 if data added, 0 if not, null if not set
     */
    public function checkDataAdded()
    {
        $dataAdded = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/data_added', 'default', 0);
        return $dataAdded !== null ? (int)$dataAdded : null;
    }

    /**
     * Set data_added flag to 1 after first tracking submission
     * @return void
     */
    public function dataAdded()
    {
        $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/data_added', 1, 'default', 0);
    }

    /**
     * Update stored admin email in DB (called every time admin visits to keep it current)
     * @param string $adminEmail
     * @return void
     */
    public function updateStoredAdminEmail($adminEmail)
    {
        $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/admin_email', $adminEmail, 'default', 0);
    }

    /**
     * Get timestamp for tracking
     * If data_added is 1, retrieve existing timestamp from DB
     * If data_added is 0 or null, generate new timestamp and save it
     * @return string
     */
    public function getTimeStamp()
    {
        $dataAdded = $this->checkDataAdded();
        
        if ($dataAdded == 1) {
            // Data already added, retrieve existing timestamp
            $timestamp = $this->bruteforceutility->getStoreConfig('miniorange/SecuritySuite/timestamp', 'default', 0);
            if ($timestamp) {
                return $timestamp;
            }
        }
        
        // Generate new timestamp (current time in milliseconds)
        $currentTimestampInMillis = round(microtime(true) * 1000);
        $currentTimestampInMillis = number_format($currentTimestampInMillis, 0, '', '');
        
        // Save timestamp to DB
        $this->bruteforceutility->setStoreConfig('miniorange/SecuritySuite/timestamp', $currentTimestampInMillis, 'default', 0);
        
        return $currentTimestampInMillis;
    }

    /**
     * Get current date in Y-m-d format
     * @return string
     */
    public function getCurrentDate(){
        $dateTimeZone = new \DateTimeZone('Asia/Calcutta'); 
        $dateTime = new \DateTime('now', $dateTimeZone);
        return $dateTime->format('n/j/Y, g:i:s a');
    }

    /**
     * Get Magento edition (Community, Enterprise, etc.)
     * @return string
     */
    public function getEdition(){
        return $this->productMetadata->getEdition() == 'Community' ? 'Magento Open Source':'Adobe Commerce Enterprise/Cloud';
    }

    /**
     * Get Magento product version
     * @return string
     */
    public function getProductVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Get current admin user as array with email
     * @return array
     */
    public function getCurrentAdminUserArray()
    {
        $user = $this->getCurrentAdminUser();
        if ($user) {
            return [
                'email' => $user->getEmail(),
                'username' => $user->getUserName(),
                'id' => $user->getId()
            ];
        }
        return ['email' => '', 'username' => '', 'id' => 0];
    }
}
