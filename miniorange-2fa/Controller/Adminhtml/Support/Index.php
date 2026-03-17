<?php

namespace MiniOrange\TwoFA\Controller\Adminhtml\Support;

use Magento\Framework\App\Action\HttpGetActionInterface;
use MiniOrange\TwoFA\Helper\TwoFAConstants;
use Magento\Framework\Controller\ResultFactory;
use MiniOrange\TwoFA\Helper\TwoFAMessages;
use MiniOrange\TwoFA\Helper\Curl;
use MiniOrange\TwoFA\Controller\Actions\BaseAdminAction;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;

/**
 * This class handles the action for endpoint: moTwoFA/support/Index
 * Extends the \Magento\Backend\App\Action for Admin Actions which
 * inturn extends the \Magento\Framework\App\Action\Action class necessary
 * for each Controller class
 *
 * This class handles processing and sending or support request
 */
class Index extends BaseAdminAction implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * Constructor method.
     *
     * @param Context $context
     * @param FileFactory $fileFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \MiniOrange\TwoFA\Helper\TwoFAUtility $twofautility,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Psr\Log\LoggerInterface $logger,
        FileFactory $fileFactory
    )
    {
        parent::__construct($context, $resultPageFactory, $twofautility, $messageManager, $logger);
        $this->fileFactory = $fileFactory;
    }

    /**
     * The first function to be called when a Controller class is invoked.
     * Usually, has all our controller logic. Returns a view/page/template
     * to be shown to the users.
     *
     * This function gets and prepares all our SP config data from the
     * database. It's called when you visis the moasaml/metadata/Index
     * URL. It prepares all the values required on the SP setting
     * page in the backend and returns the block to be displayed.
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {   
        try {
            $params = $this->getRequest()->getParams(); //get params
            if ($this->isFormOptionBeingSaved($params)) {
                if (isset($params['send_query']) && $params['send_query'] == 'Submit Query') {

                    $this->checkIfSupportQueryFieldsEmpty(['email' => $params, 'query' => $params]);
                    $email = $params['email'];
                    $phone = $params['phone'];
                    $query = $params['query'];
                    Curl::submit_contact_us($email, $phone, $query);
                    $this->messageManager->addSuccessMessage(TwoFAMessages::QUERY_SENT);
                }

                if (isset($params['option']) && $params['option'] == 'enable_debug_log') {
                    $debug_log_on = isset($params['debug_log_on']) ? 1 : 0;
                    $log_file_time = time();
                    $this->twofautility->setStoreConfig(TwoFAConstants::ENABLE_DEBUG_LOG, $debug_log_on);
                    $this->twofautility->flushCache();
                    $this->twofautility->reinitConfig();

                    if ($debug_log_on == '1') {
                        $this->twofautility->setStoreConfig(TwoFAConstants::LOG_FILE_TIME, $log_file_time);
                    } elseif ($debug_log_on == '0' && $this->twofautility->isCustomLogExist()) {
                        $this->twofautility->setStoreConfig(TwoFAConstants::LOG_FILE_TIME, NULL);
                        $this->twofautility->deleteCustomLogFile();
                    }
                    $this->messageManager->addSuccessMessage(TwoFAMessages::SETTINGS_SAVED);

                } elseif (isset($params['option']) && $params['option'] == 'clear_download_logs') {
                    if (isset($params['download_logs'])) {
                        $fileName = "mo_twofa.log"; // add your file name here
                        if ($fileName) {
                            $filePath = '../var/log/' . $fileName;
                            $content['type'] = 'filename';// type has to be "filename"
                            $content['value'] = $filePath; // path where file place
                            $content['rm'] = 0; // if you add 1 then it will be delete from server after being download, otherwise add 0.
                            if ($this->twofautility->isLogEnable()) {
                                //save configuration
                                $this->customerConfigurationSettings();
                            }
                            if ($this->twofautility->isCustomLogExist() && $this->twofautility->isLogEnable()) {
                                return $this->create_log_file($fileName, $content);
                            } else {
                                $this->messageManager->addErrorMessage('Please Enable Debug Log Setting First');

                            }
                        } else {
                            $this->messageManager->addErrorMessage('Something went wrong');

                        }
                    } elseif (isset($params['clear_logs'])) {
                        if ($this->twofautility->isCustomLogExist()) {
                            $this->twofautility->setStoreConfig(TwoFAConstants::LOG_FILE_TIME, NULL);
                            $this->twofautility->deleteCustomLogFile();
                            $this->messageManager->addSuccessMessage('Logs Cleared Successfully');
                        } else {
                            $this->messageManager->addSuccessMessage('Logs Have Already Been Removed');
                        }

                    }
                }


            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->logger->debug($e->getMessage());
        }
        // $resultPage = $this->resultPageFactory->create();
        // $resultPage->getConfig()->getTitle()->prepend(__(TwoFAConstants::MODULE_TITLE));
         $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }

    private function customerConfigurationSettings()
    {
        $customer_email = $this->twofautility->getStoreConfig(TwoFAConstants::DEFAULT_MAP_EMAIL);


        $this->twofautility->customlog("......................................................................");
        $this->twofautility->customlog("Plugin: Magento version : " . $this->twofautility->getProductVersion() . " ; Php version: " . phpversion());
        $this->twofautility->customlog("Customer_email: " . $customer_email);
        $this->twofautility->customlog("......................................................................");


    }

    /**
     * Create a log file with the given name and content in the var directory.
     *
     * @param string $fileName Log file name
     * @param string $content Log file content
     * @return mixed
     */
    public function create_log_file($fileName, $content)
    {
        return $this->fileFactory->create($fileName, $content, DirectoryList::VAR_DIR);
    }


    /**
     * Is the user allowed to view the Support settings.
     * This is based on the ACL set by the admin in the backend.
     * Works in conjugation with acl.xml
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed(TwoFAConstants::MODULE_DIR.TwoFAConstants::MODULE_SUPPORT);
    }
}
