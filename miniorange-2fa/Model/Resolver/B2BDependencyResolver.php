<?php

namespace MiniOrange\TwoFA\Model\Resolver;

use Magento\Framework\Module\Manager;
use Magento\Framework\App\ObjectManager;

/**
 * Resolver class to check B2B/Commerce module availability and initialize dependencies
 * Optional Adobe Commerce (Magento_Company) dependency
 * Resolved lazily to support Magento Open Source compatibility
 */
class B2BDependencyResolver
{
    private $moduleManager;
    private $companyModuleAvailable = null;
    private $customerExtensionFactoryAvailable = null;
    private $companyCustomerFactory = null;
    private $companyUserRepository = null;
    private $companyRepository = null;
    private $companyManagement = null;
    private $customerExtensionFactory = null;
    public function __construct(Manager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Check if Company module is enabled and all required classes/interfaces exist
     */
    public function isCompanyModuleAvailable()
    {
        if ($this->companyModuleAvailable !== null) {
            return $this->companyModuleAvailable;
        }

        if (!$this->moduleManager->isEnabled('Magento_Company')) {
            return $this->companyModuleAvailable = false;
        }

        return $this->companyModuleAvailable = 
            class_exists('Magento\Company\Api\Data\CompanyCustomerInterfaceFactory')
            && interface_exists('Magento\Company\Api\CompanyUserRepositoryInterface')
            && interface_exists('Magento\Company\Api\CompanyRepositoryInterface')
            && interface_exists('Magento\Company\Api\CompanyManagementInterface');
    }

    /**
     * Check if Customer Extension Factory is available
     */
    public function isCustomerExtensionFactoryAvailable()
    {
        if ($this->customerExtensionFactoryAvailable !== null) {
            return $this->customerExtensionFactoryAvailable;
        }

        if (!$this->moduleManager->isEnabled('Magento_Customer')) {
            return $this->customerExtensionFactoryAvailable = false;
        }

        return $this->customerExtensionFactoryAvailable = class_exists(
            'Magento\Customer\Api\Data\CustomerExtensionInterfaceFactory'
        );
    }

    /**
     * Check if this is running on Adobe Commerce/B2B edition
     *
     * @return bool
     */
    public function isCommerceEdition()
    {
        return $this->isCompanyModuleAvailable();
    }

    /**
     * Get CompanyCustomerInterfaceFactory instance if available
     */
    public function getCompanyCustomerFactory()
    {
        if ($this->companyCustomerFactory !== null) {
            return $this->companyCustomerFactory;
        }

        if (!$this->isCompanyModuleAvailable()) {
            return null;
        }

        $className = 'Magento\Company\Api\Data\CompanyCustomerInterfaceFactory';
        if (class_exists($className)) {
            return $this->companyCustomerFactory = ObjectManager::getInstance()->get($className);
        }

        return null;
    }

    /**
     * Get CompanyUserRepositoryInterface instance if available
     */
    public function getCompanyUserRepository()
    {
        if ($this->companyUserRepository !== null) {
            return $this->companyUserRepository;
        }

        if (!$this->isCompanyModuleAvailable()) {
            return null;
        }

        return $this->companyUserRepository = ObjectManager::getInstance()->get(
            'Magento\Company\Api\CompanyUserRepositoryInterface'
        );
    }

    /**
     * Get CompanyRepositoryInterface instance if available
     */
    public function getCompanyRepository()
    {
        if ($this->companyRepository !== null) {
            return $this->companyRepository;
        }

        if (!$this->isCompanyModuleAvailable()) {
            return null;
        }

        return $this->companyRepository = ObjectManager::getInstance()->get(
            'Magento\Company\Api\CompanyRepositoryInterface'
        );
    }

    /**
     * Get CompanyManagementInterface instance if available
     */
    public function getCompanyManagement()
    {
        if ($this->companyManagement !== null) {
            return $this->companyManagement;
        }

        if (!$this->isCompanyModuleAvailable()) {
            return null;
        }

        return $this->companyManagement = ObjectManager::getInstance()->get(
            'Magento\Company\Api\CompanyManagementInterface'
        );
    }

    /**
     * Get CustomerExtensionInterfaceFactory instance if available
     */
    public function getCustomerExtensionFactory()
    {
        if ($this->customerExtensionFactory !== null) {
            return $this->customerExtensionFactory;
        }

        if (!class_exists('Magento\Customer\Api\Data\CustomerExtensionInterfaceFactory')) {
            return null;
        }

        return $this->customerExtensionFactory = ObjectManager::getInstance()->get(
            'Magento\Customer\Api\Data\CustomerExtensionInterfaceFactory'
        );
    }
}
