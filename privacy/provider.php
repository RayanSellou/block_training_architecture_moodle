<?php

/**
 * GDPR compliant plugin - No personal data collected
 * @copyright  2025 
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_training_architecture\privacy;

use core_privacy\local\metadata\null_provider;

defined('MOODLE_INTERNAL') || die();

class provider implements null_provider {
    /**
     * Déclaration officielle que le plugin ne stocke aucune donnée personnelle
     */
    public static function get_reason() : string {
        return 'privacy:metadata';
    }
}