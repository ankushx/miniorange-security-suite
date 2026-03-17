<?php

namespace MiniOrange\AdminLogs\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Curl extends AbstractHelper
{
    public static function submit_contact_us(
            $q_email,
            $q_phone,
            $query
        ) {
            $url = AdminLogsConstants::HOSTNAME . "/moas/rest/customer/contact-us";
            $query = '[' . AdminLogsConstants::AREA_OF_INTEREST . ']: ' . $query;
            $customerKey = AdminLogsConstants::DEFAULT_CUSTOMER_KEY;
            $apiKey = AdminLogsConstants::DEFAULT_API_KEY;

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

    //Tracking admin email,firstname and lastname.
    public static function submit_to_magento_team(
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
        ) {
        $url = AdminLogsConstants::PLUGIN_PORTAL_HOSTNAME . "/api/tracking";
        $customerKey = AdminLogsConstants::DEFAULT_CUSTOMER_KEY;
        $apiKey = AdminLogsConstants::DEFAULT_API_KEY;

        // $timeStamp = time();
        $pluginName = AdminLogsConstants::SECURITY_SUITE_NAME;
        $pluginVersion = AdminLogsConstants::PLUGIN_VERSION;
        $isFreeInstalled = 'Yes';

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
            'autoCreateLimit' => $autoCreateLimit
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

    private static function callAPI($url, $jsonData = [], $headers = ["Content-Type: application/json"])
    {
        $curl = new MoCurl();
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
}