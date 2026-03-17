<?php

namespace MiniOrange\IpRestriction\Cron;

use MiniOrange\IpRestriction\Service\GeoIpDownloadService;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use Psr\Log\LoggerInterface;

/**
 * Cron job for automatic GeoIP database updates
 */
class UpdateGeoIpDatabase
{
    protected $downloadService;
    protected $logger;
    protected $dataHelper;

    public function __construct(
        GeoIpDownloadService $downloadService,
        LoggerInterface $logger,
        \MiniOrange\IpRestriction\Helper\Data $dataHelper
    ) {
        $this->downloadService = $downloadService;
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Execute cron job
     * 
     * @return void
     */
    public function execute()
    {
        try {
            // Check if automatic updates are enabled
            $autoUpdateEnabled = $this->dataHelper->getStoreConfig(IpRestrictionConstants::GEOIP2_AUTO_UPDATE_ENABLED, 'default', 0);
            if ($autoUpdateEnabled != '1') {
                return;
            }

            // Check if license key exists
            $licenseKey = trim($this->dataHelper->getStoreConfig(IpRestrictionConstants::GEOIP2_LICENSE_KEY, 'default', 0));
            if (empty($licenseKey)) {
                $this->logger->warning("IpRestriction: GeoIP automatic update skipped - License key not configured.");
                return;
            }

            // Download database - throws exception on error
            $this->downloadService->downloadDatabase();
            $this->logger->info("IpRestriction: GeoIP database updated successfully.");

        } catch (\Exception $e) {
            $this->logger->error("IpRestriction: Error in GeoIP automatic update cron - " . $e->getMessage());
        }
    }
}

