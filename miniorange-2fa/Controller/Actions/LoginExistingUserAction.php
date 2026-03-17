<?php

namespace MiniOrange\TwoFA\Controller\Actions;

use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Helper\Exception\AccountAlreadyExistsException;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;

/**
 * Handles processing of customer login page form or the
 * registration page form if it was found that a user
 * exists in the system.
 *
 * The main function of this action class is to authenticate
 * the user credentials as provided by calling an API and
 * fetching all of the relevant information of the customer.
 * Store the key, token and email in the database.
 */
class LoginExistingUserAction extends BaseAdminAction
{
    private $REQUEST;

    /**
     * Execute function to execute the classes function.
     *
     * @throws \Exception
     */
    public function execute()
    {



            $this->checkIfRequiredFieldsEmpty(['email'=>$this->REQUEST,
                                                        'password'=>$this->REQUEST,
                                                                                            'submit'=>$this->REQUEST]);
            $email = $this->REQUEST['email'];
            $password = $this->REQUEST['password'];
            $submit = $this->REQUEST['submit'];
            $this->getCurrentCustomer($email, $password);
            $this->twofautility->flushCache();
    }


    /**
     * Function is used to make a cURL call which will fetch
     * the user's data based on the username password provided
     * by the user.
     *
     * @param $email
     * @param $password
     * @throws AccountAlreadyExistsException
     */
    private function getCurrentCustomer($email, $password)
    {
                $content = Curl::get_customer_key($email, $password);
                $customerKey = json_decode($content, true);
                $this->twofautility->log_debug("LogExistingUserAction: getCurrentCustomer");
        if (json_last_error() == JSON_ERROR_NONE && $customerKey!=NULL) {
            // set the user values in the database
                        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_EMAIL, $email);
                        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_KEY, $customerKey['id']);
                        $this->twofautility->setStoreConfig(TwoFAConstants::API_KEY, $customerKey['apiKey']);
                        $this->twofautility->setStoreConfig(TwoFAConstants::TOKEN, $customerKey['token']);
                        $this->twofautility->setStoreConfig(TwoFAConstants::TXT_ID, '');
                        $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, TwoFAConstants::STATUS_COMPLETE_LOGIN);
                        $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_COUNT ,0);

                        $timeStamp = $this->twofautility->getStoreConfig(TwoFAConstants::TIME_STAMP);
                        if($timeStamp == null){
                            $timeStamp = time();
                            $this->twofautility->setStoreConfig(TwoFAConstants::TIME_STAMP,$timeStamp);
                            $this->twofautility->flushCache();
                        }
                        $domain = $this->twofautility->getBaseUrl();
                        $currentAdminUser =  $this->twofautility->getCurrentAdminUser();  
                        $adminEmail = $currentAdminUser['email'];
                        $miniorangeAccountEmail = $email;
                        $pluginFirstPageVisit = '';
                        $environmentName = $this->twofautility->getEdition();
                        $environmentVersion = $this->twofautility->getProductVersion();
                        $freeInstalledDate = $this->twofautility->getCurrentDate();

                        Curl::submit_to_magento_team(
                            $timeStamp,
                           $adminEmail,
                               $domain,
               $miniorangeAccountEmail,
                 $pluginFirstPageVisit,
                      $environmentName,
                   $environmentVersion,
                    $freeInstalledDate,
                        '',
                       '',
                   '',
                      '');

                        $this->messageManager->addSuccessMessage(TwoFAMessages::REG_SUCCESS);
        } else {
            // wrong credentials provided or there was some error in fetching the user details

                      $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, TwoFAConstants::STATUS_VERIFY_LOGIN);
            throw new AccountAlreadyExistsException;
        }
    }

    /** Setter for the request Parameter
     * @param $request
     * @return LoginExistingUserAction
     */
    public function setRequestParam($request)
    {
                $this->REQUEST = $request;
                return $this;
    }
}
