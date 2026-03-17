<?php

declare(strict_types=1);

namespace MiniOrange\BruteForceProtection\Cron;

use MiniOrange\BruteForceProtection\Helper\BruteForceUtility;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class CleanLoginLogs
{
    protected $logger;
    protected $bruteforceUtility;
    protected $resourceConnection;
    protected $filesystem;

    /**
     * Constructor
     *
     * @param BruteForceUtility $bruteforceUtility
     * @param ResourceConnection $resourceConnection
     * @param Filesystem $filesystem
     * @param LoggerInterface $logger
     */
    public function __construct(
        BruteForceUtility $bruteforceUtility,
        ResourceConnection $resourceConnection,
        Filesystem $filesystem,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->bruteforceUtility = $bruteforceUtility;
        $this->resourceConnection = $resourceConnection;
        $this->filesystem = $filesystem;
    }

    /**
     * Execute the cron
     * Cleans up expired temporary lockouts from forgot password and login forms
     * Also cleans up old customer login logs from JSON file based on retention period
     *
     * @return void
     */
    public function execute()
    {
        $this->bruteforceUtility->log_debug("Inside CleanLoginLogs execute - Starting cleanup of expired lockouts and old logs");
        
        try {
            // Clean up expired forgot password temporary lockouts
            $this->cleanupExpiredForgotPasswordLockouts();
            
            // Clean up expired login form temporary lockouts
            $this->cleanupExpiredLoginTemporaryLockouts();
            
        } catch (\Exception $e) {
            $errorMessage = "Error cleaning expired lockouts and logs: " . $e->getMessage();
            $this->bruteforceUtility->log_debug("CleanLoginLogs - " . $errorMessage);
            $this->logger->error("BruteForce Protection: " . $errorMessage);
        }
    }

    /**
     * Get log file path
     *
     * @return string
     */
    protected function getLogFilePath()
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $logDir = $varDirectory->getAbsolutePath('log');
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        return $logDir . '/customer_login_logs.json';
    }

    /**
     * Clean up expired forgot password temporary lockouts
     * Deletes records where lock_until has passed AND created_at is older than retention period
     *
     * @return void
     */
    protected function cleanupExpiredForgotPasswordLockouts()
    {
        try {
            // Retention period for expired forgot password lockouts (15 days or shorter)
            // This ensures records are cleaned up even if user never retries
            $retentionDays = 1;
            $this->bruteforceUtility->log_debug("CleanLoginLogs - Cleaning up expired forgot password lockouts (retention: {$retentionDays} days)");
            
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('mo_bruteforce_forgot_password_locked_accounts');
            
            // Calculate cutoff date: records created before this date will be deleted
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            $where = [
                'lock_type = ?' => 'temporary',
                'lock_until IS NOT NULL',
                'lock_until < NOW()',
                'created_at < ?' => $cutoffDate
            ];
            
            $deletedLockouts = $connection->delete($tableName, $where);
            
            $this->bruteforceUtility->log_debug("CleanLoginLogs - Successfully deleted {$deletedLockouts} expired forgot password lockout records (older than {$retentionDays} days)");
            $this->logger->info("BruteForce Protection: Cleaned up {$deletedLockouts} expired forgot password lockouts (older than {$retentionDays} days)");
            
        } catch (\Exception $e) {
            $errorMessage = "Error cleaning expired forgot password lockouts: " . $e->getMessage();
            $this->bruteforceUtility->log_debug("CleanLoginLogs - " . $errorMessage);
            $this->logger->error("BruteForce Protection: " . $errorMessage);
        }
    }

    /**
     * Clean up expired login form temporary lockouts
     * Deletes records where lock_until has passed AND updated_at is older than 15 days
     *
     * @return void
     */
    protected function cleanupExpiredLoginTemporaryLockouts()
    {
        try {
            // Retention period for expired login form temporary lockouts (15 days)
            $retentionDays = 15;
            $this->bruteforceUtility->log_debug("CleanLoginLogs - Cleaning up expired login form temporary lockouts (retention: {$retentionDays} days)");
            
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('mo_bruteforce_locked_accounts');
            
            // Calculate cutoff date: records updated before this date will be deleted
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            $where = $connection->quoteInto('lock_type = ?', 'temporary') . 
                     ' AND (lock_until IS NULL OR lock_until < NOW())' .
                     ' AND ' . $connection->quoteInto('updated_at < ?', $cutoffDate);
            
            $deletedLockouts = $connection->delete($tableName, $where);
            
            $this->bruteforceUtility->log_debug("CleanLoginLogs - Successfully deleted {$deletedLockouts} expired login form temporary lockout records (older than {$retentionDays} days)");
            $this->logger->info("BruteForce Protection: Cleaned up {$deletedLockouts} expired login form temporary lockouts (older than {$retentionDays} days)");
            
        } catch (\Exception $e) {
            $errorMessage = "Error cleaning expired login form temporary lockouts: " . $e->getMessage();
            $this->bruteforceUtility->log_debug("CleanLoginLogs - " . $errorMessage);
            $this->logger->error("BruteForce Protection: " . $errorMessage);
        }
    }
}

