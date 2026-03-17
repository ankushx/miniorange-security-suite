<?php
/**
 * @category    MiniOrange
 * @package     MiniOrange_BruteForceProtection
 * @author      miniOrange
 * @copyright   Copyright (c) 2024 miniOrange Inc. (https://www.miniorange.com)
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace MiniOrange\BruteForceProtection\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class ForgotPasswordLockedAccount
 * @package MiniOrange\BruteForceProtection\Model\ResourceModel
 */
class ForgotPasswordLockedAccount extends AbstractDb
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('mo_bruteforce_forgot_password_locked_accounts', 'id');
    }

    /**
     * Clean up expired temporary locks with retention period
     * Deletes records where lock_until has passed AND created_at is older than retention days
     *
     * @param int $retentionDays Number of days to retain expired records (default: 15)
     * @return int Number of deleted rows
     */
    public function cleanupExpiredTemporaryLocks($retentionDays = 15)
    {
        $connection = $this->getConnection();
        $tableName = $this->getMainTable();
        
        // Calculate cutoff date: records created before this date will be deleted
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        // Delete records where:
        // 1. lock_type is temporary
        // 2. lock_until has passed (expired)
        // 3. created_at is older than retention period
        $where = [
            'lock_type = ?' => 'temporary',
            'lock_until IS NOT NULL',
            'lock_until < NOW()',
            'created_at < ?' => $cutoffDate
        ];
        
        return $connection->delete($tableName, $where);
    }
}
