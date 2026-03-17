<?php

namespace MiniOrange\IpRestriction\Plugin;

use Magento\Framework\App\FrontControllerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use MiniOrange\IpRestriction\Helper\IpRestrictionUtility;
use MiniOrange\IpRestriction\Helper\Data;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use Psr\Log\LoggerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Admin HTTP Plugin to apply IP restriction to admin area
 * This ensures IP restriction is applied to all admin requests
 */
class AdminHttpPlugin
{
    protected $ipRestrictionUtility;
    protected $storeManager;
    protected $logger;
    protected $responseFactory;
    protected $url;
    protected $dataHelper;
    protected $cache;
    protected $assetRepo;
    protected $directoryList;

    public function __construct(
        IpRestrictionUtility $ipRestrictionUtility,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ResponseFactory $responseFactory,
        UrlInterface $url,
        Data $dataHelper,
        CacheInterface $cache,
        Repository $assetRepo,
        DirectoryList $directoryList
    ) {
        $this->ipRestrictionUtility = $ipRestrictionUtility;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->responseFactory = $responseFactory;
        $this->url = $url;
        $this->dataHelper = $dataHelper;
        $this->cache = $cache;
        $this->assetRepo = $assetRepo;
        $this->directoryList = $directoryList;
    }

    /**
     * Around dispatch - apply global rate limiting to admin area
     * 
     * @param FrontControllerInterface $subject
     * @param \Closure $proceed
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function aroundDispatch(FrontControllerInterface $subject, \Closure $proceed, RequestInterface $request)
    {
        try {
            
            // Only process admin area requests
            if (!$this->ipRestrictionUtility->isAdminRequest($request)) {
                return $proceed($request);
            }

            // Step 1: Check if IP restriction is disabled via CLI 
            $ipRestrictionDisabled = $this->dataHelper->getStoreConfig(
                IpRestrictionConstants::IP_RESTRICTION_DISABLED,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                0
            );
            
            if ($ipRestrictionDisabled == '1') {
                $this->logger->info("Admin: IP restriction feature is disabled via CLI, allowing access (bypassing all IP and country restrictions)");
                return $proceed($request);
            }

            // Step 2: Get visitor IP
            $ipAddress = $this->ipRestrictionUtility->getRealClientIp($request);
            
            // Validate IP address 
            if (empty($ipAddress) || $ipAddress === IpRestrictionConstants::DEFAULT_UNKNOWN_IP) {
                $this->logger->warning("Admin: Blocked request - IP address could not be determined");
                return $this->redirectToErrorPage($request);
            }
            
            $storeId = null;
            try {
                $storeId = $this->storeManager->getStore()->getId();
            } catch (\Exception $e) {
                // If store not available, continue with null
            }

            // Step 3: Check IP denylist (admin context)
            if ($this->ipRestrictionUtility->isIpInDenylist($ipAddress, null, IpRestrictionConstants::CONTEXT_ADMIN)) {
                $this->logger->warning("Admin: Blocked IP {$ipAddress} - IP in admin denylist");
                return $this->redirectToErrorPage($request);
            }

            // Step 4: Country restriction
            $adminScope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
            $adminScopeId = 0;
            
            $this->logger->debug("AdminHttpPlugin: Checking country restriction - IP: {$ipAddress}, Scope: {$adminScope}, ScopeId: {$adminScopeId}");
            
            $countryRestrictionsEnabled = $this->dataHelper->getStoreConfig(IpRestrictionConstants::COUNTRY_RESTRICTIONS_ENABLED, $adminScope, $adminScopeId);
            $this->logger->debug("AdminHttpPlugin: Country restriction enabled value: " . ($countryRestrictionsEnabled ?? 'null') . " (scope: {$adminScope}, scopeId: {$adminScopeId})");
            
            if ($countryRestrictionsEnabled == '1') {
                $countryCode = $this->ipRestrictionUtility->getCountryCode($ipAddress);
                $this->logger->debug("AdminHttpPlugin: Detected country code for IP {$ipAddress}: " . ($countryCode ?? 'null'));
                
                
                $countryRestrictionResult = $this->checkCountryRestriction($countryCode, $adminScope, $adminScopeId);

                $this->logger->debug("AdminHttpPlugin: Country restriction check result - Allowed: " . ($countryRestrictionResult['allowed'] ? 'true' : 'false') . ", Action: {$countryRestrictionResult['action']}, Message: {$countryRestrictionResult['message']}");
                
                if (!$countryRestrictionResult['allowed']) {
                    $this->logger->warning("Admin: Blocked IP {$ipAddress} (Country: {$countryCode}) - Country restricted");
                    return $this->redirectToErrorPage($request);
                }
            } else {
                $this->logger->debug("AdminHttpPlugin: Country restriction is disabled, allowing access");
            }

            // Continue normal processing
            return $proceed($request);
        } catch (\Exception $e) {
            $this->logger->error("Error in AdminHttpPlugin: " . $e->getMessage());
            return $proceed($request);
        }
    }

    /**
     * Render error page directly 
     * 
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    private function redirectToErrorPage(RequestInterface $request)
    {
        $response = $this->responseFactory->create();
        
        // Set HTTP 403 Forbidden status (more appropriate for IP/country blocking)
        if ($response instanceof Response) {
            $response->setHttpResponseCode(403);
        }
        
        // Load HTML template from file
        // Note: Reading template directly instead of using layout system to avoid area code issues in plugin context
        $html = $this->ipRestrictionUtility->getErrorPageHtml();
        
        // Load CSS from file
        $css = $this->ipRestrictionUtility->getErrorPageCss();
        
        // Wrap in standalone HTML structure
        $fullHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    ' . $css . '
</head>
<body>
    ' . $html . '
</body>
</html>';
        
        // Return raw HTML response
        $response->setBody($fullHtml);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        return $response;
    }

    /**
     * Check country restriction for admin
     * 
     * @param string|null $countryCode
     * @param string $scope
     * @param int $scopeId
     * @return array ['allowed' => bool, 'action' => string, 'message' => string]
     */
    private function checkCountryRestriction($countryCode, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0)
    {
        try {
            // If country code is NULL, allow access 
            if (empty($countryCode)) {
                $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Country code is empty, allowing access");
                return ['allowed' => true, 'action' => 'allow', 'message' => ''];
            }

            $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Checking - CountryCode: {$countryCode}, Scope: {$scope}, ScopeId: {$scopeId}");

            // Check if country restriction is enabled
            $enabled = $this->dataHelper->getStoreConfig(IpRestrictionConstants::COUNTRY_RESTRICTIONS_ENABLED, $scope, $scopeId);
            $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Enabled value: " . ($enabled ?? 'null') . " (scope: {$scope}, scopeId: {$scopeId})");

            
            if (empty($enabled) || $enabled != '1') {
                // Not enabled, allow access
                $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Country restriction not enabled, allowing access");
                return ['allowed' => true, 'action' => 'allow', 'message' => ''];
            }

            // Get denylist only
            $denylistString = $this->dataHelper->getStoreConfig(IpRestrictionConstants::COUNTRY_DENYLIST, $scope, $scopeId);
            $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Denylist string: " . ($denylistString ?? 'null') . " (scope: {$scope}, scopeId: {$scopeId})");

            // Parse denylist with strict validation and limit enforcement
            $denylistCodes = [];
            $maxCountries = $this->dataHelper->getMaxCountryLimit();
            
            if (!empty($denylistString)) {
                $codes = explode(';', $denylistString);
                foreach ($codes as $code) {
                    if (count($denylistCodes) >= $maxCountries) {
                        $this->logger->warning("AdminHttpPlugin: Country limit ({$maxCountries}) exceeded. Only checking first {$maxCountries} valid country codes from denylist.");
                        break;
                    }
                    
                    $code = strtoupper(trim($code));
                    if (!empty($code) && strlen($code) === 2 && preg_match('/^[A-Z]{2}$/', $code)) {
                        $denylistCodes[] = $code;
                    } else {
                        $this->logger->warning("AdminHttpPlugin: Invalid country code format rejected: {$code}");
                    }
                }
            }
            
            $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Parsed denylist codes: " . implode(', ', $denylistCodes));

            $countryCodeUpper = strtoupper(trim($countryCode));

            // Check denylist
            if (!empty($denylistCodes) && in_array($countryCodeUpper, $denylistCodes)) {
                $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Country {$countryCodeUpper} is in denylist, blocking access");
                return [
                    'allowed' => false,
                    'action' => 'deny',
                    'message' => __('Access denied from your country.')
                ];
            }

            // Not in denylist, allow access
            $this->logger->debug("AdminHttpPlugin checkCountryRestriction: Country {$countryCodeUpper} is not in denylist, allowing access");
            return ['allowed' => true, 'action' => 'allow', 'message' => ''];
        } catch (\Exception $e) {
            $this->logger->error("AdminHttpPlugin: Error checking country restriction: " . $e->getMessage());
            $this->logger->error("AdminHttpPlugin: Error trace: " . $e->getTraceAsString());
            // On error, allow the request 
            return ['allowed' => true, 'action' => 'allow', 'message' => ''];
        }
    }
}


