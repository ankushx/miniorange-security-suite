<?php

namespace MiniOrange\TwoFA\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use MiniOrange\TwoFA\Controller\Actions\AdminLoginAction;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use Magento\Framework\Event\Observer;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use MiniOrange\TwoFA\Helper\TwoFAUtility;
use Psr\Log\LoggerInterface;

/**
 * This is our main Observer class. Observer class are used as a callback
 * function for all of our events and hooks. This particular observer
 * class is being used to check if a SAML request or response was made
 * to the website. If so then read and process it. Every Observer class
 * needs to implement ObserverInterface.
 */
class TwoFAObserver implements ObserverInterface
{
    private $requestParams =  [
        'option'
    ];

    private $messageManager;
    private $logger;
    private $twofautility;
    private $adminLoginAction;

    private $currentControllerName;
    private $currentActionName;
//    private $requestInterface;
    private $request;

    public function __construct(
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
        AdminLoginAction $adminLoginAction,
        Http $httpRequest,
        RequestInterface $request
    ) {
        //You can use dependency injection to get any class this observer may need.
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->twofautility = $twofautility;
        $this->adminLoginAction = $adminLoginAction;
        $this->currentControllerName = $httpRequest->getControllerName();
        $this->currentActionName = $httpRequest->getActionName();
        $this->request = $request;
    }

    /**
     * This function is called as soon as the observer class is initialized.
     * Checks if the request parameter has any of the configured request
     * parameters and handles any exception that the system might throw.
     *
     * @param $observer
     */
    public function execute(Observer $observer)
    {
        $keys             = array_keys($this->request->getParams());
        $operation         = array_intersect($keys, $this->requestParams);


        try {
            $params = $this->request->getParams(); // get params
            $postData = $this->request->getPost(); // get only post params
            $isTest = $this->twofautility->getStoreConfig(TwoFAConstants::IS_TEST);

            // request has values then it takes priority over others
            if (count($operation) > 0) {
                $this->_route_data(array_values($operation)[0], $observer, $params, $postData);

            }
        } catch (\Exception $e) {
            if ($isTest) { // show a failed validation screen
                $this->testAction->setOAuthException($e)->setHasExceptionOccurred(true)->execute();
            }
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
    }


    /**
     * Route the request data to appropriate functions for processing.
     * Check for any kind of Exception that may occur during processing
     * of form post data. Call the appropriate action.
     *
     * @param $op //refers to operation to perform
     * @param $observer
     */
    private function _route_data($op, $observer, $params, $postData)
    {
        switch ($op) {
            case $this->requestParams[0]:
                if ($params['option']==TwoFAConstants::LOGIN_ADMIN_OPT) {
                    $this->adminLoginAction->execute();
                }
                break;
        }
    }
}
