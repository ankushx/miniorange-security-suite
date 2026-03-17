<?php

namespace MiniOrange\TwoFA\Model;

use Magento\Customer\Model\Session;
use MiniOrange\TwoFA\Api\HeadlessApiInterface;
use MiniOrange\TwoFA\Helper\Exception\OtpSentFailureException;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;

/**
 * Headless API Model.
 */
class HeadlessApi implements HeadlessApiInterface
{
    /**
     * @var TwoFAUtility
     */
    private $twofautility;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @param TwoFAUtility $twofautility
     * @param Session $customerSession
     */
    public function __construct(
        TwoFAUtility $twofautility,
        Session $customerSession
    ) {
        $this->twofautility = $twofautility;
        $this->customerSession = $customerSession;
    }

    /***
     * rest api to send otp based on auth type "OOS","OOSE","OOE"
     * @return array
     */
    public function sendOtpApi(string $username, string $phone, string $authType): array
    {

        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $this->twofautility->log_debug("HeadlessApi.php :invalid email");
            $result = [
                "status" => "ERROR",
                "message" =>
                    "Invalid username. Please provide a valid email address.",
            ];
            return ["" => $result];
        }
        // Fetch the last OTP sent time and resend count
        $lastOtpSentTime = $this->twofautility->getSessionValue('last_otp_sent_time');
        $resendCount = (int)($this->twofautility->getSessionValue('otp_resend_count') ?: 0);
        $currentTime = time();

        if ($resendCount > 0 && $lastOtpSentTime != null && ($currentTime - $lastOtpSentTime) < 60) { // 60 seconds delay
            $remainingTime = 60 - ($currentTime - $lastOtpSentTime);
            $this->twofautility->log_debug("CustomEMAIL: User needs to wait. Remaining time: $remainingTime seconds");

            $result = [
                'status' => 'ERROR',
                'message' => "OTP sent a few seconds ago. Please try again after $remainingTime second ",
            ];

            return ["" => $result];
        }

        $this->twofautility->log_debug("headless. authtype is $authType");
        $this->twofautility->log_debug("headless. email is  is $username");
        $this->twofautility->log_debug("headless. phone is  is $phone");

        // Phone number pattern for validation
        $phoneNumberPattern = '/^\+\d{1,4}\d{1,14}$/';

        $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $username);

        if (is_array($row) && count($row) > 0) {
            // Check if phone is empty or invalid format for OOS/OOSE methods
            $isPhoneInvalid = ($phone == '' || (($authType == 'OOS' || $authType == 'OOSE') && !preg_match($phoneNumberPattern, $phone)));
            
            if (($authType == 'OOS' || $authType == 'OOSE') && $isPhoneInvalid) {
                $row = $this->twofautility->getMoTfaUserDetails('miniorange_tfa_users', $username);

                if (is_array($row) && count($row) > 0) {
                    $phone = '+' . $row[0]['countrycode'] . $row[0]['phone'];
                    $this->twofautility->log_debug("HeadlessApi.php: Fetching phone number of already configured users from the database.");
                } else {
                    $sessionPhone = $this->twofautility->getSessionValue(TwoFAConstants::CUSTOMER_PHONE);
                    $countrycode = $this->twofautility->getSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE);
                    
                    if (!empty($sessionPhone) && !empty($countrycode)) {
                        $phone = '+' . $countrycode . $sessionPhone;
                    }
                }
            } else if (($authType) == '') {
                $twoFAMethod = $row[0]['active_method'];

                if (empty($twoFAMethod)) {
                    $idValue = $row[0]['id'];
                    $this->twofautility->deleteRowInTable('miniorange_tfa_users', 'id', $idValue);
                }
                if ($phone == '') {
                    $phone = $row[0]['phone'];
                    $countrycode = $row[0]['countrycode'];
                    $phone = '+' . $countrycode . $phone;

                    if (empty($phone)) {
                        $this->twofautility->deleteRowInTable('miniorange_tfa_users', 'id', $idValue);
                    }
                }

                $authType = $twoFAMethod;
            }
        } else {
            // If user doesn't exist in database, try to get phone from session for OOS/OOSE methods
            if (($authType == 'OOS' || $authType == 'OOSE') && ($phone == '' || !preg_match($phoneNumberPattern, $phone))) {
                $sessionPhone = $this->twofautility->getSessionValue(TwoFAConstants::CUSTOMER_PHONE);
                $countrycode = $this->twofautility->getSessionValue(TwoFAConstants::CUSTOMER_COUNTRY_CODE);
                
                if (!empty($sessionPhone) && !empty($countrycode)) {
                    $phone = '+' . $countrycode . $sessionPhone;
                    $this->twofautility->log_debug("HeadlessApi.php: Fetching phone number from session.");
                }
            }
        }


        $this->twofautility->log_debug("headless. authtype is $authType");
        $this->twofautility->log_debug("headless. email is  is $username");
        $this->twofautility->log_debug("headless. phone is  is $phone");

        // Validate phone number format for OOS/OOSE methods
        if (
            ($authType == "OOS" || $authType == "OOSE") &&
            !preg_match($phoneNumberPattern, $phone)
        ) {
            $this->twofautility->log_debug(
                "HeadlessApi.php :invalid phone number"
            );
            $result = [
                "status" => "ERROR",
                "message" =>
                    "Invalid phone number. Please provide a valid phone number with country code.",
            ];

            return [
                "" => $result
            ];
        }

        // Send OTP using MiniOrange gateway API call
        $authType = trim($authType);
        $this->twofautility->log_debug(
            "HeadlessApi.php :miniOrange gateway authType-" . $authType
        );

        $returnResponse = $this->twofautility->send_otp_using_miniOrange_gateway_usingApicall($authType, $username, $phone);

        // Ensure returnResponse is always an array
        if (!is_array($returnResponse)) {
            $this->twofautility->log_debug("HeadlessApi.php : Invalid response format from gateway");
            $returnResponse = [
                "status" => "ERROR",
                "message" => "Failed to send OTP. Please try again."
            ];
        }

        if (is_array($returnResponse) && isset($returnResponse["status"]) && $returnResponse["status"] == "SUCCESS") {
            $this->twofautility->log_debug("HeadlessApi.php : execute: :response success");
            $this->twofautility->setSessionValue('last_otp_sent_time', $currentTime);
            $this->twofautility->setSessionValue('otp_resend_count', $resendCount + 1);
            $this->twofautility->setSessionValue(TwoFAConstants::CUSTOMER_TRANSACTIONID, $returnResponse['txId'] ?? '');
        } elseif (is_array($returnResponse) && isset($returnResponse["status"]) && $returnResponse["status"] == "FAILED") {
            $this->twofautility->log_debug("headless.php : execute: response failed");
        } else {
            $this->twofautility->log_debug("HeadlessApi.php : execute: response failed or invalid format");
            if (!isset($returnResponse["status"])) {
                $returnResponse = [
                    "status" => "ERROR",
                    "message" => "Failed to send OTP. Please try again."
                ];
            }
        }

        return ["" => $returnResponse];

    }
}

