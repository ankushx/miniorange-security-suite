<?php

namespace MiniOrange\BruteForceProtection\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;
use MiniOrange\BruteForceProtection\Helper\AESEncryption;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use MiniOrange\BruteForceProtection\Helper\Curl;

/**
 * BruteForce Utility Helper
 * Contains common utility methods for BruteForce Protection
 */
class BruteForceUtility
{
    protected $scopeConfig;
    protected $configWriter;
    protected $cacheManager;
    protected $authSession;
    protected $resource;
    protected $connection;
    protected $logger;
    protected $coreSession;
    protected $storeManager;
    protected $productMetadata;
    protected $dateTime;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        CacheManager $cacheManager,
        AuthSession $authSession,
        ResourceConnection $resource,
        LoggerInterface $logger,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        DateTime $dateTime
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->cacheManager = $cacheManager;
        $this->authSession = $authSession;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->logger = $logger;
        $this->coreSession = $coreSession;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->dateTime = $dateTime;
    }

    /**
     * Get current admin user
     * @return \Magento\User\Model\User|null
     */
    public function getCurrentAdminUser()
    {
        return $this->authSession->getUser();
    }

    /**
     * Set store configuration value
     * @param string $path
     * @param mixed $value
     * @param string $scope
     * @param int $scopeId
     */
    public function setStoreConfig($path, $value, $scope = 'default', $scopeId = 0)
    {
        $this->logger->debug("BruteForceUtility::setStoreConfig - Path: $path, Value: $value, Scope: $scope, ScopeId: $scopeId");
        $this->configWriter->save($path, $value, $scope, $scopeId);
        $this->logger->debug("BruteForceUtility::setStoreConfig - Value saved successfully");
        
        // Flush config cache immediately after saving to ensure changes are reflected
        $this->cacheManager->flush(['config']);
        $this->logger->debug("BruteForceUtility::setStoreConfig - Cache flushed");
    }

    /**
     * Get store configuration value (with inheritance - this is the effective value)
     * 
     * Magento's ScopeConfig automatically handles inheritance:
     * - For 'stores' scope: checks store → website → default
     * - For 'websites' scope: checks website → default
     * - For 'default' scope: returns default only
     * 
     * @param string $path
     * @param string $scope 'stores', 'websites', or 'default'
     * @param int $scopeId Store ID, Website ID, or 0 for default
     * @return mixed
     */
    public function getStoreConfig($path, $scope = 'default', $scopeId = 0)
    {
        // Magento's ScopeConfig automatically handles inheritance chain
        $value = $this->scopeConfig->getValue($path, $scope, $scopeId);
        
        // Log to show inheritance is working
        $this->logger->debug(
            "BruteForceUtility::getStoreConfig - Path: $path, " .
            "Value: " . ($value !== null ? $value : 'NULL') . ", " .
            "Scope: $scope, ScopeId: $scopeId " .
            "(inheritance handled automatically by Magento)"
        );
        
        return $value;
    }

    /**
     * Check if Away Mode is active and should block admin login
     * Uses Magento's configured timezone
     * 
     * @return bool True if admin login should be blocked, false otherwise
     */
    public function isAwayModeActive()
    {
        // Check if away mode is enabled
        $awayModeEnabled = $this->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_enabled', 'default', 0);
        if (!$awayModeEnabled) {
            return false; // Away mode is disabled, allow access
        }

        // Get away mode settings
        $fromTime = $this->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_from_time', 'default', 0) ?: '00:00:00';
        $toTime = $this->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_to_time', 'default', 0) ?: '23:59:59';
        $daysString = $this->getStoreConfig('miniorange/SecuritySuite/bruteforce/admin/away_mode_days', 'default', 0) ?: '';
        
        if (empty($daysString)) {
            return false; // No days selected, allow access
        }

        $selectedDays = explode(',', $daysString);
        if (empty($selectedDays)) {
            return false; // No days selected, allow access
        }

        // Get current time in Magento's configured timezone
        $timezone = $this->scopeConfig->getValue('general/locale/timezone', 'default', 0);
        $dateTime = new \DateTime('now', new \DateTimeZone($timezone ?: 'UTC'));
        
        // Get current day of week (lowercase, e.g., 'monday', 'tuesday')
        $currentDay = strtolower($dateTime->format('l'));
        
        // Check if current day is in selected days
        if (!in_array($currentDay, $selectedDays)) {
            return false; // Current day is not selected, allow access
        }

        // Get current time in HH:MM:SS format
        $currentTime = $dateTime->format('H:i:s');
        
        // Parse from and to times
        $fromTimeParts = explode(':', $fromTime);
        $toTimeParts = explode(':', $toTime);
        $currentTimeParts = explode(':', $currentTime);
        
        $fromSeconds = (int)$fromTimeParts[0] * 3600 + (int)($fromTimeParts[1] ?? 0) * 60 + (int)($fromTimeParts[2] ?? 0);
        $toSeconds = (int)$toTimeParts[0] * 3600 + (int)($toTimeParts[1] ?? 0) * 60 + (int)($toTimeParts[2] ?? 0);
        $currentSeconds = (int)$currentTimeParts[0] * 3600 + (int)($currentTimeParts[1] ?? 0) * 60 + (int)($currentTimeParts[2] ?? 0);
        
        // Handle time range that spans midnight (e.g., 22:00:00 to 02:00:00)
        if ($fromSeconds > $toSeconds) {
            // Time range spans midnight
            if ($currentSeconds >= $fromSeconds || $currentSeconds <= $toSeconds) {
                return true; // Block access
            }
        } else {
            // Normal time range (e.g., 01:00:00 to 03:00:00)
            if ($currentSeconds >= $fromSeconds && $currentSeconds <= $toSeconds) {
                return true; // Block access
            }
        }
        
        return false; // Current time is outside the blocked range, allow access
    }

    /**
     * Get store configuration value directly from database at specific scope (no inheritance)
     * This returns only the value set at that exact scope, or null if not set
     * @param string $path
     * @param string $scope
     * @param int $scopeId
     * @return mixed|null
     */
    public function getStoreConfigDirect($path, $scope = 'default', $scopeId = 0)
    {
        $tableName = $this->resource->getTableName('core_config_data');
        
        $select = $this->connection->select()
            ->from($tableName, ['value'])
            ->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId)
            ->limit(1);
        
        $value = $this->connection->fetchOne($select);
        
        $this->logger->debug("BruteForceUtility::getStoreConfigDirect - Path: $path, Value: " . ($value !== false ? $value : 'NULL') . ", Scope: $scope, ScopeId: $scopeId");
        
        return $value !== false ? $value : null;
    }

    /**
     * Flush cache
     */
    public function flushCache()
    {
        // Flush multiple cache types to ensure configuration changes are reflected
        $this->cacheManager->flush(['config', 'layout', 'block_html', 'collections', 'reflection', 'db_ddl', 'eav', 'config_integration', 'config_integration_api', 'full_page', 'translate', 'config_webservice_api']);
    }

    /**
     * Reinitialize configuration
     */
    public function reinitConfig()
    {
        $this->flushCache();
    }

    public function setSessionValue( $key, $value ){
        $sessionValueArray = $this->getCompleteSession();
        $sessionValueArray[ $key ] = $value;
        $this->coreSession->setMyTestValue( $sessionValueArray );
    }

    public function getSessionValue( $key ){
        $sessionValueArray = $this->getCompleteSession();
        return isset( $sessionValueArray[ $key ] ) ? $sessionValueArray[ $key ] : null ;
    }

    public function getCompleteSession() {
        $this->coreSession->start();
        $sessionValue = $this->coreSession->getMyTestValue();
        return $sessionValue !== null ? $sessionValue : array();
    }

    /**
     * Check if BruteForce protection is enabled
     * @return bool
     */
    public function isBruteForceEnabled()
    {
        return (bool)$this->getStoreConfig(BruteForceConstants::BRUTEFORCE_ENABLED);
    }

    /**
     * Get maximum login attempts
     * @return int
     */
    public function getMaxAttempts()
    {
        return (int)$this->getStoreConfig(BruteForceConstants::MAX_ATTEMPTS) ?: BruteForceConstants::DEFAULT_MAX_ATTEMPTS;
    }

    /**
     * Get lockout duration
     * @return int
     */
    public function getLockoutDuration()
    {
        return (int)$this->getStoreConfig(BruteForceConstants::LOCKOUT_DURATION) ?: BruteForceConstants::DEFAULT_LOCKOUT_DURATION;
    }

    /**
     * Check if admin delay is enabled
     * @return bool
     */
    public function isAdminDelayEnabled()
    {
        return (bool)$this->getStoreConfig(BruteForceConstants::ADMIN_DELAY_ENABLED);
    }

    /**
     * Get admin delay seconds
     * @return int
     */
    public function getAdminDelaySeconds()
    {
        return (int)$this->getStoreConfig(BruteForceConstants::ADMIN_DELAY_SECONDS) ?: BruteForceConstants::DEFAULT_DELAY_SECONDS;
    }

    /**
     * Check if customer delay is enabled
     * @return bool
     */
    public function isCustomerDelayEnabled()
    {
        return (bool)$this->getStoreConfig(BruteForceConstants::CUSTOMER_DELAY_ENABLED);
    }

    /**
     * Get customer delay seconds
     * @return int
     */
    public function getCustomerDelaySeconds()
    {
        return (int)$this->getStoreConfig(BruteForceConstants::CUSTOMER_DELAY_SECONDS) ?: BruteForceConstants::DEFAULT_DELAY_SECONDS;
    }

    /**
     * Check if CAPTCHA is enabled
     * @return bool
     */
    public function isCaptchaEnabled()
    {
        return (bool)$this->getStoreConfig(BruteForceConstants::CAPTCHA_ENABLED);
    }

    /**
     * Get CAPTCHA type
     * @return string
     */
    public function getCaptchaType()
    {
        return $this->getStoreConfig(BruteForceConstants::CAPTCHA_TYPE) ?: BruteForceConstants::CAPTCHA_TYPE_HCAPTCHA;
    }

    /**
     * Get CAPTCHA threshold
     * @return int
     */
    public function getCaptchaThreshold()
    {
        return (int)$this->getStoreConfig(BruteForceConstants::CAPTCHA_THRESHOLD) ?: BruteForceConstants::DEFAULT_CAPTCHA_THRESHOLD;
    }

    /**
     * Check if warning emails are enabled
     * @return bool
     */
    public function isWarningEmailsEnabled()
    {
        return (bool)$this->getStoreConfig(BruteForceConstants::WARNING_EMAILS);
    }

    /**
     * Get admin email for notifications
     * @return string
     */
    public function getAdminEmail()
    {
        return $this->getStoreConfig(BruteForceConstants::ADMIN_EMAIL) ?: $this->getCurrentAdminUser()->getEmail();
    }

    /**
     * Block a user
     * @param int $userId
     * @return bool
     */
    public function blockUser($userId)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $data = [
                'user_id' => $userId,
                'blocked_at' => date('Y-m-d H:i:s'),
                'blocked_by' => $this->getCurrentAdminUser()->getId(),
                'reason' => 'BruteForce protection'
            ];
            
            $this->connection->insertOnDuplicate($tableName, $data);
            $this->logger->info("User {$userId} blocked by BruteForce protection");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to block user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unblock a user
     * @param int $userId
     * @return bool
     */
    public function unblockUser($userId)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $this->connection->delete($tableName, ['user_id = ?' => $userId]);
            $this->logger->info("User {$userId} unblocked");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to unblock user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unblock all users
     * @return bool
     */
    public function unblockAllUsers()
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $this->connection->truncateTable($tableName);
            $this->logger->info("All users unblocked");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to unblock all users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get blocked users
     * @return array
     */
    public function getBlockedUsers()
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $select = $this->connection->select()
                ->from($tableName)
                ->order('blocked_at DESC');
            
            return $this->connection->fetchAll($select);
        } catch (\Exception $e) {
            $this->logger->error("Failed to get blocked users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user is blocked
     * @param int $userId
     * @return bool
     */
    public function isUserBlocked($userId)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $select = $this->connection->select()
                ->from($tableName, ['user_id'])
                ->where('user_id = ?', $userId)
                ->limit(1);
            
            return (bool)$this->connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->error("Failed to check if user {$userId} is blocked: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Record login attempt
     * @param string $email
     * @param string $ip
     * @param bool $success
     * @return bool
     */
    public function recordLoginAttempt($email, $ip, $success = false)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_LOGIN_ATTEMPTS);
            $data = [
                'email' => $email,
                'ip_address' => $ip,
                'success' => $success ? 1 : 0,
                'attempted_at' => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            
            $this->connection->insert($tableName, $data);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to record login attempt: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get failed login attempts count
     * @param string $email
     * @param string $ip
     * @param int $timeWindow
     * @return int
     */
    public function getFailedAttemptsCount($email, $ip, $timeWindow = 300)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_LOGIN_ATTEMPTS);
            $select = $this->connection->select()
                ->from($tableName, ['COUNT(*)'])
                ->where('email = ?', $email)
                ->where('ip_address = ?', $ip)
                ->where('success = ?', 0)
                ->where('attempted_at > ?', date('Y-m-d H:i:s', time() - $timeWindow));
            
            return (int)$this->connection->fetchOne($select);
        } catch (\Exception $e) {
            $this->logger->error("Failed to get failed attempts count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if premium license is active
     * @return bool
     */
    public function isPremiumLicense()
    {
        $licenseType = $this->getStoreConfig(BruteForceConstants::LICENSE_TYPE);
        return in_array($licenseType, [BruteForceConstants::LICENSE_PREMIUM, BruteForceConstants::LICENSE_ENTERPRISE]);
    }

    /**
     * Check if enterprise license is active
     * @return bool
     */
    public function isEnterpriseLicense()
    {
        $licenseType = $this->getStoreConfig(BruteForceConstants::LICENSE_TYPE);
        return $licenseType === BruteForceConstants::LICENSE_ENTERPRISE;
    }

    /**
     * Get license type
     * @return string
     */
    public function getLicenseType()
    {
        return $this->getStoreConfig(BruteForceConstants::LICENSE_TYPE) ?: BruteForceConstants::LICENSE_FREE;
    }

    /**
     * Check if first page visit
     * @param string $email
     * @param string $page
     * @return bool
     */
    public function isFirstPageVisit($email, $page)
    {
        // Implementation for tracking first page visits
        return true;
    }

    /**
     * Log debug message
     * @param string $message
     */
    public function log_debug($message)
    {
        $this->logger->debug($message);
    }

    /**
     * Bulk unblock users
     * @param array $userIds
     * @return bool
     */
    public function bulkUnblockUsers($userIds)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $this->connection->delete($tableName, ['user_id IN (?)' => $userIds]);
            $this->logger->info("Bulk unblocked users: " . implode(',', $userIds));
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to bulk unblock users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk block users
     * @param array $userIds
     * @return bool
     */
    public function bulkBlockUsers($userIds)
    {
        try {
            $tableName = $this->resource->getTableName(BruteForceConstants::TABLE_BLOCKED_USERS);
            $data = [];
            foreach ($userIds as $userId) {
                $data[] = [
                    'user_id' => $userId,
                    'blocked_at' => date('Y-m-d H:i:s'),
                    'blocked_by' => $this->getCurrentAdminUser()->getId(),
                    'reason' => 'Bulk block action'
                ];
            }
            
            $this->connection->insertMultiple($tableName, $data);
            $this->logger->info("Bulk blocked users: " . implode(',', $userIds));
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Failed to bulk block users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is already in the restriction list
     * @param int $userId Customer ID or Admin User ID
     * @param string $context 'admin', 'customer', 'forgot_password', 'admin_forgot_password', 'customer_delay', 'admin_delay', 'forgot_password_delay', or 'admin_forgot_password_delay'
     * @return bool
     */
    public function isUserInRestrictionList($userId, $context = 'customer')
    {
        try {
            $config = $this->getTempLockoutLimitConfig($context);
            $userIds = $config['user_ids'];
            
            // Check if user ID is already in list
            return in_array($userId, $userIds);
        } catch (\Exception $e) {
            $this->log_debug("Error checking if user in restriction list: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if temporary lockout can be applied (limit not reached)
     * @param int $userId Customer ID or Admin User ID
     * @param string $context 'admin', 'customer', or 'forgot_password'
     * @return bool
     */
    public function canApplyTemporaryLockout($userId, $context = 'customer')
    {
        try {
            $config = $this->getTempLockoutLimitConfig($context);
            $maxLimit = (int)$config['max_limit'];
            $userIds = $config['user_ids'];
            
            // If user ID already in list, allow lockout (same user can be locked multiple times)
            if (in_array($userId, $userIds)) {
                return true;
            }
            
            // Check if limit reached
            if (count($userIds) >= $maxLimit) {
                // Send tracking when limit is exceeded
                $limitType = "temp_lockout_{$context}";
                $this->sendLimitExceededTracking($limitType);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            $this->log_debug("Error checking temporary lockout limit: " . $e->getMessage());
            // On error, allow lockout (fail open)
            return true;
        }
    }

    /**
     * Check if delay can be applied (limit not reached)
     * @param int|null $userId Customer ID or Admin User ID (can be null)
     * @param string $context 'admin_delay', 'customer_delay', 'forgot_password_delay', or 'admin_forgot_password_delay'
     * @return bool Returns true if delay can be applied, false if limit exceeded
     */
    public function canApplyDelay($userId, $context = 'customer_delay')
    {
        try {
            $config = $this->getTempLockoutLimitConfig($context);
            $maxLimit = (int)$config['max_limit'];
            $userIds = $config['user_ids'];
            
            // If user ID is provided and already in list, allow delay (same user can have delay multiple times)
            if ($userId && in_array($userId, $userIds)) {
                return true;
            }
           
            // Check if limit reached
            if (count($userIds) >= $maxLimit) {
                // Send tracking when limit is exceeded
                $this->sendLimitExceededTracking($context);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            $this->log_debug("Error checking delay limit: " . $e->getMessage());
            // On error, allow delay (fail open)
            return true;
        }
    }

    /**
     * Check if delay limit is exceeded (without checking if user is in list)
     * This is useful when you need to check the limit status separately
     * @param string $context 'admin_delay', 'customer_delay', 'forgot_password_delay', or 'admin_forgot_password_delay'
     * @return bool Returns true if limit exceeded, false otherwise
     */
    public function isDelayLimitExceeded($context = 'customer_delay')
    {
        try {
            $config = $this->getTempLockoutLimitConfig($context);
            $maxLimit = (int)$config['max_limit'];
            $userIds = $config['user_ids'];
            
            $isExceeded = (count($userIds) >= $maxLimit);
            
            // Send tracking if limit is exceeded
            if ($isExceeded) {
                $this->sendLimitExceededTracking($context);
            }
            
            return $isExceeded;
        } catch (\Exception $e) {
            $this->log_debug("Error checking delay limit exceeded: " . $e->getMessage());
            // On error, assume limit not exceeded (fail open)
            return false;
        }
    }

    /**
     * Add user ID to temporary lockout list
     * @param int $userId Customer ID or Admin User ID
     * @param string $context 'admin', 'customer', or 'forgot_password'
     * @return void
     */
    public function addUserToTempLockoutList($userId, $context = 'customer')
    {
        try {
            $config = $this->getTempLockoutLimitConfig($context);
            $userIds = $config['user_ids'];
            
            // Add user ID if not already in list
            if (!in_array($userId, $userIds)) {
                $userIds[] = $userId;
                $config['user_ids'] = $userIds;
                $this->saveTempLockoutLimitConfig($config, $context);
            }
        } catch (\Exception $e) {
            $this->log_debug("Error adding user to temporary lockout list: " . $e->getMessage());
        }
    }

    /**
     * Get temporary lockout limit configuration from core_config_data
     * @param string $context 'admin', 'customer', 'forgot_password', 'admin_forgot_password', 'customer_delay', 'admin_delay', 'forgot_password_delay', or 'admin_forgot_password_delay'
     * @return array
     */
    public function getTempLockoutLimitConfig($context = 'customer')
    {
        try {
            $defaultMaxLimit = 10;
            $defaultToken = 'E7XIXCVVUOYAIA2';
            
            // Determine config path based on context
            $userIdsPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/customer_ids_encrypted';
            $limitPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/max_limit_encrypted';
            $tokenPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/encryption_token';
            
            if ($context === 'admin') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_temp_lockout/user_ids_encrypted';
            } elseif ($context === 'admin_forgot_password') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_temp_lockout/forgot_user_ids';
            } elseif ($context === 'forgot_password') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/forgot_customer_ids';
            } elseif ($context === 'customer_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/delay/customer_ids_encrypted';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'admin_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_delay/user_ids_encrypted';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'forgot_password_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/delay/forgot_customer_ids';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'admin_forgot_password_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_delay/forgot_user_ids';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            }
            
            $encryptedLimit = $this->getStoreConfig($limitPath, 'default', 0);
            $encryptedUserIds = $this->getStoreConfig($userIdsPath, 'default', 0);
            $token = $this->getStoreConfig($tokenPath, 'default', 0);
            if (empty($token)) {
                $token = $defaultToken;
                $this->setStoreConfig($tokenPath, $defaultToken, 'default', 0);
            }
            
            $maxLimit = $defaultMaxLimit;
            if ($encryptedLimit !== null && $encryptedLimit !== '' && !empty(trim((string)$encryptedLimit))) {
                $decryptedLimit = AESEncryption::decrypt_data($encryptedLimit, $token);
                $decryptedLimitInt = (int)$decryptedLimit;
                if ($decryptedLimitInt > 0) {
                    $maxLimit = $decryptedLimitInt;
                }
            }
            
            $userIds = [];
            if ($encryptedUserIds !== null && $encryptedUserIds !== '' && !empty(trim((string)$encryptedUserIds))) {
                $decryptedIds = AESEncryption::decrypt_data($encryptedUserIds, $token);
                $userIds = json_decode($decryptedIds, true);
                if (!is_array($userIds)) {
                    $userIds = [];
                }
            }
            
            return [
                'max_limit' => $maxLimit,
                'user_ids' => $userIds,
                'token' => $token
            ];
        } catch (\Exception $e) {
            $this->log_debug("Error getting temporary lockout limit config: " . $e->getMessage());
            return [
                'max_limit' => 10,
                'user_ids' => [],
                'token' => 'E7XIXCVVUOYAIA2'
            ];
        }
    }

    /**
     * Save temporary lockout limit configuration to core_config_data
     * @param array $config
     * @param string $context 'admin', 'customer', 'forgot_password', 'admin_forgot_password', 'customer_delay', 'admin_delay', 'forgot_password_delay', or 'admin_forgot_password_delay'
     * @return void
     */
    public function saveTempLockoutLimitConfig($config, $context = 'customer')
    {
        try {
            $maxLimit = $config['max_limit'] ?? 10;
            $userIds = $config['user_ids'] ?? [];
            $token = $config['token'] ?? 'E7XIXCVVUOYAIA2';
            
            // Determine config path based on context
            $userIdsPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/customer_ids_encrypted';
            $limitPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/max_limit_encrypted';
            $tokenPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/encryption_token';
            
            if ($context === 'admin') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_temp_lockout/user_ids_encrypted';
            } elseif ($context === 'admin_forgot_password') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_temp_lockout/forgot_user_ids';
            } elseif ($context === 'forgot_password') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/temp_lockout/forgot_customer_ids';
            } elseif ($context === 'customer_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/delay/customer_ids_encrypted';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'admin_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_delay/user_ids_encrypted';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'forgot_password_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/delay/forgot_customer_ids';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            } elseif ($context === 'admin_forgot_password_delay') {
                $userIdsPath = 'miniorange/SecuritySuite/bruteforce/admin_delay/forgot_user_ids';
                $limitPath = 'miniorange/SecuritySuite/bruteforce/delay/max_limit_encrypted';
                $tokenPath = 'miniorange/SecuritySuite/bruteforce/delay/encryption_token';
            }
            
            $encryptedLimit = AESEncryption::encrypt_data((string)$maxLimit, $token);
            $userIdsJson = json_encode($userIds);
            $encryptedUserIds = AESEncryption::encrypt_data($userIdsJson, $token);
            
            $this->setStoreConfig($limitPath, $encryptedLimit, 'default', 0);
            $this->setStoreConfig($userIdsPath, $encryptedUserIds, 'default', 0);
            $this->setStoreConfig($tokenPath, $token, 'default', 0);
        } catch (\Exception $e) {
            $this->log_debug("Error saving temporary lockout limit config: " . $e->getMessage());
        }
    }

    /**
     * Check if notification can be sent (global count limit not reached)
     * @param string $type 'admin_alert' or 'email'
     * @param string $context 'admin' or 'customer'
     * @param string $action 'login' or 'forgot'
     * @return bool
     */
    public function canSendNotification($type, $context, $action)
    {
        try {
            $config = $this->getNotificationLimitConfig($type, $context, $action);
            $maxLimit = (int)$config['max_limit'];
            $count = (int)$config['count'];
            
            if ($count >= $maxLimit) {
                // Send tracking when limit is exceeded
                $limitType = "{$type}_{$context}_{$action}";
                $this->sendLimitExceededTracking($limitType);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            $this->log_debug("Error checking {$context}_{$type}_{$action} limit: " . $e->getMessage());
            // On error, allow notification (fail open)
            return true;
        }
    }

    /**
     * Increment global notification count
     * @param string $type 'admin_alert' or 'email'
     * @param string $context 'admin' or 'customer'
     * @param string $action 'login' or 'forgot'
     * @return void
     */
    public function incrementNotificationCount($type, $context, $action)
    {
        try {
            $config = $this->getNotificationLimitConfig($type, $context, $action);
            $count = (int)$config['count'];
            $count++;
            $config['count'] = $count;
            $this->saveNotificationLimitConfig($type, $context, $action, $config);
        } catch (\Exception $e) {
            $this->log_debug("Error incrementing {$context}_{$type}_{$action} count: " . $e->getMessage());
        }
    }

    /**
     * Get notification limit configuration from core_config_data
     * @param string $type 'admin_alert' or 'email'
     * @param string $context 'admin' or 'customer'
     * @param string $action 'login' or 'forgot'
     * @return array
     */
    public function getNotificationLimitConfig($type, $context, $action)
    {
        try {
            $defaultMaxLimit = 10;
            $defaultToken = 'E7XIXCVVUOYAIA2';
            
            // Build config path: bruteforce/{context}_{type}_{action}/...
            $configPath = "bruteforce/{$context}_{$type}_{$action}";
            
            $encryptedLimit = $this->getStoreConfig("{$configPath}/max_limit_encrypted", 'default', 0);
            $encryptedCount = $this->getStoreConfig("{$configPath}/count_encrypted", 'default', 0);
            $token = $this->getStoreConfig('miniorange/SecuritySuite/bruteforce/temp_lockout/encryption_token', 'default', 0);
            if (!$token) {
                $token = $defaultToken;
            }
            
            $maxLimit = $defaultMaxLimit;
            if ($encryptedLimit) {
                $decryptedLimit = AESEncryption::decrypt_data($encryptedLimit, $token);
                $maxLimit = (int)$decryptedLimit ?: $defaultMaxLimit;
            }
            
            $count = 0;
            if ($encryptedCount) {
                $decryptedCount = AESEncryption::decrypt_data($encryptedCount, $token);
                $count = (int)$decryptedCount ?: 0;
            }
            
            return [
                'max_limit' => $maxLimit,
                'count' => $count,
                'token' => $token
            ];
        } catch (\Exception $e) {
            $this->log_debug("Error getting {$context}_{$type}_{$action} limit config: " . $e->getMessage());
            return [
                'max_limit' => 10,
                'count' => 0,
                'token' => 'E7XIXCVVUOYAIA2'
            ];
        }
    }

    /**
     * Save notification limit configuration to core_config_data
     * @param string $type 'admin_alert' or 'email'
     * @param string $context 'admin' or 'customer'
     * @param string $action 'login' or 'forgot'
     * @param array $config
     * @return void
     */
    public function saveNotificationLimitConfig($type, $context, $action, $config)
    {
        try {
            $maxLimit = $config['max_limit'] ?? 10;
            $count = $config['count'] ?? 0;
            $token = $config['token'] ?? 'E7XIXCVVUOYAIA2';
            
            // Build config path: bruteforce/{context}_{type}_{action}/...
            $configPath = "bruteforce/{$context}_{$type}_{$action}";
            
            $encryptedLimit = AESEncryption::encrypt_data((string)$maxLimit, $token);
            $encryptedCount = AESEncryption::encrypt_data((string)$count, $token);
            
            $this->setStoreConfig("{$configPath}/max_limit_encrypted", $encryptedLimit, 'default', 0);
            $this->setStoreConfig("{$configPath}/count_encrypted", $encryptedCount, 'default', 0);
        } catch (\Exception $e) {
            $this->log_debug("Error saving {$context}_{$type}_{$action} limit config: " . $e->getMessage());
        }
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
     * Get Magento edition (Community, Enterprise, etc.)
     * @return string
     */
    public function getEdition(){
        return $this->productMetadata->getEdition() == 'Community' ? 'Magento Open Source':'Adobe Commerce Enterprise/Cloud';
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
     * Send tracking curl when any limit is exceeded
     * This will only be sent once when the first limit is exceeded
     * @param string $limitType The type of limit exceeded (e.g., "email_customer_login", "temp_lockout_customer", "customer_delay")
     * @return void
     */
    protected function sendLimitExceededTracking($limitType)
    {
        try {
             // Check if limit exceeded tracking has already been sent
            $limitExceededSent = $this->getStoreConfig('miniorange/SecuritySuite/limit_exceeded_sent', 'default', 0);
            
            // If already sent (flag is 1), don't send again
            if ($limitExceededSent == 1) {
                return;
            }
            
            // Get admin email
            // First try to get from current admin session (if available)
            $adminUser = $this->getCurrentAdminUser();
            $adminEmail = '';
            
            if ($adminUser) {
                // Admin session exists - get email from session
                if (is_array($adminUser)) {
                    $adminEmail = $adminUser['email'] ?? '';
                } else {
                    $adminEmail = $adminUser->getEmail() ?? '';
                }
                
                // Update stored admin email in DB (so it stays current)
                if ($adminEmail) {
                    $this->setStoreConfig('miniorange/SecuritySuite/admin_email', $adminEmail, 'default', 0);
                }
            }
            
            // If no admin session, try to get stored admin email from DB
            if (empty($adminEmail)) {
                $adminEmail = $this->getStoreConfig('miniorange/SecuritySuite/admin_email', 'default', 0);
            }
            
            // If still no email, try to get from system configuration as fallback
            if (empty($adminEmail)) {
                $adminEmail = $this->getStoreConfig('trans_email/ident_general/email', 'default', 0);
                if (empty($adminEmail)) {
                    $adminEmail = $this->getStoreConfig('trans_email/ident_sales/email', 'default', 0);
                }
            }
            
            // Final fallback - empty string if nothing found
            if (empty($adminEmail)) {
                $adminEmail = '';
            }
            
            // Get domain (extract from base URL)
            $domain = $this->getBaseUrl();
            
            // Get environment details
            $environmentName = $this->getEdition();
            $environmentVersion = $this->productMetadata->getVersion();
            
            // Get timestamp (check if data_added is set, if yes use existing timestamp, else generate new)
            $dataAdded = $this->getStoreConfig('miniorange/SecuritySuite/data_added', 'default', 0);
            $timeStamp = $this->getStoreConfig('miniorange/SecuritySuite/timestamp', 'default', 0);
            
            if (!$timeStamp || $dataAdded == 0) {
                // Generate new timestamp (current time in milliseconds)
                $timeStamp = round(microtime(true) * 1000);
                $timeStamp = number_format($timeStamp, 0, '', '');
                // Save timestamp to DB
                $this->setStoreConfig('miniorange/SecuritySuite/timestamp', $timeStamp, 'default', 0);
            }
            
            // Get current date
            $freeInstalledDate = $this->getCurrentDate();
            
            // Get active tab (default to empty since this is called from backend logic)
            $activeTab = '';
            
            // Convert array to JSON string for sending in $other parameter
            $limit = "Yes";
            
            // Send tracking with complete array of exceeded limits in $other parameter
            Curl::submit_to_magento_team(
                $timeStamp,
                $adminEmail,
                $domain,
                '', // miniorangeAccountEmail
                $activeTab,
                $environmentName,
                $environmentVersion,
                $freeInstalledDate,
                $limit // Send complete array of exceeded limits in $other parameter
            );
            
            // Set flag to 1 to indicate tracking has been sent (so it won't be sent again)
            $this->setStoreConfig('miniorange/SecuritySuite/limit_exceeded_sent', 1, 'default', 0);
        } catch (\Exception $e) {
            $this->log_debug("Error sending limit exceeded tracking: " . $e->getMessage());
        }
    }
}
