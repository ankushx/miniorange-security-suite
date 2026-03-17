<?php
/**
 * @category    MiniOrange
 * @package     MiniOrange_BruteForceProtection
 * @author      miniOrange
 * @copyright   Copyright (c) 2024 miniOrange Inc. (https://www.miniorange.com)
 * @license     https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace MiniOrange\BruteForceProtection\Model\ResourceModel\ForgotPasswordLockedAccount;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 * @package MiniOrange\BruteForceProtection\Model\ResourceModel\ForgotPasswordLockedAccount
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize resource collection
     */
    protected function _construct()
    {
        $this->_init(
            \MiniOrange\BruteForceProtection\Model\ForgotPasswordLockedAccount::class,
            \MiniOrange\BruteForceProtection\Model\ResourceModel\ForgotPasswordLockedAccount::class
        );
    }
}
