<?php
/**
 * Runtime companion-plugin and environment readiness checks.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Readiness {
    /** @return array<string, bool|string> */
    public static function get_status(): array {
        $chada_travel_environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $chada_travel_query_monitor_active = defined('QM_VERSION') || class_exists('QM_Plugin');
        $chada_travel_smtp = CHADA_TRAVEL_SMTP_Settings::get_public_settings();
        return [
            'smtp_enabled' => !empty($chada_travel_smtp['chada_travel_smtp_enabled']),
            'smtp_configured' => self::smtp_is_configured(),
            'query_monitor' => $chada_travel_query_monitor_active,
            'environment' => $chada_travel_environment,
            'production_safe' => $chada_travel_environment !== 'production' || !$chada_travel_query_monitor_active,
        ];
    }

    /** Returns whether enabled SMTP has all required runtime values. */
    private static function smtp_is_configured(): bool {
        return CHADA_TRAVEL_SMTP_Transport::is_complete(CHADA_TRAVEL_SMTP_Settings::get_runtime_settings());
    }

    /**
     * Returns the current request-level PHP runtime limit status for the administrator readiness notice.
     *
     * @return array{desired_seconds: int, max_execution_time: string, default_socket_timeout: string,
     *     execution_ready: bool, socket_ready: bool}
     */
    public static function get_runtime_limits_status(): array {
        return CHADA_TRAVEL_Runtime_Limits::get_status();
    }

    /**
     * Pure decision of a companion plugin's readiness state (available/inactive/missing) and whether an
     * actionable link may be offered for it. Takes plain booleans and calls no WordPress functions, so this
     * is directly unit-testable without a WordPress runtime.
     *
     * @return array{state: string, show_link: bool}
     */
    public static function resolve_companion_state(
        bool $chada_travel_available,
        bool $chada_travel_installed,
        bool $chada_travel_can_install,
        bool $chada_travel_can_activate
    ): array {
        if ($chada_travel_available) {
            return ['state' => 'available', 'show_link' => false];
        }
        if ($chada_travel_installed) {
            return ['state' => 'inactive', 'show_link' => $chada_travel_can_activate];
        }
        return ['state' => 'missing', 'show_link' => $chada_travel_can_install];
    }

    /**
     * The one authoritative email-delivery readiness resolver - the Email tab's Delivery Status section and the
     * site-wide readiness notice below both read from this instead of each keeping their own logic. Distinguishes
     * transport/plugin availability, queue availability, sender/header configuration, the administrator
     * notification recipient, and whether the most recent dispatch attempt actually failed. This is informational
     * only: an incomplete/default state never blocks checkout or any existing workflow, and even a fully
     * configured state is not a delivery guarantee - only a Send Test Email result is direct evidence of the
     * current path, and even that is not permanent.
     *
     * @return array<string, bool|string>
     */
    public static function get_email_status(): array {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_smtp = CHADA_TRAVEL_SMTP_Settings::get_public_settings();
        $chada_travel_smtp_enabled = !empty($chada_travel_smtp['chada_travel_smtp_enabled']);
        global $wpdb;
        return [
            'smtp_enabled'                => $chada_travel_smtp_enabled,
            'smtp_state'                  => $chada_travel_smtp_enabled
                ? (self::smtp_is_configured() ? 'configured' : 'incomplete') : 'disabled',
            'queue_active'                => is_object($wpdb) && method_exists($wpdb, 'get_var')
                && CHADA_TRAVEL_Email_Queue_Repository::has_active_items($wpdb),
            'sender_state'                => self::resolve_email_sender_state($chada_travel_settings),
            'admin_notifications_enabled' => !empty($chada_travel_settings['chada_travel_email_admin_notifications_enabled']),
            'admin_recipient'             => CHADA_TRAVEL_Config::resolve_admin_notification_email($chada_travel_settings),
            'recent_dispatch_failed'      => self::recent_email_dispatch_failed(),
        ];
    }

    /**
     * Classifies the effective sender configuration into one of four states: `explicit` (a real, non-default
     * default From Name plus an explicit From Address), `inherited_company_name` (From Address set, From Name
     * blank/default, and a Company Name to inherit), `managed_externally` (no explicit From Address -
     * WordPress fully controls the sender), or `incomplete` (From Address set but nothing to inherit
     * for the name either). Public (like resolve_companion_state()) so this pure decision is directly
     * unit-testable without a WordPress runtime.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function resolve_email_sender_state(array $chada_travel_settings): string {
        $chada_travel_identity = CHADA_TRAVEL_Config::resolve_email_identity($chada_travel_settings);
        if ($chada_travel_identity['from_address'] === '') {
            return 'managed_externally';
        }
        $chada_travel_from_name_raw = trim((string) ($chada_travel_settings['chada_travel_email_from_name'] ?? ''));
        if ($chada_travel_from_name_raw !== '') {
            return 'explicit';
        }
        if (trim((string) ($chada_travel_settings['chada_travel_company_name'] ?? '')) !== '') {
            return 'inherited_company_name';
        }
        return 'incomplete';
    }

    /**
     * True when the most recently dispatched plugin email (customer or admin) was not sent successfully. Safely
     * reports false (rather than fatal) when no real $wpdb is globally available.
     */
    private static function recent_email_dispatch_failed(): bool {
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare')) {
            return false;
        }
        $chada_travel_event = CHADA_TRAVEL_Payment_Repository::find_latest_event_by_type($wpdb, 'chada_travel_email_dispatched');
        if (!$chada_travel_event) {
            return false;
        }
        $chada_travel_meta = json_decode((string) ($chada_travel_event['chada_travel_event_meta'] ?? ''), true) ?: [];
        return empty($chada_travel_meta['sent']);
    }

    /**
     * The one authoritative Pages & Links readiness resolver: every workflow page's current status, keyed the
     * same as CHADA_TRAVEL_Page_Registry::get_definitions(). Shared by the Settings tab's own detailed status summary
     * and this class's site-wide admin notice, so the two never disagree.
     *
     * @return array<string, array{status: string, page_id: int, url: string}>
     */
    public static function get_pages_links_status(): array {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_result   = [];
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_result[$chada_travel_key] = CHADA_TRAVEL_Page_Settings::resolve_status($chada_travel_key, $chada_travel_settings);
        }
        return $chada_travel_result;
    }

    /**
     * Builds the concise Pages & Links readiness sentence listing only the affected workflow pages by name and
     * truthful status (never a token-bearing URL, never claiming a fallback path is a configured page),
     * or '' when every workflow page is already Ready. Informational only - never blocks activation or checkout.
     */
    private static function render_pages_links_message(): string {
        $chada_travel_page_labels   = CHADA_TRAVEL_Page_Registry::get_labels();
        $chada_travel_status_labels = CHADA_TRAVEL_Page_Settings::get_status_labels();
        $chada_travel_affected      = [];
        foreach (self::get_pages_links_status() as $chada_travel_key => $chada_travel_status) {
            if ($chada_travel_key === CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS) {
                continue;
            }
            if ($chada_travel_status['status'] === CHADA_TRAVEL_Page_Settings::STATUS_READY) {
                continue;
            }
            $chada_travel_affected[] = ($chada_travel_page_labels[$chada_travel_key] ?? $chada_travel_key) . ' ('
                . ($chada_travel_status_labels[$chada_travel_status['status']] ?? $chada_travel_status['status']) . ')';
        }
        if (!$chada_travel_affected) {
            return '';
        }
        $chada_travel_settings_url = function_exists('admin_url')
            ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Settings::MENU_SLUG . '&tab=pages-links') : '';
        $chada_travel_sentence = sprintf(
            /* translators: %s: comma-separated list of affected workflow page names and their status. */
            esc_html__('Pages & Links needs attention: %s.', 'chada-travel'),
            esc_html(implode(', ', $chada_travel_affected))
        );
        if ($chada_travel_settings_url === '') {
            return $chada_travel_sentence;
        }
        return $chada_travel_sentence . ' <a href="' . esc_url($chada_travel_settings_url) . '">'
            . esc_html__('Configure Pages & Links', 'chada-travel') . '</a>.';
    }

    /**
     * Builds the concise Policies & Consent readiness sentence, or '' when the current policy bundle is
     * already Ready. Never exposes customer data or the detailed per-policy status already available on the
     * Settings tab itself - only states honestly that new checkout drafts are unavailable until the bundle is
     * Ready. An existing order with a valid immutable snapshot is entirely unaffected by this notice.
     */
    private static function render_policies_message(): string {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        if (CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings)['ready']) {
            return '';
        }
        $chada_travel_settings_url = function_exists('admin_url')
            ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Settings::MENU_SLUG . '&tab=policies-consent') : '';
        $chada_travel_sentence = esc_html__(
            // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
            'Policies & Consent needs attention: new checkout drafts are unavailable until the policy bundle (Policy Version, Effective Date, Privacy, Terms, and Cancellation/Refund) is Ready.',
            'chada-travel'
        );
        if ($chada_travel_settings_url === '') {
            return $chada_travel_sentence;
        }
        return $chada_travel_sentence . ' <a href="' . esc_url($chada_travel_settings_url) . '">'
            . esc_html__('Configure Policies & Consent', 'chada-travel') . '</a>.';
    }

    /** Shows capability-protected notices for the Free plugin's own runtime. */
    public static function render_admin_notices(): void {
        if (!current_user_can('manage_chada_travel_visa_settings')) {
            return;
        }
        $chada_travel_status = self::get_status();
        $chada_travel_messages = [];
        if (!$chada_travel_status['production_safe']) {
            $chada_travel_messages[] = esc_html__('Query Monitor must be deactivated in production.', 'chada-travel');
        }
        $chada_travel_runtime_status = self::get_runtime_limits_status();
        if (!$chada_travel_runtime_status['execution_ready'] || !$chada_travel_runtime_status['socket_ready']) {
            $chada_travel_execution = self::format_runtime_limit($chada_travel_runtime_status['max_execution_time']);
            $chada_travel_socket = self::format_runtime_limit($chada_travel_runtime_status['default_socket_timeout']);
            $chada_travel_messages[] = sprintf(
                /* translators: 1: PHP max execution time, 2: PHP socket timeout, 3: desired seconds. */
                esc_html__(
                    'Chada Travel requested %3$d seconds; PHP reports execution %1$s and socket %2$s.',
                    'chada-travel'
                ) . ' ' . esc_html__('Ask your host to raise persistent PHP limits if operations time out.', 'chada-travel'),
                esc_html($chada_travel_execution),
                esc_html($chada_travel_socket),
                (int) $chada_travel_runtime_status['desired_seconds']
            );
        }
        $chada_travel_email_status = self::get_email_status();
        if ($chada_travel_email_status['smtp_enabled'] && $chada_travel_email_status['smtp_state'] === 'incomplete') {
            $chada_travel_messages[] = esc_html__(
                'Built-in SMTP is enabled but incomplete; WordPress mail fallback remains active until all SMTP fields are configured.',
                'chada-travel'
            );
        }
        // Informational only - never blocks checkout or any other workflow; the administrator can review the
        // Email tab's own Delivery Status for the event/audience/timestamp of the failed attempt.
        if ($chada_travel_email_status['recent_dispatch_failed']) {
            $chada_travel_messages[] = esc_html__(
                // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
                'The most recently attempted Chada Travel - Agency Manager email was not sent successfully; review Delivery Status on the Settings Email tab.',
                'chada-travel'
            );
        }
        $chada_travel_pages_links_message = self::render_pages_links_message();
        if ($chada_travel_pages_links_message !== '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_pages_links_message() already escapes every dynamic part it returns.
            $chada_travel_messages[] = $chada_travel_pages_links_message;
        }
        $chada_travel_policies_message = self::render_policies_message();
        if ($chada_travel_policies_message !== '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_policies_message() already escapes every dynamic part it returns.
            $chada_travel_messages[] = $chada_travel_policies_message;
        }
        if (!$chada_travel_messages) {
            return;
        }
        $chada_travel_notice = '<div class="notice notice-warning"><p><strong>'
            . esc_html__('Chada Travel - Agency Manager readiness:', 'chada-travel') . '</strong> '
            // Every element of $chada_travel_messages is already individually escaped by
            // esc_html() here would double-encode the intentional settings anchors.
            . implode(' ', $chada_travel_messages) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            . '</p></div>';
        echo $chada_travel_notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** Formats a raw PHP duration for a safe administrator-facing diagnostic. */
    private static function format_runtime_limit(string $chada_travel_value): string {
        if ($chada_travel_value === '') {
            return __('unavailable', 'chada-travel');
        }
        if ((int) $chada_travel_value <= 0) {
            return __('unlimited', 'chada-travel');
        }
        return sprintf(
            /* translators: %d: duration in seconds. */
            __('%d seconds', 'chada-travel'),
            (int) $chada_travel_value
        );
    }
}
