<?php
namespace MiniOrange\AdminLogs\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AdminLoginLogs extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('miniorange_admin_login_logs', 'log_id');
    }
}