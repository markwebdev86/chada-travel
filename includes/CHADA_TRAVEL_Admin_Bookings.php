<?php
/**
 * Bookings worklist administrator screen: All/Needs Review/Active/Archived tabs, real server-side search,
 * order-status/payment-method filters, and pagination (docs/wordpress-administrator-booking-records.html).
 * Routes `&view=<order-id>` to CHADA_TRAVEL_Admin_Booking_Detail for the four-tab Booking Detail/Project View.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Bookings {
    public const MENU_SLUG = 'chada-travel-bookings';
    private const CAPABILITY = 'manage_chada_travel_visa_applications';
    // Public: CHADA_TRAVEL_Admin_Booking_Detail's header Archive/Restore action posts to these same actions.
    public const ARCHIVE_ACTION = 'chada_travel_archive_booking';
    public const RESTORE_ACTION = 'chada_travel_restore_booking';

    public static function register(): void {
        add_action('admin_post_' . self::ARCHIVE_ACTION, [self::class, 'handle_archive']);
        add_action('admin_post_' . self::RESTORE_ACTION, [self::class, 'handle_restore']);
        CHADA_TRAVEL_Admin_Booking_Detail::register();
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view Visa Bookings.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list/detail view.
        $chada_travel_view_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('view', 0));
        if ($chada_travel_view_id > 0) {
            CHADA_TRAVEL_Admin_Booking_Detail::render($chada_travel_view_id);
            return;
        }
        self::render_list();
    }

    private static function render_list(): void {
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search/filter form.
        $chada_travel_search = CHADA_TRAVEL_Config::sanitize_request_get('s');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_status = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('order_status'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_method = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('payment_method'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_worklist_raw = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('worklist', 'all'));
        $chada_travel_worklist = self::sanitize_worklist($chada_travel_worklist_raw);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_paged = max(1, CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('paged', 1)));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, Generic.Files.LineLength.TooLong
        $chada_travel_notice = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('chada_travel_booking_notice'));

        $chada_travel_counts = CHADA_TRAVEL_Order_Repository::get_worklist_counts(
            $wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_method
        );
        $chada_travel_total  = CHADA_TRAVEL_Order_Repository::count_bookings(
            $wpdb,
            $chada_travel_worklist,
            $chada_travel_search,
            $chada_travel_status,
            $chada_travel_method
        );
        $chada_travel_orders = CHADA_TRAVEL_Order_Repository::search_bookings(
            $wpdb,
            $chada_travel_worklist,
            $chada_travel_search,
            $chada_travel_status,
            $chada_travel_method,
            $chada_travel_paged,
            CHADA_TRAVEL_Order_Repository::BOOKINGS_PER_PAGE
        );

        CHADA_TRAVEL_Template::output('admin/shared/page-header', [
            'chada_travel_title'       => __('Visa Bookings', 'chada-travel'),
            'chada_travel_badge_html'  => '',
            'chada_travel_tour_anchor' => 'bookings-header',
        ]);
        self::render_notice($chada_travel_notice);
        self::render_worklist_tabs($chada_travel_worklist, $chada_travel_counts, $chada_travel_search, $chada_travel_status, $chada_travel_method);
        self::render_filters($chada_travel_search, $chada_travel_status, $chada_travel_method, $chada_travel_worklist);
        $chada_travel_filters_active = $chada_travel_search !== '' || $chada_travel_status !== '' || $chada_travel_method !== '';
        self::render_table($wpdb, $chada_travel_orders, $chada_travel_worklist, $chada_travel_filters_active);
        self::render_pagination(
            $chada_travel_total,
            $chada_travel_paged,
            $chada_travel_worklist,
            $chada_travel_search,
            $chada_travel_status,
            $chada_travel_method
        );
        echo '</div>';
    }

    private static function sanitize_worklist(string $chada_travel_worklist): string {
        $chada_travel_allowed = [
            CHADA_TRAVEL_Order_Repository::WORKLIST_ALL, CHADA_TRAVEL_Order_Repository::WORKLIST_NEEDS_REVIEW,
            CHADA_TRAVEL_Order_Repository::WORKLIST_ACTIVE, CHADA_TRAVEL_Order_Repository::WORKLIST_ARCHIVED,
        ];
        return in_array($chada_travel_worklist, $chada_travel_allowed, true) ? $chada_travel_worklist : CHADA_TRAVEL_Order_Repository::WORKLIST_ALL;
    }

    private static function render_notice(string $chada_travel_notice): void {
        $chada_travel_messages = [
            'archived' => ['success', __('Visa Booking archived.', 'chada-travel')],
            'restored' => ['success', __('Visa Booking restored.', 'chada-travel')],
        ];
        if (!isset($chada_travel_messages[$chada_travel_notice])) {
            return;
        }
        [$chada_travel_type, $chada_travel_message] = $chada_travel_messages[$chada_travel_notice];
        echo '<div class="notice notice-' . esc_attr($chada_travel_type) . ' is-dismissible"><p>'
            . esc_html($chada_travel_message) . '</p></div>';
    }

    /** @param array{all: int, needs_review: int, active: int, archived: int} $chada_travel_counts */
    private static function render_worklist_tabs(
        string $chada_travel_current,
        array $chada_travel_counts,
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_method
    ): void {
        $chada_travel_tab_defs = [
            CHADA_TRAVEL_Order_Repository::WORKLIST_ALL => [__('All', 'chada-travel'), $chada_travel_counts['all']],
            CHADA_TRAVEL_Order_Repository::WORKLIST_NEEDS_REVIEW =>
                [__('Needs Review', 'chada-travel'), $chada_travel_counts['needs_review']],
            CHADA_TRAVEL_Order_Repository::WORKLIST_ACTIVE        => [__('Active', 'chada-travel'), $chada_travel_counts['active']],
            CHADA_TRAVEL_Order_Repository::WORKLIST_ARCHIVED       => [__('Archived', 'chada-travel'), $chada_travel_counts['archived']],
        ];
        $chada_travel_tabs = [];
        foreach ($chada_travel_tab_defs as $chada_travel_key => [$chada_travel_label, $chada_travel_count]) {
            $chada_travel_tabs[] = [
                'label'    => $chada_travel_label,
                'count'    => $chada_travel_count,
                'url'      => add_query_arg([
                    'page' => self::MENU_SLUG, 'worklist' => $chada_travel_key, 's' => $chada_travel_search,
                    'order_status' => $chada_travel_status, 'payment_method' => $chada_travel_method,
                ], admin_url('admin.php')),
                'selected' => $chada_travel_key === $chada_travel_current,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/bookings/worklist-tabs', [
            'chada_travel_aria_label' => __('Visa Booking worklists', 'chada-travel'),
            'chada_travel_tabs'       => $chada_travel_tabs,
        ]);
    }

    private static function render_filters(
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_method,
        string $chada_travel_worklist
    ): void {
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_method_labels = CHADA_TRAVEL_Config::get_admin_method_labels(CHADA_TRAVEL_Config::get_settings());

        $chada_travel_status_options_html = '';
        $chada_travel_status_values = [
            'chada_travel_draft', 'chada_travel_pending_payment', 'chada_travel_payment_review', 'chada_travel_paid',
            'chada_travel_in_progress', 'chada_travel_completed', 'chada_travel_payment_rejected',
            'chada_travel_cancelled', 'chada_travel_refunded',
        ];
        foreach ($chada_travel_status_values as $chada_travel_value) {
            $chada_travel_status_options_html .= '<option value="' . esc_attr($chada_travel_value) . '"'
                . selected($chada_travel_status, $chada_travel_value, false) . '>'
                . esc_html($chada_travel_status_labels[$chada_travel_value] ?? $chada_travel_value) . '</option>';
        }
        $chada_travel_method_options_html = '';
        foreach ($chada_travel_method_labels as $chada_travel_value => $chada_travel_label) {
            $chada_travel_method_options_html .= '<option value="' . esc_attr($chada_travel_value) . '"'
                . selected($chada_travel_method, $chada_travel_value, false) . '>' . esc_html($chada_travel_label) . '</option>';
        }

        CHADA_TRAVEL_Template::output('admin/bookings/filters', [
            'chada_travel_menu_slug'            => self::MENU_SLUG,
            'chada_travel_worklist'             => $chada_travel_worklist,
            'chada_travel_search'               => $chada_travel_search,
            'chada_travel_status_options_html'  => $chada_travel_status_options_html,
            'chada_travel_method_options_html'  => $chada_travel_method_options_html,
            'chada_travel_reset_url'            => admin_url('admin.php?page=' . self::MENU_SLUG),
        ]);
    }

    /** @param list<array<string, mixed>> $chada_travel_orders */
    private static function render_table(
        object $chada_travel_wpdb,
        array $chada_travel_orders,
        string $chada_travel_worklist,
        bool $chada_travel_filters_active
    ): void {
        $chada_travel_rows_html = '';
        foreach ($chada_travel_orders as $chada_travel_order) {
            $chada_travel_rows_html .= self::build_table_row_html($chada_travel_order);
        }
        CHADA_TRAVEL_Template::output('admin/bookings/table', [
            'chada_travel_rows_html'                  => $chada_travel_rows_html,
            'chada_travel_show_default_empty_message' =>
                $chada_travel_worklist === CHADA_TRAVEL_Order_Repository::WORKLIST_ALL && !$chada_travel_filters_active,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_order */
    private static function build_table_row_html(array $chada_travel_order): string {
        $chada_travel_settings       = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_method_labels = CHADA_TRAVEL_Config::get_admin_method_labels($chada_travel_settings);
        $chada_travel_order_id   = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_view_url   = admin_url('admin.php?page=' . self::MENU_SLUG . '&view=' . $chada_travel_order_id);

        $chada_travel_method_label = !empty($chada_travel_order['latest_payment_method'])
            ? ($chada_travel_method_labels[$chada_travel_order['latest_payment_method']] ?? $chada_travel_order['latest_payment_method'])
            : __('No payment attempt', 'chada-travel');
        $chada_travel_payment_status_label = !empty($chada_travel_order['latest_payment_status'])
            ? ($chada_travel_status_labels[$chada_travel_order['latest_payment_status']] ?? $chada_travel_order['latest_payment_status'])
            : '';
        $chada_travel_order_status_label = $chada_travel_status_labels[$chada_travel_order['chada_travel_order_status']]
            ?? $chada_travel_order['chada_travel_order_status'];
        $chada_travel_file_status_label  = self::resolve_file_status_label(
            (string) ($chada_travel_order['file_status_summary'] ?? ''),
            $chada_travel_status_labels
        );

        return CHADA_TRAVEL_Template::render('admin/bookings/table-row', [
            'chada_travel_view_url'             => $chada_travel_view_url,
            'chada_travel_booking_label_html'   => self::booking_label($chada_travel_order),
            'chada_travel_booker_name'          => trim(
                $chada_travel_order['chada_travel_booker_first_name'] . ' ' . $chada_travel_order['chada_travel_booker_last_name']
            ),
            'chada_travel_booker_email'         => (string) $chada_travel_order['chada_travel_booker_email'],
            'chada_travel_is_archived'          => !empty($chada_travel_order['chada_travel_archived_at']),
            'chada_travel_application_count'    => (int) ($chada_travel_order['application_count'] ?? 0),
            'chada_travel_country_summary'      => (string) ($chada_travel_order['country_summary'] ?? ''),
            'chada_travel_method_label'         => $chada_travel_method_label,
            'chada_travel_payment_status_label' => $chada_travel_payment_status_label,
            'chada_travel_order_status_label'   => $chada_travel_order_status_label,
            'chada_travel_file_status_label'    => $chada_travel_file_status_label,
            'chada_travel_total_formatted'      => CHADA_TRAVEL_Config::format_money(
                (float) $chada_travel_order['chada_travel_total'],
                (string) $chada_travel_order['chada_travel_currency'],
                $chada_travel_settings
            ),
            'chada_travel_updated_note'         => sprintf(
                /* translators: %s: last-updated date/time. */
                __('Updated %s', 'chada-travel'),
                self::format_datetime((string) $chada_travel_order['chada_travel_updated_at'], $chada_travel_settings)
            ),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_order */
    public static function booking_label(array $chada_travel_order): string {
        $chada_travel_booking_id = (string) ($chada_travel_order['chada_travel_booking_id'] ?? '');
        if ($chada_travel_booking_id !== '') {
            return '<strong>' . esc_html($chada_travel_booking_id) . '</strong>';
        }
        return '<span class="chada-travel-draft-label">' . esc_html(sprintf(
            /* translators: %d: internal order ID, shown only until a public Booking ID exists. */
            __('Draft #%d', 'chada-travel'),
            (int) $chada_travel_order['chada_travel_order_id']
        )) . '</span>';
    }

    /**
     * Picks the single highest-priority file-status label to summarize a booking's applications (Action Required
     * > Submitted for Review > Partially Submitted Files > Complete Files > No Submitted Files). Public so
     * CHADA_TRAVEL_Admin_Dashboard's Latest Bookings preview can reuse this exact business rule rather than forking it.
     *
     * @param array<string, string> $chada_travel_status_labels
     */
    public static function resolve_file_status_label(string $chada_travel_summary, array $chada_travel_status_labels): string {
        if ($chada_travel_summary === '') {
            return __('No applications', 'chada-travel');
        }
        $chada_travel_statuses = array_unique(explode(',', $chada_travel_summary));
        foreach (['chada_travel_action_required', 'chada_travel_submitted_for_review', 'chada_travel_partially_submitted_files']
            as $chada_travel_priority
        ) {
            if (in_array($chada_travel_priority, $chada_travel_statuses, true)) {
                return $chada_travel_status_labels[$chada_travel_priority] ?? $chada_travel_priority;
            }
        }
        if (count($chada_travel_statuses) === 1 && $chada_travel_statuses[0] === 'chada_travel_complete_files') {
            return $chada_travel_status_labels['chada_travel_complete_files'] ?? 'Complete Files';
        }
        return $chada_travel_status_labels['chada_travel_no_submitted_files'] ?? 'No Submitted Files';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function format_datetime(string $chada_travel_mysql_datetime, array $chada_travel_settings): string {
        return CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_mysql_datetime, $chada_travel_settings);
    }

    private static function render_pagination(
        int $chada_travel_total,
        int $chada_travel_paged,
        string $chada_travel_worklist,
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_method
    ): void {
        $chada_travel_per_page = CHADA_TRAVEL_Order_Repository::BOOKINGS_PER_PAGE;
        $chada_travel_pages    = (int) ceil($chada_travel_total / $chada_travel_per_page);
        $chada_travel_page_list = [];
        for ($chada_travel_page = 1; $chada_travel_page <= $chada_travel_pages; $chada_travel_page++) {
            $chada_travel_page_list[] = [
                'page'       => $chada_travel_page,
                'url'        => add_query_arg([
                    'page' => self::MENU_SLUG, 'worklist' => $chada_travel_worklist, 's' => $chada_travel_search,
                    'order_status' => $chada_travel_status, 'payment_method' => $chada_travel_method, 'paged' => $chada_travel_page,
                ], admin_url('admin.php')),
                'is_current' => $chada_travel_page === $chada_travel_paged,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/bookings/pagination', [
            'chada_travel_count_text' => sprintf(
                /* translators: %d: total Visa Booking count. */
                _n('%d Visa Booking', '%d Visa Bookings', $chada_travel_total, 'chada-travel'),
                $chada_travel_total
            ),
            'chada_travel_pages' => $chada_travel_page_list,
        ]);
    }

    public static function handle_archive(): void {
        $chada_travel_order_id = self::guard_worklist_action(self::ARCHIVE_ACTION);
        global $wpdb;
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, $chada_travel_order_id);
        if ($chada_travel_order) {
            CHADA_TRAVEL_Order_Repository::archive($wpdb, $chada_travel_order, get_current_user_id());
        }
        self::redirect_after_action($chada_travel_order_id, 'archived');
    }

    public static function handle_restore(): void {
        $chada_travel_order_id = self::guard_worklist_action(self::RESTORE_ACTION);
        global $wpdb;
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, $chada_travel_order_id);
        if ($chada_travel_order) {
            CHADA_TRAVEL_Order_Repository::restore($wpdb, $chada_travel_order, get_current_user_id());
        }
        self::redirect_after_action($chada_travel_order_id, 'restored');
    }

    private static function guard_worklist_action(string $chada_travel_action_prefix): int {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Bookings.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs immediately below.
        $chada_travel_order_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('order_id', 0));
        check_admin_referer($chada_travel_action_prefix . '_' . $chada_travel_order_id);
        return $chada_travel_order_id;
    }

    private static function redirect_after_action(int $chada_travel_order_id, string $chada_travel_notice): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified by the caller.
        $chada_travel_return_to_detail = CHADA_TRAVEL_Config::sanitize_request_key(
            CHADA_TRAVEL_Config::sanitize_request_post('chada_travel_return_to_detail')
        ) !== '';
        $chada_travel_url = $chada_travel_return_to_detail
            ? admin_url('admin.php?page=' . self::MENU_SLUG . '&view=' . $chada_travel_order_id
                . '&chada_travel_booking_notice=' . $chada_travel_notice)
            : admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_booking_notice=' . $chada_travel_notice);
        wp_safe_redirect($chada_travel_url);
        exit;
    }
}
