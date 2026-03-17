<?php

namespace MiniOrange\IpRestriction\Block;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use MiniOrange\IpRestriction\Helper\Data;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use Magento\Framework\UrlInterface;
use Magento\Backend\Model\Auth\Session;

/**
 * Admin Block for IP Restriction Extension
 * Common functionality for admin templates
 */
class IpRestriction extends Template
{
    protected $formKey;
    protected $storeManager;
    protected $dataHelper;
    protected $countryCollectionFactory;
    protected $urlInterface;
    protected $authSession;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        Data $dataHelper,
        FormKey $formKey,
        CountryCollectionFactory $countryCollectionFactory,
        UrlInterface $urlInterface,
        Session $authSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
        $this->dataHelper = $dataHelper;
        $this->formKey = $formKey;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->urlInterface = $urlInterface;
        $this->authSession = $authSession;
    }

    /**
     * Get current active tab
     * @return string
     */
    public function getCurrentActiveTab()
    {
        $controller = $this->getRequest()->getControllerName();
        
        $tabMap = [
            'iprestrict' => 'ip_restriction',
            'upgrade' => 'upgrade'
        ];
        
        return $tabMap[$controller] ?? 'ip_restriction';
    }

    /**
     * Get extension page URL
     * @param string $page
     * @return string
     */
    public function getExtensionPageUrl($page)
    {
        return $this->getUrl('iprestriction/' . $page . '/index');
    }

    /**
     * Get form key
     * @return string
     */
    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }

    /**
     * Get form key HTML
     * @return string
     */
    public function getFormKeyHtml()
    {
        return '<input name="form_key" type="hidden" value="' . $this->getFormKey() . '" />';
    }

    /**
     * Get store configuration value
     * @param string $path
     * @param string $scope
     * @param int $scopeId
     * @return mixed
     */
    public function getConfigValue($path, $scope = 'default', $scopeId = 0)
    {
        return $this->dataHelper->getStoreConfig($path, $scope, $scopeId);
    }

    /**
     * Get all stores
     * @return array
     */
    public function getStores()
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            $stores[] = [
                'id' => $store->getId(),
                'name' => $store->getName(),
                'website_id' => $store->getWebsiteId()
            ];
        }
        return $stores;
    }

    /**
     * Get all websites
     * @return array
     */
    public function getWebsites()
    {
        $websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websites[] = [
                'id' => $website->getId(),
                'name' => $website->getName()
            ];
        }
        return $websites;
    }

    /**
     * Get all countries from Magento Directory
     * @return array
     */
    public function getCountries()
    {
        $countries = [];
        try {
            $countryCollection = $this->countryCollectionFactory->create();
            $countryCollection->load();
            
            foreach ($countryCollection as $country) {
                $countries[] = [
                    'code' => $country->getCountryId(),
                    'name' => $country->getName() ?: $country->getCountryId()
                ];
            }
            
            // Sort by country name
            usort($countries, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        } catch (\Exception $e) {
            $countries = [];
        }
        
        return $countries;
    }

    /**
     * Get existing IPs for admin denylist
     * Admin IP lists always use default scope
     * @return array
     */
    public function getAdminDenylistIps()
    {
        try {
            $ipString = $this->dataHelper->getStoreConfig(IpRestrictionConstants::ADMIN_IP_BLACKLIST, 'default', 0);
            
            if (empty($ipString)) {
                return [];
            }
            
            // Parse semicolon-separated IPs
            $ips = explode(';', $ipString);
            $result = [];
            
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if (!empty($ip)) {
                    $result[] = $ip;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get country restriction enabled status
     * Admin scope always uses default scope
     * @return bool
     */
    public function getCountryRestrictionsEnabled()
    {
        try {
            $enabled = $this->dataHelper->getStoreConfig(IpRestrictionConstants::COUNTRY_RESTRICTIONS_ENABLED, 'default', 0);
            return !empty($enabled) && $enabled == '1';
        } catch (\Exception $e) {
            return false;
        }
    }


    /**
     * Get IP blacklist enabled state
     * Admin scope always uses default scope
     * @return bool
     */
    public function getIpBlacklistEnabled()
    {
        try {
            $enabled = $this->dataHelper->getStoreConfig(IpRestrictionConstants::IP_BLACKLIST_ENABLED, 'default', 0);
            return !empty($enabled) && $enabled == '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get country denylist codes
     * Admin scope always uses default scope
     * @return array Array of country codes
     */
    public function getCountryDenylist()
    {
        try {
            $countryCodesString = $this->dataHelper->getStoreConfig(IpRestrictionConstants::COUNTRY_DENYLIST, 'default', 0);
            
            if (empty($countryCodesString)) {
                return [];
            }
            
            // Parse semicolon-separated country codes
            $codes = explode(';', $countryCodesString);
            $result = [];
            
            foreach ($codes as $code) {
                $code = strtoupper(trim($code));
                if (!empty($code) && strlen($code) === 2) {
                    $result[] = $code;
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get GeoIP2 license key
     * @return string
     */
    public function getGeoIp2LicenseKey()
    {
        return $this->dataHelper->getStoreConfig(IpRestrictionConstants::GEOIP2_LICENSE_KEY, 'default', 0) ?: '';
    }


    /**
     * Get the current version of the module
     * @return string
     */
    public function getCurrentVersion()
    {
        return IpRestrictionConstants::VERSION;
    }

    /**
     * Mark that tracking data has been added
     */
    public function dataAdded()
    {
        $this->dataHelper->setStoreConfig(IpRestrictionConstants::DATA_ADDED, 1);
    }

    /**
     * Check if tracking data has been added
     * @return mixed
     */
    public function checkDataAdded()
    {
        return $this->dataHelper->getStoreConfig(IpRestrictionConstants::DATA_ADDED);
    }

    /**
     * Get or create timestamp for tracking
     * @return int
     */
    public function getTimeStamp()
    {
        $timeStamp = $this->dataHelper->getStoreConfig(IpRestrictionConstants::TIME_STAMP);
        if ($timeStamp == null) {
            $timeStamp = time();
            $this->dataHelper->setStoreConfig(IpRestrictionConstants::TIME_STAMP, $timeStamp);
            return $timeStamp;
        }
        return $timeStamp;
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
     * Get base URL
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->urlInterface->getBaseUrl();
    }

    /**
     * Get product version
     * @return string
     */
    public function getProductVersion()
    {
        return $this->dataHelper->getProductVersion();
    }

    /**
     * Get edition
     * @return string
     */
    public function getEdition()
    {
        return $this->dataHelper->getEdition();
    }

    /**
     * Get current date
     * @return string
     */
    public function getCurrentDate()
    {
        return $this->dataHelper->getCurrentDate();
    }
}

