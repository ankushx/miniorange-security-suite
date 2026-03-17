<?php
namespace MiniOrange\AdminLogs\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class AlertType implements ArrayInterface
{
    /**
     * Return array of options for the alert type dropdown
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'admin_notification', 'label' => __('Admin Notification')]
        ];
    }
}
