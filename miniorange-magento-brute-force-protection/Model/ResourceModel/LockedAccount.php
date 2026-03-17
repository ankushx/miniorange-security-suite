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
 * Class LockedAccount
 * @package MiniOrange\BruteForceProtection\Model\ResourceModel
 */
class LockedAccount extends AbstractDb
{
    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('mo_bruteforce_locked_accounts', 'id'); // table name and primary key column
    }

    /**
     * Load locked account by customer ID
     *
     * @param \Magento\Framework\Model\AbstractModel $object
     * @param int $customerId
     * @return $this
     */
    public function loadByCustomerId(\Magento\Framework\Model\AbstractModel $object, $customerId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId);

        $data = $connection->fetchRow($select);
        if ($data) {
            $object->setData($data);
        }

        $this->unserializeFields($object);
        $this->_afterLoad($object);

        return $this;
    }

    /**
     * Delete locked account by customer ID
     *
     * @param int $customerId
     * @return int Number of affected rows
     */
    public function deleteByCustomerId($customerId)
    {
        $connection = $this->getConnection();
        return $connection->delete(
            $this->getMainTable(),
            ['customer_id = ?' => $customerId]
        );
    }

    /**
     * Get locked account by customer ID
     *
     * @param int $customerId
     * @return array|null
     */
    public function getByCustomerId($customerId)
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId);

        return $connection->fetchRow($select);
    }

    /**
     * Get all temporarily locked accounts
     *
     * @return array
     */
    public function getTemporarilyLockedAccounts()
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('lock_type = ?', 'temporary');

        return $connection->fetchAll($select);
    }

    /**
     * Get all permanently locked accounts
     *
     * @return array
     */
    public function getPermanentlyLockedAccounts()
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('lock_type = ?', 'permanent');

        return $connection->fetchAll($select);
    }

    /**
     * Get expired temporary locks
     *
     * @return array
     */
    public function getExpiredTemporaryLocks()
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('lock_type = ?', 'temporary')
            ->where('lock_until IS NOT NULL')
            ->where('lock_until < NOW()');

        return $connection->fetchAll($select);
    }

    /**
     * Clean up expired temporary locks
     *
     * @return int Number of affected rows
     */
    public function cleanupExpiredTemporaryLocks()
    {
        $connection = $this->getConnection();
        return $connection->delete(
            $this->getMainTable(),
            [
                'lock_type = ?' => 'temporary',
                'lock_until IS NOT NULL',
                'lock_until < NOW()'
            ]
        );
    }

    /**
     * Update failed attempts for customer
     *
     * @param int $customerId
     * @param int $failedAttempts
     * @return int Number of affected rows
     */
    public function updateFailedAttempts($customerId, $failedAttempts)
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [
                'failed_attempts' => $failedAttempts,
                'updated_at' => $connection->formatDate(new \DateTime())
            ],
            ['customer_id = ?' => $customerId]
        );
    }

    /**
     * Update lock type for customer
     *
     * @param int $customerId
     * @param string $lockType
     * @return int Number of affected rows
     */
    public function updateLockType($customerId, $lockType)
    {
        $connection = $this->getConnection();
        return $connection->update(
            $this->getMainTable(),
            [
                'lock_type' => $lockType,
                'updated_at' => $connection->formatDate(new \DateTime())
            ],
            ['customer_id = ?' => $customerId]
        );
    }

    /**
     * Get statistics about locked accounts
     *
     * @return array
     */
    public function getLockedAccountsStatistics()
    {
        $connection = $this->getConnection();
        
        $select = $connection->select()
            ->from(
                $this->getMainTable(),
                [
                    'total_locked' => 'COUNT(*)',
                    'temporary_locked' => 'SUM(CASE WHEN lock_type = "temporary" THEN 1 ELSE 0 END)',
                    'permanent_locked' => 'SUM(CASE WHEN lock_type = "permanent" THEN 1 ELSE 0 END)',
                    'avg_failed_attempts' => 'AVG(failed_attempts)',
                    'max_failed_attempts' => 'MAX(failed_attempts)',
                    'website_count' => 'COUNT(DISTINCT website)'
                ]
            );

        return $connection->fetchRow($select);
    }
}
