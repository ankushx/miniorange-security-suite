<?php

namespace MiniOrange\BruteForceProtection\Helper;

use \Magento\Framework\App\Helper\AbstractHelper;
use MiniOrange\BruteForceProtection\Helper\BruteForceConstants;
use Magento\Framework\App\Config\ScopeConfigInterface;


/**
 * This class contains functions to get and set the required data
 * from Magento database or session table/file or generate some
 * necessary values to be used in our module.
 */
class Data extends AbstractHelper
{


        /**
     * @var string
     */
    const ACTIVITY_ENABLE = 'miniorange/BruteForceProtection/mosecuritysuite_admin_action_logs_enable';

    /**
     * @var string
     */
    const LOGIN_ACTIVITY_ENABLE = 'miniorange/BruteForceProtection/mosecuritysuite_login_activity';

    /**
     * @var string
     */
    const PAGE_VISIT_ENABLE = 'miniorange/BruteForceProtection/mosecuritysuite_page_visit';

    /**
     * @var string
     */
    const CLEAR_LOG_DAYS = 'miniorange/BruteForceProtection/mosecuritysuite_admin_action_logs_retention_period';

    /**
     * @var string
     */
    const MODULE_ORDER = 'miniorange/BruteForceProtection/mosecuritysuite_order';

    /**
     * @var string
     */
    const MODULE_PRODUCT = 'miniorange/BruteForceProtection/mosecuritysuite_product';

    /**
     * @var string
     */
    const MODULE_CATEGORY = 'miniorange/BruteForceProtection/mosecuritysuite_category';

    /**
     * @var string
     */
    const MODULE_CUSTOMER = 'miniorange/BruteForceProtection/mosecuritysuite_customer';

    /**
     * @var string
     */
    const MODULE_PROMOTION = 'miniorange/BruteForceProtection/mosecuritysuite_promotion';

    /**
     * @var string
     */
    const MODULE_EMAIL = 'miniorange/BruteForceProtection/mosecuritysuite_email';

    /**
     * @var string
     */
    const MODULE_PAGE = 'miniorange/BruteForceProtection/mosecuritysuite_page';

    /**
     * @var string
     */
    const MODULE_BLOCK = 'miniorange/BruteForceProtection/mosecuritysuite_block';

    /**
     * @var string
     */
    const MODULE_WIDGET = 'miniorange/BruteForceProtection/mosecuritysuite_widget';

    /**
     * @var string
     */
    const MODULE_THEME = 'miniorange/BruteForceProtection/mosecuritysuite_theme';

    /**
     * @var string
     */
    const MODULE_SYSTEM_CONFIG = 'miniorange/BruteForceProtection/mosecuritysuite_system_config';

    /**
     * @var string
     */
    const MODULE_ATTRIBUTE = 'miniorange/BruteForceProtection/mosecuritysuite_attibute';

    /**
     * @var string
     */
    const MODULE_ADMIN_USER = 'miniorange/BruteForceProtection/mosecuritysuite_admin_user';

    /**
     * @var string
     */
    const MODULE_SEO = 'miniorange/BruteForceProtection/mosecuritysuite_seo';

    /**
     * @var array
     */
    public static $wildcardModels = [
        \Magento\Framework\App\Config\Value\Interceptor::class
    ];

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
        \Magento\Framework\Url $frontendUrl,
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
        return BruteForceConstants::HOSTNAME;
    }

    /**
     * Function to extract data stored in the store config table.
     *
     * @param $config
     */
    public function getStoreConfig($config)
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue('miniorange/SecuritySuite/' . $config, $storeScope);
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
        $this->configWriter->save('miniorange/SecuritySuite/' . $config, $value);
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
        return $this->getBaseUrl() . BruteForceConstants::ISSUER_URL_PATH;
    }


    /**
     * Function to get the Image URL of our module.
     *
     * @param $image
     */
    public function getImageUrl($image)
    {
        return $this->assetRepo->getUrl(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_IMAGES.$image);
    }


    /**
     * Get Admin CSS URL
     */
    public function getAdminCssUrl($css)
    {
        return $this->assetRepo->getUrl(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_CSS.$css, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin JS URL
     */
    public function getAdminJSUrl($js)
    {
        return $this->assetRepo->getUrl(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_JS.$js, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin Metadata Download URL
     */
    public function getMetadataUrl()
    {
        return $this->assetRepo->getUrl(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_METADATA, ['area'=>'adminhtml']);
    }


    /**
     * Get Admin Metadata File Path
     */
    public function getMetadataFilePath()
    {
        return $this->assetRepo->createAsset(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_METADATA, ['area'=>'adminhtml'])
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
                    ->createAsset(BruteForceConstants::MODULE_DIR.BruteForceConstants::MODULE_CERTS.$key, ['area'=>'adminhtml'])
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
            BruteForceConstants::BRUTEFORCE_LOGIN_URL,
            ["relayState"=>$relayState]
        );
    }


    
    /**
     * Check and return status for page visit history
     * @return bool
     */
    public function isPageVisitEnable()
    {
        $status = $this->scopeConfig->isSetFlag(self::ACTIVITY_ENABLE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $pageVisitStatus = $this->scopeConfig
            ->isSetFlag(self::PAGE_VISIT_ENABLE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        if ($status == '1' && $pageVisitStatus == '1') {
            return true;    
        }

        return false;
    }

    /**
     * Get value of system config from path
     * @param $path
     * @return bool
     */
    public function getConfigValue($path)
    {
        $moduleValue = $this->scopeConfig->getValue(
            constant(
                'self::'
                . $path
            ),
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        if ($moduleValue) {
            return $moduleValue;
        }
        return false;
    }

    /**
     * Get module name is valid or not
     * @param $model
     * @return bool
     */
    public static function isWildCardModel($model)
    {
        $model = is_string($model)?$model:get_class($model);
        if (in_array($model, self::$wildcardModels)) {
            return true;
        }
        return false;
    }

       /**
     * Check and return status of module
     * @return bool
     */
    public function isEnable()
    {
        $status = $this->scopeConfig->isSetFlag(self::ACTIVITY_ENABLE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        if ($status == '1') {
            return true;
        }

        return false;
    }

    /**
     * Check and return status for login activity
     * @return bool
     */
    public function isLoginEnable()
    {
        $status = $this->scopeConfig->isSetFlag(self::ACTIVITY_ENABLE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        $loginStatus = $this->scopeConfig
            ->isSetFlag(self::LOGIN_ACTIVITY_ENABLE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        if ($status == '1' && $loginStatus == '1') {
            return true;
        }

        return false;
    }
}
