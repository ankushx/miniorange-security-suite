<?php
/**
 * Class to Handle TRUSTED Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Trusted
 * @package MiniOrange\TwoFA\Model
 */
class Trusted extends AbstractModel
{
    /**
     * Cache tag
     *
     * @var string
     */
    const CACHE_TAG = 'MiniOrange_twofactorauth_trusted';

    /**
     * Cache tag
     *
     * @var string
     */
    protected $_cacheTag = 'MiniOrange_twofactorauth_trusted';

    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'MiniOrange_twofactorauth_trusted';

    /**
     * @var string
     */
    protected $_idFieldName = 'trusted_id';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\Trusted::class);
    }

    /**
     * @return array
     */
    public function getIdentities()
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }
}
