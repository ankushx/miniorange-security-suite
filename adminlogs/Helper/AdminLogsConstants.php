<?php

namespace MiniOrange\AdminLogs\Helper;

/** This class lists down constant values used all over our MODULE_DIR. */
class AdminLogsConstants
{
    const MODULE_DIR         = 'MiniOrange_AdminLogs';
    const MODULE_TITLE       = 'miniOrange Admin Activity Log';
    const SECURITY_SUITE_NAME = 'Security Suite';
    const VERSION            = "v1.0.0";

    //ACL Settings
    const MODULE_BASE         = '::AdminLogs';
    const MODULE_ADMINLOGSSETTINGS= '::login';
    const MODULE_ADMINCRUDSETTINGS= '::crud';
    const MODULE_ADMINROLESSETTINGS= '::roles';
    const MODULE_SUPPORT    = '::support';

    // Admin Users Limit and Count (encrypted)
    const ADMIN_USERS_LIMIT = 'miniorange/SecuritySuite/admin_users_limit';
    const ADMIN_USERS_COUNT = 'miniorange/SecuritySuite/admin_users_count';

    //plugin constants
    const DEFAULT_CUSTOMER_KEY     = "16555";
    const DEFAULT_TOKEN = "E7XIXCVVUOYAIA2";
    const DEFAULT_API_KEY         = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";

    const ACTION_LOGIN = 'Login';
    const ACTION_LOGOUT = 'Logout';
    const STATUS_SUCCESS = 'Success';
    const STATUS_FAILURE = 'Failure';

    const ERROR_QUERY                     = 'Your query could not be submitted. Please try again.';
    const QUERY_SENT                    = 'Thanks for getting in touch! We shall get back to you shortly.';
    const REQUIRED_QUERY_FIELDS         = 'Please fill up Email and Query fields to submit your query.';

    const HOSTNAME                = "https://login.xecurify.com";
    const AREA_OF_INTEREST         = 'Magento Admin Activity Log';

    //anusha
    const TIME_STAMP = 'miniorange/SecuritySuite/timestamp';
    const DATA_ADDED = 'miniorange/SecuritySuite/data_added';
    const PLUGIN_PORTAL_HOSTNAME  = "https://magento.shanekatear.in/plugin-portal";
    const PLUGIN_VERSION = 'v1.0.0';
    const PLUGIN_NAME = 'miniOrange Admin Activity Log';
    const BASE = 'base';
    const EMAIL_ID        = 'email';
    
}