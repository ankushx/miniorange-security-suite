<?php
/**
 * @category    MiniOrange
 * @package     MiniOrange_BruteForceProtection
 * @author      miniOrange
 * @copyright   Copyright (c) 2024 miniOrange Inc. (https://www.miniorange.com)
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace MiniOrange\BruteForceProtection\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class LockedAccount
 * @package MiniOrange\BruteForceProtection\Model
 */
class LockedAccount extends AbstractModel
{
    /**
     * Lock types constants
     */
    const LOCK_TYPE_NONE = 'none';
    const LOCK_TYPE_TEMPORARY = 'temporary';
    const LOCK_TYPE_PERMANENT = 'permanent';

    /**
     * User types constants
     */
    const USER_TYPE_CUSTOMER = 'customer';
    const USER_TYPE_ADMIN = 'admin';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init(\MiniOrange\BruteForceProtection\Model\ResourceModel\LockedAccount::class);
    }

    /**
     * Get customer ID
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        return $this->getData('customer_id');
    }

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get email
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->getData('email');
    }

    /**
     * Set email
     *
     * @param string $email
     * @return $this
     */
    public function setEmail($email)
    {
        return $this->setData('email', $email);
    }

    /**
     * Get user type
     *
     * @return string
     */
    public function getUserType()
    {
        return $this->getData('user_type') ?: self::USER_TYPE_CUSTOMER;
    }

    /**
     * Set user type
     *
     * @param string $userType
     * @return $this
     */
    public function setUserType($userType)
    {
        return $this->setData('user_type', $userType);
    }

    /**
     * Get lock type
     *
     * @return string
     */
    public function getLockType()
    {
        return $this->getData('lock_type') ?: self::LOCK_TYPE_NONE;
    }

    /**
     * Set lock type
     *
     * @param string $lockType
     * @return $this
     */
    public function setLockType($lockType)
    {
        return $this->setData('lock_type', $lockType);
    }

    /**
     * Get lock until timestamp
     *
     * @return string|null
     */
    public function getLockUntil()
    {
        return $this->getData('lock_until');
    }

    /**
     * Set lock until timestamp
     *
     * @param string $lockUntil
     * @return $this
     */
    public function setLockUntil($lockUntil)
    {
        return $this->setData('lock_until', $lockUntil);
    }

    /**
     * Get failed attempts count
     *
     * @return int
     */
    public function getFailedAttempts()
    {
        return (int)$this->getData('failed_attempts');
    }

    /**
     * Set failed attempts count
     *
     * @param int $failedAttempts
     * @return $this
     */
    public function setFailedAttempts($failedAttempts)
    {
        return $this->setData('failed_attempts', $failedAttempts);
    }

    /**
     * Get temporary lock count
     *
     * @return int
     */
    public function getTempLockCount()
    {
        return (int)$this->getData('temp_lock_count');
    }

    /**
     * Set temporary lock count
     *
     * @param int $tempLockCount
     * @return $this
     */
    public function setTempLockCount($tempLockCount)
    {
        return $this->setData('temp_lock_count', $tempLockCount);
    }

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt()
    {
        return $this->getData('updated_at');
    }

    /**
     * Set updated at timestamp
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData('updated_at', $updatedAt);
    }

    /**
     * Get first time lockout timestamp
     *
     * @return string|null
     */
    public function getFirstTimeLockout()
    {
        return $this->getData('first_time_lockout');
    }

    /**
     * Set first time lockout timestamp
     *
     * @param string $firstTimeLockout
     * @return $this
     */
    public function setFirstTimeLockout($firstTimeLockout)
    {
        return $this->setData('first_time_lockout', $firstTimeLockout);
    }

    /**
     * Check if account is locked
     *
     * @return bool
     */
    public function isLocked()
    {
        return $this->getLockType() !== self::LOCK_TYPE_NONE;
    }

    /**
     * Check if account is temporarily locked
     *
     * @return bool
     */
    public function isTemporarilyLocked()
    {
        return $this->getLockType() === self::LOCK_TYPE_TEMPORARY;
    }

    /**
     * Check if account is permanently locked
     *
     * @return bool
     */
    public function isPermanentlyLocked()
    {
        return $this->getLockType() === self::LOCK_TYPE_PERMANENT;
    }

    /**
     * Check if temporary lock has expired
     *
     * @return bool
     */
    public function isTemporaryLockExpired()
    {
        if (!$this->isTemporarilyLocked() || !$this->getLockUntil()) {
            return true;
        }

        return strtotime($this->getLockUntil()) < time();
    }

    /**
     * Increment failed attempts
     *
     * @return $this
     */
    public function incrementFailedAttempts()
    {
        $currentAttempts = $this->getFailedAttempts();
        return $this->setFailedAttempts($currentAttempts + 1);
    }

    /**
     * Increment temporary lock count
     *
     * @return $this
     */
    public function incrementTempLockCount()
    {
        $currentCount = $this->getTempLockCount();
        return $this->setTempLockCount($currentCount + 1);
    }

    /**
     * Reset failed attempts
     *
     * @return $this
     */
    public function resetFailedAttempts()
    {
        return $this->setFailedAttempts(0);
    }

    /**
     * Reset temporary lock count
     *
     * @return $this
     */
    public function resetTempLockCount()
    {
        return $this->setTempLockCount(0);
    }

    /**
     * Set temporary lock with duration
     *
     * @param int $minutes
     * @return $this
     */
    public function setTemporaryLock($minutes = 30)
    {
        $this->setLockType(self::LOCK_TYPE_TEMPORARY);
        $this->setLockUntil(date('Y-m-d H:i:s', time() + ($minutes * 60)));
        $this->incrementTempLockCount();
        return $this;
    }

    /**
     * Set permanent lock
     *
     * @return $this
     */
    public function setPermanentLock()
    {
        $this->setLockType(self::LOCK_TYPE_PERMANENT);
        $this->setLockUntil(null);
        return $this;
    }

    /**
     * Clear lock
     *
     * @return $this
     */
    public function clearLock()
    {
        $this->setLockType(self::LOCK_TYPE_NONE);
        $this->setLockUntil(null);
        $this->resetFailedAttempts();
        return $this;
    }
}
