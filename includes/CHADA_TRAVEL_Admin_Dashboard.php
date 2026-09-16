<?php
/**
 * Capability-protected administrator Dashboard: the plugin's default landing page. Free shows a read-only period
 * selector, operational summary cards, the latest booking preview, and Setup & System Status. Pro adds the advanced
 * cards, analytics, Attention Required, and bounded latest-record previews over existing records. This screen is
 * read-only -
 * every card, attention item, preview row, and status item links back to its existing authoritative screen
 * (Bookings, Payment Review, Booking Detail, Settings) and never duplicates a payment
 * decision, document review, Settings save, or archive/restore action.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Dashboard {
    public const MENU_SLUG = 'chada-travel-dashboard';
    private const CAPABILITY = 'manage_chada_travel_visa_applications';
    private const SETTINGS_CAPABILITY = 'manage_chada_travel_visa_settings';

    /**
     * No admin-post/admin-ajax handlers: the Dashboard has no mutations of its own. Kept for structural
     * consistency with the other CHADA_TRAVEL_Admin_* screens and as the one call site CHADA_TRAVEL_Plugin::run() needs if a
     * later phase adds a Dashboard-owned hook.
     */
    public static function register(): void {
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view the dashboard.', 'chada-travel'));
        }
        global $wpdb;
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET period selector, no state change.
        $chada_travel_period_raw = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('period'));
        $chada_travel_period     = CHADA_TRAVEL_Dashboard_Period::sanitize($chada_travel_period_raw);
        $chada_travel_period_start_utc = CHADA_TRAVEL_Dashboard_Period::resolve_utc_start(
            $chada_travel_period,
            CHADA_TRAVEL_Config::get_company_timezone($chada_travel_settings)
        );

        CHADA_TRAVEL_Template::output('admin/shared/page-header', [
            'chada_travel_title'       => __('Dashboard', 'chada-travel'),
            'chada_travel_badge_html'  => '',
            'chada_travel_tour_anchor' => 'dashboard-header',
        ]);

        self::render_checkout_readiness_notice($chada_travel_settings);
        self::render_period_selector($chada_travel_period);
        self::render_cards_section($wpdb, $chada_travel_settings, $chada_travel_period, $chada_travel_period_start_utc);
        self::render_latest_bookings_section($wpdb, $chada_travel_settings);
        if (function_exists('do_action')) {
            do_action('chada_travel_render_dashboard_extensions', [
                'wpdb'             => $wpdb,
                'settings'         => $chada_travel_settings,
                'period'           => $chada_travel_period,
                'period_start_utc' => $chada_travel_period_start_utc,
            ]);
        }
        self::render_setup_status_section($chada_travel_settings);
        echo '</div>';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_checkout_readiness_notice(array $chada_travel_settings): void {
        try {
            $chada_travel_readiness = CHADA_TRAVEL_Dashboard_Repository::get_checkout_readiness($chada_travel_settings);
        } catch (\Throwable $chada_travel_error) {
            self::render_notice(__(
                'Visa Application Checkout readiness is temporarily unavailable. Review Settings before accepting new applications.',
                'chada-travel'
            ));
            return;
        }
        if ($chada_travel_readiness['ready']) {
            return;
        }

        $chada_travel_can_manage_settings = current_user_can(self::SETTINGS_CAPABILITY);
        $chada_travel_issues = array_map(
            static function (array $chada_travel_issue) use ($chada_travel_can_manage_settings): array {
                $chada_travel_issue['configure_url'] = $chada_travel_can_manage_settings
                    ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Settings::MENU_SLUG . '&tab=' . $chada_travel_issue['tab']) : '';
                return $chada_travel_issue;
            },
            $chada_travel_readiness['issues']
        );
        CHADA_TRAVEL_Template::output('admin/dashboard/checkout-readiness-notice', [
            'chada_travel_issues' => $chada_travel_issues,
        ]);
    }

    /** @return array<string, string> Machine period value => translated label, in the approved display order. */
    private static function period_labels(): array {
        return [
            CHADA_TRAVEL_Dashboard_Period::PERIOD_7D   => __('Last 7 days', 'chada-travel'),
            CHADA_TRAVEL_Dashboard_Period::PERIOD_30D  => __('Last 30 days', 'chada-travel'),
            CHADA_TRAVEL_Dashboard_Period::PERIOD_YEAR => __('This year', 'chada-travel'),
            CHADA_TRAVEL_Dashboard_Period::PERIOD_ALL  => __('All time', 'chada-travel'),
        ];
    }

    /**
     * A normal read-only GET form - works with JavaScript disabled via the Apply button; no nonce, since this
     * only ever selects a display period and never changes data.
     */
    private static function render_period_selector(string $chada_travel_period): void {
        $chada_travel_options_html = '';
        foreach (self::period_labels() as $chada_travel_value => $chada_travel_label) {
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_value) . '"'
                . selected($chada_travel_period, $chada_travel_value, false) . '>' . esc_html($chada_travel_label) . '</option>';
        }
        CHADA_TRAVEL_Template::output('admin/dashboard/period-selector', [
            'chada_travel_menu_slug'    => self::MENU_SLUG,
            'chada_travel_period_label' => __('Period', 'chada-travel'),
            'chada_travel_options_html' => $chada_travel_options_html,
            'chada_travel_apply_label'  => __('Apply', 'chada-travel'),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_cards_section(
        object $chada_travel_wpdb,
        array $chada_travel_settings,
        string $chada_travel_period,
        ?string $chada_travel_period_start_utc
    ): void {
        try {
            $chada_travel_cards = self::build_cards($chada_travel_wpdb, $chada_travel_settings, $chada_travel_period, $chada_travel_period_start_utc);
            if (function_exists('apply_filters')) {
                $chada_travel_cards = (array) apply_filters('chada_travel_dashboard_cards', $chada_travel_cards, [
                    'wpdb' => $chada_travel_wpdb,
                    'settings' => $chada_travel_settings,
                    'period' => $chada_travel_period,
                    'period_start_utc' => $chada_travel_period_start_utc,
                ]);
            }
        } catch (\Throwable $chada_travel_error) {
            self::render_notice(__(
                'Summary totals are temporarily unavailable. Existing screens remain fully usable below.',
                'chada-travel'
            ));
            return;
        }
        $chada_travel_card_html_list = array_map(
            static fn(array $chada_travel_card): string => CHADA_TRAVEL_Template::render('admin/dashboard/card', [
                'chada_travel_label'     => $chada_travel_card['label'],
                'chada_travel_values'    => $chada_travel_card['values'],
                'chada_travel_secondary' => $chada_travel_card['secondary'],
                'chada_travel_url'       => $chada_travel_card['url'],
                'chada_travel_link_text' => $chada_travel_card['link_text'],
            ]),
            $chada_travel_cards
        );
        CHADA_TRAVEL_Template::output('admin/dashboard/cards-section', [
            'chada_travel_summary_label'  => __('Summary', 'chada-travel'),
            'chada_travel_card_html_list' => $chada_travel_card_html_list,
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return list<array{label: string, values: list<string>, secondary: list<string>, url: string, link_text: string}>
     */
    private static function build_cards(
        object $chada_travel_wpdb,
        array $chada_travel_settings,
        string $chada_travel_period,
        ?string $chada_travel_period_start_utc
    ): array {
        $chada_travel_bookings_url = admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG);
        $chada_travel_grid_url     = $chada_travel_bookings_url;
        $chada_travel_period_label = self::period_labels()[$chada_travel_period] ?? $chada_travel_period;
        // The `all` period's "created in period" figure would just repeat the all-time headline, so it is
        // omitted entirely rather than shown as a redundant duplicate line.
        $chada_travel_show_period_secondary = $chada_travel_period !== CHADA_TRAVEL_Dashboard_Period::PERIOD_ALL;

        $chada_travel_total_bookings    = CHADA_TRAVEL_Dashboard_Repository::get_total_bookings($chada_travel_wpdb);
        $chada_travel_visa_applications = CHADA_TRAVEL_Dashboard_Repository::get_visa_applications($chada_travel_wpdb);
        $chada_travel_active           = CHADA_TRAVEL_Dashboard_Repository::get_active_bookings($chada_travel_wpdb);
        $chada_travel_needs_review     = CHADA_TRAVEL_Dashboard_Repository::get_needs_review($chada_travel_wpdb);

        $chada_travel_total_bookings_secondary = [sprintf(
            /* translators: %d: number of archived bookings already included in the total. */
            __('Includes %d archived', 'chada-travel'),
            $chada_travel_total_bookings['archived']
        )];
        $chada_travel_applications_secondary = [sprintf(
            /* translators: %d: number of currently active (non-archived) visa applications. */
            __('%d currently active', 'chada-travel'),
            $chada_travel_visa_applications['active']
        )];

        if ($chada_travel_show_period_secondary) {
            $chada_travel_bookings_created = CHADA_TRAVEL_Dashboard_Repository::get_bookings_created_in_period(
                $chada_travel_wpdb,
                $chada_travel_period_start_utc
            );
            $chada_travel_total_bookings_secondary[] = sprintf(
                /* translators: 1: number of bookings created in the selected period, 2: period label. */
                __('%1$d Visa Bookings created in %2$s', 'chada-travel'),
                $chada_travel_bookings_created,
                $chada_travel_period_label
            );

            $chada_travel_applications_created = CHADA_TRAVEL_Dashboard_Repository::get_applications_created_in_period(
                $chada_travel_wpdb,
                $chada_travel_period_start_utc
            );
            $chada_travel_applications_secondary[] = sprintf(
                /* translators: 1: number of applications created in the selected period, 2: period label. */
                __('%1$d created in %2$s', 'chada-travel'),
                $chada_travel_applications_created,
                $chada_travel_period_label
            );

        }

        $chada_travel_current_note = __('Current snapshot - not affected by the period filter', 'chada-travel');

        $chada_travel_cards = [
            [
                'label'     => __('Total Visa Bookings', 'chada-travel'),
                'values'    => [(string) $chada_travel_total_bookings['total']],
                'secondary' => $chada_travel_total_bookings_secondary,
                'url'       => $chada_travel_bookings_url . '&worklist=' . CHADA_TRAVEL_Order_Repository::WORKLIST_ALL,
                'link_text' => __('View all Visa Bookings', 'chada-travel'),
            ],
            [
                'label'     => __('Visa Applications', 'chada-travel'),
                'values'    => [(string) $chada_travel_visa_applications['total']],
                'secondary' => $chada_travel_applications_secondary,
                'url'       => $chada_travel_bookings_url,
                'link_text' => __('View all applications', 'chada-travel'),
            ],
            [
                'label'     => __('Active Visa Bookings', 'chada-travel'),
                'values'    => [(string) $chada_travel_active],
                'secondary' => [__('Not yet completed, cancelled, or refunded', 'chada-travel'), $chada_travel_current_note],
                'url'       => $chada_travel_bookings_url . '&worklist=' . CHADA_TRAVEL_Order_Repository::WORKLIST_ACTIVE,
                'link_text' => __('View active Visa Bookings', 'chada-travel'),
            ],
            [
                'label'     => __('Needs Review', 'chada-travel'),
                'values'    => [(string) $chada_travel_needs_review],
                'secondary' => [
                    __('Awaiting payment verification or document review', 'chada-travel'), $chada_travel_current_note,
                ],
                'url'       => $chada_travel_bookings_url . '&worklist=' . CHADA_TRAVEL_Order_Repository::WORKLIST_NEEDS_REVIEW,
                'link_text' => __('View Visa Bookings needing review', 'chada-travel'),
            ],
        ];
        return $chada_travel_cards;
    }

    /**
     * Latest Bookings - the newest bounded preview, not a replacement for Bookings' own search/filter/pagination.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function render_latest_bookings_section(object $chada_travel_wpdb, array $chada_travel_settings): void {
        try {
            $chada_travel_bookings = CHADA_TRAVEL_Dashboard_Repository::get_latest_bookings($chada_travel_wpdb);
        } catch (\Throwable $chada_travel_error) {
            self::render_notice(__(
                'Latest Visa Bookings is temporarily unavailable. Existing screens remain fully usable.',
                'chada-travel'
            ));
            return;
        }
        CHADA_TRAVEL_Template::output('admin/dashboard/latest-bookings-section', [
            'chada_travel_checkout_url' => self::safe_checkout_url($chada_travel_settings),
            'chada_travel_table_html'   => $chada_travel_bookings
                ? self::build_latest_bookings_table_html($chada_travel_bookings, $chada_travel_settings)
                : '',
            'chada_travel_view_all_url' => admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG),
        ]);
    }

    /**
     * Resolves the public checkout page URL only when it is Ready (published, correct shortcode, no token).
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function safe_checkout_url(array $chada_travel_settings): string {
        $chada_travel_status = CHADA_TRAVEL_Page_Settings::resolve_status(CHADA_TRAVEL_Page_Registry::CHECKOUT, $chada_travel_settings);
        return $chada_travel_status['status'] === CHADA_TRAVEL_Page_Settings::STATUS_READY ? (string) $chada_travel_status['url'] : '';
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_bookings
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function build_latest_bookings_table_html(array $chada_travel_bookings, array $chada_travel_settings): string {
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_method_labels = CHADA_TRAVEL_Config::get_admin_method_labels($chada_travel_settings);
        $chada_travel_rows_html = '';
        foreach ($chada_travel_bookings as $chada_travel_booking) {
            $chada_travel_rows_html .= self::build_latest_booking_row_html(
                $chada_travel_booking,
                $chada_travel_settings,
                $chada_travel_status_labels,
                $chada_travel_method_labels
            );
        }
        return CHADA_TRAVEL_Template::render('admin/dashboard/latest-bookings-table', ['chada_travel_rows_html' => $chada_travel_rows_html]);
    }

    /**
     * @param array<string, mixed> $chada_travel_booking
     * @param array<string, mixed> $chada_travel_settings
     * @param array<string, string> $chada_travel_status_labels
     * @param array<string, string> $chada_travel_method_labels
     */
    private static function build_latest_booking_row_html(
        array $chada_travel_booking,
        array $chada_travel_settings,
        array $chada_travel_status_labels,
        array $chada_travel_method_labels
    ): string {
        $chada_travel_order_id = (int) $chada_travel_booking['chada_travel_order_id'];
        $chada_travel_view_url = admin_url(
            'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . $chada_travel_order_id
        );
        $chada_travel_method_label = !empty($chada_travel_booking['latest_payment_method'])
            ? ($chada_travel_method_labels[$chada_travel_booking['latest_payment_method']] ?? $chada_travel_booking['latest_payment_method'])
            : __('No payment attempt', 'chada-travel');
        $chada_travel_payment_status_label = !empty($chada_travel_booking['latest_payment_status'])
            ? ($chada_travel_status_labels[$chada_travel_booking['latest_payment_status']] ?? $chada_travel_booking['latest_payment_status'])
            : '';
        $chada_travel_order_status_label = $chada_travel_status_labels[$chada_travel_booking['chada_travel_order_status']]
            ?? $chada_travel_booking['chada_travel_order_status'];
        $chada_travel_file_status_label = CHADA_TRAVEL_Admin_Bookings::resolve_file_status_label(
            (string) ($chada_travel_booking['file_status_summary'] ?? ''),
            $chada_travel_status_labels
        );

        return CHADA_TRAVEL_Template::render('admin/dashboard/latest-booking-row', [
            'chada_travel_view_url'             => $chada_travel_view_url,
            'chada_travel_booking_label_html'   => CHADA_TRAVEL_Admin_Bookings::booking_label($chada_travel_booking),
            'chada_travel_booker_name'          => trim(
                $chada_travel_booking['chada_travel_booker_first_name'] . ' ' . $chada_travel_booking['chada_travel_booker_last_name']
            ),
            'chada_travel_booker_email'         => (string) $chada_travel_booking['chada_travel_booker_email'],
            'chada_travel_is_archived'          => !empty($chada_travel_booking['chada_travel_archived_at']),
            'chada_travel_application_count'    => (int) ($chada_travel_booking['application_count'] ?? 0),
            'chada_travel_country_summary'      => (string) ($chada_travel_booking['country_summary'] ?? ''),
            'chada_travel_method_label'         => $chada_travel_method_label,
            'chada_travel_payment_status_label' => $chada_travel_payment_status_label,
            'chada_travel_order_status_label'   => $chada_travel_order_status_label,
            'chada_travel_file_status_label'    => $chada_travel_file_status_label,
            'chada_travel_total_formatted'      => CHADA_TRAVEL_Config::format_money(
                (float) $chada_travel_booking['chada_travel_total'],
                (string) $chada_travel_booking['chada_travel_currency'],
                $chada_travel_settings
            ),
            'chada_travel_created_formatted'    => CHADA_TRAVEL_Config::format_utc_datetime(
                (string) $chada_travel_booking['chada_travel_created_at'],
                $chada_travel_settings
            ),
        ]);
    }

    /**
     * Latest active Tours - a bounded preview linking back to the authoritative Tours manager.
     *
     * @param array<string, mixed> $chada_travel_settings
     */

    /**
     * @param list<array<string, mixed>> $chada_travel_tours
     * @param array<string, mixed> $chada_travel_settings
     */

    /**
     * Awaiting Payment Review - rendered only for a user who holds the same capability the standalone Payment
     * Review queue itself requires; hidden entirely (not merely un-linked) otherwise.
     *
     * @param array<string, mixed> $chada_travel_settings
     */

    /**
     * @param list<array<string, mixed>> $chada_travel_payments
     * @param array<string, mixed> $chada_travel_settings
     */

    /**
     * Latest Document Uploads - current (non-superseded) versions only. The original filename is shown only to a
     * user who holds the protected-document view capability; every other field (application reference, applicant
     * name, country snapshot, requirement, status, upload time) uses the Dashboard's own base capability, already
     * enforced by render_page(). The View Booking link is always shown; Booking Detail enforces its own document
     * capability independently at the destination.
     *
     * @param array<string, mixed> $chada_travel_settings
     */

    /**
     * @param list<array<string, mixed>> $chada_travel_documents
     * @param array<string, mixed> $chada_travel_settings
     */

    /** @param array<string, mixed> $chada_travel_settings */
    private static function render_setup_status_section(array $chada_travel_settings): void {
        try {
            $chada_travel_status = CHADA_TRAVEL_Dashboard_Repository::get_setup_status($chada_travel_settings);
        } catch (\Throwable $chada_travel_error) {
            self::render_notice(__(
                'Setup & System Status is temporarily unavailable. Existing screens remain fully usable above.',
                'chada-travel'
            ));
            return;
        }

        $chada_travel_can_manage_settings = current_user_can(self::SETTINGS_CAPABILITY);

        $chada_travel_unready_items = [];
        foreach ($chada_travel_status['items'] as $chada_travel_item) {
            if ($chada_travel_item['ready']) {
                continue;
            }
            $chada_travel_unready_items[] = [
                'label'         => $chada_travel_item['label'],
                'detail'        => $chada_travel_item['detail'],
                'configure_url' => $chada_travel_can_manage_settings ? self::settings_tab_url($chada_travel_item['tab']) : '',
            ];
        }

        CHADA_TRAVEL_Template::output('admin/dashboard/setup-status-section', [
            'chada_travel_all_ready'     => $chada_travel_status['all_ready'],
            'chada_travel_unready_items' => $chada_travel_unready_items,
        ]);
    }

    private static function settings_tab_url(string $chada_travel_tab): string {
        return admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Settings::MENU_SLUG . '&tab=' . $chada_travel_tab);
    }

    private static function render_notice(string $chada_travel_message): void {
        echo '<div class="notice notice-warning"><p>' . esc_html($chada_travel_message) . '</p></div>';
    }
}
