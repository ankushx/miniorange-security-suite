<?php
namespace MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\Framework\DB\Select;
use Magento\Framework\App\RequestInterface;

class Collection extends SearchResult
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param \Magento\Framework\Data\Collection\EntityFactoryInterface 
     * @param \Psr\Log\LoggerInterface 
     * @param \Magento\Framework\Data\Collection\Db\FetchStrategyInterface 
     * @param \Magento\Framework\Event\ManagerInterface 
     * @param string 
     * @param string 
     * @param RequestInterface 
     * @param string 
     * @param string 
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        $mainTable,
        $resourceModel,
        RequestInterface $request,
        $identifierName = 'log_id',
        $connectionName = null
    ) {
        $this->request = $request;
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(
            \MiniOrange\AdminLogs\Model\AdminLoginLogs::class,
            \MiniOrange\AdminLogs\Model\ResourceModel\AdminLoginLogs::class
        );
    }

    /**
     * @inheritdoc
     */
    protected function _renderFiltersBefore()
    {
        $fulltextFilter = null;
        $filters = $this->request->getParam('filters', []);
        if (isset($filters['fulltext'])) {
            $fulltextFilter = $filters['fulltext'];
        }
        
        if ($fulltextFilter) {
            $connection = $this->getConnection();
            $mainTable = $this->getMainTable();
            $searchFields = [
                'username',
                'full_name',
                'ip_address',
                'user_agent',
                'location',
                'status',
                'type'
            ];
            
            $conditions = [];
            foreach ($searchFields as $field) {
                $conditions[] = $connection->quoteInto(
                    $mainTable . '.' . $field . ' LIKE ?',
                    '%' . $fulltextFilter . '%'
                );
            }
            
            if (!empty($conditions)) {
                $this->getSelect()->where(implode(' OR ', $conditions));
            }
        }
        parent::_renderFiltersBefore();
    }
}
