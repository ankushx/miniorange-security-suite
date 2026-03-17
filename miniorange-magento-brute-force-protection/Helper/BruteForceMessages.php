<?php

namespace MiniOrange\BruteForceProtection\Helper;

/**
 * BruteForce Protection Messages
 * Contains all messages used throughout the extension
 */
class BruteForceMessages
{
    // Success Messages
    const SETTINGS_SAVED = 'BruteForce protection settings saved successfully.';
    const USER_BLOCKED = 'User has been blocked successfully.';
    const USER_UNBLOCKED = 'User has been unblocked successfully.';
    const USERS_BULK_UNBLOCKED = 'Selected users have been unblocked successfully.';
    const USERS_BULK_BLOCKED = 'Selected users have been blocked successfully.';
    const ALL_USERS_UNBLOCKED = 'All users have been unblocked successfully.';
    const LICENSE_ACTIVATED = 'License has been activated successfully.';
    const LICENSE_DEACTIVATED = 'License has been deactivated successfully.';
    const CAPTCHA_CONFIGURED = 'CAPTCHA has been configured successfully.';
    const EMAIL_SETTINGS_SAVED = 'Email notification settings saved successfully.';

    // Error Messages
    const SETTINGS_SAVE_FAILED = 'Failed to save BruteForce protection settings.';
    const USER_BLOCK_FAILED = 'Failed to block user.';
    const USER_UNBLOCK_FAILED = 'Failed to unblock user.';
    const INVALID_LICENSE = 'Invalid license key provided.';
    const LICENSE_EXPIRED = 'Your license has expired. Please renew to continue using premium features.';
    const FEATURE_NOT_AVAILABLE = 'This feature is not available in your current plan.';
    const PREMIUM_FEATURE_REQUIRED = 'This feature requires a premium license.';
    const ENTERPRISE_FEATURE_REQUIRED = 'This feature requires an enterprise license.';
    const CAPTCHA_CONFIG_FAILED = 'Failed to configure CAPTCHA settings.';
    const EMAIL_SEND_FAILED = 'Failed to send email notification.';
    const INVALID_CAPTCHA = 'Invalid CAPTCHA response.';
    const CAPTCHA_REQUIRED = 'CAPTCHA verification is required.';
    const USER_ALREADY_BLOCKED = 'User is already blocked.';
    const USER_NOT_BLOCKED = 'User is not currently blocked.';
    const MAX_ATTEMPTS_EXCEEDED = 'Maximum login attempts exceeded. Account has been temporarily locked.';
    const ACCOUNT_LOCKED = 'Your account has been locked due to multiple failed login attempts.';
    const INVALID_CREDENTIALS = 'Invalid login credentials provided.';
    const IP_BLOCKED = 'Your IP address has been blocked due to suspicious activity.';

    // Warning Messages
    const TRIAL_EXPIRING = 'Your trial will expire in %d days. Please upgrade to continue using premium features.';
    const TRIAL_EXPIRED = 'Your trial has expired. Please upgrade to continue using premium features.';
    const LICENSE_VERIFICATION_FAILED = 'License verification failed. Some features may not be available.';
    const CAPTCHA_NOT_CONFIGURED = 'CAPTCHA is not properly configured. Please check your settings.';
    const EMAIL_NOT_CONFIGURED = 'Email notifications are not configured. Please set up email settings.';

    // Info Messages
    const BRUTEFORCE_ENABLED = 'BruteForce protection has been enabled.';
    const BRUTEFORCE_DISABLED = 'BruteForce protection has been disabled.';
    const CAPTCHA_ENABLED = 'CAPTCHA protection has been enabled.';
    const CAPTCHA_DISABLED = 'CAPTCHA protection has been disabled.';
    const ADMIN_DELAY_ENABLED = 'Admin login delay has been enabled.';
    const ADMIN_DELAY_DISABLED = 'Admin login delay has been disabled.';
    const CUSTOMER_DELAY_ENABLED = 'Customer login delay has been enabled.';
    const CUSTOMER_DELAY_DISABLED = 'Customer login delay has been disabled.';
    const FORGOT_PASSWORD_PROTECTION_ENABLED = 'Forgot password protection has been enabled.';
    const FORGOT_PASSWORD_PROTECTION_DISABLED = 'Forgot password protection has been disabled.';
    const WARNING_EMAILS_ENABLED = 'Warning email notifications have been enabled.';
    const WARNING_EMAILS_DISABLED = 'Warning email notifications have been disabled.';

