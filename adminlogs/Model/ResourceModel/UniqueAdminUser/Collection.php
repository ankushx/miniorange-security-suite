<?php
namespace MiniOrange\AdminLogs\Model\ResourceModel\UniqueAdminUser;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \MiniOrange\AdminLogs\Model\UniqueAdminUser::class,
            \MiniOrange\AdminLogs\Model\ResourceModel\UniqueAdminUser::class
        );
    }
}

