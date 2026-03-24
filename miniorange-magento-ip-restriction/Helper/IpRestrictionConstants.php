<?php

namespace MiniOrange\IpRestriction\Helper;

/**
 * IP Restriction Constants
 * Contains all constants used throughout the extension
 */
class IpRestrictionConstants
{
    // Module Information
    const MODULE_NAME = 'IpRestriction';
    const MODULE_DIR = 'MiniOrange_IpRestriction::';
    const MODULE_TITLE = 'miniOrange IP Restriction';
    const SECURITY_SUITE_NAME = 'Security Suite';
    const MODULE_BASE = 'ipratelimit';
    const VERSION = 'v1.0.1';

    // Config Path Prefix
    const CONFIG_PATH_PREFIX = 'miniorange/IpRestriction/';

    // IP Denylist Config Paths
    const ADMIN_IP_BLACKLIST = 'ip_admin_blacklist';
    const IP_BLACKLIST_ENABLED = 'ip_blacklist_enabled';
    const IP_RESTRICTION_DISABLED = 'ip_restriction_disabled'; 

    // Country Restriction Config Paths
    const COUNTRY_RESTRICTIONS_ENABLED = 'country_restrictions_enabled';
    const COUNTRY_DENYLIST = 'country_denylist';

    // GeoIP2 Config Paths
    const GEOIP2_LICENSE_KEY = 'geoip2/license_key';
    const GEOIP2_AUTO_UPDATE_ENABLED = 'geoip2/auto_update_enabled';

    // Error Page Template Paths
    const ERROR_TEMPLATE_PATH = 'adminhtml/templates/restrict.phtml';
    const ERROR_CSS_PATH = 'adminhtml/web/css/restrict.css';
    const ERROR_TEMPLATE_NAME = 'MiniOrange_IpRestriction::restrict.phtml';

    // GeoIP2 Download Constants
    const GEOIP2_MAX_DOWNLOAD_SIZE = 50 * 1024 * 1024; // 50MB
    const GEOIP2_MIN_DOWNLOAD_SIZE = 1000000; // 1MB
    const GEOIP2_DOWNLOAD_TIMEOUT = 300; // 5 minutes
    const MAXMIND_DOWNLOAD_URL = 'https://download.maxmind.com/app/geoip_download';
    const MAXMIND_EDITION_ID = 'GeoLite2-Country';

    // Context Constants
    const CONTEXT_ADMIN = 'admin';
    const CONTEXT_FRONTEND = 'frontend';

    // Limit Type Constants
    const LIMIT_TYPE_IP = 'ip';
    const LIMIT_TYPE_COUNTRY = 'country';

    // Limit Values
    const DEFAULT_MAX_IP_LIMIT = 5;
    const DEFAULT_MAX_COUNTRY_LIMIT = 2;

    // Encrypted Limit Config Paths
    const MAX_IP_LIMIT_ENCRYPTED = 'limits/max_ip_limit_encrypted';
    const MAX_COUNTRY_LIMIT_ENCRYPTED = 'limits/max_country_limit_encrypted';
    const ENCRYPTION_TOKEN = 'limits/encryption_token';
    const DEFAULT_ENCRYPTION_TOKEN = 'E7XIXCVVUOYAIA2';

    // File Paths
    const GEOIP_DIRECTORY = 'var/geoip';
    const ADMIN_PATH_PREFIX = '/admin';

    // Default Values
    const DEFAULT_UNKNOWN_IP = 'UNKNOWN';
    const DEFAULT_GEOIP_DATABASE_PATH = 'var/geoip2/GeoLite2-Country.mmdb';

    // Tracking Constants
    const PLUGIN_PORTAL_HOSTNAME = "https://magento.shanekatear.in/plugin-portal";
    const TIME_STAMP = 'time_stamp';
    const DATA_ADDED = 'data_added';

    // Support/Contact API Constants
    const HOSTNAME = "https://login.xecurify.com";
    const AREA_OF_INTEREST = 'Magento IP Restriction Plugin';
}

