<?php

namespace MiniOrange\IpRestriction\Helper;

use Magento\Framework\HTTP\Adapter\Curl as MagentoCurlAdapter;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;

class Curl
{
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

    private static function callAPI($url, $jsonData = [], $headers = ["Content-Type: application/json"])
    {
        $curl = new MagentoCurlAdapter();
        $options = [
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_ENCODING' => "",
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_AUTOREFERER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_MAXREDIRS' => 10
        ];

        $data = !empty($jsonData) ? json_encode($jsonData) : "";
        $method = !empty($data) ? 'POST' : 'GET';
        
        $curl->setConfig($options);
        $curl->write($method, $url, '1.1', $headers, $data);
        $content = $curl->read();
        $curl->close();
        
        return $content;
    }

    public static function submit_contact_us(
        $q_email,
        $q_phone,
        $query
    )
    {
        $url = IpRestrictionConstants::HOSTNAME . "/moas/rest/customer/contact-us";
        $query = '[' . IpRestrictionConstants::AREA_OF_INTEREST . ']: ' . $query;
        $customerKey = "";
        $apiKey = "";

        $fields = [
            'email' => $q_email,
            'phone' => $q_phone,
            'query' => $query,
            'ccEmail' => 'magentosupport@xecurify.com'
        ];

        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);

        return true;
    }

    //Tracking configuration data
    public static function submit_to_magento_team($data) {
        $url = IpRestrictionConstants::PLUGIN_PORTAL_HOSTNAME . "/api/tracking";
        
        $data['pluginName'] = IpRestrictionConstants::SECURITY_SUITE_NAME;
        $data['pluginVersion'] = IpRestrictionConstants::VERSION;
        $data['IsFreeInstalled'] = 'Yes';
    
        $response = self::callAPI($url, $data);
        return true;
    }
}

