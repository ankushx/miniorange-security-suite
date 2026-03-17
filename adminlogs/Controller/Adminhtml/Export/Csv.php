<?php

namespace MiniOrange\AdminLogs\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Security\Model\ResourceModel\AdminSessionInfo\CollectionFactory as AdminSessionInfoCollectionFactory;
use MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs\CollectionFactory as AdminLoginLogsCollectionFactory;

class Csv extends Action
{
    const ADMIN_RESOURCE = 'MiniOrange_AdminLogs::login';

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var AdminLoginLogsCollectionFactory
     */
    protected $adminLoginLogsCollectionFactory;

    /**
     * @var AdminSessionInfoCollectionFactory
     */
    protected $adminSessionInfoCollectionFactory;

    /**
     * @param Context $context
     * @param FileFactory $fileFactory
     * @param ResourceConnection $resource
     * @param AdminLoginLogsCollectionFactory $adminLoginLogsCollectionFactory
     * @param AdminSessionInfoCollectionFactory $adminSessionInfoCollectionFactory
     */
    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        ResourceConnection $resource,
        AdminLoginLogsCollectionFactory $adminLoginLogsCollectionFactory,
        AdminSessionInfoCollectionFactory $adminSessionInfoCollectionFactory
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->resource = $resource;
        $this->adminLoginLogsCollectionFactory = $adminLoginLogsCollectionFactory;
        $this->adminSessionInfoCollectionFactory = $adminSessionInfoCollectionFactory;
    }

    /**
     * Execute CSV export
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        try {
            $logType = $this->getRequest()->getParam('log_type', 'login_logout');
            
            $fileName = $this->getFileName($logType);
            $content = $this->generateCsvContent($logType);
            
            return $this->fileFactory->create(
                $fileName,
                $content,
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting CSV: %1', $e->getMessage()));
            return $this->_redirect('adminlogs/configuration/index', [
                '_query' => [
                    'active_tab' => 'logs_view',
                    'logs_type' => $this->getRequest()->getParam('log_type', 'login_logout')
                ]
            ]);
        }
    }

    /**
     * Get file name based on log type
     *
     * @param string $logType
     * @return string
     */
    protected function getFileName($logType)
    {
        $fileNames = [
            'login_logout' => 'admin_login_logout_logs',
            'active_sessions' => 'admin_active_sessions',
            'concurrent_sessions' => 'admin_concurrent_sessions'
        ];
        
        $baseName = isset($fileNames[$logType]) ? $fileNames[$logType] : 'admin_logs';
        return $baseName . '_' . date('Y-m-d_H-i-s') . '.csv';
    }

    /**
     * Generate CSV content based on log type
     *
     * @param string $logType
     * @return string
     */
    protected function generateCsvContent($logType)
    {
        switch ($logType) {
            case 'login_logout':
                return $this->generateLoginLogoutCsv();
            case 'active_sessions':
                return $this->generateActiveSessionsCsv();
            case 'concurrent_sessions':
                return $this->generateConcurrentSessionsCsv();
            default:
                return $this->generateLoginLogoutCsv();
        }
    }

    /**
     * Generate CSV for Login/Logout logs
     *
     * @return string
     */
    protected function generateLoginLogoutCsv()
    {
        $collection = $this->adminLoginLogsCollectionFactory->create();
        $collection->setOrder('log_id', 'DESC');
        $collection->load();
        
        if ($collection->getSize() == 0) {
            throw new \Exception(__('No records found to download.'));
        }
        
        $content = '';
        $headers = ['ID', 'Logged At', 'Username', 'Full Name', 'IP Address', 'User Agent', 'Location', 'Status', 'Type'];
        $content .= implode(',', $headers) . "\n";
        
        foreach ($collection as $item) {
            $row = [
                $item->getLogId(),
                $this->formatDate($item->getLoggedAt()),
                $this->escapeCsvValue($item->getUsername()),
                $this->escapeCsvValue($item->getFullName()),
                $this->escapeCsvValue($item->getIpAddress()),
                $this->escapeCsvValue($item->getUserAgent()),
                $this->escapeCsvValue($item->getLocation()),
                $this->escapeCsvValue($item->getStatus()),
                $this->escapeCsvValue($item->getType())
            ];
            $content .= implode(',', $row) . "\n";
        }
        
        return $content;
    }

    /**
     * Escape CSV value
     *
     * @param mixed $value
     * @return string
     */
    protected function escapeCsvValue($value)
    {
        if ($value === null) {
            return '';
        }
        $value = (string) $value;
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            $value = '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    /**
     * Format date
     *
     * @param string $date
     * @return string
     */
    protected function formatDate($date)
    {
        if (empty($date)) {
            return '';
        }
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $date;
        }
    }
}

