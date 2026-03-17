<?php

namespace MiniOrange\TwoFA\Helper;

/** This class lists down constant values used all over our Module. */
class TwoFAConstants
{
    const MODULE_DIR = "MiniOrange_TwoFA";
    const MODULE_TITLE = "Two Factor Authentication";
    const APPLICATION_NAME = "app_name";
    //ACL Settings
    const MODULE_BASE = "::TwoFA";
    const MODULE_TWOFASETTINGS = "::twofa_settings";
    const MODULE_SIGNIN = "::signin_settings";
    const MODULE_ACCOUNT = "::account_settings";
    const MODULE_ADVANCE_2FA = "::advance_2fa";
    const MODULE_USER_MANAGEMENT = "::user_management";
    const MODULE_UPGRADE = "::upgrade";
    const MODULE_TFA_SETTINGS_CONFIGURATION_TABLE = "::tfa_settings_configuration_table";
    const MODULE_TFA = "moduleTfa";
    const INVOKE_INLINE_REGISTERATION = "invokeInline";
    const ACTIVE_METHOD = "activeMethods";
    const MODULE_SUPPORT = "::support";
    const MODULE_IMAGES = "::images/";
    const MODULE_CERTS = "::certs/";
    const MODULE_CSS = "::css/";
    const MODULE_JS = "::js/";
    const TwoFA_AUTHENTICATOR_ISSUER = "miniOrange";
    const TwoFA_AUTHENTICATOR_SECRET_DB_KEY = "moAuthenticatorSecret";
    const SEND_EMAIL = "send_email";
    // request option parameter values
    const LOGIN_ADMIN_OPT = "TwoFALoginAdminUser";
    const TEST_CONFIG_OPT = "testConfig";

    //database keys

    const APP_NAME = "appName";
    const CLIENT_ID = "clientID";
    const CLIENT_SECRET = "clientSecret";
    const SCOPE = "scope";
    const AUTHORIZE_URL = "authorizeURL";
    const ACCESSTOKEN_URL = "accessTokenURL";
    const GETUSERINFO_URL = "getUserInfoURL";
    const TwoFA_LOGOUT_URL = "TwoFALogoutURL";
    const TEST_RELAYSTATE = "testvalidate";
    const MAP_MAP_BY = "amAccountMatcher";
    const DEFAULT_MAP_BY = "email";
    const DEFAULT_GROUP = "General";
    const SEND_HEADER = "header";
    const SEND_BODY = "body";

    const NAME_ID = "nameId";
    const IDP_NAME = "identityProviderName";
    const X509CERT = "certificate";
    const RESPONSE_SIGNED = "responseSigned";
    const ASSERTION_SIGNED = "assertionSigned";
    const ISSUER = "samlIssuer";
    const DB_FIRSTNAME = "firstname";
    const USER_NAME = "username";
    const DB_LASTNAME = "lastname";
    const CUSTOMER_KEY = "customerKey";
    const CUSTOMER_EMAIL = "email";
    const CUSTOMER_PHONE = "phone";
    const CUSTOMER_NAME = "cname";
    const CUSTOMER_FNAME = "customerFirstName";
    const CUSTOMER_LNAME = "customerLastName";
    const SAMLSP_CKL = "ckl";
    const SAMLSP_LK = "lk";
    const SHOW_ADMIN_LINK = "showadminlink";
    const SHOW_CUSTOMER_LINK = "showcustomerlink";
    const REG_STATUS = "registrationStatus";
    const API_KEY = "apiKey";
    const TOKEN = "token";
    const BUTTON_TEXT = "buttonText";
    const IS_TEST = "isTest";

    // attribute mapping constants
    const MAP_EMAIL = "amEmail";
    const DEFAULT_MAP_EMAIL = "email";
    const MAP_USERNAME = "amUsername";
    const DEFAULT_MAP_USERN = "username";
    const MAP_FIRSTNAME = "amFirstName";
    const DEFAULT_MAP_FN = "firstName";
    const MAP_LASTNAME = "amLastName";
    const MAP_DEFAULT_ROLE = "defaultRole";
    const DEFAULT_ROLE = "General";
    const MAP_GROUP = "group";
    const UNLISTED_ROLE = "unlistedRole";
    const CREATEIFNOTMAP = "createUserIfRoleNotMapped";

    //URLs
    const TwoFA_LOGIN_URL = "moTwoFA/actions/sendAuthorizationRequest";

    //images
    const IMAGE_RIGHT = "right.png";
    const IMAGE_WRONG = "wrong.png";

    const TXT_ID = "miniorange/TwoFA/transactionID";
    const CALLBACK_URL = "moTwoFA/actions/ReadAuthorizationResponse";
    const CODE = "code";
    const GRANT_TYPE = "authorization_code";

    //TwoFA Constants
    const TwoFA = "TwoFA";
    const HTTP_REDIRECT = "HttpRedirect";

    //Registration Status
    const STATUS_VERIFY_LOGIN = "MO_VERIFY_CUSTOMER";
    const STATUS_COMPLETE_LOGIN = "MO_VERIFIED";

    //plugin constants
    const DEFAULT_CUSTOMER_KEY = "16555";
    const DEFAULT_API_KEY = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";
    const SAMLSP_KEY = "customerKey";
    const HOSTNAME = "https://login.xecurify.com";
    const PLUGIN_PORTAL_HOSTNAME  = "https://magento.shanekatear.in/plugin-portal";

    const AREA_OF_INTEREST = "Magento 2 Factor Authentication Plugin";

