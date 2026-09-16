<?php
/** Stable extension actions shared by the Free plugin and optional add-ons. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Extension_Manager {
    /** Reports whether an optional add-on has announced itself to the Free runtime. */
    public static function is_pro_active(): bool {
        return function_exists('apply_filters') ? (bool) apply_filters('chada_travel_pro_active', false) : false;
    }

    /**
     * Runs the public registration actions after Free has loaded its stable contracts. Each action is intentionally
     * narrow so a future add-on can register only the extension surface it owns.
     */
    public static function register_extensions(): void {
        if (function_exists('do_action')) {
            do_action('chada_travel_register_extensions', self::class);
            do_action('chada_travel_register_payment_providers', self::class);
            do_action('chada_travel_register_admin_extensions', self::class);
            do_action('chada_travel_register_settings_extensions', self::class);
            do_action('chada_travel_register_rest_extensions', self::class);
            do_action('chada_travel_register_dashboard_extensions', self::class);
            do_action('chada_travel_register_automation_extensions', self::class);
            do_action('chada_travel_register_public_extensions', self::class);
        }
    }
}
