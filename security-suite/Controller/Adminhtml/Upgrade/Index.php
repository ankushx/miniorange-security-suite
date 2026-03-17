<?php

namespace MiniOrange\SecuritySuite\Controller\Adminhtml\Upgrade;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * This class handles the action for endpoint: securitysuite/upgrade/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 */
class Index extends Action implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our upgrade /license page.
     * It's called when you visit the securitysuite/upgrade/Index
     * URL. It prepares all the values required on the license upgrade
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MiniOrange_SecuritySuite::SecuritySuite');
        $resultPage->addBreadcrumb(__('Upgrade Plans'), __('Upgrade Plans'));
        $resultPage->getConfig()->getTitle()->prepend(__('Upgrade Plans'));
        return $resultPage;
    }

    /**
     * Check if the form is being saved in the backend or just
     * show the page. Checks if the request parameter has
     * an option key. All our forms need to have a hidden option
     * key.
     *
     * @param array $params
     * @return bool
     */
    protected function isFormOptionBeingSaved($params)
    {
        return array_key_exists('option', $params);
    }

    /**
     * This function checks if the required fields passed to
     * this function are empty or not. If empty, it throws an exception.
     *
     * @param array $array
     * @throws \Exception
     */
    protected function checkIfRequiredFieldsEmpty($array)
    {
        foreach ($array as $key => $value) {
            if ((is_array($value) && (!array_key_exists($key, $value) || $this->isBlank($value[$key])))
                || $this->isBlank($value)
            ) {
                throw new \Exception(__('Required fields are missing.'));
            }
        }
    }

    /**
     * Check if support query forms are empty. If empty, throw
     * an exception. This is an extension of the requiredFields
     * function.
     *
     * @param array $array
     * @throws \Exception
     */
    public function checkIfSupportQueryFieldsEmpty($array)
    {
        try {
            $this->checkIfRequiredFieldsEmpty($array);
        } catch (\Exception $e) {
            throw new \Exception(__('Support query required fields are missing.'));
        }
    }

    /**
     * Check if a value is blank (empty or null)
     *
     * @param mixed $value
     * @return bool
     */
    protected function isBlank($value)
    {
        return empty($value) && $value !== '0';
    }

    /**
     * Is the user allowed to view the Upgrade settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MiniOrange_SecuritySuite::upgrade');
    }
}
