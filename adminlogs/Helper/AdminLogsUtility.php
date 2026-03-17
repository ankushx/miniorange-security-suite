<?php

namespace MiniOrange\AdminLogs\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Framework\UrlInterface;

class AdminLogsUtility extends AbstractHelper
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var DateTime
     */
    protected $dateTime;

    /**
     * @var AuthSession
     */
    protected $authSession;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param ProductMetadataInterface $productMetadata
     * @param DateTime $dateTime
     * @param AuthSession $authSession
     * @param UrlInterface $urlInterface
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        ProductMetadataInterface $productMetadata,
        DateTime $dateTime,
        AuthSession $authSession,
        UrlInterface $urlInterface
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->productMetadata = $productMetadata;
        $this->dateTime = $dateTime;
        $this->authSession = $authSession;
        $this->urlInterface = $urlInterface;
    }

    /**
     * This function checks if a value is set or
     * empty. Returns true if value is empty
     *
     * @return True or False
     * @param $value //references the variable passed.
     */
    public function isBlank($value)
    {
        if (! isset($value) || empty($value)) {
            return true;
        }
        return false;
    }

    /**
     * Set store configuration value
     *
     * @param string $path Configuration path
     * @param mixed $value Configuration value
     * @param string $scope Scope type (default: 'default')
     * @param int $scopeId Scope ID (default: 0)
     * @return void
     */
    public function setStoreConfig($path, $value, $scope = 'default', $scopeId = 0)
    {
        try {
            $this->configWriter->save($path, $value, $scope, $scopeId);
        } catch (\Exception $e) {
            $this->_logger->error('Error setting store config: ' . $e->getMessage());
        }
    }

    public function getStoreConfig($path, $scopeType = \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $scopeCode = null)
    {
        return $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
    }

    /**
     * Flush cache
     *
     * @return void
     */
    public function flushCache()
    {
        try {
            $this->cacheTypeList->cleanType('config');
        } catch (\Exception $e) {
            $this->_logger->error('Error flushing cache: ' . $e->getMessage());
        }
    }

    //This function is used to get the product version
    public function getProductVersion(){
        return  $this->productMetadata->getVersion(); 
    }

    //This function is used to get the edition of the product
    public function getEdition(){
        return $this->productMetadata->getEdition() == 'Community' ? 'Magento Open Source':'Adobe Commerce Enterprise/Cloud';
    }

    //This function is used to get the current date
    public function getCurrentDate(){

        return $this->dateTime->gmtDate('Y-m-d H:i');
    }

    /**
     * Get the Current Admin User who is logged in
     */
    public function getCurrentAdminUser()
    {
        return $this->authSession->getUser();
    }

    /**
     * Function to get the sites Base URL.
     */
    public function getBaseUrl()
    {
        return is_null($this->getStoreConfig(AdminLogsConstants::BASE))?$this->urlInterface->getBaseUrl():$this->getStoreConfig(AdminLogsConstants::BASE);
    }
}