<?php

namespace MiniOrange\AdminLogs\Controller\Adminhtml\ConcurrentSessions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use MiniOrange\AdminLogs\Helper\Data as HelperData;

class GetSessions extends Action
{
    const ADMIN_RESOURCE = 'MiniOrange_AdminLogs::activesessions';

    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var HelperData
     */
    protected $helper;

    /**
     * @param Context $context
     * @param ResourceConnection $resource
     * @param JsonFactory $resultJsonFactory
     * @param HelperData $helper
     */
    public function __construct(
        Context $context,
        ResourceConnection $resource,
        JsonFactory $resultJsonFactory,
        HelperData $helper
    ) {
        parent::__construct($context);
        $this->resource = $resource;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helper = $helper;
    }

    /**
     * Execute get sessions action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        $userId = $this->getRequest()->getParam('user_id');

        if (empty($userId)) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('User ID is required.'),
                'sessions' => []
            ]);
        }

        try {
            $userId = (int) $userId;
            
            $connection = $this->resource->getConnection();
            
            $select = $connection->select()
                ->from(['aus' => $this->resource->getTableName('admin_user_session')], [
                    'id',
                    'ip' => 'ip',
                    'created_at' => 'created_at',
                    'updated_at' => 'updated_at',
                    'user_id' => 'user_id'
                ])
                ->joinLeft(
                    ['au' => $this->resource->getTableName('admin_user')],
                    'aus.user_id = au.user_id',
                    ['username' => 'au.username']
                )
                ->where('aus.user_id = ?', $userId)
                ->where('aus.status = ?', 1)
                ->order('aus.created_at DESC');

            $sessions = $connection->fetchAll($select);

            // Format sessions data
            $formattedSessions = [];
            foreach ($sessions as $session) {
                $ip = $session['ip'] ?? '';
                $location = 'Unknown';
                if (!empty($ip)) {
                    $location = $this->helper->getLocationFromIp($ip);
                }
                
                $formattedSessions[] = [
                    'id' => $session['id'] ?? '',
                    'ip' => $ip ?: 'Unknown',
                    'location' => $location,
                    'login_time' => $this->formatDate($session['created_at'] ?? ''),
                    'last_activity' => $this->formatDate($session['updated_at'] ?? ''),
                    'user_agent' => 'Unknown'
                ];
            }

            $response = [
                'success' => true,
                'sessions' => $formattedSessions
            ];
            
            if (empty($formattedSessions)) {
                $response['message'] = __('No active sessions found for this user.');
            }

            return $resultJson->setData($response);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Error fetching sessions: %1', $e->getMessage()),
                'sessions' => []
            ]);
        }
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

