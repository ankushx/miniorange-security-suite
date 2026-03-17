<?php
namespace MiniOrange\AdminLogs\Plugin;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Plugin to override RemoteAddress::getRemoteAddress() to use public IP detection logic
 * This ensures admin_user_session table stores public IP instead of local DDEV IP
 */
class RemoteAddressPlugin
{
    /**
     * Intercept getRemoteAddress to check proxy headers for real client IP
     *
     * @param RemoteAddress $subject
     * @param callable $proceed
     * @param bool $ipToLong
     * @return string|int|null
     */
    public function aroundGetRemoteAddress(
        RemoteAddress $subject,
        callable $proceed,
        $ipToLong = false
    ) {
        // Check for real client IP through various proxy headers
        // This is the same logic used in Helper/Data::getIpAddress()
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'HTTP_X_REAL_IP'             // Real IP header
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For may contain multiple IPs: take the first one
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP and ensure it's not a private/reserved range
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    // If ipToLong is true, return IP as long integer
                    if ($ipToLong) {
                        return ip2long($ip);
                    }
                    return $ip;
                }
            }
        }

        // Fallback to Magento's original method
        $ip = $proceed($ipToLong);
        
        // If parent returns a Docker/internal IP, try to get from X-Forwarded-For
        if (!$ipToLong && $ip) {
            $ipString = is_string($ip) ? $ip : long2ip($ip);
            if (filter_var($ipString, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    $realIp = trim($ips[0]);
                    if (filter_var($realIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $realIp;
                    }
                }
            }
        }
        
        return $ip;
    }
}

