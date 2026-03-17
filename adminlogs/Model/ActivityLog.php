<?php
namespace MiniOrange\AdminLogs\Model;

use Magento\Framework\Model\AbstractModel;

class ActivityLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init('MiniOrange\AdminLogs\Model\ResourceModel\ActivityLog');
    }
}

