<?php

namespace MiniOrange\BruteForceProtection\Plugin;

use Magento\Config\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Plugin to prevent Magento's default brute force and CAPTCHA settings from being enabled via Config Model
 * This is used when saving from the admin dashboard
 */
class PreventConfigModelSave
{
    /**
     * Protected field IDs that must always be 0
     * Key: section ID, Value: array of field IDs
     */
    protected $protectedFields = [
        'security' => [
            'lockout_threshold',
            'max_number_password_reset_requests',
            'min_time_between_password_reset_requests',
        ],
        'password' => [
            'lockout_failures',
            'lockout_threshold',
            'max_number_password_reset_requests',
            'min_time_between_password_reset_requests',
        ],
        'captcha' => [
            'enable',
        ],
    ];

    protected $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Intercept config save via Config Model (used by admin dashboard) and force protected paths to 0
     *
     * @param Config $subject
     * @return void
     */
    public function beforeSave(Config $subject)
    {
        $groups = $subject->getGroups();
        if (!$groups) {
            return;
        }

        $modified = false;
        foreach ($groups as $sectionId => $section) {
            if (!is_array($section) || !isset($section['fields'])) {
                continue;
            }
            
            // Check if this section has protected fields
            if (!isset($this->protectedFields[$sectionId])) {
                continue;
            }
            
            $protectedFieldIds = $this->protectedFields[$sectionId];
            
            foreach ($section['fields'] as $fieldId => $field) {
                // Check if this field is protected
                if (!in_array($fieldId, $protectedFieldIds)) {
                    continue;
                }
                
                // If someone tries to set a non-zero value, force it to 0
                if (isset($field['value']) && $field['value'] != 0 && $field['value'] != '0') {
                    $path = $sectionId . '/' . $fieldId;
                    
                    $this->logger->info(
                        "PreventConfigModelSave: Attempted to set '{$path}' to '{$field['value']}' via Config Model, " .
                        "but forcing it to 0 to keep Magento's default brute force protection disabled."
                    );
                    $groups[$sectionId]['fields'][$fieldId]['value'] = 0;
                    $modified = true;
                }
            }
        }

        if ($modified) {
            $subject->setGroups($groups);
        }
    }
}

