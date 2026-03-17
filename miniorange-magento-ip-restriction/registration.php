<?php

/**
 * The code below is used to register the
 * IpRestriction extension/component with the Magento
 * core Module. It specifies the root directory
 * of the plugin.
 */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'MiniOrange_IpRestriction',
    __DIR__
);

