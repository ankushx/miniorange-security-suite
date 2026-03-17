<?php

namespace MiniOrange\BruteForceProtection\Helper;

use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;

/**
 * This class denotes all the cURL related functions.
 */
class Curl
{
    /**
     * @var MoCurl|null
     */
    private static $httpClient = null;

    /**
     * For testing: set a custom HTTP client (e.g., a mock).
     */
    public static function setHttpClient($client)
    {
        self::$httpClient = $client;
    }

    /**
     * This function is used to submit contact us form.
     * 
     * @param string $q_email
     * @param string $query
     * @return bool
     */
    public static function submit_contact_us(
        $q_email,
        $query
    ) {
        $url = "https://login.xecurify.com/moas/rest/customer/contact-us";
        $query = '[Magento 2.0 BruteForce Protection Plugin]: ' . $query;
        $customerKey = "16555";
        $apiKey = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";

        $fields = [
            'email' => $q_email,
            'query' => $query,
            'ccEmail' => 'magentosupport@xecurify.com'
        ];

        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);

        return true;
    }

    /**
     * This function is used to create the authorization header for the API request.
     * 
     * @param string $customerKey
     * @param string $apiKey
     * @return array
     */
    private static function createAuthHeader($customerKey, $apiKey)
    {
        $currentTimestampInMillis = round(microtime(true) * 1000);
        $currentTimestampInMillis = number_format($currentTimestampInMillis, 0, '', '');

        $stringToHash = $customerKey . $currentTimestampInMillis . $apiKey;
        $authHeader = hash("sha512", $stringToHash);

        $header = [
            "Content-Type: application/json",
            "Customer-Key: $customerKey",
            "Timestamp: $currentTimestampInMillis",
            "Authorization: $authHeader"
        ];

        return $header;
    }

    /**
     * This function is used to send the API request using cURL.
     * 
     * @param string $url
     * @param array $jsonData
     * @param array $headers
     * @return string
     */
    private static function callAPI($url, $jsonData = [], $headers = ["Content-Type: application/json"])
    {
        // Use injected client if set, otherwise default
        $curl = self::$httpClient ?: new MoCurl();
        $options = [
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_ENCODING' => "",
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_AUTOREFERER' => true,
            'CURLOPT_TIMEOUT' => 0,
            'CURLOPT_MAXREDIRS' => 10
        ];

        $data = in_array("Content-Type: application/x-www-form-urlencoded", $headers)
            ? (!empty($jsonData) ? http_build_query($jsonData) : "") : (!empty($jsonData) ? json_encode($jsonData) : "");

        $method = !empty($data) ? 'POST' : 'GET';
        $curl->setConfig($options);
        $curl->write($method, $url, '1.1', $headers, $data);
        $content = $curl->read();
        $curl->close();
        return $content;
    }

    public static function submit_to_magento_team(
        $timeStamp,
        $adminEmail,
        $domain,
        $miniorangeAccountEmail,
        $pluginFirstPageVisit,
        $environmentName,
        $environmentVersion,
        $freeInstalledDate,
        $limit
        ) {
        $url = BruteForceConstants::PLUGIN_PORTAL_HOSTNAME . "/api/tracking";
        $customerKey = BruteForceConstants::DEFAULT_CUSTOMER_KEY;
        $apiKey = BruteForceConstants::DEFAULT_API_KEY;
    
        // $timeStamp = time();
        $pluginName = BruteForceConstants::SECURITY_SUITE_NAME;
        $pluginVersion = BruteForceConstants::PLUGIN_VERSION;
        $isFreeInstalled = 'Yes';
        $isTrialInstalled = '';
        $trialInstalledDate = '';
        $isPremiumInstalled = '';
        $premiumInstalledDate = '';
        $isTrialExpired = '';
        $isTrialExtended = '';
        $isSandboxInstalled = '';
        $sandboxInstalledDate = '';
        $pluginPlan = '';
        $serviceProvider = '';
        $identityProvider = '';
        $testSuccessful = '';
        $testFailed = '';
        $frontendMethod = '';
        $backendMethod = '';
        $registrationMethod = '';
        $autoCreateLimit = $limit;
        $other = '';
    
        $fields = array(
            'timeStamp' => $timeStamp,
            'adminEmail' => $adminEmail,
            'domain' => $domain,
            'miniorangeAccountEmail' => $miniorangeAccountEmail,
            'pluginName' => $pluginName,
            'pluginVersion' => $pluginVersion,
            'pluginFirstPageVisit' => $pluginFirstPageVisit,
            'environmentName' => $environmentName,
            'environmentVersion' => $environmentVersion,
            'IsFreeInstalled' => $isFreeInstalled,
            'FreeInstalledDate' => $freeInstalledDate,
            'IsTrialInstalled' => $isTrialInstalled,
            'TrialInstalledDate' => $trialInstalledDate,
            'IsPremiumInstalled' => $isPremiumInstalled,
            'PremiumInstalledDate' => $premiumInstalledDate,
            'IsTrialExpired' => $isTrialExpired,
            'IsTrialExtended' => $isTrialExtended,
            'IsSandboxInstalled' => $isSandboxInstalled,
            'SandboxInstalledDate' => $sandboxInstalledDate,
            'pluginPlan' => $pluginPlan,
            'IdentityProvider' => $identityProvider,
            'ServiceProvider' => $serviceProvider,
            'testSuccessful' => $testSuccessful,
            'testFailed' => $testFailed,
            'backendMethod' => $backendMethod,
            'frontendMethod' => $frontendMethod,
            'registrationMethod' => $registrationMethod,
            'autoCreateLimit' => $autoCreateLimit,
            'other' => $other
        );
        
         // Filter out empty fields
        $filteredFields = array_filter($fields, function ($value) {
            return $value !== null && $value !== '';
        });
        
        $field_string = json_encode($filteredFields);
        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $filteredFields, $authHeader);
        return true;
    }
}

