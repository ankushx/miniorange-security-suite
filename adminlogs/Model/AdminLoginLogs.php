<?php
namespace MiniOrange\AdminLogs\Model;

use Magento\Framework\Model\AbstractModel;

class AdminLoginLogs extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs');
    }
}
