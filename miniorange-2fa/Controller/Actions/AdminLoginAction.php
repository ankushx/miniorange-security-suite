<?php

namespace MiniOrange\TwoFA\Controller\Actions;

use MiniOrange\TwoFA\Helper\TwoFAConstants;
use Magento\Framework\App\Action\HttpPostActionInterface;
use MiniOrange\TwoFA\Controller\Actions\BaseAction;


/**
 * This class is called from the observer class to log the
 * admin user in. Read the appropriate values required from the
 * requset parameter passed along with the redirect to log the user in.
 * <b>NOTE</b> : Admin ID, Session Index and relaystate are passed
 *              in the request parameter.
 */
class AdminLoginAction extends BaseAction implements HttpPostActionInterface
{
    private $relayState;
    private $user;
    private $adminSession;
    private $cookieManager;
    private $adminConfig;
    private $cookieMetadataFactory;
    private $adminSessionManager;
    private $urlInterface;
    private $userFactory;
    protected $_resultPage;
    private $request;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
        \Magento\Backend\Model\Auth\Session $adminSession,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Backend\Model\Session\AdminConfig $adminConfig,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Security\Model\AdminSessionsManager $adminSessionManager,
        \Magento\Backend\Model\UrlInterface $urlInterface,
        \Magento\User\Model\UserFactory $userFactory,
        \Magento\Framework\App\RequestInterface $request
    ) {
        //You can use dependency injection to get any class this observer may need.
        $this->adminSession = $adminSession;
        $this->cookieManager = $cookieManager;
        $this->adminConfig =$adminConfig;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->adminSessionManager = $adminSessionManager;
        $this->urlInterface = $urlInterface;
        $this->userFactory = $userFactory;
        $this->request = $request;
        parent::__construct($context, $twofautility);
    }

    /**
     * Execute function to execute the classes function.
     */
    public function execute()
    {   
            /**
             * Check if valid request by checking the SESSION_INDEX in the request
             * and the session index in the database. If they don't match then return
             * This is done to take care of the backdoor that this URL creates if no
             * session index is checked
             */

             $this->twofautility->log_debug("AdminLoginAction: execute");
            $params = $this->request->getParams(); // get request params
            $user = $this->userFactory->create()->load($params['userid']);
            $this->adminSession->setUser($user);
            $this->adminSession->processLogin();

        if ($this->adminSession->isLoggedIn()) {
            $cookieValue = $this->adminSession->getSessionId();

            if ($cookieValue) {
                // generate admin cookie value - this is required to create a valid admin session
                $cookiePath = str_replace('autologin.php', 'index.php', $this->adminConfig->getCookiePath());
                $cookieMetadata = $this->cookieMetadataFactory->createPublicCookieMetadata()->setDuration(3600)
                                        ->setPath($cookiePath)->setDomain($this->adminConfig->getCookieDomain())
                                        ->setSecure($this->adminConfig->getCookieSecure())
                                        ->setHttpOnly($this->adminConfig->getCookieHttpOnly());
                $this->cookieManager->setPublicCookie($this->adminSession->getName(), $cookieValue, $cookieMetadata);
                $this->adminSessionManager->processLogin();
            }
        }


            // get relayState URL and redirect the user to appropriate URL. Log the user to
            // dashboard by default.
            $path = $this->urlInterface->getStartupPageUrl();
            $url = $this->urlInterface->getUrl($path);
            $url = str_replace('autologin.php', 'index.php', $url);

            return $this->resultRedirectFactory->create()->setUrl($url);
    }
}
