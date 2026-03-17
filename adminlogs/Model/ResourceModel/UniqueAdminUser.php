<?php
namespace MiniOrange\AdminLogs\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class UniqueAdminUser extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('mo_unique_admin_users', 'id');
    }
}

