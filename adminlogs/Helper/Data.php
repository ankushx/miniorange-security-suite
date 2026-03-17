<?php
namespace MiniOrange\AdminLogs\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\HTTP\Header;
use Magento\Customer\Model\Session;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory as EmailCollectionFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\Cache\Manager as CacheManager; 
use Magento\Framework\HTTP\Client\Curl; 
use Magento\Framework\Stdlib\DateTime\DateTime;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use MiniOrange\AdminLogs\Helper\AESEncryption;
use MiniOrange\AdminLogs\Helper\Curl as MiniOrangeCurl; // Renamed to avoid conflict with Magento\Framework\HTTP\Client\Curl
use MiniOrange\AdminLogs\Helper\AdminLogsUtility;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;
use MiniOrange\AdminLogs\Model\UniqueAdminUserFactory;
use MiniOrange\AdminLogs\Model\ResourceModel\UniqueAdminUser\CollectionFactory as UniqueAdminUserCollectionFactory;

class Data extends AbstractHelper
{
    protected $remoteAddress;
    protected $httpHeader;
    protected $adminSession;
    protected $customerSession;
    protected $authSession;
    protected $coreSession;
    protected $emailCollectionFactory;
    protected $configWriter;
    protected $scopeConfig;
    protected $resourceConnection;
    protected $adminLogsUtility;
    protected $urlBuilder;
    protected $cacheManager; 
    protected $curlClient; 
    protected $userCollectionFactory;
    protected $dateTime;
    protected $uniqueAdminUserFactory;
    protected $uniqueAdminUserCollectionFactory;

    public function __construct(
        Context $context,
        RemoteAddress $remoteAddress,
        Header $httpHeader,
        \Magento\Backend\Model\Session $adminSession,
        Session $customerSession,
        \Magento\Backend\Model\Auth\Session $authSession,
        \Magento\Framework\Session\SessionManagerInterface $coreSession,
        EmailCollectionFactory $emailCollectionFactory,
        WriterInterface $configWriter,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        AdminLogsUtility $adminLogsUtility,
        UrlInterface $urlBuilder,
        CacheManager $cacheManager, 
        Curl $curlClient, 
        UserCollectionFactory $userCollectionFactory,
        DateTime $dateTime,
        UniqueAdminUserFactory $uniqueAdminUserFactory,
        UniqueAdminUserCollectionFactory $uniqueAdminUserCollectionFactory
    ) {
        $this->remoteAddress = $remoteAddress;
        $this->httpHeader = $httpHeader;
        $this->adminSession = $adminSession;
        $this->customerSession = $customerSession;
        $this->authSession = $authSession;
        $this->coreSession = $coreSession;
        $this->emailCollectionFactory = $emailCollectionFactory;
        $this->configWriter = $configWriter;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->adminLogsUtility = $adminLogsUtility;
        $this->urlBuilder = $urlBuilder;
        $this->cacheManager = $cacheManager; 
        $this->curlClient = $curlClient; 
        $this->userCollectionFactory = $userCollectionFactory;
        $this->dateTime = $dateTime;
        $this->uniqueAdminUserFactory = $uniqueAdminUserFactory;
        $this->uniqueAdminUserCollectionFactory = $uniqueAdminUserCollectionFactory;
        parent::__construct($context);
    }

    /**
     * Get the real client IP address, checking various proxy headers.
     *
     * @return string
     */
    public function getIpAddress()
    {
        // Check for real client IP through various proxy headers
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP and ensure it's not a private/reserved range
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to Magento's RemoteAddress
        $ip = $this->remoteAddress->getRemoteAddress();
        
        // If it's a Docker/internal IP, try to get from X-Forwarded-For
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $realIp = trim($ips[0]);
                if (filter_var($realIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $realIp;
                }
            }
        }
        
