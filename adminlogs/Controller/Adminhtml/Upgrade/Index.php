<?php
namespace MiniOrange\AdminLogs\Controller\Adminhtml\Upgrade;

use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use MiniOrange\AdminLogs\Controller\Actions\BaseAdminAction;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\AdminLogs\Helper\AdminLogsUtility;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class Index extends BaseAdminAction implements HttpGetActionInterface
{
    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param AdminLogsUtility $adminLogsUtility
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        AdminLogsUtility $adminLogsUtility,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        parent::__construct($context, $resultPageFactory, $adminLogsUtility, $messageManager, $logger);
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MiniOrange_AdminLogs::upgrade');
        $resultPage->getConfig()->getTitle()->prepend(__('miniOrange Admin Activity Log'));
        return $resultPage;
    }

    /**
     * Is the user allowed to view the page.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MiniOrange_AdminLogs::upgrade');
    }
}
