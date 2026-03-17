<?php
/**
 * Class to Handle COLLECTION Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\Model\ResourceModel\Trusted;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MiniOrange\TwoFA\Model\ResourceModel\Trusted;

/**
 * Class Collection
 * @package MiniOrange\TwoFA\Model\ResourceModel\Trusted
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'trusted_id';

    /**
     * Define model & resource model
     */
    protected function _construct()
    {
        $this->_init(\MiniOrange\TwoFA\Model\Trusted::class, Trusted::class);
    }
}
