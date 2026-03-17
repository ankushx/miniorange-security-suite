<?php

namespace MiniOrange\IpRestriction\Controller\Adminhtml\GeoIp;

use MiniOrange\IpRestriction\Controller\Actions\BaseAdminAction;
use MiniOrange\IpRestriction\Service\GeoIpDownloadService;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;

class Index extends BaseAdminAction
{
    const ADMIN_RESOURCE = 'MiniOrange_IpRestriction::geoip_download';
    
    protected $downloadService;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \MiniOrange\IpRestriction\Helper\Data $dataHelper,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        GeoIpDownloadService $downloadService
    ) {
        parent::__construct($context, $resultPageFactory, $dataHelper, $messageManager, $logger);
        $this->downloadService = $downloadService;
    }

    public function execute()
    {
        try {
            $request = $this->getRequest();
            
            // Save license key 
            $licenseKey = $request->getParam('geoip2_license_key');
            if (!empty($licenseKey)) {
                $this->dataHelper->setStoreConfig(IpRestrictionConstants::GEOIP2_LICENSE_KEY, trim($licenseKey), 'default', 0);
                // Flush cache to ensure the new license key is available immediately
                $this->dataHelper->flushCache();
            }
            
            // Use the download service - throws exceptions on error
            $this->downloadService->downloadDatabase();
            
            // Success message
            $this->addSuccessMessage(__('GeoIP2 database downloaded successfully.'));
            
            // Show library warning if needed
            if (!class_exists(\GeoIp2\Database\Reader::class)) {
                $this->addWarningMessage(__('Please install GeoIP2 supporting file using following command: composer require geoip2/geoip2'));
            }
            
        } catch (\Exception $e) {
            $this->addErrorMessage(__($e->getMessage()));
            $this->logError('Error downloading GeoIP2 database: ' . $e->getMessage());
        }
        
        // Redirect back to settings page
        return $this->redirectToPath('iprestriction/iprestrict/index');
    }
}

