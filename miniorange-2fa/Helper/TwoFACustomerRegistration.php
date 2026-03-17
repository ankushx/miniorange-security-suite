<?php
namespace MiniOrange\TwoFA\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Customer\Model\CustomerFactory;
use Magento\Store\Model\StoreManagerInterface;

class TwoFACustomerRegistration {

    protected $twofautility;
    protected $context;
    protected $customerFactory;
    protected $storeManager;
    protected $customerRepository;

    public function __construct(
        Context $context,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
        CustomerFactory $customerFactory,
        StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
    ) {
        $this->context=$context;
        $this->twofautility = $twofautility;
        $this->customerFactory = $customerFactory;
        $this->storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
    }

    public function execute(){

    }

    public function createNewCustomerAtRegistration(){
        $groupId = 1;
        $customer_registration_parameter= json_decode( $this->twofautility->getSessionValue( 'mo_customer_page_parameters'),true);
     $current_username=$customer_registration_parameter['email'];
         $firstname= $customer_registration_parameter['firstname'];
         $lastname= $customer_registration_parameter['lastname'];
         $password= $customer_registration_parameter['password'];
         $websiteId = $this->storeManager->getWebsite()->getWebsiteId();
         $store = $this->storeManager->getStore();
        // $storeId = $store->getStoreId();
         $customer = $this->customerFactory->create()
             ->setWebsiteId($websiteId)
             ->setStore($store)
             ->setEmail($current_username)
             ->setFirstname($firstname)
             ->setLastname($lastname)
             ->setPassword($password)
             ->setGroupId($groupId)
             ->save();

        $this->checkAndProcessB2BFlow($customer);
    }

    /**
     * Check if user is B2B or B2C and set customer type accordingly
     */
    public function checkAndProcessB2BFlow($customer)
    {
        if (!$this->twofautility->isCommerceEdition()) {
            return;
        }
        
        // For TwoFA, always set as B2C since we don't handle company mapping
        $this->twofautility->log_debug("Setting customer as B2C for TwoFA module");
        
        try {
            $customerData = $this->customerRepository->getById($customer->getId());
            $updatedCustomerData = $this->twofautility->saveCustomerAsB2CUser($customerData, $customer->getId());
            // Save the customer with extension attributes
            $this->customerRepository->save($customerData);
        } catch (\Exception $e) {
            $this->twofautility->log_debug("Error saving B2C customer: " . $e->getMessage());
        }
    }
}