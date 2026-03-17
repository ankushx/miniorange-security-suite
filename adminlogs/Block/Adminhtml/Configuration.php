<?php
namespace MiniOrange\AdminLogs\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use MiniOrange\AdminLogs\Helper\Data;
use MiniOrange\AdminLogs\Helper\AdminLogsUtility;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;
use MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs\CollectionFactory;
use MiniOrange\AdminLogs\Model\ActiveSessions\DataProvider as ActiveSessionsDataProvider;
use MiniOrange\AdminLogs\Model\ConcurrentSessions\DataProvider as ConcurrentSessionsDataProvider;

class Configuration extends Template
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var CollectionFactory
     */
    protected $loginLogsCollectionFactory;

    /**
     * @var ActiveSessionsDataProvider
     */
    protected $activeSessionsDataProvider;

    /**
     * @var ConcurrentSessionsDataProvider
     */
    protected $concurrentSessionsDataProvider;

    /**
     * @var AdminLogsUtility
     */
    protected $adminLogsUtility;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param Data $helper
     * @param AdminLogsUtility $adminLogsUtility
     * @param CollectionFactory $loginLogsCollectionFactory
     * @param ActiveSessionsDataProvider $activeSessionsDataProvider
     * @param ConcurrentSessionsDataProvider $concurrentSessionsDataProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        Data $helper,
        AdminLogsUtility $adminLogsUtility,
        CollectionFactory $loginLogsCollectionFactory,
        ActiveSessionsDataProvider $activeSessionsDataProvider,
        ConcurrentSessionsDataProvider $concurrentSessionsDataProvider,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->helper = $helper;
        $this->adminLogsUtility = $adminLogsUtility;
        $this->loginLogsCollectionFactory = $loginLogsCollectionFactory;
        $this->activeSessionsDataProvider = $activeSessionsDataProvider;
        $this->concurrentSessionsDataProvider = $concurrentSessionsDataProvider;
        parent::__construct($context, $data);
    }

    /**
     * Get config value
     *
     * @param string $path
     * @return mixed
     */
    public function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get form action URL
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getUrl('adminlogs/configuration/save');
    }

    /**
     * Get login/logout logs collection
     *
     * @param int $limit
     * @param int $offset
     * @return \MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs\Collection
     */
    public function getLoginLogsCollection($limit = 0, $offset = 0)
    {
        $collection = $this->loginLogsCollectionFactory->create();
        $collection->setOrder('logged_at', 'DESC');
        
        if ($limit > 0) {
            $collection->setPageSize($limit);
            $collection->setCurPage(($offset / $limit) + 1);
        }
        
        return $collection;
    }

    /**
     * Get total count of login/logout logs
     *
     * @return int
     */
    public function getLoginLogsCount()
    {
        $collection = $this->loginLogsCollectionFactory->create();
        return $collection->getSize();
    }

    /**
     * Get active sessions data provider
     *
     * @return ActiveSessionsDataProvider
     */
    public function getActiveSessionsDataProvider()
    {
        return $this->activeSessionsDataProvider;
    }
    
    
    /**
     * Get concurrent sessions data provider
     *
     * @return ConcurrentSessionsDataProvider
     */
    public function getConcurrentSessionsDataProvider()
    {
        return $this->concurrentSessionsDataProvider;
    }
    
    /**
     * Get active sessions collection
     *
     * @return \MiniOrange\AdminLogs\Model\ResourceModel\ActiveSessions\Collection
     */
    public function getActiveSessionsCollection()
    {
        return $this->activeSessionsDataProvider->getCollection();
    }
    
    /**
     * Get concurrent sessions collection
     *
     * @return \MiniOrange\AdminLogs\Model\ResourceModel\ConcurrentSessions\Collection
     */
    public function getConcurrentSessionsCollection()
    {
        return $this->concurrentSessionsDataProvider->getCollection();
    }

    /**
     * Get login logs collection with pagination
     */
    public function getLoginLogCollection($page = 1, $pageSize = null)
    {
        if ($pageSize === null) {
            $pageSize = $this->getRequest()->getParam('entries_per_page', 10);
        }
        
        $collection = $this->loginLogsCollectionFactory->create();
        $collection->setOrder('logged_at', 'DESC');
        
        if ($pageSize > 0) {
            $collection->setPageSize($pageSize);
            $collection->setCurPage($page);
        }
        
        return $collection;
    }

    /**
     * Get current page number from request
     */
    public function getCurrentPage()
    {
        return (int) $this->getRequest()->getParam('p', 1);
    }

    /**
     * Get entries per page from request
     */
    public function getEntriesPerPage()
    {
        return (int) $this->getRequest()->getParam('entries_per_page', 10);
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return false;
    }

    /**
     * Check if login/logout log limit has been reached
     *
     * @return array
     */
    public function checkLoginLogoutLogLimit()
    {
        return $this->helper->checkLoginLogoutLogLimit();
    }

    /**
     * Check if data has been added
     *
     * @return mixed
     */
    public function checkDataAdded()
    {
        return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::DATA_ADDED);
    }

    /**
     * Set the data added flag to 1
     */
    public function dataAdded()
    {
        $this->adminLogsUtility->setStoreConfig(AdminLogsConstants::DATA_ADDED, 1);
        $this->adminLogsUtility->flushCache();
    }

    /**
     * Get timestamp
     *
     * @return int
     */
    public function getTimeStamp()
    {
        if ($this->adminLogsUtility->getStoreConfig(AdminLogsConstants::TIME_STAMP) == null) {
            $this->adminLogsUtility->setStoreConfig(AdminLogsConstants::TIME_STAMP, time());
            $this->adminLogsUtility->flushCache();
            return time();
        }
        return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::TIME_STAMP);
    }

    /**
     * Get the Current Admin user from session
     *
     * @return array|null
     */
    public function getCurrentAdminUser()
    {
        $user = $this->adminLogsUtility->getCurrentAdminUser();
        if ($user && $user->getId()) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUserName(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname()
            ];
        }
        return null;
    }

    /**
     * Get/Create Base URL of the site
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return is_null($this->adminLogsUtility->getStoreConfig(AdminLogsConstants::BASE)) 
            ? $this->adminLogsUtility->getBaseUrl() 
            : $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::BASE);
    }

    /**
     * Get the miniOrange customer Email
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::EMAIL_ID);
    }

    /**
     * Get product edition
     *
     * @return string
     */
    public function getEdition()
    {
        return $this->adminLogsUtility->getEdition();
    }

    /**
     * Get product version
     *
     * @return string
     */
    public function getProductVersion()
    {
        return $this->adminLogsUtility->getProductVersion();
    }

    /**
     * Get current date
     *
     * @return string
     */
    public function getCurrentDate()
    {
        return $this->adminLogsUtility->getCurrentDate();
    }
    
}