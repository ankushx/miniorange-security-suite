<?php

namespace MiniOrange\IpRestriction\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ProductMetadataInterface;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use MiniOrange\IpRestriction\Helper\AESEncryption;

class Data extends AbstractHelper
{
    protected $scopeConfig;
    protected $configWriter;
    protected $cacheManager;
    protected $resource;
    protected $connection;
    protected $productMetadata;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\App\Cache\Manager $cacheManager,
        ResourceConnection $resource,
        ProductMetadataInterface $productMetadata
    ) {
        parent::__construct($context);
        $this->scopeConfig = $context->getScopeConfig();
        $this->configWriter = $configWriter;
        $this->cacheManager = $cacheManager;
        $this->resource = $resource;
        $this->connection = $resource->getConnection();
        $this->productMetadata = $productMetadata;
    }

    public function getStoreConfig($config, $scope = 'default', $scopeId = 0)
    {
        if ($scope === 'stores') {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        } elseif ($scope === 'websites') {
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE;
        } else {
            $storeScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        }
        return $this->scopeConfig->getValue(IpRestrictionConstants::CONFIG_PATH_PREFIX . $config, $storeScope, $scopeId);
    }

    public function setStoreConfig($config, $value, $scope = 'default', $scopeId = 0)
    {
        $this->configWriter->save(IpRestrictionConstants::CONFIG_PATH_PREFIX . $config, $value, $scope, $scopeId);
        $this->cacheManager->flush(['config']);
    }

    public function getStoreConfigDirect($config, $scope = 'default', $scopeId = 0)
    {
        $tableName = $this->resource->getTableName('core_config_data');
        $path = IpRestrictionConstants::CONFIG_PATH_PREFIX . $config;
        
        $select = $this->connection->select()
            ->from($tableName, ['value'])
            ->where('path = ?', $path)
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId)
            ->limit(1);
        
        $value = $this->connection->fetchOne($select);
        
        return $value !== false ? $value : null;
    }

    /**
     * Flush cache comprehensively
     */
    public function flushCache()
    {
        $this->cacheManager->flush(['config', 'layout', 'block_html', 'collections', 'reflection', 'db_ddl', 'eav', 'config_integration', 'config_integration_api', 'full_page', 'translate', 'config_webservice_api']);
    }

    /**
     * Get current date
     * @return string
     */
    public function getCurrentDate(){
        $dateTimeZone = new \DateTimeZone('Asia/Kolkata'); 
        $dateTime = new \DateTime('now', $dateTimeZone);
        return $dateTime->format('n/j/Y, g:i:s a');
    }

    /**
     * Get product version
     * @return string
     */
    public function getProductVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * Get edition
     * @return string
     */
    public function getEdition()
    {
        return $this->productMetadata->getEdition() == 'Community' ? 'Magento Open Source' : 'Adobe Commerce Enterprise/Cloud';
    }

    /**
     * Get max IP limit
     * @return int
     */
    public function getMaxIpLimit()
    {
        try {
            $defaultLimit = IpRestrictionConstants::DEFAULT_MAX_IP_LIMIT;
            $encryptedLimit = $this->getStoreConfig(
                IpRestrictionConstants::MAX_IP_LIMIT_ENCRYPTED,
                'default',
                0
            );
            
            $maxLimit = $defaultLimit;
            if ($encryptedLimit !== null && $encryptedLimit !== '' && !empty(trim((string)$encryptedLimit))) {
                $token = $this->getEncryptionToken();
                $decryptedLimit = AESEncryption::decrypt_data($encryptedLimit, $token);
                $decryptedLimitInt = (int)$decryptedLimit;
                if ($decryptedLimitInt > 0) {
                    $maxLimit = $decryptedLimitInt;
                }
            }
            
            return $maxLimit;
            
        } catch (\Exception $e) {
            // Fallback to constant if decryption fails
            return IpRestrictionConstants::DEFAULT_MAX_IP_LIMIT;
        }
    }

    /**
     * Get max country limit
     * @return int
     */
    public function getMaxCountryLimit()
    {
        try {
            $defaultLimit = IpRestrictionConstants::DEFAULT_MAX_COUNTRY_LIMIT;
            $encryptedLimit = $this->getStoreConfig(
                IpRestrictionConstants::MAX_COUNTRY_LIMIT_ENCRYPTED,
                'default',
                0
            );
            
            $maxLimit = $defaultLimit;
            if ($encryptedLimit !== null && $encryptedLimit !== '' && !empty(trim((string)$encryptedLimit))) {
                $token = $this->getEncryptionToken();
                $decryptedLimit = AESEncryption::decrypt_data($encryptedLimit, $token);
                $decryptedLimitInt = (int)$decryptedLimit;
                if ($decryptedLimitInt > 0) {
                    $maxLimit = $decryptedLimitInt;
                }
            }
            
            return $maxLimit;
            
        } catch (\Exception $e) {
            // Fallback to constant if decryption fails
            return IpRestrictionConstants::DEFAULT_MAX_COUNTRY_LIMIT;
        }
    }

    /**
     * Set max IP limit
     * 
     * @param int $limit
     * @return void
     */
    public function setMaxIpLimit($limit)
    {
        try {
            $token = $this->getEncryptionToken();
            $encrypted = AESEncryption::encrypt_data((string)$limit, $token);
            $this->setStoreConfig(
                IpRestrictionConstants::MAX_IP_LIMIT_ENCRYPTED,
                $encrypted,
                'default',
                0
            );
        } catch (\Exception $e) {
            $this->_logger->error("IpRestriction: Failed to set max IP limit: " . $e->getMessage());
        }
    }

    /**
     * Set max country limit 
     * 
     * @param int $limit
     * @return void
     */
    public function setMaxCountryLimit($limit)
    {
        try {
            $token = $this->getEncryptionToken();
            $encrypted = AESEncryption::encrypt_data((string)$limit, $token);
            $this->setStoreConfig(
                IpRestrictionConstants::MAX_COUNTRY_LIMIT_ENCRYPTED,
                $encrypted,
                'default',
                0
            );
        } catch (\Exception $e) {
            $this->_logger->error("IpRestriction: Failed to set max country limit: " . $e->getMessage());
        }
    }

    /**
     * Initialize encrypted limit values if they don't exist in DB
     * Called on first save operation to store default limits
     * 
     * @return void
     */
    public function initializeLimitsIfNeeded()
    {
        // Check and initialize IP limit if not exists
        $encryptedIpLimit = $this->getStoreConfig(
            IpRestrictionConstants::MAX_IP_LIMIT_ENCRYPTED,
            'default',
            0
        );
        
        if (empty($encryptedIpLimit)) {
            $this->setMaxIpLimit(IpRestrictionConstants::DEFAULT_MAX_IP_LIMIT);
        }
        
        // Check and initialize country limit if not exists
        $encryptedCountryLimit = $this->getStoreConfig(
            IpRestrictionConstants::MAX_COUNTRY_LIMIT_ENCRYPTED,
            'default',
            0
        );
        
        if (empty($encryptedCountryLimit)) {
            $this->setMaxCountryLimit(IpRestrictionConstants::DEFAULT_MAX_COUNTRY_LIMIT);
        }
    }

    /**
     * Get or generate encryption token
     * @return string
     */
    private function getEncryptionToken()
    {
        $token = $this->getStoreConfig(
            IpRestrictionConstants::ENCRYPTION_TOKEN,
            'default',
            0
        );
        
        if (empty($token)) {
            // Use default token if none exists
            $token = IpRestrictionConstants::DEFAULT_ENCRYPTION_TOKEN;
            $this->setStoreConfig(
                IpRestrictionConstants::ENCRYPTION_TOKEN,
                $token,
                'default',
                0
            );
        }
        
        return $token;
    }
}