    // Feature Descriptions
    const FEATURE_DESCRIPTION_BASIC_PROTECTION = 'Basic BruteForce protection with login attempt limiting and account lockout.';
    const FEATURE_DESCRIPTION_ADMIN_DELAY = 'Adds a 30-second delay to admin login form after failed attempts.';
    const FEATURE_DESCRIPTION_CUSTOMER_DELAY = 'Adds a 30-second delay to customer login form after failed attempts.';
    const FEATURE_DESCRIPTION_FORGOT_PASSWORD = 'Protects forgot password forms from BruteForce attacks.';
    const FEATURE_DESCRIPTION_CAPTCHA = 'Integrates CAPTCHA verification to prevent automated attacks.';
    const FEATURE_DESCRIPTION_WARNING_EMAILS = 'Sends email notifications when BruteForce attacks are detected.';
    const FEATURE_DESCRIPTION_ONE_CLICK_UNBLOCK = 'Allows one-click unblocking of all blocked users.';
    const FEATURE_DESCRIPTION_PER_STORE_CONFIG = 'Enables per-store/site configuration for multi-store setups.';
    const FEATURE_DESCRIPTION_RECAPTCHA = 'Advanced reCAPTCHA v2/v3 integration with customizable settings.';
    const FEATURE_DESCRIPTION_BULK_ACTIONS = 'Bulk blocked users management actions for efficient administration.';
    const FEATURE_DESCRIPTION_IP_WHITELIST = 'IP whitelist management for trusted addresses.';
    const FEATURE_DESCRIPTION_IP_BLACKLIST = 'IP blacklist management for blocked addresses.';

    // Upgrade Messages
    const UPGRADE_TO_PREMIUM = 'Upgrade to Premium to unlock advanced features like customer login delay, one-click unblock, and per-store configuration.';
    const UPGRADE_TO_ENTERPRISE = 'Upgrade to Enterprise to unlock all features including IP management and custom integrations.';
    const PREMIUM_FEATURES_AVAILABLE = 'Premium features are available with a valid license.';
    const ENTERPRISE_FEATURES_AVAILABLE = 'Enterprise features are available with a valid enterprise license.';

    // Validation Messages
    const VALIDATION_MAX_ATTEMPTS_REQUIRED = 'Maximum attempts must be a positive integer.';
    const VALIDATION_LOCKOUT_DURATION_REQUIRED = 'Lockout duration must be a positive integer.';
    const VALIDATION_DELAY_SECONDS_REQUIRED = 'Delay seconds must be a positive integer.';
    const VALIDATION_CAPTCHA_THRESHOLD_REQUIRED = 'CAPTCHA threshold must be a positive integer.';
    const VALIDATION_EMAIL_REQUIRED = 'Valid email address is required.';
    const VALIDATION_SITE_KEY_REQUIRED = 'CAPTCHA site key is required.';
    const VALIDATION_SECRET_KEY_REQUIRED = 'CAPTCHA secret key is required.';
    const VALIDATION_IP_ADDRESS_INVALID = 'Invalid IP address format.';
    const VALIDATION_LICENSE_KEY_REQUIRED = 'License key is required.';

    // Help Messages
    const HELP_MAX_ATTEMPTS = 'Maximum number of failed login attempts before account lockout.';
    const HELP_LOCKOUT_DURATION = 'Duration in seconds for account lockout after exceeding max attempts.';
    const HELP_DELAY_SECONDS = 'Delay in seconds added to login form after failed attempts.';
    const HELP_CAPTCHA_THRESHOLD = 'Number of failed attempts before CAPTCHA is triggered.';
    const HELP_CAPTCHA_TYPE = 'Type of CAPTCHA to use: hCaptcha (free) or reCAPTCHA (premium).';
    const HELP_EMAIL_NOTIFICATIONS = 'Send email notifications to admin when BruteForce attacks are detected.';
    const HELP_IP_WHITELIST = 'List of trusted IP addresses that bypass BruteForce protection.';
    const HELP_IP_BLACKLIST = 'List of blocked IP addresses that are always denied access.';

    // Status Messages
    const STATUS_ACTIVE = 'Active';
    const STATUS_INACTIVE = 'Inactive';
    const STATUS_BLOCKED = 'Blocked';
    const STATUS_LOCKED = 'Locked';
    const STATUS_PENDING = 'Pending';
    const STATUS_EXPIRED = 'Expired';
    const STATUS_VALID = 'Valid';
    const STATUS_INVALID = 'Invalid';

    // License Messages
    const LICENSE_FREE = 'Free Version';
    const LICENSE_PREMIUM = 'Premium Version';
    const LICENSE_ENTERPRISE = 'Enterprise Version';
    const LICENSE_TRIAL = 'Trial Version';
    const LICENSE_EXPIRED_MSG = 'License Expired';
    const LICENSE_INVALID_MSG = 'Invalid License';
    const LICENSE_VERIFYING = 'Verifying License...';
    const LICENSE_ACTIVATING = 'Activating License...';
    const LICENSE_DEACTIVATING = 'Deactivating License...';
    
    // Support Messages
    const QUERY_SENT = 'Thanks for getting in touch! We shall get back to you shortly.';
}
