<?php

namespace MiniOrange\IpRestriction\Service;

use MiniOrange\IpRestriction\Helper\Data;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Shell;
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * GeoIP Database Download Service
 * Handles downloading and updating GeoIP2 database from MaxMind
 */
class GeoIpDownloadService
{
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Shell
     */
    protected $shell;

    /**
     * Constructor
     *
     * @param Data $dataHelper
     * @param DirectoryList $directoryList
     * @param File $file
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param Shell $shell
     */
    public function __construct(
        Data $dataHelper,
        DirectoryList $directoryList,
        File $file,
        Curl $curl,
        LoggerInterface $logger,
        Shell $shell
    ) {
        $this->dataHelper = $dataHelper;
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->curl = $curl;
        $this->logger = $logger;
        $this->shell = $shell;
    }

    /**
     * Download and update GeoIP2 database
     *
     * @return void
     * @throws \Exception
     */
    public function downloadDatabase(): void
    {
        $tempFile = null;
        $finalPath = null;
        $mmdbFile = null;
        $extractedDir = null;
        $geoipDir = null;

        try {
            // Validate and setup
            $licenseKey = $this->validateLicenseKey();
            $geoipDir = $this->setupDownloadDirectory();
            
            // Download
            $tempFile = $this->downloadFile($licenseKey, $geoipDir);
            
            // Extract and process
            $mmdbFile = $this->extractArchive($tempFile, $geoipDir);
            // Capture extracted directory path before moving the file
            $extractedDir = dirname($mmdbFile);
            $finalPath = $this->moveToFinalLocation($mmdbFile, $geoipDir);
            
            // Verify and finalize
            $this->verifyDatabase($finalPath, $geoipDir);
            $this->finalizeDownload($finalPath, $tempFile, $extractedDir, $geoipDir);

        } catch (\Exception $e) {
            // Cleanup on error before re-throwing
            $this->cleanupOnError($tempFile, $finalPath, $mmdbFile, $geoipDir);
            $this->logger->error("IpRestriction: Error downloading GeoIP2 database - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate license key
     *
     * @return string
     * @throws \Exception
     */
    private function validateLicenseKey(): string
    {
        // Use getStoreConfigDirect to read directly from database, bypassing cache
        // This ensures we get the latest value immediately after saving
        $licenseKeyValue = $this->dataHelper->getStoreConfigDirect(IpRestrictionConstants::GEOIP2_LICENSE_KEY, 'default', 0);
        $licenseKey = $licenseKeyValue !== null ? trim($licenseKeyValue) : '';

        if (empty($licenseKey)) {
            throw new LocalizedException(
                __('GeoIP2 License Key is required. Please enter your MaxMind license key.')
            );
        }

        if (strlen($licenseKey) < 10 || !preg_match('/^[A-Za-z0-9_-]+$/', $licenseKey)) {
            throw new LocalizedException(
                __('Invalid license key format. MaxMind license keys should be alphanumeric.')
            );
        }

        return $licenseKey;
    }

    /**
     * Setup download directory
     *
     * @return string
     * @throws \Exception
     */
    private function setupDownloadDirectory(): string
    {
        $geoipDir = $this->directoryList->getRoot() . '/' . IpRestrictionConstants::GEOIP_DIRECTORY;
        
        if (!$this->file->fileExists($geoipDir)) {
            $this->file->mkdir($geoipDir, 0755, true);
        }
        
        if (!$this->file->isWriteable($geoipDir)) {
            throw new LocalizedException(
                __("GeoIP directory is not writable: %1. Please check permissions.", $geoipDir)
            );
        }

        return $geoipDir;
    }

    /**
     * Build download URL
     *
     * @param string $licenseKey
     * @return string
     */
    private function buildDownloadUrl(string $licenseKey): string
    {
        $params = [
            'edition_id' => IpRestrictionConstants::MAXMIND_EDITION_ID,
            'license_key' => urlencode($licenseKey),
            'suffix' => 'tar.gz'
        ];
        
        return IpRestrictionConstants::MAXMIND_DOWNLOAD_URL . '?' . http_build_query($params);
    }

    /**
     * Download file from MaxMind
     *
     * @param string $licenseKey
     * @param string $geoipDir
     * @return string Path to temporary file
     * @throws \Exception
     */
    private function downloadFile(string $licenseKey, string $geoipDir): string
    {
        $downloadUrl = $this->buildDownloadUrl($licenseKey);
        $tempFile = $geoipDir . '/geoip_temp_' . uniqid() . '.tar.gz';

        try {
            $this->curl->setTimeout(IpRestrictionConstants::GEOIP2_DOWNLOAD_TIMEOUT);
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, true);
            $this->curl->get($downloadUrl);

            $httpCode = $this->curl->getStatus();
            $responseBody = $this->curl->getBody();
            
            $this->validateResponse($httpCode, $responseBody);
            $this->file->write($tempFile, $responseBody);

            return $tempFile;

        } catch (\Exception $e) {
            if ($this->file->fileExists($tempFile)) {
                try {
                    $this->file->rm($tempFile);
                } catch (\Exception $deleteException) {
                    $this->logger->warning(
                        "IpRestriction: Could not delete temp file: " . $deleteException->getMessage()
                    );
                }
            }
            throw $e;
        }
    }

    /**
     * Validate HTTP response
     *
     * @param int $httpCode
     * @param string $responseBody
     * @return void
     * @throws \Exception
     */
    private function validateResponse(int $httpCode, string $responseBody): void
    {
        if ($httpCode !== 200 && $httpCode !== 302) {
            $errorMessage = $this->getErrorMessage($httpCode, $responseBody);
            $this->logger->error(
                "IpRestriction: MaxMind download failed - HTTP Code: {$httpCode}, Message: {$errorMessage}"
            );
            throw new LocalizedException(__($errorMessage));
        }

        // Basic check for empty or corrupted response
        $size = strlen($responseBody);
        if ($size < 1000) {
            throw new LocalizedException(
                __('Invalid response from MaxMind. The downloaded file appears to be empty or corrupted.')
            );
        }
    }

    /**
     * Extract archive file
     *
     * @param string $tempFile
     * @param string $geoipDir
     * @return string Path to extracted mmdb file
     * @throws \Exception
     */
    private function extractArchive(string $tempFile, string $geoipDir): string
    {
        // Try PharData first if available and properly configured
        $pharAvailable = class_exists('\PharData') 
            && extension_loaded('phar') 
            && in_array('phar', stream_get_wrappers());
        
        if ($pharAvailable) {
            try {
                $phar = new \PharData($tempFile);
                $phar->extractTo($geoipDir);
                $mmdbFile = $this->findMmdbFile($geoipDir);
                if ($mmdbFile && $this->file->fileExists($mmdbFile)) {
                    $this->logger->debug("IpRestriction: Successfully extracted archive using PharData");
                    return $mmdbFile;
                }
            } catch (\Exception $e) {
                // Silently fall through to shell method - this is expected behavior
                // PharData may not be available or configured properly
            }
        }
        
        // Fallback to shell command (preferred method for most servers)
        $this->logger->debug("IpRestriction: Using shell command to extract archive");
        try {
            $command = sprintf(
                'cd %s && tar -xzf %s 2>&1',
                escapeshellarg($geoipDir),
                escapeshellarg($tempFile)
            );
            $this->shell->execute($command);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __(
                    'Failed to extract archive. Please ensure tar and gzip are installed on your server. Error: %1',
                    $e->getMessage()
                )
            );
        }

        $mmdbFile = $this->findMmdbFile($geoipDir);
        if (!$mmdbFile || !$this->file->fileExists($mmdbFile)) {
            throw new LocalizedException(
                __('Could not find GeoLite2-Country.mmdb in extracted archive.')
            );
        }

        return $mmdbFile;
    }

    /**
     * Move extracted file to final location
     *
     * @param string $mmdbFile
     * @param string $geoipDir
     * @return string Final path
     */
    private function moveToFinalLocation(string $mmdbFile, string $geoipDir): string
    {
        $finalPath = $geoipDir . '/GeoLite2-Country.mmdb';
        $backupPath = $finalPath . '.backup';
        
        // Backup existing database
        if ($this->file->fileExists($finalPath)) {
            if ($this->file->fileExists($backupPath)) {
                try {
                    $this->file->rm($backupPath);
                } catch (\Exception $e) {
                    $this->logger->warning(
                        "IpRestriction: Could not delete backup file: " . $e->getMessage()
                    );
                }
            }
            $this->file->mv($finalPath, $backupPath);
        }

        // Move to final location
        $this->file->mv($mmdbFile, $finalPath);
        $this->file->chmod($finalPath, 0644);

        return $finalPath;
    }

    /**
     * Verify downloaded database
     *
     * @param string $finalPath
     * @param string $geoipDir
     * @return void
     * @throws \Exception
     */
    private function verifyDatabase(string $finalPath, string $geoipDir): void
    {
        $backupPath = $finalPath . '.backup';

        // Basic file verification
        if (!$this->file->fileExists($finalPath) || filesize($finalPath) < 1000) {
            if ($this->file->fileExists($backupPath)) {
                $this->file->mv($backupPath, $finalPath);
            }
            throw new LocalizedException(
                __('Downloaded file appears to be invalid or corrupted.')
            );
        }

        // Verify with GeoIP2 library if available
        if (class_exists(\GeoIp2\Database\Reader::class)) {
            try {
                $reader = new \GeoIp2\Database\Reader($finalPath);
                $reader->country('8.8.8.8');
                $reader->close();
            } catch (\Exception $e) {
                if ($this->file->fileExists($backupPath)) {
                    $this->file->mv($backupPath, $finalPath);
                }
                throw new LocalizedException(
                    __('Downloaded database file is corrupted or invalid: %1', $e->getMessage())
                );
            }
        }
    }

    /**
     * Finalize download - cleanup and save timestamp
     *
     * @param string $finalPath
     * @param string $tempFile
     * @param string $extractedDir
     * @param string $geoipDir
     * @return void
     */
    private function finalizeDownload(string $finalPath, string $tempFile, string $extractedDir, string $geoipDir): void
    {
        $backupPath = $finalPath . '.backup';
        $this->cleanupTempFiles($tempFile, $backupPath, $extractedDir, $geoipDir);
    }


    /**
     * Cleanup on error
     *
     * @param string|null $tempFile
     * @param string|null $finalPath
     * @param string|null $mmdbFile
     * @param string|null $geoipDir
     * @return void
     */
    private function cleanupOnError(?string $tempFile, ?string $finalPath, ?string $mmdbFile, ?string $geoipDir): void
    {
        if ($geoipDir) {
            // Get extracted directory from mmdbFile path if available
            $extractedDir = $mmdbFile ? dirname($mmdbFile) : null;
            $this->cleanupTempFiles($tempFile, null, $extractedDir, $geoipDir);
        }
        if ($finalPath && $this->file->fileExists($finalPath) 
            && !$this->file->fileExists($finalPath . '.backup')
        ) {
            try {
                $this->file->rm($finalPath);
            } catch (\Exception $e) {
                $this->logger->warning(
                    "IpRestriction: Could not delete final path: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Find the .mmdb file in extracted directory
     *
     * @param string $directory
     * @return string|null
     */
    private function findMmdbFile(string $directory): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'mmdb') {
                return $file->getPathname();
            }
        }
        
        return null;
    }

    /**
     * Get error message based on HTTP code
     *
     * @param int $httpCode
     * @param string $responseBody
     * @return string
     */
    private function getErrorMessage(int $httpCode, string $responseBody): string
    {
        $errorMessage = '';
        
        // Try to extract error from JSON response
        $jsonError = json_decode($responseBody, true);
        if ($jsonError && isset($jsonError['error'])) {
            $errorMessage = $jsonError['error'];
        }
        
        $specificError = $errorMessage ? " Details: {$errorMessage}" : '';
        
        if ($httpCode === 401) {
            return "Invalid license key. Please check your MaxMind license key." . $specificError;
        } elseif ($httpCode === 403) {
            return "License key does not have permission to download GeoLite2 database." . $specificError;
        } else {
            return "Failed to download GeoIP2 database. HTTP Code: {$httpCode}" . $specificError;
        }
    }

    /**
     * Clean up temporary files and directories
     *
     * @param string|null $tempFile
     * @param string|null $backupPath
     * @param string|null $extractedDir
     * @param string $geoipDir
     * @return void
     */
    private function cleanupTempFiles(?string $tempFile, ?string $backupPath, ?string $extractedDir, string $geoipDir): void
    {
        if ($backupPath && $this->file->fileExists($backupPath)) {
            try {
                $this->file->rm($backupPath);
            } catch (\Exception $e) {
                $this->logger->warning(
                    "IpRestriction: Could not delete backup file: " . $e->getMessage()
                );
            }
        }
        
        if ($tempFile && $this->file->fileExists($tempFile)) {
            try {
                $this->file->rm($tempFile);
            } catch (\Exception $e) {
                $this->logger->warning(
                    "IpRestriction: Could not delete temp file: " . $e->getMessage()
                );
            }
        }
        
        // Clean up extracted directory (e.g., GeoLite2-Country_20251205)
        if ($extractedDir && $geoipDir && $extractedDir !== $geoipDir && $this->file->fileExists($extractedDir)) {
            try {
                // Check if directory is empty or contains only empty subdirectories
                $this->removeDirectory($extractedDir);
                $this->logger->debug("IpRestriction: Successfully removed extracted directory: {$extractedDir}");
            } catch (\Exception $e) {
                $this->logger->warning(
                    "IpRestriction: Could not remove extracted directory {$extractedDir}: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Recursively remove directory and all its contents
     *
     * @param string $dir
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $this->file->rmdir($dir, true);
        } catch (\Exception $e) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    try {
                        if (file_exists($path)) {
                            unlink($path);
                        }
                    } catch (\Exception $unlinkException) {
                        $this->logger->warning(
                            "IpRestriction: Could not delete file {$path}: " . $unlinkException->getMessage()
                        );
                    }
                }
            }
            try {
                if (is_dir($dir)) {
                    rmdir($dir);
                }
            } catch (\Exception $rmdirException) {
                $this->logger->warning(
                    "IpRestriction: Could not remove directory {$dir}: " . $rmdirException->getMessage()
                );
            }
        }
    }
}

