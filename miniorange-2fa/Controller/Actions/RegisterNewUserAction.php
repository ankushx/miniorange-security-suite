<?php

namespace MiniOrange\TwoFA\Controller\Actions;

use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use MiniOrange\TwoFA\Helper\Exception\PasswordMismatchException;
use MiniOrange\TwoFA\Helper\Exception\AccountAlreadyExistsException;
use MiniOrange\TwoFA\Helper\Exception\InvalidEmailException;
use MiniOrange\TwoFA\Helper\Exception\TransactionLimitExceededException;

/**
 * Handles registration of new user account. This is called when the
 * registration form is submitted. Process the credentials and
 * information provided by the admin.
 *
 * This action class first checks if a customer exists with the email
 * address provided. If no customer exists then start the validation process.
 */
class RegisterNewUserAction extends BaseAdminAction
{
    private $REQUEST;
    private $loginExistingUserAction;

	public function __construct(\Magento\Backend\App\Action\Context $context,
                                \Magento\Framework\View\Result\PageFactory $resultPageFactory,
                                \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
                                \Magento\Framework\Message\ManagerInterface $messageManager,
                                \Psr\Log\LoggerInterface $logger,
                                \MiniOrange\TwoFA\Controller\Actions\LoginExistingUserAction $loginExistingUserAction)
    {
        //You can use dependency injection to get any class this observer may need.
        parent::__construct($context,$resultPageFactory,$twofautility,$messageManager,$logger);
        $this->loginExistingUserAction = $loginExistingUserAction;
    }


	/**
	 * Execute function to execute the classes function.
     *
	 * @throws \Exception
	 */
	public function execute()
	{

        $this->logger->debug("RegisterNewUserAction: execute()");
        $email = $this->REQUEST['email'];
        $password = $this->REQUEST['password'];
        $confirmPassword = isset($this->REQUEST['confirmPassword']) ? $this->REQUEST['confirmPassword'] : null; // Check if confirmPassword is set
        
        $this->checkIfRequiredFieldsEmpty(['email' => $email, 'password' => $password]);
        
        $companyName = '';
        $firstName = '';
        $lastName = '';

    // Check if confirmPassword is set and non-empty
    if ($confirmPassword !== null && !empty($confirmPassword)) {
        if (strcasecmp($confirmPassword, $password) != 0) {
            throw new PasswordMismatchException;
        }
    }

        $result = $this->checkIfUserExists($email);
        if (strcasecmp($result['status'], 'CUSTOMER_NOT_FOUND') == 0) {
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_EMAIL,$email);
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_NAME,$companyName);
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_FNAME,$firstName);
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_LNAME,$lastName);
            $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS,TwoFAConstants::STATUS_COMPLETE_LOGIN);
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_COUNT ,0);

            $this->startVerificationProcess($result, $email, $companyName, $firstName, $lastName, $password);
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
        } else {
             //     //already register
             if($confirmPassword)
             {
                 throw new \Exception("You already have an account, please login to continue");
             }
            $this->twofautility->setStoreConfig(TwoFAConstants::CUSTOMER_EMAIL,$email);
            $this->loginExistingUserAction
                 ->setRequestParam($this->REQUEST)
                 ->execute();
        }

        
    }


   /**
     * Function is used to make a cURL call which will check
     * if a user exists with the given credentials. If a user
     * is found then his details are fetched automatically and
     * saved.
     *
     * @param $email
     */
    private function checkIfUserExists($email)
    {
        $this->twofautility->log_debug("RegisterNewUserAction: checkIfUserExists");
        $content = Curl::check_customer($email);
        return json_decode($content, true);
    }



    private function startVerificationProcess($result,$email,$companyName,$firstName,$lastName,$password)
    {
        $this->logger->debug("RegisterNewUserAction: StartVerificationProcess");

        if ($this->REQUEST['confirmPassword']=='') {
          
            throw new \Exception("Account does not exist");
        } else {
            $this->createUserInMiniorange($result, $email, $companyName, $firstName, $lastName, $password);
        }
    }


    private function createUserInMiniorange($result,$email,$companyName,$firstName,$lastName,$pass)
    {
        $this->logger->debug("In createUserInMiniorange()");
        $result = Curl::create_customer($email, $companyName,$pass, '', $firstName, $lastName);
        $result= json_decode($result, true);
        $this->logger->debug(print_r($result,true));
        if (strcasecmp($result['status'], 'SUCCESS') == 0) {
            $content = Curl::get_customer_key($email, $pass);
            $customerKey = json_decode($content, true);
            $this->configureUserInMagento($result,$customerKey);
        }
        elseif(strcasecmp($result['status'], 'CUSTOMER_USERNAME_ALREADY_EXISTS') == 0)
        {
            $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, '');
            throw new AccountAlreadyExistsException;
        }
        elseif(strcasecmp($result['status'], 'TRANSACTION_LIMIT_EXCEEDED')==0)
        {
            $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, '');
            throw new TransactionLimitExceededException;
        }
        elseif((strcasecmp($result['status'], 'INVALID_EMAIL_QUICK_EMAIL')==0) || (strcasecmp($result['status'], 'INVALID_EMAIL')==0))
        {
            $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, '');
            throw new InvalidEmailException;
        }

    }

    private function configureUserInMagento($result,$customerKey)
    {
        $this->logger->debug("In configureUserInMagento()");
        $this->twofautility->setStoreConfig(TwoFAConstants::SAMLSP_KEY, $result['id']);
        $this->twofautility->setStoreConfig(TwoFAConstants::API_KEY, $result['apiKey']);
        $this->twofautility->setStoreConfig(TwoFAConstants::TOKEN, $result['token']);
        $this->twofautility->setStoreConfig(TwoFAConstants::REG_STATUS, TwoFAConstants::STATUS_COMPLETE_LOGIN);
        $this->getMessageManager()->addSuccessMessage(TwoFAMessages::REG_SUCCESS);
    }





	/** Setter for the request Parameter */
    public function setRequestParam($request)
    {
		$this->REQUEST = $request;
		return $this;
    }
}

