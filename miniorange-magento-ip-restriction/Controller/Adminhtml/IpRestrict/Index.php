<?php

namespace MiniOrange\IpRestriction\Controller\Adminhtml\IpRestrict;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\IpRestriction\Controller\Actions\BaseAdminAction;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use MiniOrange\IpRestriction\Helper\Curl;
use Magento\Framework\UrlInterface;

class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    protected $urlInterface;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MiniOrange\IpRestriction\Helper\Data $dataHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        UrlInterface $urlInterface
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelper, $messageManager, $logger);
        $this->urlInterface = $urlInterface;
    }

    public function execute()
    {
        try {
            $params = $this->getRequest()->getParams();
            
            // Handle form submission
            if ($this->getRequest()->isPost() && isset($params['form_key'])) {
                // Admin scope always uses default scope
                $scope = 'default';
                $scopeId = 0;
                
                // Check if saving GeoIP2 license key 
                if (isset($params['save_geoip2_license'])) {
                    $licenseKey = trim($params['geoip2_license_key'] ?? '');
                    
                    // Validate license key format before saving
                    if (empty($licenseKey)) {
                        $this->addErrorMessage(__('GeoIP2 License Key cannot be empty.'));
                        return $this->redirectToPath('iprestriction/iprestrict/index');
                    }
                    
                    // Basic format validation: MaxMind license keys are typically alphanumeric and 10+ characters
                    if (strlen($licenseKey) < 10 || !preg_match('/^[A-Za-z0-9_-]+$/', $licenseKey)) {
                        $this->addErrorMessage(__('Invalid license key format. MaxMind license keys should be alphanumeric and at least 10 characters long.'));
                        return $this->redirectToPath('iprestriction/iprestrict/index');
                    }
                    
                    // Save the license key 
                    $this->dataHelper->setStoreConfig(IpRestrictionConstants::GEOIP2_LICENSE_KEY, $licenseKey, 'default', 0);
                    
                    // Redirect to download for validation
                    return $this->redirectToPath('iprestriction/geoip/index');
                }
                // Save all settings
                else {
                    $hasError = false;
                    
                    // Initialize encrypted limit values if not already stored
                    $this->dataHelper->initializeLimitsIfNeeded();
                    
                    // Save GeoIP2 license key 
                    $this->saveGeoIp2LicenseKey($params);
                    
                    // Save GeoIP2 automatic update settings
                    $this->saveGeoIp2AutoUpdate($params);
                    
                    // Save IP lists
                    $result = $this->saveIpList($params);
                    if (isset($result['limit_exceeded']) && $result['limit_exceeded']) {
                        $this->addErrorMessage(__('Limit is exceeded. Please Upgrade to the Premium Plan to continue the service.'));
                        
                        $hasError = true;
                    } elseif (!$result['success'] && $result['invalid_count'] > 0 && $result['saved_count'] == 0) {
                        $this->addErrorMessage(__('No valid IP addresses found. Please check your input and try again.'));
                            $hasError = true;
                    }
                    
                    // Save country restriction
                    $countryResult = $this->saveCountryRestrictions($params);
                    if (isset($countryResult['limit_exceeded']) && $countryResult['limit_exceeded']) {
                        $this->addErrorMessage(__('Limit is exceeded. Please Upgrade to any of the Premium Plan to continue the service.'));
                        $hasError = true;
                    }

                    // Show library warning if country restrictions are enabled and GeoIP2 package is not installed
                    $countryRestrictionsEnabled = isset($params['country_restrictions_enabled']) && $params['country_restrictions_enabled'] == '1';
                    if ($countryRestrictionsEnabled && !class_exists(\GeoIp2\Database\Reader::class)) {
                        $this->addWarningMessage(__('Please install GeoIP2 supporting file using following command: composer require geoip2/geoip2'));
                    }
                    
                    // Show success message if no errors
                    if (!$hasError) {
                        $this->addSuccessMessage(__('Settings saved successfully.'));
                    }
                    
                    // Comprehensive cache flushing after all saves
                    $this->dataHelper->flushCache();
                }
                
                return $this->redirectToPath('iprestriction/iprestrict/index');
            }
        } catch (\Exception $e) {
            $this->handleException($e, 'IpRestrict controller');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__(IpRestrictionConstants::MODULE_TITLE));
        return $resultPage;
    }

    /**
     * Save IP denylist to core_config_data
     * Admin scope always uses default scope
     * @param array $params
     */
    protected function saveIpList($params)
    {
        $scope = 'default';
        $scopeId = 0;
        
        // Save IP blacklist enabled state
        $ipBlacklistEnabled = false;
        if (isset($params['ip_blacklist_enabled'])) {
            $ipBlacklistEnabled = $params['ip_blacklist_enabled'] == '1';
            $this->dataHelper->setStoreConfig(IpRestrictionConstants::IP_BLACKLIST_ENABLED, $ipBlacklistEnabled ? '1' : '0', $scope, $scopeId);
            $this->logInfo("Saved ip_blacklist_enabled = " . ($ipBlacklistEnabled ? '1' : '0') . " for admin scope");
            
            // If disabled, clear denylist
            if (!$ipBlacklistEnabled) {
                $this->dataHelper->setStoreConfig(IpRestrictionConstants::ADMIN_IP_BLACKLIST, '', $scope, $scopeId);
                $this->logInfo("IP blacklist disabled - cleared denylist");
            }
        }
        
        $totalSaved = 0;
        $totalInvalid = 0;
        $limitExceeded = false;
        $limitType = null;
        
        // Only save IPs if IP blacklist is enabled
        if ($ipBlacklistEnabled && isset($params['admin_denylist_ips'])) {
            $this->logDebug("Saving admin denylist IPs");
            $result = $this->saveIpListToConfig(IpRestrictionConstants::ADMIN_IP_BLACKLIST, $params['admin_denylist_ips'], $scope, $scopeId);
                $totalSaved += $result['saved_count'];
                $totalInvalid += $result['invalid_count'];
                if (isset($result['limit_exceeded']) && $result['limit_exceeded']) {
                    $limitExceeded = true;
                $limitType = IpRestrictionConstants::LIMIT_TYPE_IP;
            }
        }
        
        return [
            'success' => $totalSaved > 0 || $totalInvalid == 0,
            'saved_count' => $totalSaved,
            'invalid_count' => $totalInvalid,
            'limit_exceeded' => $limitExceeded,
            'limit_type' => $limitType
        ];
    }
    
    /**
     * Save IP list to core_config_data
     * @param string $configPath 
     * @param string $ipString
     * @param string $scope
     * @param int $scopeId
     */
    protected function saveIpListToConfig($configPath, $ipString, $scope = 'default', $scopeId = 0)
    {
        $ipString = trim($ipString ?? '');
        
        $ipString = str_replace(["\r\n", "\r"], "\n", $ipString);
        $ipString = str_replace("\n", ";", $ipString);
 
        $ips = explode(";", $ipString);
        
        // Process and validate IPs
        $validIps = [];
        $invalidIps = [];
        $maxIps = $this->dataHelper->getMaxIpLimit(); 
        $totalValidIps = 0; 
            
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (empty($ip)) {
                continue;
            }
            
            $ip = preg_replace('/\s+/', '', $ip);
            
            // Only allow simple IPs (no wildcards, CIDR, or ranges)
                if (!$this->isValidSimpleIp($ip)) {
                    $invalidIps[] = $ip;
                    continue;
            }

            // Check for duplicates
            if (in_array($ip, $validIps, true)) {
                continue;
            }
            
            $totalValidIps++;
            
            // Check IP count limit
            if (count($validIps) >= $maxIps) {
                $invalidIps[] = $ip;
                continue;
            }
            
            $validIps[] = $ip;
        }

        $ipValue = !empty($validIps) ? implode('; ', $validIps) : '';
        $this->dataHelper->setStoreConfig($configPath, $ipValue, $scope, $scopeId);
        
        $savedCount = count($validIps);
        $limitExceeded = $totalValidIps > $maxIps;
        
        return [
            'saved_count' => $savedCount,
            'invalid_count' => count($invalidIps),
            'limit_exceeded' => $limitExceeded,
            'limit_type' => IpRestrictionConstants::LIMIT_TYPE_IP
        ];
    }
    
    /**
     * Validate IP address 
     * @param string $ip
     * @return bool
     */
    protected function isValidSimpleIp($ip)
    {
        if (empty($ip)) {
            return false;
        }
        
        $ip = trim($ip);
        
        // Reject IP ranges
        if (strpos($ip, ' - ') !== false || (strpos($ip, '-') !== false && strpos($ip, '/') === false)) {
            return false;
        }
        
        // Reject CIDR notation
        if (strpos($ip, '/') !== false) {
            return false;
        }
        
        // Reject wildcard patterns
        if (strpos($ip, '*') !== false) {
            return false;
        }
        
        // Only allow plain IP address (IPv4 or IPv6)
        // Allow both private and public IPs
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Save GeoIP2 license key if provided
     * @param array $params
     */
    protected function saveGeoIp2LicenseKey($params)
    {
        if (isset($params['geoip2_license_key'])) {
            $licenseKey = trim($params['geoip2_license_key'] ?? '');
            if (!empty($licenseKey)) {
                $this->dataHelper->setStoreConfig(IpRestrictionConstants::GEOIP2_LICENSE_KEY, $licenseKey, 'default', 0);
            }
        }
    }

    /**
     * Save GeoIP2 automatic update settings
     * @param array $params
     */
    protected function saveGeoIp2AutoUpdate($params)
    {
        $enabled = isset($params['geoip2_auto_update_enabled']) ? $params['geoip2_auto_update_enabled'] : '0';
        $this->dataHelper->setStoreConfig(IpRestrictionConstants::GEOIP2_AUTO_UPDATE_ENABLED, $enabled, 'default', 0);
    }

    /**
     * Save country restriction to core_config_data
     * Admin scope always uses default scope
     * @param array $params
     */
    protected function saveCountryRestrictions($params)
    {
        $scope = 'default';
        $scopeId = 0;
        
        // Get enabled status from checkbox 
        $enabled = isset($params['country_restrictions_enabled']) && $params['country_restrictions_enabled'] == '1';
        
        // Save enabled status
        $this->dataHelper->setStoreConfig(IpRestrictionConstants::COUNTRY_RESTRICTIONS_ENABLED, $enabled ? '1' : '0', $scope, $scopeId);
        
        // If disabled, clear denylist
        if (!$enabled) {
            $this->dataHelper->setStoreConfig(IpRestrictionConstants::COUNTRY_DENYLIST, '', $scope, $scopeId);
            return [
                'limit_exceeded' => false,
                'limit_type' => null,
                'saved_count' => 0
            ];
        }
        
        // Process and validate country codes
        $denylistCodes = $params['country_denylist'] ?? [];
        $validDenylistCodes = [];
        $maxCountries = $this->dataHelper->getMaxCountryLimit();
        $limitExceeded = false;
        
        if (is_array($denylistCodes) && !empty($denylistCodes)) {
            foreach ($denylistCodes as $countryCode) {
                $countryCode = strtoupper(trim($countryCode));
                
                // Validate: must be 2 uppercase letters
                if (empty($countryCode) || strlen($countryCode) !== 2 || !preg_match('/^[A-Z]{2}$/', $countryCode)) {
                    continue;
                }
                
                // Check limit
                if (count($validDenylistCodes) >= $maxCountries) {
                    $limitExceeded = true;
                    continue;
                }
                
                $validDenylistCodes[] = $countryCode;
            }
        }
        
        // Save denylist
        $denylistValue = !empty($validDenylistCodes) ? implode('; ', $validDenylistCodes) : '';
        $this->dataHelper->setStoreConfig(IpRestrictionConstants::COUNTRY_DENYLIST, $denylistValue, $scope, $scopeId);
        
        return [
            'limit_exceeded' => $limitExceeded,
            'limit_type' => IpRestrictionConstants::LIMIT_TYPE_COUNTRY,
            'saved_count' => count($validDenylistCodes)
        ];
    }
}

