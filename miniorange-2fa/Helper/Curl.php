<?php

namespace MiniOrange\TwoFA\Helper;

use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;

/**
 * This class denotes all the cURL related functions.
 */
class Curl
{

    public static function create_customer($email, $company, $password, $phone = '', $first_name = '', $last_name = '')
    {
        $url ='https://login.xecurify.com/moas/rest/customer/add';
        $customerKey = "";
        $apiKey = "";
        $fields = [
            'companyName' => $company,
            'areaOfInterest' => 'Magento 2 Factor Authentication Plugin',
            'firstname' => $first_name,
            'lastname' => $last_name,
            'email' => $email,
            'phone' => '',
            'password' => $password
        ];
        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);
        return $response;
    }

    public static function get_customer_key($email, $password)
    {
        $url = TwoFAConstants::HOSTNAME . "/moas/rest/customer/key";
        $customerKey = "";
        $apiKey = "";
        $fields = [
            'email' => $email,
            'password' => $password
        ];

        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);

        return $response;
    }

    public static function check_customer($email)
    {
        $url = TwoFAConstants::HOSTNAME . "/moas/rest/customer/check-if-exists";
        $customerKey = "";
        $apiKey = "";
        $fields = [
            'email' => $email,
        ];
        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);
        return $response;
    }


  	public static function update_customer_2fa($customerKey,$apiKey,$url,$fields) {

        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);
        return $response;
    }


    public static function mo_send_access_token_request($postValue, $url, $clientID, $clientSecret)
    {
        $authHeader = [
            "Content-Type: application/x-www-form-urlencoded",
            'Authorization: Basic '.base64_encode($clientID.":".$clientSecret)
        ];
        $response = self::callAPI($url, $postValue, $authHeader);
        return $response;
    }

    public static function mo_send_user_info_request($url, $headers)
    {

        $response = self::callAPI($url, [], $headers);
        return $response;
    }

    public static function submit_contact_us(
        $q_email,
        $q_phone,
        $query
    ) {
        $url = TwoFAConstants::HOSTNAME . "/moas/rest/customer/contact-us";
        $query = '[' . TwoFAConstants::AREA_OF_INTEREST . ']: ' . $query;
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

 public static function challenge($customerKey, $apiKey,$url,$fields){

   $authHeader = self::createAuthHeader($customerKey, $apiKey);
   $response = self::callAPI($url, $fields, $authHeader);

   return $response;
 }

 public static function validate($customerKey,$apiKey,$url,$fields){

   $authHeader = self::createAuthHeader($customerKey, $apiKey);
   $response = self::callAPI($url, $fields, $authHeader);
   return $response;
 }

 public static function update($customerKey,$apiKey,$url,$fields){

    $authHeader = self::createAuthHeader($customerKey, $apiKey);
    $response = self::callAPI($url, $fields, $authHeader);
    return $response;
  }


    public static function check_customer_ln($customerKey, $apiKey)
    {
        $url = TwoFAConstants::HOSTNAME . '/moas/rest/customer/license';
        $fields = [
            'customerId' => $customerKey,
            'applicationName' => TwoFAConstants::APPLICATION_NAME,
            'licenseType' => !MoUtility::micr() ? 'DEMO' : 'PREMIUM',
        ];

        $authHeader = self::createAuthHeader($customerKey, $apiKey);
        $response = self::callAPI($url, $fields, $authHeader);
        return $response;
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

    private static function callAPI($url, $jsonData = [], $headers = ["Content-Type: application/json"])
    {
        // Custom functionality written to be in tune with Mangento2 coding standards.
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

    public static function get_email_sms_transactions($customerKey,$apiKey)
	{   //change application name after being created new license plan for magento twofa.c
		$url = TwoFAConstants::HOSTNAME . '/moas/rest/customer/license';
        //check for premium license
        $fields = array(
            'customerId' 	  => $customerKey,
            'applicationName' => 'magento_2fa_basic_plan',
    );
    $authHeader  = self::createAuthHeader($customerKey,$apiKey);
    $response 	 = self::callAPI($url, $fields, $authHeader);
    $result= json_decode($response);
    //if premium license not found then check for free license
            if($result->status != 'SUCCESS'){
                $fields = array(
                    'customerId' 	  => $customerKey,
                    'licenseType' =>  'DEMO',
                    );
            $authHeader  = self::createAuthHeader($customerKey,$apiKey);
            $response 	 = self::callAPI($url, $fields, $authHeader);
            $result= json_decode($response);
            }
		return $result;
	}

    public static function ccl($customerKey,$apiKey)
	{
	    $url = TwoFAConstants::HOSTNAME . '/moas/rest/customer/license';
		$fields = array(
				'customerId' 	  => $customerKey,
                'applicationName' => 'magento_2fa_basic_plan',
		);
		$authHeader  = self::createAuthHeader($customerKey,$apiKey);
		$response 	 = self::callAPI($url, $fields, $authHeader);
        $result= json_decode($response);
		return  $result;
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
    $backendMethod,
    $frontendMethod,
    $registrationMethod,
    $autoCreateLimit
    ) {
    $url = TwoFAConstants::PLUGIN_PORTAL_HOSTNAME . "/api/tracking";
    $customerKey = "";
    $apiKey = "";

    // $timeStamp = time();
    $pluginName = TwoFAConstants::SECURITY_SUITE_NAME;
    $pluginVersion = TwoFAUtility::getModuleVersion();
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
