<?php

namespace MiniOrange\BruteForceProtection\Helper;

/**
 * BruteForce Protection Constants
 * Contains all constants used throughout the extension
 */
class BruteForceConstants
{
    // Module Information
    const MODULE_NAME = 'BruteForceProtection';
    const MODULE_DIR = 'MiniOrange_BruteForceProtection::';
    const MODULE_TITLE = 'miniOrange BruteForce Protection';
    const SECURITY_SUITE_NAME = 'Security Suite';
    const MODULE_BASE = 'bruteforce';
    const MODULE_VERSION = '1.0.0';

    // ACL Resources
    const BRUTEFORCE_SETTINGS = 'bruteforce_settings';
    const ADVANCED_PROTECTION = 'advanced_protection';
    const BLOCKED_USERS = 'blocked_users';
    const CAPTCHA_SETTINGS = 'captcha_settings';
    const ACCOUNT_SETTINGS = 'account_settings';
    const SUPPORT = 'support';
    const UPGRADE = 'upgrade';

    // General Settings
    const BRUTEFORCE_ENABLED = 'miniorange/SecuritySuite/bruteforce/general/enabled';
    const MAX_ATTEMPTS = 'miniorange/SecuritySuite/bruteforce/general/max_attempts';
    const LOCKOUT_DURATION = 'miniorange/SecuritySuite/bruteforce/general/lockout_duration';
    // Forgot Password Protection
    const FORGOT_PASSWORD_PROTECTION = 'miniorange/SecuritySuite/bruteforce/forgot_password/enabled';    

    // IP Management (Enterprise)
    const IP_WHITELIST = 'miniorange/SecuritySuite/bruteforce/ip/whitelist';
    const IP_BLACKLIST = 'miniorange/SecuritySuite/bruteforce/ip/blacklist';

    const CUSTOMER_SECURITY_BFP_ENABLE = 'miniorange/SecuritySuite/bruteforce/customer/enabled';
    const CUSTOMER_MAX_ATTEMPTS_DELAY = 'miniorange/SecuritySuite/bruteforce/customer/max_attempts_delay';
    const CUSTOMER_DELAY_SECONDS = 'miniorange/SecuritySuite/bruteforce/customer/delay_seconds';
    const CUSTOMER_MAX_ATTEMPTS_WARNING = 'miniorange/SecuritySuite/bruteforce/customer/max_attempts_warning';
    const CUSTOMER_ADMIN_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/customer/admin_email_template';
    const CUSTOMER_CUSTOMER_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/customer/customer_email_template';
    const CUSTOMER_MAX_ATTEMPTS_LOCKOUT = 'miniorange/SecuritySuite/bruteforce/customer/max_attempts_lockout';

    const CUSTOMER_SECURITY_FORGOT_PASSWORD_PROTECTION = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_enabled';
    const CUSTOMER_FORGOT_PASSWORD_MAX_ATTEMPTS_DELAY = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_delay';
    const CUSTOMER_FORGOT_PASSWORD_DELAY_SECONDS = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_delay_seconds';
    const CUSTOMER_FORGOT_PASSWORD_MAX_ATTEMPTS_WARNING = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_warning';
    const CUSTOMER_FORGOT_PASSWORD_ADMIN_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_admin_email_template';
    const CUSTOMER_FORGOT_PASSWORD_CUSTOMER_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_customer_email_template';
    const CUSTOMER_FORGOT_PASSWORD_MAX_ATTEMPTS_LOCKOUT = 'miniorange/SecuritySuite/bruteforce/customer/forgot_password_max_attempts_lockout';

    const ADMIN_SECURITY_BFP_ENABLE = 'miniorange/SecuritySuite/bruteforce/admin/enabled';
    const ADMIN_MAX_ATTEMPTS_DELAY = 'miniorange/SecuritySuite/bruteforce/admin/max_attempts_delay';
    const ADMIN_DELAY_SECONDS = 'miniorange/SecuritySuite/bruteforce/admin/delay_seconds';
    const ADMIN_MAX_ATTEMPTS_WARNING = 'miniorange/SecuritySuite/bruteforce/admin/max_attempts_warning';
    const ADMIN_ADMIN_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/admin/admin_email_template';
    const ADMIN_CUSTOMER_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/admin/customer_email_template';
    const ADMIN_MAX_ATTEMPTS_LOCKOUT = 'miniorange/SecuritySuite/bruteforce/admin/max_attempts_lockout';

    const ADMIN_SECURITY_FORGOT_PASSWORD_PROTECTION = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_enabled';
    const ADMIN_FORGOT_PASSWORD_MAX_ATTEMPTS_DELAY = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_delay';
    const ADMIN_FORGOT_PASSWORD_DELAY_SECONDS = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_delay_seconds';
    const ADMIN_FORGOT_PASSWORD_MAX_ATTEMPTS_WARNING = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_warning';
    const ADMIN_FORGOT_PASSWORD_ADMIN_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_admin_email_template';
    const ADMIN_FORGOT_PASSWORD_CUSTOMER_EMAIL_TEMPLATE = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_customer_email_template';
    const ADMIN_FORGOT_PASSWORD_MAX_ATTEMPTS_LOCKOUT = 'miniorange/SecuritySuite/bruteforce/admin/forgot_password_max_attempts_lockout';


    

    const CUSTOMER_IP_WHITELIST = 'miniorange/SecuritySuite/bruteforce/customer/ip_whitelist';

    // License Settings
    const LICENSE_KEY = 'miniorange/SecuritySuite/bruteforce/license/key';
    const LICENSE_TYPE = 'miniorange/SecuritySuite/bruteforce/license/type';
    const TRIAL_DAYS = 'miniorange/SecuritySuite/bruteforce/license/trial_days';

    // Default Values
    const DEFAULT_MAX_ATTEMPTS = 5;
    const DEFAULT_LOCKOUT_DURATION = 300; // 5 minutes
    const DEFAULT_DELAY_SECONDS = 30;
    const DEFAULT_CAPTCHA_THRESHOLD = 3;

    // Feature Flags
    const FEATURE_CUSTOMER_DELAY = 'customer_delay';
    const FEATURE_ONE_CLICK_UNBLOCK = 'one_click_unblock';
    const FEATURE_PER_STORE_CONFIG = 'per_store_config';
    const FEATURE_RECAPTCHA = 'recaptcha';
    const FEATURE_ADVANCED_PROTECTION = 'advanced_protection';
    const FEATURE_BULK_ACTIONS = 'bulk_actions';
    const FEATURE_IP_WHITELIST = 'ip_whitelist';
    const FEATURE_IP_BLACKLIST = 'ip_blacklist';

    // License Types
    const LICENSE_FREE = 'free';
    const LICENSE_PREMIUM = 'premium';
    const LICENSE_ENTERPRISE = 'enterprise';

    const PLUGIN_PORTAL_HOSTNAME  = "https://magento.shanekatear.in/plugin-portal";
    const DEFAULT_CUSTOMER_KEY = "16555";
    const DEFAULT_API_KEY = "fFd2XcvTGDemZvbw1bcUesNJWEqKbbUq";
    const PLUGIN_VERSION = 'v1.0.0';

}
