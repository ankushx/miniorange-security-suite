<?php

namespace MiniOrange\BruteForceProtection\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class DisableDefaultLockout implements DataPatchInterface, PatchVersionInterface
{
    protected $configWriter;
    protected $moduleDataSetup;
    protected $cacheTypeList;
    protected $cacheFrontendPool;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Set 'Maximum Login Failures to Lockout Account' to 0 (disabled)
        $this->configWriter->save('admin/security/lockout_threshold', 0);

        // Set max number password reset requests to 0
        $this->configWriter->save('admin/security/max_number_password_reset_requests', 0);

        // Set min time between password reset requests to 0
        $this->configWriter->save('admin/security/min_time_between_password_reset_requests', 0);

        // Customer password lockout settings
        $this->configWriter->save('customer/password/lockout_failures', 0);
        $this->configWriter->save('customer/password/lockout_threshold', 0);
        $this->configWriter->save('customer/password/max_number_password_reset_requests', 0);
        $this->configWriter->save('customer/password/min_time_between_password_reset_requests', 0);

        // Disable CAPTCHA
        $this->configWriter->save('admin/captcha/enable', 0);
        $this->configWriter->save('customer/captcha/enable', 0);

        // Flush config cache
        $this->cacheTypeList->cleanType('config');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }
    
    public static function getVersion()
    {
        return '1.0.1';
    }
    
    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}