        return $ip;
    }

    /**
     * Get the HTTP User Agent string.
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->httpHeader->getHttpUserAgent();
    }

    /**
     * Get location (city, country) from an IP address using an external API.
     *
     * @param string $ip
     * @return string
     */
    public function getLocationFromIp($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local';
        }

        try {
            $url = 'http://ip-api.com/json/' . $ip;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($result, true);

            if ($data && isset($data['status']) && $data['status'] == 'success') {
                return $data['city'] . ', ' . $data['country'];
            }
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }
        
        return 'Unknown';
    }

    // --- Session Management ---

    public function getCompleteSession() {
        $this->coreSession->start();
        // Assuming 'getMyTestValue' is the custom key used by the module
        $sessionValue = $this->coreSession->getMyTestValue(); 
        return $sessionValue !== null ? $sessionValue : [];
    }

    public function getSessionValue( $key ){
        $sessionValueArray = $this->getCompleteSession();
        return isset( $sessionValueArray[ $key ] ) ? $sessionValueArray[ $key ] : null ;
    }

    public function setSessionValue( $key, $value ){
        $sessionValueArray = $this->getCompleteSession();
        $sessionValueArray[ $key ] = $value;
        $this->coreSession->setMyTestValue( $sessionValueArray );
    }

    /**
     * Set store configuration value
     *
     * @param string $path Configuration path
     * @param mixed $value Configuration value
     * @param string $scope Scope type (default: 'default')
     * @param int $scopeId Scope ID (default: 0)
     * @return void
     */
    public function setStoreConfig($path, $value, $scope = 'default', $scopeId = 0)
    {
        try {
            $this->configWriter->save($path, $value, $scope, $scopeId);
        } catch (\Exception $e) {
            $this->_logger->error('Error setting store config: ' . $e->getMessage());
        }
    }

    /**
     * Get store configuration value
     *
     * @param string $path Configuration path
     * @param string $scope Scope type (default: 'default')
     * @param int $scopeId Scope ID (default: 0)
     * @return mixed Configuration value or null if not found
     */
    public function getStoreConfig($path, $scope = 'default', $scopeId = 0)
    {
        try {
            return $this->scopeConfig->getValue($path, $scope, $scopeId);
        } catch (\Exception $e) {
            $this->_logger->error('Error getting store config: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a username exists in the unique users table.
     *
     * @param string $username
     * @return bool
     */
    public function isUserInUniqueList($username)
    {
        if (!$username) {
            return false;
        }
        $normalizedUsername = strtolower(trim($username));

        try {
            $collection = $this->uniqueAdminUserCollectionFactory->create();
            // Use case-insensitive comparison matching original behavior
            $collection->getSelect()->where('LOWER(TRIM(username)) = ?', $normalizedUsername);
            
            return $collection->getSize() > 0;

        } catch (\Exception $e) {
            $this->_logger->error(
                'Error checking if user is in unique list: ' . $e->getMessage()
            );
            return false;
        }
    }

    public function getAdminUsersLimitValue()
    {
        $encryptedLimit = $this->getStoreConfig(AdminLogsConstants::ADMIN_USERS_LIMIT);
        if ($encryptedLimit === null || $encryptedLimit === '') {
            $encryptedLimit = AESEncryption::encrypt_data('10', AdminLogsConstants::DEFAULT_API_KEY);
            $this->setStoreConfig(AdminLogsConstants::ADMIN_USERS_LIMIT, $encryptedLimit);
            $this->flushCache();
        }
        $encryptedLimit = $this->getStoreConfig(AdminLogsConstants::ADMIN_USERS_LIMIT);
        return (int)AESEncryption::decrypt_data($encryptedLimit, AdminLogsConstants::DEFAULT_API_KEY);
    }

    /**
     * Add a username to the unique users table.
     *
     * @param string $username
     * @return bool True if added successfully or already exists, false otherwise
     */
    public function addUserToUniqueList($username, $skipExistenceCheck = false)
    {
        if (!$username) {
            return false;
        }
        
        try {
            if (!$skipExistenceCheck && $this->isUserInUniqueList($username)) {
                return true; // Already exists
            }
            
            $uniqueAdminUser = $this->uniqueAdminUserFactory->create();
            $uniqueAdminUser->setUsername($username);
            $uniqueAdminUser->setCreatedAt($this->dateTime->gmtDate());
            $uniqueAdminUser->save();
            
            $this->_logger->info('Added user to unique list: ' . $username);
            return true;
        } catch (\Exception $e) {
            $this->_logger->error('Error adding user to unique list: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the count of existing records in miniorange_admin_login_logs table
     *
     * @return int
     */
    public function getTotalUniqueLoggedInUserCount()
    {
        try {
            $collection = $this->uniqueAdminUserCollectionFactory->create();
            $count = $collection->getSize();
            return (int)$count;
        } catch (\Exception $e) {
            $this->_logger->error('Error getting total unique logged-in user count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if a user can have their login/logout logged.
     * Returns true if:
     * 1. The user is already in the unique list, OR
     * 2. The unique user count is less than the limit (10) - user will be added automatically.
     *
     * @param int $userId
     * @param string $username Optional username (if not provided, will be fetched from userId)
     * @return bool
     */
    public function canUserBeLogged($userId, $username = null)
    {
        if (!$userId && !$username) {
            return false;
        }
        
        try {
            // Get username from user ID if not provided
            if (!$username && $userId) {
                $user = $this->userCollectionFactory->create()
                    ->addFieldToFilter('user_id', $userId)
                    ->getFirstItem();
                if ($user && $user->getId()) {
                    $username = $user->getUsername();
                } else {
                    return false;
                }
            }
            
            if (!$username) {
                return false;
            }
            
            if ($this->isUserInUniqueList($username)) {
                return true;
            }
            
                $adminUsersLimit = $this->getAdminUsersLimitValue();
                
                // Get current count from the simplified source of truth (the table)
                $adminUsersCount = $this->getTotalUniqueLoggedInUserCount();
                
                $remaining = $adminUsersLimit - $adminUsersCount;
                
                if ($remaining <= 0) {
                    return false; 
                }
            
            // Limit is not reached, add the new user
            return $this->addUserToUniqueList($username, true);
        } catch (\Exception $e) {
            $this->_logger->error('Error checking if user can be logged: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a user ID is within the first 10 unique logged-in users.
     * Convenience wrapper for canUserBeLogged().
     *
     * @param int $userId
     * @return bool
     */
    public function isUserWithinFirstTen($userId)
    {
        return $this->canUserBeLogged($userId);
    }

    /**
     * Get user IDs for the first N unique logged-in users from mo_unique_admin_users table.
     *
     * @param int $limit Number of users to retrieve
     * @return array Array of user IDs
     */
    public function getAllowedUserIds($limit)
    {
        try {
            $uniqueUsersCollection = $this->uniqueAdminUserCollectionFactory->create();
            $uniqueUsersCollection->setOrder('created_at', 'ASC');
            $uniqueUsersCollection->setPageSize($limit);
            
            $usernames = [];
            foreach ($uniqueUsersCollection as $uniqueUser) {
                $usernames[] = $uniqueUser->getUsername();
            }
            
            if (empty($usernames)) {
                return [];
            }
            
            $adminUserCollection = $this->userCollectionFactory->create();
            $adminUserCollection->addFieldToFilter('username', ['in' => $usernames]);
            
            $userIds = [];
            foreach ($adminUserCollection as $adminUser) {
                $userIds[] = (int)$adminUser->getId();
            }
            
            return $userIds;
            
        } catch (\Exception $e) {
            $this->_logger->error('Error getting first N unique logged-in user IDs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if login/logout log limit has been reached
     *
     * @return array
     */
    public function checkLoginLogoutLogLimit()
    {
            $adminUsersLimit = $this->getAdminUsersLimitValue();
            $adminUsersCount = $this->getTotalUniqueLoggedInUserCount();
            $remaining = $adminUsersLimit - $adminUsersCount;
            $limitReached = $remaining <= 0;

        if ($limitReached) {
            $timeStamp = $this->getStoreConfig(AdminLogsConstants::TIME_STAMP);

            if ($timeStamp == null) {
                $timeStamp = time();
                $this->setStoreConfig(AdminLogsConstants::TIME_STAMP, $timeStamp);
                $this->flushCache();
            }

            $currentAdminUser = $this->getCurrentAdminUser();
            $adminEmail = $currentAdminUser ? $currentAdminUser->getEmail() : '';
            $domain = $this->getBaseUrl();
            $miniorangeAccountEmail = '';
            $pluginFirstPageVisit = '';
            $environmentName = '';
            $environmentVersion = '';
            $freeInstalledDate = '';
            $spp_name = '';
            $autoCreateLimit = 'Yes';

            MiniOrangeCurl::submit_to_magento_team(
                $timeStamp,
                $adminEmail,
                $domain,
                $miniorangeAccountEmail,
                $pluginFirstPageVisit,
                $environmentName,
                $environmentVersion,
                $freeInstalledDate,
                $spp_name,
                $autoCreateLimit
            );
        }

        return [
            'limit_reached' => $limitReached,
            'current_count' => $adminUsersCount,
            'limit' => $adminUsersLimit,
            'remaining' => $remaining,
            'message' => __('You have reached your free limit. Upgrade to Premium to continue using all features.')
        ];
    }


    /**
     * Get current admin user.
     *
     * @return \Magento\User\Model\User|null
     */
    public function getCurrentAdminUser()
    {
        try {
            return $this->authSession->getUser();
        } catch (\Exception $e) {
            $this->_logger->error('Error getting current admin user: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl()
    {
        try {
            return $this->urlBuilder->getBaseUrl();
        } catch (\Exception $e) {
            $this->_logger->error('Error getting base URL: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Flush cache
     *
     * @return void
     */
    public function flushCache()
    {
        try {
            // IMPROVEMENT: Use injected CacheManager to clear only necessary cache types
            $this->cacheManager->clean(['config']);
        } catch (\Exception $e) {
            $this->_logger->error('Error flushing cache: ' . $e->getMessage());
        }
    }

}
