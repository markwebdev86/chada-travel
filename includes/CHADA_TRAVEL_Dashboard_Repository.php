<?php
/**
 * Read-only aggregate queries for the Free administrator Dashboard's summary cards and the Setup & System Status
 * panel. Active/Needs Review reuse CHADA_TRAVEL_Order_Repository::count_bookings() directly (the exact method the
 * Bookings worklist tabs already call) so the Dashboard and Bookings can never disagree; every other card is a
 * new bounded aggregate over the same canonical tables. The Setup & System Status panel reuses each area's own
 * authoritative readiness resolver rather than recomputing any rule independently.
 *
 * Every method throws when the underlying query fails (real WordPress sets $wpdb->last_error rather than
 * returning a distinguishable failure value), so a caller never mistakes an unavailable total for a verified
 * zero. Callers (CHADA_TRAVEL_Admin_Dashboard) must catch and render a safe notice per section.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Dashboard_Repository {
    /** @throws \RuntimeException When the query just executed against $chada_travel_wpdb failed. */
    private static function guard_no_db_error(object $chada_travel_wpdb): void {
        if ((string) ($chada_travel_wpdb->last_error ?? '') !== '') {
            throw new \RuntimeException('Chada Travel - Agency Manager Dashboard query failed.');
        }
    }

    /**
     * All order rows and the archived subset shown as secondary context, never silently subtracted from the
     * headline total.
     *
     * @return array{total: int, archived: int}
     */
    public static function get_total_bookings(object $chada_travel_wpdb): array {
        $chada_travel_total = CHADA_TRAVEL_Order_Repository::count_bookings(
            $chada_travel_wpdb,
            CHADA_TRAVEL_Order_Repository::WORKLIST_ALL,
            '',
            '',
            ''
        );
        self::guard_no_db_error($chada_travel_wpdb);
        $chada_travel_archived = CHADA_TRAVEL_Order_Repository::count_bookings(
            $chada_travel_wpdb,
            CHADA_TRAVEL_Order_Repository::WORKLIST_ARCHIVED,
            '',
            '',
            ''
        );
        self::guard_no_db_error($chada_travel_wpdb);
        return ['total' => $chada_travel_total, 'archived' => $chada_travel_archived];
    }

    /**
     * All application rows and the subset currently active (using the application's own
     * chada_travel_archived_at, never inferred from applicant name uniqueness).
     *
     * @return array{total: int, active: int}
     */
    public static function get_visa_applications(object $chada_travel_wpdb): array {
        $chada_travel_table = CHADA_TRAVEL_Order_Repository::applications_table($chada_travel_wpdb);
        $chada_travel_total = $chada_travel_wpdb->get_var("SELECT COUNT(*) FROM {$chada_travel_table}");
        self::guard_no_db_error($chada_travel_wpdb);
        $chada_travel_active = $chada_travel_wpdb->get_var(
            "SELECT COUNT(*) FROM {$chada_travel_table} WHERE chada_travel_archived_at IS NULL"
        );
        self::guard_no_db_error($chada_travel_wpdb);
        return ['total' => (int) $chada_travel_total, 'active' => (int) $chada_travel_active];
    }

    /** The current Bookings worklist's Active definition, verbatim - never approximated. */
    public static function get_active_bookings(object $chada_travel_wpdb): int {
        $chada_travel_count = CHADA_TRAVEL_Order_Repository::count_bookings(
            $chada_travel_wpdb,
            CHADA_TRAVEL_Order_Repository::WORKLIST_ACTIVE,
            '',
            '',
            ''
        );
        self::guard_no_db_error($chada_travel_wpdb);
        return $chada_travel_count;
    }

    /**
     * The current Bookings worklist's Needs Review definition, verbatim - a unique booking count, never a sum
     * of payment rows plus application rows that would double-count one booking needing both.
     */
    public static function get_needs_review(object $chada_travel_wpdb): int {
        $chada_travel_count = CHADA_TRAVEL_Order_Repository::count_bookings(
            $chada_travel_wpdb,
            CHADA_TRAVEL_Order_Repository::WORKLIST_NEEDS_REVIEW,
            '',
            '',
            ''
        );
        self::guard_no_db_error($chada_travel_wpdb);
        return $chada_travel_count;
    }

    /**
     * Runs `SELECT COUNT(*) FROM {$chada_travel_table} WHERE <conditions>`, preparing only when a placeholder is
     * actually present (mirrors CHADA_TRAVEL_Order_Repository::count_bookings()'s own empty-params shortcut), and
     * guards the result against a real query failure.
     *
     * @param list<string> $chada_travel_conditions
     * @param list<mixed>  $chada_travel_params
     */
    private static function count_where(
        object $chada_travel_wpdb,
        string $chada_travel_table,
        array $chada_travel_conditions,
        array $chada_travel_params
    ): int {
        $chada_travel_sql = "SELECT COUNT(*) FROM {$chada_travel_table} WHERE " . implode(' AND ', $chada_travel_conditions);
        $chada_travel_count = $chada_travel_params
            ? (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_params))
            : (int) $chada_travel_wpdb->get_var($chada_travel_sql);
        self::guard_no_db_error($chada_travel_wpdb);
        return $chada_travel_count;
    }

    // ================================================================== Phase 2: period-scoped secondary figures
    //
    // The Phase 1 six-card methods above are intentionally left untouched (still all-time, unconditional). Each
    // method below is a new sibling used only for the small period-specific secondary line shown beneath a Phase 1
    // headline; the renderer omits that line entirely for the `all` period, since it would just repeat the
    // all-time headline.

    /** Orders created within the period (or all-time when $chada_travel_period_start_utc is null). */
    public static function get_bookings_created_in_period(object $chada_travel_wpdb, ?string $chada_travel_period_start_utc): int {
        $chada_travel_table = CHADA_TRAVEL_Order_Repository::orders_table($chada_travel_wpdb);
        $chada_travel_cond  = self::created_since_conditions($chada_travel_period_start_utc);
        return self::count_where($chada_travel_wpdb, $chada_travel_table, $chada_travel_cond['sql'], $chada_travel_cond['params']);
    }

    /** Applications created within the period (or all-time). */
    public static function get_applications_created_in_period(
        object $chada_travel_wpdb,
        ?string $chada_travel_period_start_utc
    ): int {
        $chada_travel_table = CHADA_TRAVEL_Order_Repository::applications_table($chada_travel_wpdb);
        $chada_travel_cond  = self::created_since_conditions($chada_travel_period_start_utc);
        return self::count_where($chada_travel_wpdb, $chada_travel_table, $chada_travel_cond['sql'], $chada_travel_cond['params']);
    }

    /** @return array{sql: list<string>, params: list<mixed>} */
    private static function created_since_conditions(?string $chada_travel_period_start_utc): array {
        if ($chada_travel_period_start_utc === null) {
            return ['sql' => ['1=1'], 'params' => []];
        }
        return ['sql' => ['chada_travel_created_at >= %s'], 'params' => [$chada_travel_period_start_utc]];
    }

    /** Bounded latest-booking preview linking back to the authoritative Bookings screen. */
    //
    // Bounded navigational previews only - every list here links back to its existing authoritative screen
    // (Bookings, Payment Review, Booking Detail) for any action. Limits are named constants so the query, the
    // renderer, and the tests can never silently disagree.

    /** Newest-bookings preview row count. */
    public const LATEST_BOOKINGS_LIMIT = 5;

    /**
     * The newest LATEST_BOOKINGS_LIMIT bookings, ordered by CHADA_TRAVEL_Order_Repository::search_bookings()'s own
     * chada_travel_created_at DESC, chada_travel_order_id DESC - reused verbatim (including its correlated application-count/
     * country-summary/file-status-summary/latest-payment subqueries) rather than a second, forked query. Includes
     * archived records, exactly like the Phase 1 Total Bookings definition and the Bookings "All" worklist.
     *
     * @return list<array<string, mixed>>
     */
    public static function get_latest_bookings(
        object $chada_travel_wpdb,
        int $chada_travel_limit = self::LATEST_BOOKINGS_LIMIT
    ): array {
        $chada_travel_rows = CHADA_TRAVEL_Order_Repository::search_bookings(
            $chada_travel_wpdb,
            CHADA_TRAVEL_Order_Repository::WORKLIST_ALL,
            '',
            '',
            '',
            1,
            $chada_travel_limit
        );
        self::guard_no_db_error($chada_travel_wpdb);
        return $chada_travel_rows;
    }

    /**
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array<string, mixed>
     */
    public static function get_setup_status(array $chada_travel_settings): array {
        $chada_travel_items = [
            'company_status'    => self::resolve_company_status_item($chada_travel_settings),
            'pages_links'       => self::resolve_pages_links_item(),
            'payment_methods'   => self::resolve_payment_methods_item($chada_travel_settings),
            'policies_consent'  => self::resolve_policies_consent_item($chada_travel_settings),
            'email'             => self::resolve_email_item(),
            'documents_uploads' => self::resolve_documents_uploads_item($chada_travel_settings),
        ];

        $chada_travel_all_ready = true;
        foreach ($chada_travel_items as $chada_travel_item) {
            if (!$chada_travel_item['ready']) {
                $chada_travel_all_ready = false;
                break;
            }
        }

        return [
            'items'     => $chada_travel_items,
            'all_ready' => $chada_travel_all_ready,
        ];
    }

    /**
     * Resolves the administrator-facing configuration issues that affect the customer Visa Application workflow.
     * Each issue reuses the owning Settings validator or readiness service; this method only groups the results for
     * the Dashboard notification and never changes settings or customer checkout state.
     *
     * @param array<string, mixed> $chada_travel_settings Effective CHADA_TRAVEL_Config::get_settings() values.
     * @return array{ready: bool, issues: list<array{key: string, label: string, details: list<string>, impact: string, tab: string}>}
     */
    public static function get_checkout_readiness(array $chada_travel_settings): array {
        $chada_travel_issues = [];
        $chada_travel_general_labels = self::general_field_labels();
        $chada_travel_missing_fields = CHADA_TRAVEL_General_Settings_Validator::missing_required_fields($chada_travel_settings);
        if ($chada_travel_missing_fields) {
            $chada_travel_details = array_values(array_map(
                static fn(string $chada_travel_option): string => $chada_travel_general_labels[$chada_travel_option] ?? $chada_travel_option,
                $chada_travel_missing_fields
            ));
            $chada_travel_issues[] = [
                'key'     => 'general',
                'label'   => __('General', 'chada-travel'),
                'details' => $chada_travel_details,
                'impact'  => __('Complete these required fields before relying on the full checkout configuration.', 'chada-travel'),
                'tab'     => 'general',
            ];
        }

        if (!CHADA_TRAVEL_Config::is_accepting_new_applications($chada_travel_settings)) {
            $chada_travel_issues[] = [
                'key'     => 'company_status',
                'label'   => __('Company Status', 'chada-travel'),
                'details' => [__('Under Maintenance', 'chada-travel')],
                'impact'  => __(
                    'New Visa Application checkout content is hidden until Company Status is set to Active.',
                    'chada-travel'
                ),
                'tab'     => 'general',
            ];
        }

        if (!CHADA_TRAVEL_Payment_Readiness::has_any_ready_method($chada_travel_settings)) {
            $chada_travel_issues[] = [
                'key'     => 'payment_methods',
                'label'   => __('Payment Methods', 'chada-travel'),
                'details' => [__('No enabled and fully configured payment method is ready.', 'chada-travel')],
                'impact'  => __('Payment selection cannot be completed until at least one payment method is Ready.', 'chada-travel'),
                'tab'     => 'payment-method',
            ];
        }

        $chada_travel_page_labels = CHADA_TRAVEL_Page_Registry::get_labels();
        $chada_travel_page_status_labels = CHADA_TRAVEL_Page_Settings::get_status_labels();
        $chada_travel_page_details = [];
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_status = CHADA_TRAVEL_Page_Settings::resolve_status($chada_travel_key, $chada_travel_settings);
            if ($chada_travel_status['status'] === CHADA_TRAVEL_Page_Settings::STATUS_READY) {
                continue;
            }
            $chada_travel_page_details[] = ($chada_travel_page_labels[$chada_travel_key] ?? $chada_travel_key) . ': '
                . ($chada_travel_page_status_labels[$chada_travel_status['status']] ?? $chada_travel_status['status']);
        }
        if ($chada_travel_page_details) {
            $chada_travel_issues[] = [
                'key'     => 'pages_links',
                'label'   => __('Pages & Links', 'chada-travel'),
                'details' => $chada_travel_page_details,
                'impact'  => __('The affected public workflow pages are not fully ready for visitors.', 'chada-travel'),
                'tab'     => 'pages-links',
            ];
        }

        $chada_travel_policy_bundle = CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings);
        $chada_travel_policy_details = [];
        if (!$chada_travel_policy_bundle['version_valid']) {
            $chada_travel_policy_details[] = __('Policy Version: missing or invalid', 'chada-travel');
        }
        if (!$chada_travel_policy_bundle['effective_date_valid']) {
            $chada_travel_policy_details[] = __('Policy Effective Date: missing, invalid, or in the future', 'chada-travel');
        }
        $chada_travel_policy_status_labels = CHADA_TRAVEL_Policy_Settings::get_status_labels();
        foreach ($chada_travel_policy_bundle['policies'] as $chada_travel_key => $chada_travel_status) {
            if (CHADA_TRAVEL_Policy_Settings::is_ready_status($chada_travel_status['status'])) {
                continue;
            }
            $chada_travel_policy_label = CHADA_TRAVEL_Policy_Registry::get_labels()[$chada_travel_key] ?? $chada_travel_key;
            $chada_travel_policy_details[] = $chada_travel_policy_label . ': '
                . ($chada_travel_policy_status_labels[$chada_travel_status['status']] ?? $chada_travel_status['status']);
        }
        if ($chada_travel_policy_details) {
            $chada_travel_issues[] = [
                'key'     => 'policies_consent',
                'label'   => __('Policies & Consent', 'chada-travel'),
                'details' => $chada_travel_policy_details,
                'impact'  => __(
                    'New Visa Application checkout drafts remain unavailable until the policy bundle is Ready.',
                    'chada-travel'
                ),
                'tab'     => 'policies-consent',
            ];
        }

        return ['ready' => $chada_travel_issues === [], 'issues' => $chada_travel_issues];
    }

    /** @return array<string, string> Required General Settings option name => administrator-facing label. */
    private static function general_field_labels(): array {
        return [
            'chada_travel_company_name'                => __('Company Name', 'chada-travel'),
            'chada_travel_company_website_url'         => __('Company Website URL', 'chada-travel'),
            'chada_travel_company_support_email'       => __('Support Email', 'chada-travel'),
            'chada_travel_company_country'             => __('Company Country', 'chada-travel'),
            'chada_travel_company_address_line_1'      => __('Address Line 1', 'chada-travel'),
            'chada_travel_company_city'                => __('City/Municipality', 'chada-travel'),
            'chada_travel_company_province'            => __('Province/Region', 'chada-travel'),
            'chada_travel_company_address_country'     => __('Address Country', 'chada-travel'),
            'chada_travel_booking_id_prefix'           => __('Booking ID Prefix', 'chada-travel'),
            'chada_travel_transaction_id_prefix'       => __('Transaction ID Prefix', 'chada-travel'),
            'chada_travel_company_maintenance_message' => __('Maintenance Message', 'chada-travel'),
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array{label: string, ready: bool, detail: string, tab: string}
     */
    private static function resolve_company_status_item(array $chada_travel_settings): array {
        $chada_travel_accepting = CHADA_TRAVEL_Config::is_accepting_new_applications($chada_travel_settings);
        return [
            'label'  => __('Company Status', 'chada-travel'),
            'ready'  => $chada_travel_accepting,
            'detail' => $chada_travel_accepting ? __('Active', 'chada-travel') : __('Under Maintenance', 'chada-travel'),
            'tab'    => 'general',
        ];
    }

    /** @return array{label: string, ready: bool, detail: string, tab: string} */
    private static function resolve_pages_links_item(): array {
        $chada_travel_ready = true;
        foreach (CHADA_TRAVEL_Readiness::get_pages_links_status() as $chada_travel_page_status) {
            if ($chada_travel_page_status['status'] !== CHADA_TRAVEL_Page_Settings::STATUS_READY) {
                $chada_travel_ready = false;
                break;
            }
        }
        return [
            'label'  => __('Pages & Links', 'chada-travel'),
            'ready'  => $chada_travel_ready,
            'detail' => $chada_travel_ready
                ? __('All workflow pages ready', 'chada-travel')
                : __('Needs attention', 'chada-travel'),
            'tab'    => 'pages-links',
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array{label: string, ready: bool, detail: string, tab: string}
     */
    private static function resolve_payment_methods_item(array $chada_travel_settings): array {
        $chada_travel_count = count(CHADA_TRAVEL_Payment_Readiness::get_enabled_ready_methods($chada_travel_settings));
        return [
            'label'  => __('Payment Methods', 'chada-travel'),
            'ready'  => $chada_travel_count > 0,
            'detail' => sprintf(
                /* translators: %d: number of enabled-and-ready payment methods. */
                _n('%d method ready', '%d methods ready', $chada_travel_count, 'chada-travel'),
                $chada_travel_count
            ),
            'tab'    => 'payment-method',
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array{label: string, ready: bool, detail: string, tab: string}
     */
    private static function resolve_policies_consent_item(array $chada_travel_settings): array {
        $chada_travel_ready = CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings)['ready'];
        return [
            'label'  => __('Policies & Consent', 'chada-travel'),
            'ready'  => $chada_travel_ready,
            'detail' => $chada_travel_ready
                ? __('Policy bundle ready', 'chada-travel')
                : __('Needs attention', 'chada-travel'),
            'tab'    => 'policies-consent',
        ];
    }

    /** @return array{label: string, ready: bool, detail: string, tab: string} */
    private static function resolve_email_item(): array {
        $chada_travel_status = CHADA_TRAVEL_Readiness::get_email_status();
        $chada_travel_ready  = $chada_travel_status['sender_state'] !== 'incomplete'
            && (!$chada_travel_status['smtp_enabled'] || $chada_travel_status['smtp_state'] === 'configured')
            && !$chada_travel_status['recent_dispatch_failed'];
        $chada_travel_detail = __('Ready', 'chada-travel');
        if ($chada_travel_status['recent_dispatch_failed']) {
            $chada_travel_detail = __('Latest dispatch failed', 'chada-travel');
        } elseif ($chada_travel_status['smtp_enabled'] && $chada_travel_status['smtp_state'] !== 'configured') {
            $chada_travel_detail = __('SMTP configuration incomplete', 'chada-travel');
        } elseif (!$chada_travel_ready) {
            $chada_travel_detail = __('Needs attention', 'chada-travel');
        }
        return [
            'label'  => __('Email', 'chada-travel'),
            'ready'  => $chada_travel_ready,
            'detail' => $chada_travel_detail,
            'tab'    => 'email',
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array{label: string, ready: bool, detail: string, tab: string}
     */
    private static function resolve_documents_uploads_item(array $chada_travel_settings): array {
        $chada_travel_status = CHADA_TRAVEL_Upload_Settings::get_status($chada_travel_settings);
        $chada_travel_ready  = !$chada_travel_status['server_limit_unavailable'] && !$chada_travel_status['over_server_limit'];
        $chada_travel_detail = __('Ready', 'chada-travel');
        if ($chada_travel_status['server_limit_unavailable']) {
            $chada_travel_detail = __('Server limit unavailable', 'chada-travel');
        } elseif ($chada_travel_status['over_server_limit']) {
            $chada_travel_detail = __('Server limit exceeded', 'chada-travel');
        }
        return [
            'label'  => __('Documents & Uploads', 'chada-travel'),
            'ready'  => $chada_travel_ready,
            'detail' => $chada_travel_detail,
            'tab'    => 'documents-uploads',
        ];
    }
}
