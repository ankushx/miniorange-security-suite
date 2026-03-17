<?php
/**
 * Class to Handle AUTHENTICATION Operations
 *
 * @category Core, Helpers
 * @package  MoOauthClient
 * @author   miniOrange <info@miniorange.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @link     https://miniorange.com
 */
namespace MiniOrange\TwoFA\App\Action\Plugin;

/**
 * Class Authentication
 * @package MiniOrange\TwoFA\App\Action\Plugin
 */
class Authentication extends \Magento\Backend\App\Action\Plugin\Authentication
{
    /**
     * @var string[]
     */
    protected $_openActions = [
        'authindex',
        'authpost',
        'forgotpassword',
        'resetpassword',
        'resetpasswordpost',
        'logout',
        'refresh', // captcha refresh
    ];
}
