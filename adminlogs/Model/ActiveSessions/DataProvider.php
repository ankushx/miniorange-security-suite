<?php
namespace MiniOrange\AdminLogs\Model\ActiveSessions;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Magento\Framework\App\RequestInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\User\Model\UserFactory;
use MiniOrange\AdminLogs\Helper\Data as HelperData;
use Magento\Security\Model\ResourceModel\AdminSessionInfo\CollectionFactory as AdminSessionInfoCollectionFactory;

class DataProvider extends AbstractDataProvider
{
    protected $request;
    protected $adminSession;
    protected $userFactory;
    protected $helper;
    protected $adminSessionInfoCollectionFactory;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        AdminSessionInfoCollectionFactory $adminSessionInfoCollectionFactory,
        RequestInterface $request,
        AdminSession $adminSession,
        UserFactory $userFactory,
        HelperData $helper,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->adminSessionInfoCollectionFactory = $adminSessionInfoCollectionFactory;
        $this->collection = $this->adminSessionInfoCollectionFactory->create();
        $this->request = $request;
        $this->adminSession = $adminSession;
        $this->userFactory = $userFactory;
        $this->helper = $helper;
    }

    public function getData()
    {
        // Get the allowed admin user IDs
        $allowedUserIds = $this->helper->getAllowedUserIds($this->helper->getAdminUsersLimitValue());
        
        // Filter: status must be 1 and user_id must not be empty
        $this->getCollection()->addFieldToFilter('status', ['eq' => 1]);
        $this->getCollection()->addFieldToFilter('user_id', ['notnull' => true]);
        $this->getCollection()->addFieldToFilter('user_id', ['gt' => 0]);
        
        if (!empty($allowedUserIds)) {
            $this->getCollection()->addFieldToFilter('user_id', ['in' => $allowedUserIds]);
        } else {
            $this->getCollection()->addFieldToFilter('user_id', ['eq' => 0]);
        }
        $id = $this->request->getParam('id');
        if($id){
            if (in_array((int)$id, $allowedUserIds, true)) {
                $this->getCollection()->addFieldToFilter('user_id', ['eq' => $id ]);
            } else {
                $this->getCollection()->addFieldToFilter('user_id', ['eq' => 0]);
            }
        }
        
        if (!$this->getCollection()->isLoaded()) {
            $this->getCollection()->load();
        }
        
        $currentUserId = $this->adminSession->getUser()->getId();
        $items = $this->getCollection()->toArray();
        
        foreach($items['items'] as &$item){
            $item['is_current_user'] = isset($item['user_id']) && $item['user_id'] == $currentUserId;

            $userId = isset($item['user_id']) ? (int)$item['user_id'] : 0;
            $user = $userId ? $this->userFactory->create()->load($userId) : null;

            if (!isset($item['user_name']) && $user && $user->getId()) {
                $item['user_name'] = $user->getUserName();
            }

            if (!isset($item['name']) && $user && $user->getId()) {
                $item['name'] = trim($user->getFirstname() . ' ' . $user->getLastname());
            }

            if (!isset($item['full_name']) && $user && $user->getId()) {
                $item['full_name'] = trim($user->getFirstname() . ' ' . $user->getLastname());
            }

            if (!isset($item['logdate'])) {
                if (isset($item['created_at'])) {
                    $item['logdate'] = $item['created_at'];
                } elseif (isset($item['login_time'])) {
                    $item['logdate'] = $item['login_time'];
                }
            }

            if (!isset($item['login_time'])) {
                if (isset($item['created_at'])) {
                    $item['login_time'] = $item['created_at'];
                } elseif (isset($item['logdate'])) {
                    $item['login_time'] = $item['logdate'];
                }
            }

            if (!isset($item['last_activity'])) {
                if (isset($item['updated_at'])) {
                    $item['last_activity'] = $item['updated_at'];
                } elseif (isset($item['last_usage'])) {
                    $item['last_activity'] = $item['last_usage'];
                }
            }

            if (!isset($item['location'])) {
                $ipAddress = isset($item['ip']) ? $item['ip'] : null;
                $item['location'] = $ipAddress ? $this->helper->getLocationFromIp($ipAddress) : 'Unknown';
            }
        }
        
        return [
            'totalRecords' => $this->getCollection()->getSize(),
            'items' => !empty($items['items']) ? array_values($items['items']) : []
        ];
    }
}
