<?php
namespace MiniOrange\AdminLogs\Block;

use MiniOrange\AdminLogs\Helper\AdminLogsUtility;
use MiniOrange\AdminLogs\Helper\AdminLogsConstants;

class AdminLogs extends \Magento\Framework\View\Element\Template
{
    /**
     * @var AdminLogsUtility
     */
    protected $adminLogsUtility;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param AdminLogsUtility $adminLogsUtility
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        AdminLogsUtility $adminLogsUtility,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->adminLogsUtility = $adminLogsUtility;
    }

    public function isEnabled()
    {
        return false;
    }

    public function checkDataAdded(){
        return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::DATA_ADDED);

    }

    /**
     * Set the data added flag to 1
     */
    public function dataAdded(){
        $this->adminLogsUtility->setStoreConfig(AdminLogsConstants::DATA_ADDED,1);
        $this->adminLogsUtility->flushCache() ;

    }

    public function getTimeStamp(){
        if($this->adminLogsUtility->getStoreConfig(AdminLogsConstants::TIME_STAMP) == null){
            $this->adminLogsUtility->setStoreConfig(AdminLogsConstants::TIME_STAMP,time());
            $this->adminLogsUtility->flushCache();
            return time();
        }
        return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::TIME_STAMP);
    }

    public function getProductVersion(){
        return  $this->adminLogsUtility->getProductVersion(); 
    }

    public function getEdition(){
        return $this->adminLogsUtility->getEdition();
    }

    public function getCurrentDate(){
        return $this->adminLogsUtility->getCurrentDate();

    }

    /**
     * Get the Current Admin user from session
     */
    public function getCurrentAdminUser()
    {
        return $this->adminLogsUtility->getCurrentAdminUser();
    }

    /**
     * Get/Create Base URL of the site
     */
    public function getBaseUrl()
    {
        return is_null($this->adminLogsUtility->getStoreConfig(AdminLogsConstants::BASE))?$this->adminLogsUtility->getBaseUrl():$this->adminLogsUtility->getStoreConfig(AdminLogsConstants::BASE);
    }

    /**
     * This function retrieves the miniOrange customer Email
     * from the database. To be used on our template pages.
     */

     public function getCustomerEmail()
     {
         return $this->adminLogsUtility->getStoreConfig(AdminLogsConstants::EMAIL_ID);
     }
}
