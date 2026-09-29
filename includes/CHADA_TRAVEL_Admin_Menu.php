<?php
/**
 * Centralizes WordPress administrator menu registration for every Chada Travel admin screen, so the
 * top-level menu is registered exactly once and submenu order/ownership lives in one place.
 *
 * Individual admin-screen classes (CHADA_TRAVEL_Admin_Dashboard, CHADA_TRAVEL_Admin_Bookings, CHADA_TRAVEL_Admin_Payment_Review,
 * CHADA_TRAVEL_Admin_Visa_Countries) keep owning their own admin-post/admin-ajax
 * action hooks and render methods; only add_menu_page()/add_submenu_page() calls live here.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Menu {
    private const CAPABILITY = 'manage_chada_travel_visa_applications';
    private const PAYMENT_CAPABILITY = 'verify_chada_travel_payments';
    private const SETTINGS_CAPABILITY = 'manage_chada_travel_visa_settings';

    /** Query-string `page` values for every Chada Travel admin screen. */
    private const PLUGIN_PAGES = [
        'chada-travel-dashboard',
        'chada-travel-tours',
        'chada-travel-tour-destinations',
        // Pro owns this page; include its slug so the shared admin assets load when Pro is active.
        'chada-travel-tour-types',
        'chada-travel-bookings',
        'chada-travel-payment-review',
        'chada-travel-visa-countries',
        'chada-travel-settings',
        'chada-travel-features',
        'chada-travel-instructions',
    ];

    public static function register(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_shared_assets']);
    }

    /** Enqueues the shared admin CSS/JS only on this plugin's own admin pages, never globally. */
    public static function enqueue_shared_assets(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page identification, no state change.
        $chada_travel_page = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('page'));
        if (!in_array($chada_travel_page, self::PLUGIN_PAGES, true)) {
            return;
        }
        wp_enqueue_style('chada-travel-admin', CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-admin.css', [], CHADA_TRAVEL_VERSION);
        wp_enqueue_script('chada-travel-admin', CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-admin.js', [], CHADA_TRAVEL_VERSION, true);
        if ($chada_travel_page === CHADA_TRAVEL_Feature_Comparison::MENU_SLUG) {
            wp_enqueue_style(
                'chada-travel-features',
                CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-features.css',
                ['chada-travel-admin'],
                CHADA_TRAVEL_VERSION
            );
        }
    }

    /**
     * Registers the single top-level "Chada Travel" menu (clicking it opens the Dashboard) and its submenus in
     * the required order: Dashboard, Tours, Tour Destinations, Visa Bookings, Payment Review, Visa
     * Countries, Settings, Features, Instructions. Existing bookmarked chada-travel-bookings/
    * chada-travel-payment-review/chada-travel-visa-countries URLs
     * keep working because their slugs are unchanged - only the parent/landing slug moved from Bookings to
     * Dashboard.
    */
    public static function register_menu(): void {
        $chada_travel_menu_label = __('Chada Travel', 'chada-travel');
        add_menu_page(
            $chada_travel_menu_label,
            $chada_travel_menu_label,
            self::CAPABILITY,
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Dashboard::class, 'render_page'],
            'dashicons-airplane',
            56
        );

        // Relabels the auto-duplicated first submenu item (slug === parent slug) from "Chada Travel" to
        // "Dashboard" and is also what makes clicking the top-level menu item itself open the Dashboard.
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Dashboard',
            'Dashboard',
            self::CAPABILITY,
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Dashboard::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Tours',
            self::tours_menu_title(),
            'manage_chada_travel_tours',
            CHADA_TRAVEL_Admin_Tours::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Tours::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Tour Destinations',
            'Tour Destinations',
            'manage_chada_travel_tours',
            CHADA_TRAVEL_Admin_Tour_Terms::DESTINATIONS_MENU_SLUG,
            [CHADA_TRAVEL_Admin_Tour_Terms::class, 'render_destinations_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Visa Bookings',
            'Visa Bookings',
            self::CAPABILITY,
            CHADA_TRAVEL_Admin_Bookings::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Bookings::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Payment Review',
            self::payment_review_menu_title(),
            self::PAYMENT_CAPABILITY,
            CHADA_TRAVEL_Admin_Payment_Review::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Payment_Review::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Visa Countries',
            self::visa_countries_menu_title(),
            self::SETTINGS_CAPABILITY,
            CHADA_TRAVEL_Admin_Visa_Countries::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Visa_Countries::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Settings',
            'Settings',
            self::SETTINGS_CAPABILITY,
            CHADA_TRAVEL_Admin_Settings::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Settings::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Features',
            'Features',
            self::SETTINGS_CAPABILITY,
            CHADA_TRAVEL_Feature_Comparison::MENU_SLUG,
            [CHADA_TRAVEL_Feature_Comparison::class, 'render_page']
        );
        add_submenu_page(
            CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG,
            'Instructions',
            'Instructions',
            self::CAPABILITY,
            CHADA_TRAVEL_Admin_Instructions::MENU_SLUG,
            [CHADA_TRAVEL_Admin_Instructions::class, 'render_page']
        );
    }

    /** Returns the Payment Review label with a native WordPress pending-count badge when needed. */
    private static function payment_review_menu_title(): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return 'Payment Review';
        }
        $chada_travel_count = 0;
        $chada_travel_payment_table = $wpdb->prefix . 'chada_travel_payments';
        $chada_travel_order_table = $wpdb->prefix . 'chada_travel_orders';
        $chada_travel_tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $chada_travel_payment_table)) ?: [];
        $chada_travel_order_tables = $wpdb->get_col(
            $wpdb->prepare('SHOW TABLES LIKE %s', $chada_travel_order_table)
        ) ?: [];
        $chada_travel_tables_ready = in_array($chada_travel_payment_table, $chada_travel_tables, true)
            && in_array($chada_travel_order_table, $chada_travel_order_tables, true);
        if ($chada_travel_tables_ready) {
            $chada_travel_count = CHADA_TRAVEL_Payment_Repository::count_review_queue($wpdb, '', '');
        }
        if ($chada_travel_count <= 0) {
            return 'Payment Review';
        }
        return 'Payment Review <span class="awaiting-mod count-' . (int) $chada_travel_count . '"><span class="pending-count">'
            . (int) $chada_travel_count . '</span></span>';
    }

    /** Returns the Tours label with a native WordPress badge for active Tours when the table is available. */
    private static function tours_menu_title(): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return 'Tours';
        }
        $chada_travel_table = $wpdb->prefix . 'chada_travel_tours';
        if (!self::table_exists($wpdb, $chada_travel_table)) {
            return 'Tours';
        }
        $chada_travel_count = CHADA_TRAVEL_Tour_Repository::count_active($wpdb);
        return self::menu_title_with_count('Tours', $chada_travel_count, 'active Tour', 'active Tours');
    }

    /** Returns the Visa Countries label with a badge for active settings-backed countries. */
    private static function visa_countries_menu_title(): string {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_countries = (array) ($chada_travel_settings['chada_travel_country_fees'] ?? []);
        $chada_travel_count = count(array_filter($chada_travel_countries, static fn($chada_travel_country): bool =>
            is_array($chada_travel_country) && !empty($chada_travel_country['is_active'])
        ));
        return self::menu_title_with_count(
            'Visa Countries',
            $chada_travel_count,
            'active Visa Country',
            'active Visa Countries'
        );
    }

    /** Returns a menu label with WordPress's standard pending-count badge when the count is positive. */
    private static function menu_title_with_count(
        string $chada_travel_label,
        int $chada_travel_count,
        string $chada_travel_singular,
        string $chada_travel_plural
    ): string {
        if ($chada_travel_count <= 0) {
            return $chada_travel_label;
        }
        $chada_travel_count_label = $chada_travel_count === 1
            ? '1 ' . $chada_travel_singular
            : $chada_travel_count . ' ' . $chada_travel_plural;
        $chada_travel_badge = '<span class="awaiting-mod count-' . $chada_travel_count . '">'
            . '<span class="pending-count" aria-hidden="true">' . $chada_travel_count . '</span>'
            . '<span class="screen-reader-text">' . esc_html($chada_travel_count_label) . '</span></span>';
        return $chada_travel_label . ' ' . $chada_travel_badge;
    }

    /** Returns whether a concrete WordPress table exists during activation or an incomplete upgrade. */
    private static function table_exists(object $chada_travel_wpdb, string $chada_travel_table): bool {
        $chada_travel_tables = $chada_travel_wpdb->get_col($chada_travel_wpdb->prepare('SHOW TABLES LIKE %s', $chada_travel_table)) ?: [];
        return in_array($chada_travel_table, $chada_travel_tables, true);
    }
}
