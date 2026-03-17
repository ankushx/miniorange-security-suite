<?php

namespace MiniOrange\TwoFA\Controller\Actions;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseFactory;
use MiniOrange\TwoFA\Controller\Actions\BaseAction;
use MiniOrange\TwoFA\Helper\TwoFAUtility;

/**
 * This class is called to log the customer user in. RelayState and
 * user are set separately. This is a simple class.
 */
class CustomerLoginAction extends BaseAction implements HttpPostActionInterface
{
    private $user;
    private $customerSession;
    private $responseFactory;

    public function __construct(
        Context $context,
        twofautility $twofautility,
        Session $customerSession,
        ResponseFactory $responseFactory
    ) {
        //You can use dependency injection to get any class this observer may need.
            $this->customerSession = $customerSession;
            $this->responseFactory = $responseFactory;
            parent::__construct($context, $twofautility);
    }

    /**
     * Execute function to execute the classes function.
     */
    public function execute()
    {
        $relayState = $this->twofautility->getBaseUrl(). "customer/account";
        $this->twofautility->log_debug("CustomerLoginAction: execute");
        $this->customerSession->setCustomerAsLoggedIn($this->user);
        return $this->getResponse()->setRedirect($this->twofautility->getUrl($relayState))->sendResponse();
    }


     /** Setter for the user Parameter
      * @param $user
      * @return CustomerLoginAction
      */
    public function setUser($user)
    {
        $this->twofautility->log_debug("CustomerLoginAction: setUser");
        $this->user = $user;
        return $this;
    }
}
