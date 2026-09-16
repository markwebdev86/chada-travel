<?php
/**
 * Standalone Payment Review work queue: only manual Bank/Digital Wallet payments currently in
 * chada_travel_awaiting_verification, with search, method filter, and pagination. This is a focused queue, not the
 * complete payment history or a duplicate Booking Detail - the Review action opens the authoritative Payment
 * Review tab on Booking Detail (CHADA_TRAVEL_Admin_Booking_Detail), and Confirm/Reject here call the exact same
 * admin-post.php handlers as that tab so there is only ever one decision workflow.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Payment_Review {
    public const MENU_SLUG = 'chada-travel-payment-review';
    private const CAPABILITY = 'verify_chada_travel_payments';
    // Public: CHADA_TRAVEL_Admin_Booking_Detail's Payment Review tab posts to these same admin-post.php actions.
    public const CONFIRM_ACTION = 'chada_travel_confirm_payment';
    public const REJECT_ACTION = 'chada_travel_reject_payment';
    // Public: CHADA_TRAVEL_Admin_Booking_Detail links to the same protected Deposit Slip download.
    public const VIEW_PROOF_ACTION = 'chada_travel_view_proof';
    private const PER_PAGE = 20;

    public static function register(): void {
        add_action('admin_post_' . self::CONFIRM_ACTION, [self::class, 'handle_confirm']);
        add_action('admin_post_' . self::REJECT_ACTION, [self::class, 'handle_reject']);
        add_action('admin_post_' . self::VIEW_PROOF_ACTION, [self::class, 'handle_view_proof']);
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to review payments.', 'chada-travel'));
        }
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search/filter form.
        $chada_travel_search = CHADA_TRAVEL_Config::sanitize_request_get('s');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_method_raw = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('method'));
        $chada_travel_allowed_methods = [CHADA_TRAVEL_Payment_Service::METHOD_BANK, CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET];
        $chada_travel_method = in_array($chada_travel_method_raw, $chada_travel_allowed_methods, true) ? $chada_travel_method_raw : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_paged = max(1, CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('paged', 1)));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, Generic.Files.LineLength.TooLong
        $chada_travel_notice = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('chada_travel_payment_notice'));

        $chada_travel_total    = CHADA_TRAVEL_Payment_Repository::count_review_queue($wpdb, $chada_travel_search, $chada_travel_method);
        $chada_travel_payments = CHADA_TRAVEL_Payment_Repository::search_review_queue(
            $wpdb,
            $chada_travel_search,
            $chada_travel_method,
            $chada_travel_paged,
            self::PER_PAGE
        );

        CHADA_TRAVEL_Template::output('admin/shared/page-header', [
            'chada_travel_title'       => __('Payment Review', 'chada-travel'),
            'chada_travel_badge_html'  => '<span class="chada-travel-badge">' . (int) $chada_travel_total . '</span>',
            'chada_travel_tour_anchor' => 'payment-review-header',
        ]);
        self::render_notice($chada_travel_notice);
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal, kept unbroken for WordPress.WP.I18n.
        echo '<p class="description">' . esc_html__('A focused work queue for manual Bank and Digital Wallet payments awaiting administrator verification. It does not show the complete payment history or Visa Bookings that need no action.', 'chada-travel') . '</p>';
        self::render_filters($chada_travel_search, $chada_travel_method);

        if (!$chada_travel_payments) {
            echo '<p>' . esc_html__(
                'No Bank or Digital Wallet payments are awaiting administrator verification.',
                'chada-travel'
            ) . '</p></div>';
            return;
        }
        self::render_table($chada_travel_payments, CHADA_TRAVEL_Config::get_settings());
        self::render_pagination($chada_travel_total, $chada_travel_paged, $chada_travel_search, $chada_travel_method);
        echo '</div>';
    }

    private static function render_notice(string $chada_travel_notice): void {
        $chada_travel_messages = [
            'confirmed' => ['success', __('Payment confirmed.', 'chada-travel')],
            'rejected'  => ['success', __('Payment rejected.', 'chada-travel')],
            'error'     => ['error', __('That payment could not be updated from its current status.', 'chada-travel')],
            'invalid'   => ['error', __('That payment could not be found.', 'chada-travel')],
        ];
        if (!isset($chada_travel_messages[$chada_travel_notice])) {
            return;
        }
        [$chada_travel_type, $chada_travel_message] = $chada_travel_messages[$chada_travel_notice];
        echo '<div class="notice notice-' . esc_attr($chada_travel_type) . ' is-dismissible"><p>'
            . esc_html($chada_travel_message) . '</p></div>';
    }

    private static function render_filters(string $chada_travel_search, string $chada_travel_method): void {
        $chada_travel_method_labels = CHADA_TRAVEL_Config::get_admin_method_labels(CHADA_TRAVEL_Config::get_settings());
        $chada_travel_method_options_html = '';
        foreach ([CHADA_TRAVEL_Payment_Service::METHOD_BANK, CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET] as $chada_travel_value) {
            $chada_travel_method_options_html .= '<option value="' . esc_attr($chada_travel_value) . '"'
                . selected($chada_travel_method, $chada_travel_value, false) . '>'
                . esc_html($chada_travel_method_labels[$chada_travel_value] ?? $chada_travel_value) . '</option>';
        }
        CHADA_TRAVEL_Template::output('admin/payment-review/filters', [
            'chada_travel_menu_slug'           => self::MENU_SLUG,
            'chada_travel_search'              => $chada_travel_search,
            'chada_travel_method_options_html' => $chada_travel_method_options_html,
            'chada_travel_reset_url'           => admin_url('admin.php?page=' . self::MENU_SLUG),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_payments
     * @param array<string, mixed>       $chada_travel_settings
     */
    private static function render_table(array $chada_travel_payments, array $chada_travel_settings): void {
        $chada_travel_rows = [];
        foreach ($chada_travel_payments as $chada_travel_payment) {
            $chada_travel_review_url = admin_url(
                'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . (int) $chada_travel_payment['chada_travel_order_id']
                    . '&tab=payment'
            );
            // Uses this specific payment's own immutable snapshot, never current Settings, so a later Settings
            // provider-name change never relabels a payment already awaiting verification in this queue.
            $chada_travel_reference = CHADA_TRAVEL_Payment_Service::resolve_reference_no($chada_travel_payment);
            $chada_travel_rows[] = [
                'review_url'          => $chada_travel_review_url,
                'booking_id'          => (string) ($chada_travel_payment['chada_travel_booking_id'] ?? ''),
                'booker_name'         => trim(
                    $chada_travel_payment['chada_travel_booker_first_name'] . ' ' . $chada_travel_payment['chada_travel_booker_last_name']
                ),
                'method_label'        => CHADA_TRAVEL_Payment_Service::resolve_method_label($chada_travel_payment),
                'amount_formatted'    => CHADA_TRAVEL_Config::format_money(
                    (float) $chada_travel_payment['chada_travel_amount'],
                    (string) $chada_travel_payment['chada_travel_currency'],
                    $chada_travel_settings
                ),
                'reference'           => $chada_travel_reference,
                'submitted_formatted' => CHADA_TRAVEL_Config::format_utc_datetime(
                    (string) ($chada_travel_payment['chada_travel_submitted_at'] ?? ''),
                    $chada_travel_settings
                ),
            ];
        }
        CHADA_TRAVEL_Template::output('admin/payment-review/table', ['chada_travel_rows' => $chada_travel_rows]);
    }

    private static function render_pagination(
        int $chada_travel_total,
        int $chada_travel_paged,
        string $chada_travel_search,
        string $chada_travel_method
    ): void {
        $chada_travel_pages = (int) ceil($chada_travel_total / self::PER_PAGE);
        if ($chada_travel_pages <= 1) {
            return;
        }
        $chada_travel_page_list = [];
        for ($chada_travel_page = 1; $chada_travel_page <= $chada_travel_pages; $chada_travel_page++) {
            $chada_travel_page_list[] = [
                'page'       => $chada_travel_page,
                'url'        => add_query_arg([
                    'page' => self::MENU_SLUG, 's' => $chada_travel_search, 'method' => $chada_travel_method, 'paged' => $chada_travel_page,
                ], admin_url('admin.php')),
                'is_current' => $chada_travel_page === $chada_travel_paged,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/payment-review/pagination', ['chada_travel_pages' => $chada_travel_page_list]);
    }

    public static function handle_confirm(): void {
        $chada_travel_payment_id = self::guard_admin_action(self::CONFIRM_ACTION);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard_admin_action() above already ran check_admin_referer().
        $chada_travel_confirmation_note = CHADA_TRAVEL_Config::sanitize_request_post('confirmation_note');
        global $wpdb;
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_id($wpdb, $chada_travel_payment_id);
        $chada_travel_order   = $chada_travel_payment
            ? CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, (int) $chada_travel_payment['chada_travel_order_id']) : null;
        $chada_travel_notice  = 'invalid';
        if ($chada_travel_payment && $chada_travel_order) {
            $chada_travel_result = CHADA_TRAVEL_Payment_Service::admin_confirm(
                $wpdb,
                $chada_travel_order,
                $chada_travel_payment,
                get_current_user_id(),
                CHADA_TRAVEL_Config::get_settings()
            );
            $chada_travel_notice = $chada_travel_result['errors'] ? 'error' : 'confirmed';
            // Only newly-confirmed (never the already_confirmed idempotent replay) writes the optional note,
            // so repeated clicks/redirects never duplicate it.
            $chada_travel_should_log_note = !$chada_travel_result['errors'] && empty($chada_travel_result['already_confirmed'])
                && $chada_travel_confirmation_note !== '';
            if ($chada_travel_should_log_note) {
                CHADA_TRAVEL_Payment_Repository::log_event(
                    $wpdb,
                    (int) $chada_travel_order['chada_travel_order_id'],
                    $chada_travel_payment_id,
                    'chada_travel_payment_confirmation_note',
                    'admin',
                    ['note' => $chada_travel_confirmation_note],
                    get_current_user_id()
                );
            }
        }
        self::redirect_after_decision($chada_travel_notice);
    }

    public static function handle_reject(): void {
        $chada_travel_payment_id = self::guard_admin_action(self::REJECT_ACTION);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard_admin_action() above already ran check_admin_referer().
        $chada_travel_reason = CHADA_TRAVEL_Config::sanitize_request_post('rejection_reason');
        global $wpdb;
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_id($wpdb, $chada_travel_payment_id);
        $chada_travel_order   = $chada_travel_payment
            ? CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, (int) $chada_travel_payment['chada_travel_order_id']) : null;
        $chada_travel_notice  = 'invalid';
        if ($chada_travel_payment && $chada_travel_order) {
            $chada_travel_result = CHADA_TRAVEL_Payment_Service::admin_reject(
                $wpdb,
                $chada_travel_order,
                $chada_travel_payment,
                get_current_user_id(),
                $chada_travel_reason
            );
            $chada_travel_notice = $chada_travel_result['errors'] ? 'error' : 'rejected';
        }
        self::redirect_after_decision($chada_travel_notice);
    }

    /** Streams a protected Deposit Slip only after a capability check and an action-specific nonce. */
    public static function handle_view_proof(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to review payments.', 'chada-travel'), '', ['response' => 403]);
        }
        $chada_travel_payment_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('payment_id', 0));
        check_admin_referer(self::VIEW_PROOF_ACTION . '_' . $chada_travel_payment_id);

        global $wpdb;
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_id($wpdb, $chada_travel_payment_id);
        $chada_travel_snapshot = $chada_travel_payment
            ? (json_decode((string) ($chada_travel_payment['chada_travel_payment_details_snapshot'] ?? ''), true) ?: []) : [];
        $chada_travel_path = (string) ($chada_travel_snapshot['proof_storage_path'] ?? '');
        if (!$chada_travel_payment || empty($chada_travel_payment['chada_travel_proof_attachment_id']) || $chada_travel_path === '') {
            wp_die(esc_html__('Proof file not found.', 'chada-travel'), '', ['response' => 404]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        CHADA_TRAVEL_Upload_Service::stream_private_file(
            $chada_travel_path,
            (string) ($chada_travel_snapshot['proof_mime_type'] ?? 'application/octet-stream'),
            (string) ($chada_travel_snapshot['proof_original_filename'] ?? 'deposit-slip'),
            CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('download')) !== ''
        );
    }

    /** Verifies capability + the row-specific nonce and returns the validated payment ID. */
    private static function guard_admin_action(string $chada_travel_action_prefix): int {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to review payments.', 'chada-travel'));
        }
        $chada_travel_payment_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('payment_id', 0));
        check_admin_referer($chada_travel_action_prefix . '_' . $chada_travel_payment_id);
        return $chada_travel_payment_id;
    }

    /**
     * Redirects back to wherever the decision form was submitted from (the standalone queue, or Booking
     * Detail's Payment Review tab) with a WordPress admin notice, so Confirm/Reject stays one authoritative
     * handler shared by both surfaces. The redirect target is restricted to this plugin's own admin pages.
     */
    private static function redirect_after_decision(string $chada_travel_notice): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified by guard_admin_action() above.
        $chada_travel_redirect_to = CHADA_TRAVEL_Config::sanitize_url(CHADA_TRAVEL_Config::sanitize_request_post('redirect_to'));
        $chada_travel_target = self::is_allowed_redirect($chada_travel_redirect_to)
            ? $chada_travel_redirect_to
            : admin_url('admin.php?page=' . self::MENU_SLUG);
        wp_safe_redirect(add_query_arg('chada_travel_payment_notice', $chada_travel_notice, $chada_travel_target));
        exit;
    }

    /** Restricts redirect_to to this plugin's own Payment Review or Bookings admin pages. */
    private static function is_allowed_redirect(string $chada_travel_url): bool {
        if ($chada_travel_url === '') {
            return false;
        }
        $chada_travel_parts = wp_parse_url($chada_travel_url);
        if (!is_array($chada_travel_parts) || empty($chada_travel_parts['query'])) {
            return false;
        }
        parse_str($chada_travel_parts['query'], $chada_travel_query);
        $chada_travel_page = (string) ($chada_travel_query['page'] ?? '');
        return in_array($chada_travel_page, [self::MENU_SLUG, CHADA_TRAVEL_Admin_Bookings::MENU_SLUG], true);
    }
}
