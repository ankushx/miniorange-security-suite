<?php
namespace MiniOrange\AdminLogs\Model\ConcurrentSessions;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\User\Model\ResourceModel\User\CollectionFactory as UserCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use MiniOrange\AdminLogs\Helper\Data as HelperData;

class DataProvider extends AbstractDataProvider
{
    protected $userCollectionFactory;
    protected $resource;
    protected $helper;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        UserCollectionFactory $userCollectionFactory,
        ResourceConnection $resource,
        HelperData $helper,
        array $meta = [],
        array $data = []
    ) {
        $this->userCollectionFactory = $userCollectionFactory;
        $this->resource = $resource;
        $this->helper = $helper;
        $this->collection = $this->userCollectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData()
    {
        // Get the allowed admin user IDs
        $allowedUserIds = $this->helper->getAllowedUserIds($this->helper->getAdminUsersLimitValue());
        
        // Get all sessions from the admin_user_session table
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('admin_user_session');
        
        $select = $connection->select()
            ->from($tableName, ['user_id'])
            ->where('status = ?', 1)  // Only count active sessions (status = 1)
            ->where('user_id IS NOT NULL')
            ->where('user_id > ?', 0);
        
        // Filter to only sessions for the allowed admin users
        if (empty($allowedUserIds)) {
            return [
                'totalRecords' => 0,
                'items' => [],
            ];
        }
        $select->where('user_id IN (?)', $allowedUserIds);

        
        $sessions = $connection->fetchAll($select);

        $sessionCounts = [];
        foreach ($sessions as $session) {
            $userId = $session['user_id'];
            if (!isset($sessionCounts[$userId])) {
                $sessionCounts[$userId] = 0;
            }
            $sessionCounts[$userId]++;
        }

        // Filter collection to only allowed admin users
        if (!empty($allowedUserIds)) {
            $this->collection->addFieldToFilter('user_id', ['in' => $allowedUserIds]);
        } else {
            $this->collection->addFieldToFilter('user_id', ['eq' => 0]);
        }

        // Prepare items with session count (only for allowed users)
        $items = [];
        foreach ($this->collection as $user) {
            $userId = $user->getId();
            $items[$userId] = $user->getData();
            $firstName = $user->getFirstname() ?: '';
            $lastName = $user->getLastname() ?: '';
            $items[$userId]['name'] = trim($firstName . ' ' . $lastName);
            $items[$userId]['session_count'] = isset($sessionCounts[$userId]) ? $sessionCounts[$userId] : 0;
        }

        return [
            'totalRecords' => $this->collection->getSize(),
            'items' => array_values($items),
        ];
    }
}
