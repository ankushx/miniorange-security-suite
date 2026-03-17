<?php
namespace MiniOrange\AdminLogs\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class ObjectsToLog implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'all', 'label' => __('All Objects')],
            ['value' => 'product', 'label' => __('Product')],
            ['value' => 'customer', 'label' => __('Customer')],
            ['value' => 'customer_address', 'label' => __('Customer Address')],
            ['value' => 'order', 'label' => __('Order')],
            ['value' => 'category', 'label' => __('Category')],
            ['value' => 'cms_page', 'label' => __('CMS Page')],
            ['value' => 'cms_block', 'label' => __('CMS Block')],
            ['value' => 'attribute', 'label' => __('Product Attribute')],
            ['value' => 'attribute_set', 'label' => __('Attribute Set')],
            ['value' => 'customer_group', 'label' => __('Customer Group')],
            ['value' => 'store', 'label' => __('Store')],
            ['value' => 'store_group', 'label' => __('Store Group')],
            ['value' => 'website', 'label' => __('Website')],
            ['value' => 'shipping_method', 'label' => __('Shipping Method')],
            ['value' => 'payment_method', 'label' => __('Payment Method')],
            ['value' => 'currency', 'label' => __('Currency')],
            ['value' => 'email_template', 'label' => __('Email Template')],
            ['value' => 'newsletter_subscriber', 'label' => __('Newsletter Subscriber')],
            ['value' => 'review', 'label' => __('Product Review')],
            ['value' => 'rating', 'label' => __('Rating')],
            ['value' => 'coupon', 'label' => __('Coupon')],
            ['value' => 'cart_rule', 'label' => __('Cart Rule')],
            ['value' => 'catalog_rule', 'label' => __('Catalog Rule')],
            ['value' => 'widget', 'label' => __('Widget')],
            ['value' => 'theme', 'label' => __('Theme')],
            ['value' => 'admin_user', 'label' => __('Admin User')],
            ['value' => 'role', 'label' => __('Admin Role')],
            ['value' => 'config', 'label' => __('Configuration')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $array = [];
        foreach ($this->toOptionArray() as $item) {
            $array[$item['value']] = $item['label'];
        }
        return $array;
    }
}
