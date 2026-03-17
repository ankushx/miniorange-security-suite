<?php

namespace MiniOrange\AdminLogs\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Security\Model\ResourceModel\AdminSessionInfo\CollectionFactory as AdminSessionInfoCollectionFactory;
use MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs\CollectionFactory as AdminLoginLogsCollectionFactory;

class Excel extends Action
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
     * Execute Excel XML export
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        try {
            $logType = $this->getRequest()->getParam('log_type', 'login_logout');
            
            $fileName = $this->getFileName($logType);
            $content = $this->generateExcelXmlContent($logType);
            
            return $this->fileFactory->create(
                $fileName,
                $content,
                DirectoryList::VAR_DIR,
                'application/vnd.ms-excel'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error exporting Excel XML: %1', $e->getMessage()));
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
        return $baseName . '_' . date('Y-m-d_H-i-s') . '.xml';
    }

    /**
     * Generate Excel XML content based on log type
     *
     * @param string $logType
     * @return string
     */
    protected function generateExcelXmlContent($logType)
    {
        switch ($logType) {
            case 'login_logout':
                return $this->generateLoginLogoutExcel();
            case 'active_sessions':
                return $this->generateActiveSessionsExcel();
            case 'concurrent_sessions':
                return $this->generateConcurrentSessionsExcel();
            default:
                return $this->generateLoginLogoutExcel();
        }
    }

    /**
     * Generate Excel XML for Login/Logout logs
     *
     * @return string
     */
    protected function generateLoginLogoutExcel()
    {
        $collection = $this->adminLoginLogsCollectionFactory->create();
        $collection->setOrder('log_id', 'DESC');
        $collection->load();
        
        if ($collection->getSize() == 0) {
            throw new \Exception(__('No records found to download.'));
        }
        
        $xml = $this->getXmlHeader('Admin Login Logout Logs');
        
        $headers = ['ID', 'Logged At', 'Username', 'Full Name', 'IP Address', 'User Agent', 'Location', 'Status', 'Type'];
        $xml .= $this->generateXmlRow($headers);
        
        foreach ($collection as $item) {
            $rowData = [
                $item->getLogId(),
                $this->formatDate($item->getLoggedAt()),
                $item->getUsername() ?? '',
                $item->getFullName() ?? '',
                $item->getIpAddress() ?? '',
                $item->getUserAgent() ?? '',
                $item->getLocation() ?? '',
                $item->getStatus() ?? '',
                $item->getType() ?? ''
            ];
            $xml .= $this->generateXmlRow($rowData);
        }
        
        $xml .= $this->getXmlFooter();
        return $xml;
    }

    /**
     * Get XML header
     *
     * @param string $worksheetName
     * @return string
     */
    protected function getXmlHeader($worksheetName)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $xml .= '<Worksheet ss:Name="' . htmlspecialchars($worksheetName) . '">' . "\n";
        $xml .= '<Table>' . "\n";
        return $xml;
    }

    /**
     * Get XML footer
     *
     * @return string
     */
    protected function getXmlFooter()
    {
        $xml = '</Table>' . "\n";
        $xml .= '</Worksheet>' . "\n";
        $xml .= '</Workbook>' . "\n";
        return $xml;
    }

    /**
     * Generate XML row
     *
     * @param array $rowData
     * @return string
     */
    protected function generateXmlRow($rowData)
    {
        $xml = '<Row>' . "\n";
        foreach ($rowData as $data) {
            $type = is_numeric($data) ? 'Number' : 'String';
            $xml .= '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($data) . '</Data></Cell>' . "\n";
        }
        $xml .= '</Row>' . "\n";
        return $xml;
    }

    /**
     * Format date for XML export
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

