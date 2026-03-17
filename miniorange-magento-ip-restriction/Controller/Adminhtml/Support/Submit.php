<?php

namespace MiniOrange\IpRestriction\Controller\Adminhtml\Support;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use MiniOrange\IpRestriction\Helper\Curl;
use Psr\Log\LoggerInterface;

/**
 * Submit Controller for Admin
 * 
 * This controller handles the submission of the support form from admin area.
 * It sends the support request to the support team.
 * 
 */
class Submit extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'MiniOrange_IpRestriction::iprestriction';

    protected $resultJsonFactory;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $request = $this->getRequest();
            $params = $request->getParams();  // Get all request parameters

            $email = $params['email'] ?? '';
            $query = $params['query'] ?? '';

            // Validate required fields
            if (empty($email) || empty($query)) {
                return $result->setData([
                    'success' => false,
                    'message' => 'Please fill in all required fields.'
                ]);
            }

            $this->logger->debug("Support form submission: " . json_encode($params));  // Log params
            Curl::submit_contact_us($email, '', $query);  // Pass empty string for phone
            
            return $result->setData([
                'success' => true,
                'message' => "Thanks for your inquiry. We will get back shortly via email.<br><br>
                    If you don't hear from us within 24 hours, please send a follow-up email to magentosupport@xecurify.com."
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Support form submission error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => 'Something went wrong. Please try again.'
            ]);
        }
    }
}

