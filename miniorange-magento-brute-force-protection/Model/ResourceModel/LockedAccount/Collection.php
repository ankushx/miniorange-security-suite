<?php
/**
 * @category    MiniOrange
 * @package     MiniOrange_BruteForceProtection
 * @author      miniOrange
 * @copyright   Copyright (c) 2024 miniOrange Inc. (https://www.miniorange.com)
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace MiniOrange\BruteForceProtection\Model\ResourceModel\LockedAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MiniOrange\BruteForceProtection\Model\LockedAccount;
use MiniOrange\BruteForceProtection\Model\ResourceModel\LockedAccount as LockedAccountResource;

/**
 * Class Collection
 * @package MiniOrange\BruteForceProtection\Model\ResourceModel\LockedAccount
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(LockedAccount::class, LockedAccountResource::class);
    }

    /**
     * Filter by customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerIdFilter($customerId)
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter by lock type
     *
     * @param string $lockType
     * @return $this
     */
    public function addLockTypeFilter($lockType)
    {
        $this->addFieldToFilter('lock_type', $lockType);
        return $this;
    }

    /**
     * Filter by temporary locks
     *
     * @return $this
     */
    public function addTemporaryLockFilter()
    {
        return $this->addLockTypeFilter(LockedAccount::LOCK_TYPE_TEMPORARY);
    }

    /**
     * Filter by permanent locks
     *
     * @return $this
     */
    public function addPermanentLockFilter()
    {
        return $this->addLockTypeFilter(LockedAccount::LOCK_TYPE_PERMANENT);
    }

    /**
     * Filter by expired temporary locks
     *
     * @return $this
     */
    public function addExpiredTemporaryLockFilter()
    {
        $this->addTemporaryLockFilter();
        $this->addFieldToFilter('lock_until', ['lt' => new \Zend_Db_Expr('NOW()')]);
        return $this;
    }

    /**
     * Filter by active temporary locks
     *
     * @return $this
     */
    public function addActiveTemporaryLockFilter()
    {
        $this->addTemporaryLockFilter();
        $this->addFieldToFilter('lock_until', ['gteq' => new \Zend_Db_Expr('NOW()')]);
        return $this;
    }

    /**
     * Order by updated at descending
     *
     * @return $this
     */
    public function addOrderByUpdatedAt()
    {
        $this->addOrder('updated_at', self::SORT_ORDER_DESC);
        return $this;
    }

    /**
     * Order by failed attempts descending
     *
     * @return $this
     */
    public function addOrderByFailedAttempts()
    {
        $this->addOrder('failed_attempts', self::SORT_ORDER_DESC);
        return $this;
    }

    /**
     * Order by temp lock count descending
     *
     * @return $this
     */
    public function addOrderByTempLockCount()
    {
        $this->addOrder('website', self::SORT_ORDER_ASC);
        return $this;
    }
}
