<?php
namespace MiniOrange\AdminLogs\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use Psr\Log\LoggerInterface;
use Magento\User\Model\UserFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class LoginSuccess implements ObserverInterface
{
    protected $logger;
    protected $_adminLogsFactory;
    protected $_helper;
    protected $userFactory;
    protected $scopeConfig;
    protected $dateTime;
    protected $request;
    protected $adminSession;

    public function __construct(
        LoggerInterface $logger,
        \MiniOrange\AdminLogs\Model\AdminLoginLogsFactory $adminLogsFactory,
        \MiniOrange\AdminLogs\Helper\Data $helper,
        UserFactory $userFactory,
        ScopeConfigInterface $scopeConfig,
        DateTime $dateTime,
        RequestInterface $request,
        AdminSession $adminSession
    ) {
        $this->logger = $logger;
        $this->_adminLogsFactory = $adminLogsFactory;
        $this->_helper = $helper;
        $this->userFactory = $userFactory;
        $this->scopeConfig = $scopeConfig;
        $this->dateTime = $dateTime;
        $this->request = $request;
        $this->adminSession = $adminSession;
    }

    public function execute(Observer $observer)
    {
        try {
            $adminActionLogEnabled = $this->scopeConfig->getValue(
                'miniorange/adminlogs/configuration/admin_action_log'
            );
            
            if (!$adminActionLogEnabled) {
                $this->logger->info('LoginSuccess observer: Admin Action Log is disabled, skipping login log');
                return;
            }
            
            $event = $observer->getEvent();
            $result = $event->getData('result');

            if ($result === false) {
                $this->logger->info('LoginSuccess observer: Login failed, skipping email');
                return;
            }

            $user = null;

            if ($event->getUser()) {
                $user = $event->getUser();
            } elseif ($event->getData('user')) {
                $user = $event->getData('user');
            } elseif ($event->getData('username')) {
                $username = $event->getData('username');
                $user = $this->userFactory->create()->loadByUsername($username);
            }

            if (!$user || !$user->getId()) {
                $this->logger->warning('LoginSuccess observer: No user found for successful login');
                return;
            }

            // Check if the request is coming from a user save/edit action
            $actionName = $this->request->getActionName();
            $controllerName = $this->request->getControllerName();
            $moduleName = $this->request->getModuleName();
            $fullActionName = $this->request->getFullActionName();
            
            if (($moduleName == 'admin' || $moduleName == 'user') && 
                ($controllerName == 'user' || $controllerName == 'role') &&
                ($actionName == 'save' || $actionName == 'edit' || $actionName == 'roleGrid' || 
                 $actionName == 'rolesGrid' || $actionName == 'delete' || $actionName == 'massDelete' ||
                 strpos($fullActionName, 'user') !== false || strpos($fullActionName, 'role') !== false)) {
                $this->logger->info('LoginSuccess observer: Skipping login log - user/role update operation detected. Module: ' . $moduleName . ', Controller: ' . $controllerName . ', Action: ' . $actionName);
                return;
            }
            
            $existingSessionId = $this->_helper->getSessionValue('admin_session_id');
            $currentLoggedInUser = $this->adminSession->getUser();
            
            if ($existingSessionId && $currentLoggedInUser && 
                $currentLoggedInUser->getId() == $user->getId()) {
                $recentLoginTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
                $recentLogs = $this->_adminLogsFactory->create()->getCollection()
                    ->addFieldToFilter('username', $user->getUserName())
                    ->addFieldToFilter('action', AdminLogsConstants::ACTION_LOGIN)
                    ->addFieldToFilter('status', AdminLogsConstants::STATUS_SUCCESS)
                    ->addFieldToFilter('logged_at', ['gteq' => $recentLoginTime])
                    ->setOrder('logged_at', 'DESC')
                    ->setPageSize(1);
                
                if ($recentLogs->getSize() > 0) {
                    $this->logger->info('LoginSuccess observer: Skipping login log - recent login already recorded for username: ' . $user->getUserName());
                    return;
                }
            }

            // Check if user is within the first 10 admin users
            if (!$this->_helper->canUserBeLogged($user->getId())) {
                $this->logger->info('LoginSuccess observer: User ID ' . $user->getId() . ' is not within the first 10 admin users. Skipping log creation.');
                return;
            }

            $ipAddress = $this->_helper->getIpAddress();
            $userAgent = $this->_helper->getUserAgent();
            
            
            $location = $this->_helper->getLocationFromIp($ipAddress);

            $log = $this->_adminLogsFactory->create();
            $log->setUsername($user->getUserName());
            $log->setIpAddress($ipAddress);
            $log->setUserAgent($userAgent);
            $log->setLocation($location);
            $log->setAction(AdminLogsConstants::ACTION_LOGIN);
            $log->setFullName($user->getFirstname() . ' ' . $user->getLastname());
            $log->setStatus(AdminLogsConstants::STATUS_SUCCESS);
            $log->setType(AdminLogsConstants::ACTION_LOGIN);
            $log->setLoggedAt($this->dateTime->gmtDate());
            
            $sessionId = uniqid('admin_session_', true) . '_' . time();
            $log->setSessionId($sessionId);
            $this->_helper->setSessionValue('admin_session_id', $sessionId);
            $this->logger->info('Generated admin session ID: ' . $sessionId);
            
            $log->save();

            try {
            	$this->_helper->setSessionValue('failed_login_attempts', 0);
            	$this->logger->info('Reset failed login attempts counter after successful login');
            } catch (\Exception $e) {
            	$this->logger->warning('Could not reset failed login attempts counter: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->logger->error('LoginSuccess observer error: ' . $e->getMessage());
        }
    }
}
