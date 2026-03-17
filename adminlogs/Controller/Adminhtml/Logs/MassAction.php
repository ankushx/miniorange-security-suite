<?php
namespace MiniOrange\AdminLogs\Controller\Adminhtml\Logs;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Security\Model\ResourceModel\AdminSessionInfo\CollectionFactory as AdminSessionInfoCollectionFactory;

class MassAction extends Action
{
    const ADMIN_RESOURCE = 'MiniOrange_AdminLogs::login';

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var AdminSessionInfoCollectionFactory
     */
    protected $adminSessionInfoCollectionFactory;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param Context $context
     * @param ResourceConnection $resource
     * @param AdminSessionInfoCollectionFactory $adminSessionInfoCollectionFactory
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        AdminSessionInfoCollectionFactory $adminSessionInfoCollectionFactory,
        UrlInterface $urlBuilder
    ) {
        $this->resource = $resource;
        $this->adminSessionInfoCollectionFactory = $adminSessionInfoCollectionFactory;
        $this->urlBuilder = $urlBuilder;
        parent::__construct($context);
    }

    /**
     * Execute mass action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $logType = $this->getRequest()->getParam('log_type');
        $actionType = $this->getRequest()->getParam('action_type');
        $idsString = $this->getRequest()->getParam('selected_ids');

        if (empty($idsString) || empty($logType) || empty($actionType)) {
            $this->messageManager->addErrorMessage(__('Invalid parameters provided.'));
            $redirectUrl = $this->urlBuilder->getUrl('adminlogs/logsview/index', [
                '_query' => [
                    'logs_type' => $logType
                ]
            ]);
            return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
        }

        $ids = array_filter(explode(',', $idsString));

        if (empty($ids)) {
            $this->messageManager->addNoticeMessage(__('No records were selected.'));
            $redirectUrl = $this->urlBuilder->getUrl('adminlogs/logsview/index', [
                '_query' => [
                    'logs_type' => $logType
                ]
            ]);
            return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
        }

        if ($actionType === "delete") {
            $deletedCount = $this->deleteRecords($logType, $ids);
            if ($deletedCount > 0) {
                $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deletedCount));
            } else {
                $this->messageManager->addNoticeMessage(__('No records were deleted.'));
            }
        } else {
            $this->messageManager->addErrorMessage(__('Invalid action type.'));
        }

        // Redirect to logsview route to stay on the logs view tab
        $redirectUrl = $this->urlBuilder->getUrl('adminlogs/logsview/index', [
            '_query' => [
                'logs_type' => $logType
            ]
        ]);
        return $this->resultRedirectFactory->create()->setUrl($redirectUrl);
    }

    /**
     * Delete records from the specified table
     *
     * @param string $logType
     * @param array $ids
     * @return int Number of deleted records
     */
    private function deleteRecords($logType, $ids)
    {

        $map = [
            'login_logout' => 'miniorange_admin_login_logs',
        ];

        if (!isset($map[$logType])) {
            return 0;
        }

        $table = $map[$logType];
        $connection = $this->resource->getConnection();
        
        $primaryKey = 'log_id';
        
        try {
            $deletedCount = $connection->delete(
                $this->resource->getTableName($table),
                [$connection->quoteIdentifier($primaryKey) . ' IN (?)' => $ids]
            );
            return $deletedCount;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while deleting records: %1', $e->getMessage()));
            return 0;
        }
    }

}