    //consts for before configuration info of user
    const PRE_USERNAME = "pre_username";
    const PRE_EMAIL = "pre_email";
    const PRE_ID = "pre_id";
    const PRE_PHONE = "pre_phone";
    const PRE_TRANSACTIONID = "pre_transactionid";
    const PRE_SECRET = "pre_secret";
    const PRE_ACTIVE_METHOD = "pre_active_method";
    const PRE_CONFIG_METHOD = "pre_config_method";
    const PRE_COUNTRY_CODE = "countrycode";
    const MAGENTO_COUNTER = "google_auth_counter_customer";
    const PRE_IS_INLINE = "isinline";

    //const for customer inline configuration
    const CUSTOMER_USERNAME = "customer_username";
    const CUSTOMER__EMAIL = "customer_email";
    const CUSTOMER_ID = "customer_id";
    const CUSTOMER__PHONE = "customer_phone";
    const CUSTOMER_TRANSACTIONID = "customer_transactionid";
    const CUSTOMER_SECRET = "customer_secret";
    const CUSTOMER_ACTIVE_METHOD = "customer_active_method";
    const CUSTOMER_CONFIG_METHOD = "customer_config_method";
    const CUSTOMER_COUNTRY_CODE = "customer_countrycode";
    const CUSTOMER_MAGENTO_COUNTER = "customer_google_auth_counter";
    //const CUSTOMER_IS_INLINE = 'customer_isinline';
    const CUSTOMER_STATUS_OF_MOTFA = "customer_status_of_motfa";
    const CUSTOMER_INLINE = "customer_inline";

    //const for admin inline configuration
    const ADMIN_USERNAME = "admin_username";
    const ADMIN__EMAIL = "admin_email";
    const ADMIN_ID = "admin_id";
    const ADMIN__PHONE = "admin_phone";
    const ADMIN_TRANSACTIONID = "admin_transactionid";
    const ADMIN_SECRET = "admin_secret";
    const ADMIN_ACTIVE_METHOD = "admin_active_method";
    const ADMIN_CONFIG_METHOD = "admin_config_method";
    const ADMIN_COUNTRY_CODE = "admin_countrycode";
    const ADMIN_MAGENTO_COUNTER = "admin_google_auth_counter";
    const ADMIN_IS_INLINE = "admin_isinline";
    const ADMIN_STATUS_OF_MOTFA = "admin_status_of_motfa";

    //license key details
    const LK_NO_OF_USERS = "lk_no_of_users";
    const LK_VERIFY = "lk_verify";
    const CUSTOMER_COUNT = "customer_count";
    const LICENSE_PLAN = 0;

    //USER MANAGEMENT CONSTANTS.
    const USER_MANAGEMENT_USERNAME = "user_management_username";
    const USER_MANAGEMENT_EMAIL = "user_management_email";
    const USER_MANAGEMENT_COUNTRYCODE = "user_management_countrycode";
    const USER_MANAGEMENT_PHONE = "user_management_phone";
    const USER_MANAGEMENT_ACTIVEMETHOD = "user_management_activemethod";
    const USER_MANAGEMENT_CONFIGUREDMETHOD = "user_management_configuredmethod";

    //customgateway
    const ENABLE_CUSTOMGATEWAY_SMS = "enable_customgateway_sms";
    const ENABLE_CUSTOMGATEWAY_EMAIL = "enable_customgateway_email";

    //sign in setting
    const NUMBER_OF_ADMIN_METHOD = "number_of_admin_method";
    const ADMIN_ACTIVE_METHOD_INLINE = "admin_active_method_inline";
    const NUMBER_OF_CUSTOMER_METHOD = "number_of_customer_method";

    const ADMIN_SMS_METHOD = "admin_sms_method";
    const ADMIN_EMAIL_METHOD = "admin_email_method";
    const ADMIN_SMSANDEMAIL_METHOD = "admin_smsandemail_method";
    const ADMIN_GOOGLEAUTH_METHOD = "admin_googleauth_method";
    const CUSTOMER_SMS_METHOD = "customer_sms_method";
    const CUSTOMER_EMAIL_METHOD = "customer_email_method";
    const CUSTOMER_SMSANDEMAIL_METHOD = "customer_smsandemail_method";
    const CUSTOMER_GOOGLEAUTH_METHOD = "customer_googleauth_method";

    const ADMIN_INLINE_SMS_METHOD = "admin_inline_sms_method";
    const ADMIN_INLINE_EMAIL_METHOD = "admin_inline_email_method";
    const ADMIN_INLINE_SMSANDEMAIL_METHOD = "admin_inline_smsandemail_method";
    const ADMIN_INLINE_GOOGLEAUTH_METHOD = "admin_inline_googleauth_method";

    const SKIP_TWOFA = "skiptwofa_for_all_customer";
    const SKIP_TWOFA_ADMIN = "skiptwofa_for_all_admin_";

    const REGISTER_CHECKBOX = "verify_otp_on_register";
    const REGISTER_OTP_TYPE = "otp_type_on_register";
    const CUSTOMER_2FA_FOR_REGISTRATION = "customer_2fa_for_registration";
    const NUMBER_OF_CUSTOMER_METHOD_AT_REGISTRATION = "number_of_customer_method_registration";

    const USER_LIMIT_EMAIL_SENT = "user_limit_email_sent";
    const INVOKE_INLINE_TWOFA_METHOD_CHECK = "invoke_inline_twofa_method_check";
    const DATA_ADDED = 'data_added';
    const TIME_STAMP = 'time_stamp';

    const CURRENT_ADMIN_RULE = 'current_admin_rules';
    const CURRENT_CUSTOMER_RULE = 'current_customer_rules';

    const ENABLE_DEBUG_LOG = 'debug_log_twofa';
    const LOG_FILE_TIME = 'log_file_time';

    // B2B/Commerce Edition constants
    const NO_COMPANY_ID = 0;
    const COMPANY_ACTIVE = 1;

}
