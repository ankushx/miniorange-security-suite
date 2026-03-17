<?php

namespace MiniOrange\AdminLogs\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use MiniOrange\AdminLogs\Helper\Data;
use MiniOrange\AdminLogs\Model\AdminLoginLogsFactory;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use Magento\User\Model\UserFactory;
use Magento\Framework\App\ResponseInterface;
class LoginFailed implements ObserverInterface
{
    protected $logger;
    protected $adminLogsFactory;
    protected $helper;
    protected $userFactory;
    protected $scopeConfig;
    protected $dateTime;

    protected $response;
    public function __construct(
        LoggerInterface $logger,
        AdminLoginLogsFactory $adminLogsFactory,
        Data $helper,
        UserFactory $userFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        DateTime $dateTime,
        ResponseInterface $response
    ) {
        $this->logger = $logger;
        $this->adminLogsFactory = $adminLogsFactory;
        $this->helper = $helper;
        $this->userFactory = $userFactory;
        $this->scopeConfig = $scopeConfig;
        $this->dateTime = $dateTime;
        $this->response = $response;
    }

    public function execute(Observer $observer)
    {
        
        try {
            $adminActionLogEnabled = $this->scopeConfig->getValue(
                'miniorange/adminlogs/configuration/admin_action_log'
            );
            
            if (!$adminActionLogEnabled) {
                $this->logger->info('LoginFailed observer: Admin Action Log is disabled, skipping login log');
                return;
            }

            // Check if response is redirecting to 2FA
            if ($this->response->isRedirect()) {
                $location = $this->response->getHeader('Location')->getFieldValue();
                if (strpos($location, 'motwofa') !== false || strpos($location, 'mobruteforce') !== false || strpos($location, 'Backdoor') !== false) {
                    $this->logger->info('LoginFailed observer: Redirecting to 2FA, skipping failed login log');
                    return;
                }
            }
        
            
            $event = $observer->getEvent();
            $username = $event->getUserName();
            $exception = $event->getException();

            if (!$username) {
                $this->logger->warning('LoginFailed: No username found in event data');
                return;
            }

            $user = $this->userFactory->create()->loadByUsername($username);
            $userId = $user && $user->getId() ? $user->getId() : null;
            $fullName = ($user && $user->getId()) ? ($user->getFirstname() . ' ' . $user->getLastname()) : 'Unknown User';

            // Check if user is within the first 10 admin users
            if (!$userId || !$this->helper->canUserBeLogged($user->getId(), $user->getUsername())) {
                $this->logger->info('LoginFailed observer: User ID ' . ($userId ?: 'unknown') . ' is not within the first 10 admin users. Skipping log creation.');
                return;
            }

            $ipAddress = $this->helper->getIpAddress();
            $userAgent = $this->helper->getUserAgent();
            
            $location = $this->helper->getLocationFromIp($ipAddress);

            $log = $this->adminLogsFactory->create();
            $log->setUsername($username);
            $log->setIpAddress($ipAddress);
            $log->setUserAgent($userAgent);
            $log->setLocation($location);
            $log->setAction(AdminLogsConstants::ACTION_LOGIN);
            $log->setFullName($fullName);
            $log->setStatus(AdminLogsConstants::STATUS_FAILURE);
            $log->setType(AdminLogsConstants::ACTION_LOGIN);
            $log->setMessage($exception ? $exception->getMessage() : 'Invalid credentials');
            $log->setLoggedAt($this->dateTime->gmtDate());
            $log->save();

        } catch (\Exception $e) {
            $this->logger->error('Failed to record admin login failure: ' . $e->getMessage());
        }
    }
}
