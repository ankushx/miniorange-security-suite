<?php

namespace MiniOrange\TwoFA\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use MiniOrange\TwoFA\Helper\TwoFAConstants;

/**
 * This class contains functions to get and set the required data
 * from Magento database or session table/file or generate some
 * necessary values to be used in our module.
 */
class Data extends AbstractHelper
{

    protected $scopeConfig;
    protected $adminFactory;
    protected $customerFactory;
    protected $urlInterface;
    protected $configWriter;
    protected $assetRepo;
    protected $helperBackend;
    protected $frontendUrl;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\User\Model\UserFactory $adminFactory,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Backend\Helper\Data $helperBackend,
        \Magento\Framework\Url $frontendUrl
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->adminFactory = $adminFactory;
        $this->customerFactory = $customerFactory;
        $this->urlInterface = $urlInterface;
        $this->configWriter = $configWriter;
        $this->assetRepo = $assetRepo;
        $this->helperBackend = $helperBackend;
        $this->frontendUrl = $frontendUrl;
    }


    /**
     * Get base url of miniorange
     */
    public function getMiniOrangeUrl()
    {
        return TwoFAConstants::HOSTNAME;
    }

    /**
     * Function to extract data stored in the store config table.
     *
     * @param $config
     */
    public function getStoreConfig($config)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('miniorange/TwoFA/' . $config, $storeScope);
    }

    public function getStoreCustomConfig( $config )
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue( $config, $storeScope);
    }


    /**
     * Function to store data stored in the store config table.
     *
     * @param $config
     * @param $value
     */
    public function setStoreConfig($config, $value)
    {
        $this->configWriter->save('miniorange/TwoFA/' . $config, $value);
    }
    

    /**
     * This function is used to save user attributes to the
     * database and save it. Mostly used in the SSO flow to
     * update user attributes. Decides which user to update.
     *
     * @param $url
     * @param $value
     * @param $id
     * @param $admin
     * @throws \Exception
     */
    public function saveConfig($url, $value, $id, $admin)
    {
        $admin ? $this->saveAdminStoreConfig($url, $value, $id) : $this->saveCustomerStoreConfig($url, $value, $id);
    }


    /**
     * Function to extract information stored in the admin user table.
     *
     * @param $config
     * @param $id
     */
    public function getAdminStoreConfig($config, $id)
    {
        return $this->adminFactory->create()->load($id)->getData($config);
    }


    /**
     * This function is used to save admin attributes to the
     * database and save it. Mostly used in the SSO flow to
     * update user attributes.
     *
     * @param $url
     * @param $value
     * @param $id
     * @throws \Exception
     */
    private function saveAdminStoreConfig($url, $value, $id)
    {
        $data = [$url=>$value];
        $model = $this->adminFactory->create()->load($id)->addData($data);
        $model->setId($id)->save();
    }
    

    /**
     * Function to extract information stored in the customer user table.
     *
     * @param $config
     * @param $id
     */
    public function getCustomerStoreConfig($config, $id)
    {
        return $this->customerFactory->create()->load($id)->getData($config);
    }


    /**
     * This function is used to save customer attributes to the
     * database and save it. Mostly used in the SSO flow to
     * update user attributes.
     *
     * @param $url
     * @param $value
     * @param $id
     * @throws \Exception
     */
    private function saveCustomerStoreConfig($url, $value, $id)
    {
        $data = [$url=>$value];
        $model = $this->customerFactory->create()->load($id)->addData($data);
        $model->setId($id)->save();
    }


    /**
     * Function to get the sites Base URL.
     */
    public function getBaseUrl()
    {
        return  $this->urlInterface->getBaseUrl();
    }


    /**
     * Function get the current url the user is on.
     */
    public function getCurrentUrl()
    {
        return  $this->urlInterface->getCurrentUrl();
    }


    /**
     * Function to get the url based on where the user is.
     *
     * @param $url
     */
    public function getUrl($url, $params = [])
    {
        return  $this->urlInterface->getUrl($url, ['_query'=>$params]);
    }


    /**
     * Function to get the sites frontend url.
     *
     * @param $url
     */
    public function getFrontendUrl($url, $params = [])
    {
        return  $this->frontendUrl->getUrl($url, ['_query'=>$params]);
    }


    /**
     * Function to get the sites Issuer URL.
     */
    public function getIssuerUrl()
    {
        return $this->getBaseUrl() . TwoFAConstants::ISSUER_URL_PATH;
    }


    /**
     * Function to get the Image URL of our module.
     *
     * @param $image
     */
    public function getImageUrl($image)
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_IMAGES.$image);
    }


    /**
     * Get Admin CSS URL
     */
    public function getAdminCssUrl($css)
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_CSS.$css, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin JS URL
     */
    public function getAdminJSUrl($js)
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_JS.$js, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin Metadata Download URL
     */
    public function getMetadataUrl()
    {
        return $this->assetRepo->getUrl(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_METADATA, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin Metadata File Path
     */
    public function getMetadataFilePath()
    {
        return $this->assetRepo->createAsset(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_METADATA, ['area'=>'adminhtml'])
                    ->getSourceFile();
    }


    /**
     * Function to get the resource as a path instead of the URL.
     *
     * @param $key
     */
    public function getResourcePath($key)
    {
        return $this->assetRepo
                    ->createAsset(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_CERTS.$key, ['area'=>'adminhtml'])
                    ->getSourceFile();
    }


    /**
     * Get admin Base url for the site.
     */
    public function getAdminBaseUrl()
    {
        return $this->helperBackend->getHomePageUrl();
    }

    /**
     * Get the Admin url for the site based on the path passed,
     * Append the query parameters to the URL if necessary.
     *
     * @param $url
     * @param $params
     */
    public function getAdminUrl($url, $params = [])
    {
        return $this->helperBackend->getUrl($url, ['_query'=>$params]);
    }


    /**
     * Get the Admin secure url for the site based on the path passed,
     * Append the query parameters to the URL if necessary.
     *
     * @param $url
     * @param $params
     */
    public function getAdminSecureUrl($url, $params = [])
    {
        return $this->helperBackend->getUrl($url, ['_secure'=>true,'_query'=>$params]);
    }


    /**
     * Get the SP InitiatedURL
     *
     * @param $relayState
     */
    public function getSPInitiatedUrl($relayState = null)
    {
        $relayState = is_null($relayState) ?$this->getCurrentUrl() : $relayState;
        return $this->getFrontendUrl(
            TwoFAConstants::TwoFA_LOGIN_URL,
            ["relayState"=>$relayState]
        );
    }
}
