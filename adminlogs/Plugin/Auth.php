<?php
namespace MiniOrange\AdminLogs\Plugin;

use Magento\Backend\Model\Auth as AdminAuth;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use Psr\Log\LoggerInterface;

class Auth
{
    protected $logger;
    protected $_adminLogsFactory;
    protected $_helper;
    protected $dateTime;
    protected $scopeConfig;
    protected $resourceConnection;

    public function __construct(
        LoggerInterface $logger,
        \MiniOrange\AdminLogs\Model\AdminLoginLogsFactory $adminLogsFactory,
        \MiniOrange\AdminLogs\Helper\Data $helper,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
        $this->_adminLogsFactory = $adminLogsFactory;
        $this->_helper = $helper;
        $this->dateTime = $dateTime;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
    }

    public function beforeLogout(AdminAuth $subject)
    {
        try {
            $adminActionLogEnabled = $this->scopeConfig->getValue(
                'miniorange/adminlogs/configuration/admin_action_log'
            );
            
            if (!$adminActionLogEnabled) {
                $this->logger->info('Auth plugin: Admin Action Log is disabled, skipping logout log');
                return null;
            }
            
            $user = $subject->getAuthStorage()->getUser();
            if ($user) {
                $sessionId = $this->_helper->getSessionValue('admin_session_id');
                
                if (!$this->_helper->canUserBeLogged($user->getId())) {
                    $this->logger->info('Auth plugin: User ID ' . $user->getId() . ' is not within the allowed admin users. Skipping logout log creation.');
                } else {
                    $ipAddress = $this->_helper->getIpAddress();
                    $userAgent = $this->_helper->getUserAgent();
                    $location = $this->_helper->getLocationFromIp($ipAddress);
                    $username = $user->getUserName();

                    // Create logout record
                    $log = $this->_adminLogsFactory->create();
                    $log->setUsername($username);
                    $log->setFullName($user->getFirstname() . ' ' . $user->getLastname());
                    $log->setIpAddress($ipAddress);
                    $log->setUserAgent($userAgent);
                    $log->setLocation($location);
                    $log->setAction(AdminLogsConstants::ACTION_LOGOUT);
                    $log->setStatus(AdminLogsConstants::STATUS_SUCCESS);
                    $log->setType(AdminLogsConstants::ACTION_LOGOUT);
                    $log->setLoggedAt($this->dateTime->gmtDate());
                    if ($sessionId) {
                        $log->setSessionId($sessionId);
                    }

                    $log->save();
                }

                // Handle session cleanup regardless of user limit
                if ($sessionId) {
                    $this->_helper->setSessionValue('admin_session_id', null);
                    $this->_helper->setSessionValue('last_visit_id', null);
                    $this->_helper->setSessionValue('last_page_url', null);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return null;
    }
}
