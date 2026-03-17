<?php
 
 /**
  * The code below is used to register the
  * TwoFA extension/component with the Mangeto
  * core Module. It specifies the root directory
  * of the plugin.
  */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'MiniOrange_TwoFA',
    __DIR__
);
