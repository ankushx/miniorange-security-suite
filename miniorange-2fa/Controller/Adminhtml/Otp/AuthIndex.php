<?php
/**
 * Class to Handle AUTHINDEX Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\Controller\Adminhtml\Otp;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class AuthIndex
 * @package MiniOrange\TwoFactorAuth\Controller\Adminhtml\Google
 */
class AuthIndex extends Action
{
    /**
     * Page result factory
     *
     * @var PageFactory
     */
    public $resultPageFactory;

    /**
     * @var SessionManager
     */
    protected $_storageSession;

    /**
     * @var HelperData
     */
    protected $_helperData;

    /**
     * AuthIndex constructor.
     *
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param SessionManager $storageSession
     * @param HelperData $helperData
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        SessionManager $storageSession
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_storageSession   = $storageSession;

        parent::__construct($context);
    }

    /**
     * execute the action
     *
     * @return \Magento\Backend\Model\View\Result\Page|Page
     */
    public function execute()
    {
        return $this->resultPageFactory->create();
    }

    /**
     * Check if user has permissions to access this controller
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return (bool) $this->_storageSession->getData('user');
    }
}
