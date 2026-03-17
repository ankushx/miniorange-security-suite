<?php
namespace MiniOrange\AdminLogs\Controller\Adminhtml\Configuration;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class Save extends Action
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var Pool
     */
    protected $cacheFrontendPool;

    /**
     * @param Context $context
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $activeTab = $this->getRequest()->getParam('active_tab', 'configuration');
        
        if ($data && isset($data['config'])) {
            $configData = $data['config'];
            try {
                
                if (isset($configData['admin_action_log'])) {
                    $this->saveConfig('miniorange/adminlogs/configuration/admin_action_log', $configData['admin_action_log']);
                }

                $this->messageManager->addSuccessMessage(__('Configuration saved successfully.'));

                $this->cacheTypeList->cleanType('config');

            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('adminlogs/configuration/index', ['active_tab' => $activeTab]);
        return $resultRedirect;
    }

    /**
     * Save config value
     *
     * @param string $path
     * @param mixed $value
     */
    private function saveConfig($path, $value)
    {
        $this->configWriter->save($path, $value);
    }

    /**
     * Is the user allowed to perform the action.
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MiniOrange_AdminLogs::configuration');
    }
}