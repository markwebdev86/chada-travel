<?php
/**
 * wpdb-backed persistence for Stage 1-3 draft orders, applicants, applications, and audit events.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Order_Repository {
    public static function orders_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_orders';
    }

    public static function applicants_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_applicants';
    }

    public static function applications_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_applications';
    }

    public static function events_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_events';
    }

    /** @return array{token: string, hash: string} Raw token is returned once and never stored. */
    public static function generate_draft_token(): array {
        $chada_travel_raw = bin2hex(random_bytes(32));
        return ['token' => $chada_travel_raw, 'hash' => hash('sha256', $chada_travel_raw)];
    }

    public static function generate_reference(string $chada_travel_prefix): string {
        return $chada_travel_prefix . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Generates a Booker-facing public identifier such as CHADA_TRAVEL-202607-8F4K2Q or CHADA_TRAVEL-TXN-202607-8F4K2Q.
     * Excludes visually ambiguous characters (0/O, 1/I) since Bookers may retype this value manually.
     */
    public static function generate_public_id(string $chada_travel_prefix): string {
        return $chada_travel_prefix . '-' . gmdate('Ym') . '-' . self::random_alnum(6);
    }

    private static function random_alnum(int $chada_travel_length): string {
        $chada_travel_alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $chada_travel_value    = '';
        for ($chada_travel_i = 0; $chada_travel_i < $chada_travel_length; $chada_travel_i++) {
            $chada_travel_value .= $chada_travel_alphabet[random_int(0, strlen($chada_travel_alphabet) - 1)];
        }
        return $chada_travel_value;
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Finalizes the order's public Booking ID exactly once, when a payment method is first initiated.
     *
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function finalize_booking_id(object $chada_travel_wpdb, array $chada_travel_order, string $chada_travel_prefix): array {
        if (!empty($chada_travel_order['chada_travel_booking_id'])) {
            return $chada_travel_order;
        }
        $chada_travel_booking_id = self::generate_public_id($chada_travel_prefix);
        $chada_travel_now        = self::now();
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            ['chada_travel_booking_id' => $chada_travel_booking_id, 'chada_travel_updated_at' => $chada_travel_now],
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            ['%s', '%s'],
            ['%d']
        );
        self::log_event($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'], 'chada_travel_booking_id_created', 'system', [
            'booking_id' => $chada_travel_booking_id,
        ]);
        return array_merge($chada_travel_order, [
            'chada_travel_booking_id' => $chada_travel_booking_id, 'chada_travel_updated_at' => $chada_travel_now,
        ]);
    }

    /**
     * Writes the code-owned order/payment status pair together so the two columns never disagree.
     *
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function update_order_payment_state(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        string $chada_travel_order_status,
        string $chada_travel_payment_status,
        ?int $chada_travel_checkout_stage = null
    ): array {
        $chada_travel_now  = self::now();
        $chada_travel_data = [
            'chada_travel_order_status'   => $chada_travel_order_status,
            'chada_travel_payment_status' => $chada_travel_payment_status,
            'chada_travel_updated_at'     => $chada_travel_now,
        ];
        $chada_travel_formats = ['%s', '%s', '%s'];
        if ($chada_travel_checkout_stage !== null) {
            $chada_travel_data['chada_travel_checkout_stage'] = max(
                $chada_travel_checkout_stage,
                (int) $chada_travel_order['chada_travel_checkout_stage']
            );
            $chada_travel_formats[] = '%d';
        }
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            $chada_travel_formats,
            ['%d']
        );
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /** @return array<string, mixed>|null */
    public static function find_by_booking_id(object $chada_travel_wpdb, string $chada_travel_booking_id): ?array {
        if ($chada_travel_booking_id === '') {
            return null;
        }
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_booking_id = %s LIMIT 1",
            $chada_travel_booking_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    public const WORKLIST_ALL = 'all';
    public const WORKLIST_NEEDS_REVIEW = 'needs_review';
    public const WORKLIST_ACTIVE = 'active';
    public const WORKLIST_ARCHIVED = 'archived';
    public const BOOKINGS_PER_PAGE = 20;

    /**
     * @return list<string> Order statuses treated as terminal/finished for worklist purposes. This is the one
     * executable terminal-status registry - extension-owned retention services can reuse it directly rather than
     * duplicating the list, so a booking is never made eligible by a status this registry does not
     * itself already treat as finished.
     */
    public static function terminal_order_statuses(): array {
        return ['chada_travel_completed', 'chada_travel_cancelled', 'chada_travel_refunded'];
    }

    /**
     * @return list<string> Application file statuses that require administrator attention; reused by
     * extension-owned eligibility services so a booking with a document still needing review is never eligible.
     */
    public static function needs_review_file_statuses(): array {
        return ['chada_travel_submitted_for_review', 'chada_travel_action_required'];
    }

    /**
     * Builds the shared FROM/JOIN clause used by both search_bookings() and count_bookings(): the orders table
     * with its most recent payment attempt left-joined by correlated subquery (avoids fanning out order rows).
     */
    private static function bookings_base_sql(object $chada_travel_wpdb): string {
        $chada_travel_orders_table   = self::orders_table($chada_travel_wpdb);
        $chada_travel_payments_table = $chada_travel_wpdb->prefix . 'chada_travel_payments';
        return "FROM {$chada_travel_orders_table} o
            LEFT JOIN {$chada_travel_payments_table} p ON p.chada_travel_payment_id = (
                SELECT p2.chada_travel_payment_id FROM {$chada_travel_payments_table} p2
                WHERE p2.chada_travel_order_id = o.chada_travel_order_id
                ORDER BY p2.chada_travel_payment_id DESC LIMIT 1
            )";
    }

    /**
     * Builds the WHERE clause (and matching bound params) shared by search_bookings()/count_bookings()/
     * get_worklist_counts(), covering the worklist tab, order-status filter, payment-method filter, and the
     * required search fields (Booking ID, Booker name/email, applicant name, country name/code, application
     * reference) using EXISTS subqueries so applicant/application matches never duplicate an order row.
     *
     * @return array{sql: string, params: list<mixed>}
     */
    private static function bookings_where(
        object $chada_travel_wpdb,
        string $chada_travel_worklist,
        string $chada_travel_search,
        string $chada_travel_order_status,
        string $chada_travel_payment_method
    ): array {
        $chada_travel_applicants_table   = self::applicants_table($chada_travel_wpdb);
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_conditions = ['1=1'];
        $chada_travel_params     = [];

        switch ($chada_travel_worklist) {
            case self::WORKLIST_ARCHIVED:
                $chada_travel_conditions[] = 'o.chada_travel_archived_at IS NOT NULL';
                break;
            case self::WORKLIST_NEEDS_REVIEW:
                $chada_travel_conditions[] = 'o.chada_travel_archived_at IS NULL';
                $chada_travel_file_statuses = self::needs_review_file_statuses();
                $chada_travel_placeholders  = implode(',', array_fill(0, count($chada_travel_file_statuses), '%s'));
                $chada_travel_conditions[]  = "(p.chada_travel_payment_status = %s OR EXISTS (
                    SELECT 1 FROM {$chada_travel_applications_table} nr
                    WHERE nr.chada_travel_order_id = o.chada_travel_order_id AND nr.chada_travel_file_status IN ($chada_travel_placeholders)
                ))";
                $chada_travel_params[] = 'chada_travel_awaiting_verification';
                array_push($chada_travel_params, ...$chada_travel_file_statuses);
                break;
            case self::WORKLIST_ACTIVE:
                $chada_travel_conditions[] = 'o.chada_travel_archived_at IS NULL';
                $chada_travel_terminal     = self::terminal_order_statuses();
                $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_terminal), '%s'));
                $chada_travel_conditions[] = "o.chada_travel_order_status NOT IN ($chada_travel_placeholders)";
                array_push($chada_travel_params, ...$chada_travel_terminal);
                $chada_travel_file_statuses = self::needs_review_file_statuses();
                $chada_travel_placeholders2 = implode(',', array_fill(0, count($chada_travel_file_statuses), '%s'));
                $chada_travel_conditions[]  = "NOT (p.chada_travel_payment_status = %s OR EXISTS (
                    SELECT 1 FROM {$chada_travel_applications_table} nr2
                    WHERE nr2.chada_travel_order_id = o.chada_travel_order_id AND nr2.chada_travel_file_status IN ($chada_travel_placeholders2)
                ))";
                $chada_travel_params[] = 'chada_travel_awaiting_verification';
                array_push($chada_travel_params, ...$chada_travel_file_statuses);
                break;
            default:
                // WORKLIST_ALL: no additional restriction, including drafts and archived records.
                break;
        }

        if ($chada_travel_order_status !== '') {
            $chada_travel_conditions[] = 'o.chada_travel_order_status = %s';
            $chada_travel_params[]     = $chada_travel_order_status;
        }
        if ($chada_travel_payment_method !== '') {
            $chada_travel_conditions[] = 'p.chada_travel_payment_method = %s';
            $chada_travel_params[]     = $chada_travel_payment_method;
        }
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = '(
                o.chada_travel_booking_id LIKE %s OR o.chada_travel_booker_first_name LIKE %s OR o.chada_travel_booker_last_name LIKE %s
                OR o.chada_travel_booker_email LIKE %s
                OR EXISTS (SELECT 1 FROM ' . $chada_travel_applicants_table . ' ap WHERE ap.chada_travel_order_id = o.chada_travel_order_id
                    AND (ap.chada_travel_first_name LIKE %s OR ap.chada_travel_last_name LIKE %s))
                OR EXISTS (SELECT 1 FROM ' . $chada_travel_applications_table . ' se WHERE se.chada_travel_order_id = o.chada_travel_order_id
                    AND (se.chada_travel_country_name_snapshot LIKE %s OR se.chada_travel_country_code_snapshot LIKE %s
                        OR se.chada_travel_application_reference LIKE %s))
            )';
            array_push($chada_travel_params, ...array_fill(0, 9, $chada_travel_like));
        }

        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /**
     * Real database-side search/filter/sort/pagination for the Bookings worklist. Never loads more than one
     * page of orders into PHP; per-order application count, country summary, and file-status summary are
     * computed by correlated subqueries in the same query.
     *
     * @return list<array<string, mixed>>
     */
    public static function search_bookings(
        object $chada_travel_wpdb,
        string $chada_travel_worklist,
        string $chada_travel_search,
        string $chada_travel_order_status,
        string $chada_travel_payment_method,
        int $chada_travel_page = 1,
        int $chada_travel_per_page = self::BOOKINGS_PER_PAGE
    ): array {
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_where = self::bookings_where(
            $chada_travel_wpdb,
            $chada_travel_worklist,
            $chada_travel_search,
            $chada_travel_order_status,
            $chada_travel_payment_method
        );
        $chada_travel_page     = max(1, $chada_travel_page);
        $chada_travel_per_page = max(1, min(100, $chada_travel_per_page));
        $chada_travel_offset   = ($chada_travel_page - 1) * $chada_travel_per_page;

        $chada_travel_sql = 'SELECT o.*, p.chada_travel_payment_id AS latest_payment_id,
                p.chada_travel_payment_method AS latest_payment_method,
                p.chada_travel_payment_status AS latest_payment_status,
                (SELECT COUNT(*) FROM ' . $chada_travel_applications_table . '
                    ac WHERE ac.chada_travel_order_id = o.chada_travel_order_id) AS application_count,
                (SELECT GROUP_CONCAT(DISTINCT cs.chada_travel_country_name_snapshot
                    ORDER BY cs.chada_travel_country_name_snapshot SEPARATOR ", ")
                    FROM ' . $chada_travel_applications_table . ' cs
                    WHERE cs.chada_travel_order_id = o.chada_travel_order_id) AS country_summary,
                (SELECT GROUP_CONCAT(DISTINCT fs.chada_travel_file_status SEPARATOR ",")
                    FROM ' . $chada_travel_applications_table . ' fs
                    WHERE fs.chada_travel_order_id = o.chada_travel_order_id) AS file_status_summary
            ' . self::bookings_base_sql($chada_travel_wpdb) . '
            WHERE ' . $chada_travel_where['sql'] . '
            ORDER BY o.chada_travel_created_at DESC, o.chada_travel_order_id DESC
            LIMIT %d OFFSET %d';

        $chada_travel_params   = array_merge($chada_travel_where['params'], [$chada_travel_per_page, $chada_travel_offset]);
        $chada_travel_prepared = $chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_params);
        return $chada_travel_wpdb->get_results($chada_travel_prepared, ARRAY_A) ?: [];
    }

    /** Total booking count matching the same filters as search_bookings(), for pagination. */
    public static function count_bookings(
        object $chada_travel_wpdb,
        string $chada_travel_worklist,
        string $chada_travel_search,
        string $chada_travel_order_status,
        string $chada_travel_payment_method
    ): int {
        $chada_travel_where = self::bookings_where(
            $chada_travel_wpdb,
            $chada_travel_worklist,
            $chada_travel_search,
            $chada_travel_order_status,
            $chada_travel_payment_method
        );
        $chada_travel_sql = 'SELECT COUNT(*) ' . self::bookings_base_sql($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql'];
        if (!$chada_travel_where['params']) {
            return (int) $chada_travel_wpdb->get_var($chada_travel_sql);
        }
        return (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']));
    }

    /**
     * Real server-side counts for each of the four worklist tabs, honoring the current search/status/method
     * filters so the counts always agree with what the grid would show if that tab were selected.
     *
     * @return array{all: int, needs_review: int, active: int, archived: int}
     */
    public static function get_worklist_counts(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_order_status,
        string $chada_travel_payment_method
    ): array {
        $chada_travel_counts = [];
        foreach ([self::WORKLIST_ALL, self::WORKLIST_NEEDS_REVIEW, self::WORKLIST_ACTIVE, self::WORKLIST_ARCHIVED]
            as $chada_travel_worklist
        ) {
            $chada_travel_counts[$chada_travel_worklist] = self::count_bookings(
                $chada_travel_wpdb,
                $chada_travel_worklist,
                $chada_travel_search,
                $chada_travel_order_status,
                $chada_travel_payment_method
            );
        }
        return [
            'all'          => $chada_travel_counts[self::WORKLIST_ALL],
            'needs_review' => $chada_travel_counts[self::WORKLIST_NEEDS_REVIEW],
            'active'       => $chada_travel_counts[self::WORKLIST_ACTIVE],
            'archived'     => $chada_travel_counts[self::WORKLIST_ARCHIVED],
        ];
    }

    /**
     * Archives a booking (soft-delete via chada_travel_archived_at) without touching payment/workflow history.
     *
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function archive(object $chada_travel_wpdb, array $chada_travel_order, int $chada_travel_actor_user_id): array {
        $chada_travel_now  = self::now();
        $chada_travel_data = ['chada_travel_archived_at' => $chada_travel_now, 'chada_travel_updated_at' => $chada_travel_now];
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            ['%s', '%s'],
            ['%d']
        );
        self::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            'chada_travel_booking_archived',
            'admin',
            ['actor_user_id' => $chada_travel_actor_user_id]
        );
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /**
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function restore(object $chada_travel_wpdb, array $chada_travel_order, int $chada_travel_actor_user_id): array {
        $chada_travel_data = ['chada_travel_archived_at' => null, 'chada_travel_updated_at' => self::now()];
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            ['%s', '%s'],
            ['%d']
        );
        self::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            'chada_travel_booking_restored',
            'admin',
            ['actor_user_id' => $chada_travel_actor_user_id]
        );
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_order_id): ?array {
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_order_id = %d LIMIT 1",
            $chada_travel_order_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * @param array<string, mixed> $chada_travel_booker Clean booker fields from CHADA_TRAVEL_Checkout_Validator.
     * @param string|null $chada_travel_policy_snapshot Server-built CHADA_TRAVEL_Policy_Snapshot::encode() JSON; always
     *                                            non-null for a genuinely new draft (the REST layer must have
     *                                            already confirmed the policy bundle is Ready before calling
     *                                            this - see CHADA_TRAVEL_Rest_Controller::handle_booker()).
     * @return array{order: array<string, mixed>, draft_token: string}
     */
    public static function create_draft(
        object $chada_travel_wpdb,
        array $chada_travel_booker,
        string $chada_travel_policy_version,
        string $chada_travel_currency,
        ?string $chada_travel_policy_snapshot = null
    ): array {
        $chada_travel_token_pair = self::generate_draft_token();
        $chada_travel_now        = self::now();
        $chada_travel_data       = [
            'chada_travel_booker_first_name'   => $chada_travel_booker['first_name'],
            'chada_travel_booker_last_name'    => $chada_travel_booker['last_name'],
            'chada_travel_booker_email'        => $chada_travel_booker['email'],
            'chada_travel_booker_mobile'       => $chada_travel_booker['mobile'],
            'chada_travel_booker_full_address' => $chada_travel_booker['address'] !== '' ? $chada_travel_booker['address'] : null,
            'chada_travel_privacy_accepted_at' => $chada_travel_now,
            'chada_travel_terms_accepted_at'   => $chada_travel_now,
            'chada_travel_policy_version'      => $chada_travel_policy_version,
            'chada_travel_policy_snapshot'     => $chada_travel_policy_snapshot,
            'chada_travel_currency'            => $chada_travel_currency,
            'chada_travel_subtotal'            => '0.00',
            'chada_travel_total'               => '0.00',
            'chada_travel_order_status'        => 'chada_travel_draft',
            'chada_travel_payment_status'      => 'chada_travel_unpaid',
            'chada_travel_checkout_stage'      => 2,
            'chada_travel_draft_token_hash'    => $chada_travel_token_pair['hash'],
            'chada_travel_created_at'          => $chada_travel_now,
            'chada_travel_updated_at'          => $chada_travel_now,
        ];
        $chada_travel_wpdb->insert(self::orders_table($chada_travel_wpdb), $chada_travel_data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s',
        ]);
        $chada_travel_order_id       = (int) $chada_travel_wpdb->insert_id;
        $chada_travel_data['chada_travel_order_id'] = $chada_travel_order_id;

        self::log_event($chada_travel_wpdb, $chada_travel_order_id, 'chada_travel_order_created', 'booker', [
            'checkout_stage' => 2,
        ]);
        if ($chada_travel_policy_snapshot !== null) {
            self::log_policy_consent_event($chada_travel_wpdb, $chada_travel_order_id, $chada_travel_policy_snapshot, ['privacy', 'terms']);
        }

        return ['order' => $chada_travel_data, 'draft_token' => $chada_travel_token_pair['token']];
    }

    /**
     * Logs a safe `chada_travel_policy_consent_recorded` audit event decoded from the just-written snapshot: policy
     * version, effective date, accepted policy keys, and snapshot schema version only - never the full snapshot
     * JSON, a full URL, or any customer tracking data. A no-op (never fatals) when $chada_travel_snapshot_json fails to
     * decode, since a malformed snapshot must never block the checkout write that already succeeded.
     *
     * @param list<string> $chada_travel_accepted_keys
     */
    private static function log_policy_consent_event(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        string $chada_travel_snapshot_json,
        array $chada_travel_accepted_keys
    ): void {
        $chada_travel_decoded = CHADA_TRAVEL_Policy_Snapshot::decode($chada_travel_snapshot_json);
        if ($chada_travel_decoded === null) {
            return;
        }
        self::log_event($chada_travel_wpdb, $chada_travel_order_id, 'chada_travel_policy_consent_recorded', 'booker', [
            'policy_version' => $chada_travel_decoded['policy_version'],
            'effective_date' => $chada_travel_decoded['effective_date'],
            'accepted'       => array_values($chada_travel_accepted_keys),
            'schema_version' => $chada_travel_decoded['schema_version'],
        ]);
    }

    /** True when at least one order (any status) has recorded consent for $chada_travel_policy_version. */
    public static function any_order_with_policy_version(object $chada_travel_wpdb, string $chada_travel_policy_version): bool {
        if ($chada_travel_policy_version === '') {
            return false;
        }
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT 1 FROM {$chada_travel_table} WHERE chada_travel_policy_version = %s LIMIT 1",
            $chada_travel_policy_version
        );
        return (bool) $chada_travel_wpdb->get_var($chada_travel_sql);
    }

    /** @return array<string, mixed>|null */
    public static function find_draft_by_token(object $chada_travel_wpdb, string $chada_travel_token): ?array {
        if ($chada_travel_token === '') {
            return null;
        }
        $chada_travel_hash = hash('sha256', $chada_travel_token);
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_draft_token_hash = %s AND chada_travel_order_status = %s LIMIT 1",
            $chada_travel_hash,
            'chada_travel_draft'
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Resolves the same opaque browser-session token as find_draft_by_token(), but at any order status. The
     * draft token hash is never cleared once assigned, so it continues to identify "this Booker's checkout
     * session" through Stage 4 payment and the Stage 5 confirmation, after the order has moved off chada_travel_draft.
     * Stage 1-3 handlers must keep using find_draft_by_token() (re-editing a submitted order is not allowed);
     * Stage 4/5 handlers that need to resolve the same session again after its first status change use this.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_draft_token(object $chada_travel_wpdb, string $chada_travel_token): ?array {
        if ($chada_travel_token === '') {
            return null;
        }
        $chada_travel_hash  = hash('sha256', $chada_travel_token);
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_draft_token_hash = %s LIMIT 1",
            $chada_travel_hash
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Pure inactivity-expiry decision for a Stage 1-3 draft: true only when the order is still `chada_travel_draft`
     * AND its last successful `chada_travel_updated_at` write is at or before `now - $chada_travel_expiry_hours` (UTC). Fails
     * closed (treated as expired) when `chada_travel_updated_at` is missing or unparseable. Always false for any order
     * status other than `chada_travel_draft`, so a caller may safely run this check against a Stage 4/5 session row
     * too - the result is always false once an order has legitimately left Draft, matching
     * CHADA_TRAVEL_Booking_Workflow settings' documented invariant.
     *
     * @param array<string, mixed> $chada_travel_order
     */
    public static function is_draft_expired(array $chada_travel_order, int $chada_travel_expiry_hours): bool {
        if (($chada_travel_order['chada_travel_order_status'] ?? '') !== 'chada_travel_draft') {
            return false;
        }
        $chada_travel_updated = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            (string) ($chada_travel_order['chada_travel_updated_at'] ?? ''),
            new \DateTimeZone('UTC')
        );
        if ($chada_travel_updated === false) {
            return true;
        }
        $chada_travel_cutoff = $chada_travel_updated->modify('+' . $chada_travel_expiry_hours . ' hours');
        return $chada_travel_cutoff <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @param array<string, mixed> $chada_travel_order  Current order row.
     * @param array<string, mixed> $chada_travel_booker Clean booker fields.
     * @param string|null $chada_travel_policy_version  Pass only for an existing active draft that predates the
     *                                            chada_travel_policy_snapshot column and is being granted its first
     *                                            snapshot now under re-consent (see
     *                                            CHADA_TRAVEL_Rest_Controller::handle_booker()); otherwise leave null
     *                                            so an order's existing immutable snapshot is never touched by
     *                                            a routine Booker Details edit.
     * @param string|null $chada_travel_policy_snapshot  Must be provided together with $chada_travel_policy_version.
     * @return array<string, mixed> Updated order row.
     */
    public static function update_booker(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_booker,
        ?string $chada_travel_policy_version = null,
        ?string $chada_travel_policy_snapshot = null
    ): array {
        $chada_travel_now  = self::now();
        $chada_travel_data = [
            'chada_travel_booker_first_name'   => $chada_travel_booker['first_name'],
            'chada_travel_booker_last_name'    => $chada_travel_booker['last_name'],
            'chada_travel_booker_email'        => $chada_travel_booker['email'],
            'chada_travel_booker_mobile'       => $chada_travel_booker['mobile'],
            'chada_travel_booker_full_address' => $chada_travel_booker['address'] !== '' ? $chada_travel_booker['address'] : null,
            'chada_travel_privacy_accepted_at' => $chada_travel_now,
            'chada_travel_terms_accepted_at'   => $chada_travel_now,
            'chada_travel_updated_at'          => $chada_travel_now,
        ];
        $chada_travel_formats = ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
        $chada_travel_granting_first_snapshot = $chada_travel_policy_version !== null && $chada_travel_policy_snapshot !== null;
        if ($chada_travel_granting_first_snapshot) {
            $chada_travel_data['chada_travel_policy_version']  = $chada_travel_policy_version;
            $chada_travel_data['chada_travel_policy_snapshot'] = $chada_travel_policy_snapshot;
            $chada_travel_formats[] = '%s';
            $chada_travel_formats[] = '%s';
        }
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            $chada_travel_formats,
            ['%d']
        );
        self::log_event($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'], 'chada_travel_booker_details_updated', 'booker');
        if ($chada_travel_granting_first_snapshot) {
            self::log_policy_consent_event(
                $chada_travel_wpdb,
                (int) $chada_travel_order['chada_travel_order_id'],
                (string) $chada_travel_policy_snapshot,
                ['privacy', 'terms']
            );
        }
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /**
     * Replaces all applicant/application rows for the order with the freshly validated Stage 2 set.
     *
     * @param array<string, mixed>       $chada_travel_order        Current order row.
     * @param list<array<string, mixed>> $chada_travel_applications Clean application rows.
     * @param array<string, mixed>       $chada_travel_totals       Result of CHADA_TRAVEL_Checkout_Validator::calculate_totals().
     * @return array{order: array<string, mixed>, applications: list<array<string, mixed>>}
     */
    public static function replace_applications(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_applications,
        array $chada_travel_totals
    ): array {
        $chada_travel_order_id           = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_applicants_table   = self::applicants_table($chada_travel_wpdb);
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_now                = self::now();

        $chada_travel_has_transactions = is_a($chada_travel_wpdb, 'wpdb');
        if ($chada_travel_has_transactions) {
            $chada_travel_wpdb->query('START TRANSACTION');
        }
        try {

            if ($chada_travel_wpdb->delete(
                $chada_travel_applications_table,
                ['chada_travel_order_id' => $chada_travel_order_id],
                ['%d']
            ) === false || $chada_travel_wpdb->delete(
                $chada_travel_applicants_table,
                ['chada_travel_order_id' => $chada_travel_order_id],
                ['%d']
            ) === false) {
                throw new \RuntimeException('Unable to replace application rows.');
            }

        $chada_travel_stored = [];
        foreach ($chada_travel_applications as $chada_travel_index => $chada_travel_application) {
            $chada_travel_line = $chada_travel_totals['lines'][$chada_travel_index];
            $chada_travel_applicant_reference = self::generate_reference('CHADA_TRAVEL-APT');
            $chada_travel_applicant_inserted = $chada_travel_wpdb->insert($chada_travel_applicants_table, [
                'chada_travel_order_id'            => $chada_travel_order_id,
                'chada_travel_applicant_reference' => $chada_travel_applicant_reference,
                'chada_travel_first_name'          => $chada_travel_application['first_name'],
                'chada_travel_last_name'           => $chada_travel_application['last_name'],
                // Always null here: Date of Birth is administrator-only (see
                // CHADA_TRAVEL_Order_Repository::update_applicant_details()) and is never accepted from a customer
                // submission, so a fresh applicant row created from checkout never carries one.
                'chada_travel_date_of_birth'       => null,
                'chada_travel_is_booker'           => $chada_travel_application['is_booker'] ? 1 : 0,
                'chada_travel_record_status'       => 'chada_travel_active',
                'chada_travel_created_at'          => $chada_travel_now,
                'chada_travel_updated_at'          => $chada_travel_now,
            ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']);
            if ($chada_travel_applicant_inserted === false) {
                throw new \RuntimeException('Unable to save applicant row.');
            }
            $chada_travel_applicant_id = (int) $chada_travel_wpdb->insert_id;

            $chada_travel_application_reference = self::generate_reference('CHADA_TRAVEL-APP');
            $chada_travel_application_inserted = $chada_travel_wpdb->insert($chada_travel_applications_table, [
                'chada_travel_application_reference'      => $chada_travel_application_reference,
                'chada_travel_order_id'                    => $chada_travel_order_id,
                'chada_travel_applicant_id'                => $chada_travel_applicant_id,
                // 0 when the country has not been synced into chada_travel_countries yet (e.g. a brand-new admin
                // country before its first CHADA_TRAVEL_Country_Repository::upsert()); the application still checks
                // out correctly, it simply has no document requirements to show until the country is synced.
                'chada_travel_country_id'                  => CHADA_TRAVEL_Country_Repository::find_id_by_code(
                    $chada_travel_wpdb,
                    $chada_travel_line['country_code']
                ),
                'chada_travel_country_code_snapshot'       => $chada_travel_line['country_code'],
                'chada_travel_country_name_snapshot'       => $chada_travel_line['country_name'],
                'chada_travel_processing_fee_snapshot'     => $chada_travel_line['processing_fee'],
                'chada_travel_currency_snapshot'           => $chada_travel_line['currency'],
                'chada_travel_checklist_version_snapshot'  => $chada_travel_line['checklist_version'],
                'chada_travel_target_travel_date'          => $chada_travel_application['target_travel_date'],
                'chada_travel_application_status'          => 'chada_travel_active',
                'chada_travel_file_status'                 => 'chada_travel_no_submitted_files',
                'chada_travel_created_at'                  => $chada_travel_now,
                'chada_travel_updated_at'                  => $chada_travel_now,
            ], ['%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            if ($chada_travel_application_inserted === false) {
                throw new \RuntimeException('Unable to save application row.');
            }

            $chada_travel_stored[] = array_merge($chada_travel_line, [
                'application_reference' => $chada_travel_application_reference,
                'applicant_reference'   => $chada_travel_applicant_reference,
                'is_booker'             => $chada_travel_application['is_booker'],
            ]);
        }

        $chada_travel_order_data = [
            'chada_travel_subtotal'       => $chada_travel_totals['subtotal'],
            'chada_travel_total'          => $chada_travel_totals['total'],
            'chada_travel_currency'       => $chada_travel_totals['currency'],
            'chada_travel_checkout_stage' => max(3, (int) $chada_travel_order['chada_travel_checkout_stage']),
            'chada_travel_updated_at'     => $chada_travel_now,
        ];
        $chada_travel_order_updated = $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_order_data,
            ['chada_travel_order_id' => $chada_travel_order_id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );
        if ($chada_travel_order_updated === false) {
            throw new \RuntimeException('Unable to update the order totals.');
        }

        self::log_event($chada_travel_wpdb, $chada_travel_order_id, 'chada_travel_applications_updated', 'booker', [
            'applications_count' => count($chada_travel_stored),
            'subtotal'           => $chada_travel_totals['subtotal'],
            'total'              => $chada_travel_totals['total'],
        ]);

        if ($chada_travel_has_transactions) {
            $chada_travel_wpdb->query('COMMIT');
        }

        return ['order' => array_merge($chada_travel_order, $chada_travel_order_data), 'applications' => $chada_travel_stored];
        } catch (\Throwable $chada_travel_error) {
            if ($chada_travel_has_transactions) {
                $chada_travel_wpdb->query('ROLLBACK');
            }
            throw $chada_travel_error;
        }
    }

    /**
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function record_review_consent(object $chada_travel_wpdb, array $chada_travel_order): array {
        if (!empty($chada_travel_order['chada_travel_cancellation_refund_accepted_at'])) {
            // Idempotent: a repeated successful Stage 3 request must never overwrite the original acceptance
            // timestamp or log a second, misleading consent-audit event for the same order.
            return $chada_travel_order;
        }
        $chada_travel_order_id = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_now      = self::now();
        $chada_travel_data     = [
            'chada_travel_cancellation_refund_accepted_at' => $chada_travel_now,
            // Unlocks Stage 4 payment-method viewing; selecting a method is what moves the order off chada_travel_draft.
            'chada_travel_checkout_stage' => max(4, (int) $chada_travel_order['chada_travel_checkout_stage']),
            'chada_travel_updated_at'     => $chada_travel_now,
        ];
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => $chada_travel_order_id],
            ['%s', '%d', '%s'],
            ['%d']
        );
        self::log_event($chada_travel_wpdb, $chada_travel_order_id, 'chada_travel_review_acknowledged', 'booker');
        $chada_travel_decoded = CHADA_TRAVEL_Policy_Snapshot::decode($chada_travel_order['chada_travel_policy_snapshot'] ?? null);
        if ($chada_travel_decoded !== null) {
            self::log_event($chada_travel_wpdb, $chada_travel_order_id, 'chada_travel_policy_consent_recorded', 'booker', [
                'policy_version' => $chada_travel_decoded['policy_version'],
                'effective_date' => $chada_travel_decoded['effective_date'],
                'accepted'       => ['cancellation_refund'],
                'schema_version' => $chada_travel_decoded['schema_version'],
            ]);
        }
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /** @return array<string, mixed>|null Same joined shape as get_applications() rows. */
    public static function find_application_by_id(object $chada_travel_wpdb, int $chada_travel_application_id): ?array {
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_applicants_table   = self::applicants_table($chada_travel_wpdb);
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            "SELECT a.*, p.chada_travel_first_name, p.chada_travel_last_name, p.chada_travel_date_of_birth, p.chada_travel_is_booker
             FROM {$chada_travel_applications_table} a
             INNER JOIN {$chada_travel_applicants_table} p ON p.chada_travel_applicant_id = a.chada_travel_applicant_id
             WHERE a.chada_travel_application_id = %d LIMIT 1",
            $chada_travel_application_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** Updates the aggregate document-collection status for one visa application. */
    public static function update_application_file_status(
        object $chada_travel_wpdb,
        int $chada_travel_application_id,
        string $chada_travel_file_status
    ): void {
        $chada_travel_wpdb->update(
            self::applications_table($chada_travel_wpdb),
            ['chada_travel_file_status' => $chada_travel_file_status, 'chada_travel_updated_at' => self::now()],
            ['chada_travel_application_id' => $chada_travel_application_id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /** @return list<array<string, mixed>> */
    public static function get_applications(object $chada_travel_wpdb, int $chada_travel_order_id): array {
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_applicants_table   = self::applicants_table($chada_travel_wpdb);
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            "SELECT a.*, p.chada_travel_first_name, p.chada_travel_last_name, p.chada_travel_date_of_birth, p.chada_travel_is_booker
             FROM {$chada_travel_applications_table} a
             INNER JOIN {$chada_travel_applicants_table} p ON p.chada_travel_applicant_id = a.chada_travel_applicant_id
             WHERE a.chada_travel_order_id = %d ORDER BY a.chada_travel_application_id ASC",
            $chada_travel_order_id
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /**
     * Administrator-only correction of one application's Date of Birth and Target Travel Date, updating both
     * the owning chada_travel_applicants row and this chada_travel_applications row together. The applicant_id is always
     * resolved from the application row itself (never trusted from the caller), and the application is looked
     * up scoped to $chada_travel_order_id, so an application_id belonging to a different order can never be edited.
     *
     * @return bool False when no matching application exists for this order (caller must treat as not-found).
     */
    public static function update_applicant_details(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        int $chada_travel_application_id,
        ?string $chada_travel_date_of_birth,
        string $chada_travel_target_travel_date
    ): bool {
        $chada_travel_applications_table = self::applications_table($chada_travel_wpdb);
        $chada_travel_applicants_table   = self::applicants_table($chada_travel_wpdb);
        $chada_travel_application = $chada_travel_wpdb->get_row($chada_travel_wpdb->prepare(
            "SELECT chada_travel_applicant_id FROM {$chada_travel_applications_table}
             WHERE chada_travel_application_id = %d AND chada_travel_order_id = %d",
            $chada_travel_application_id,
            $chada_travel_order_id
        ), ARRAY_A);
        if (!$chada_travel_application) {
            return false;
        }
        $chada_travel_applicant_id = (int) $chada_travel_application['chada_travel_applicant_id'];
        $chada_travel_now = self::now();

        $chada_travel_has_transactions = is_a($chada_travel_wpdb, 'wpdb');
        if ($chada_travel_has_transactions) {
            $chada_travel_wpdb->query('START TRANSACTION');
        }
        try {
            $chada_travel_applicant_updated = $chada_travel_wpdb->update(
                $chada_travel_applicants_table,
                ['chada_travel_date_of_birth' => $chada_travel_date_of_birth, 'chada_travel_updated_at' => $chada_travel_now],
                ['chada_travel_applicant_id' => $chada_travel_applicant_id],
                ['%s', '%s'],
                ['%d']
            );
            $chada_travel_application_updated = $chada_travel_wpdb->update(
                $chada_travel_applications_table,
                ['chada_travel_target_travel_date' => $chada_travel_target_travel_date, 'chada_travel_updated_at' => $chada_travel_now],
                ['chada_travel_application_id' => $chada_travel_application_id],
                ['%s', '%s'],
                ['%d']
            );
            if ($chada_travel_applicant_updated === false || $chada_travel_application_updated === false) {
                throw new \RuntimeException('Unable to update applicant details.');
            }
            if ($chada_travel_has_transactions) {
                $chada_travel_wpdb->query('COMMIT');
            }
            return true;
        } catch (\Throwable $chada_travel_error) {
            if ($chada_travel_has_transactions) {
                $chada_travel_wpdb->query('ROLLBACK');
            }
            throw $chada_travel_error;
        }
    }

    /** @return array{token: string, hash: string} Raw token is returned once and never stored. */
    public static function generate_upload_token(): array {
        $chada_travel_raw = bin2hex(random_bytes(32));
        return ['token' => $chada_travel_raw, 'hash' => hash('sha256', $chada_travel_raw)];
    }

    /**
     * Mints a fresh hashed/expiring visa-document upload token for a paid order. Overwriting the single
     * chada_travel_upload_token_hash column implicitly revokes any previously issued token for this order.
     *
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array{order: array<string, mixed>, token: string}
     */
    public static function issue_upload_token(object $chada_travel_wpdb, array $chada_travel_order, int $chada_travel_hours): array {
        $chada_travel_token_pair = self::generate_upload_token();
        $chada_travel_expires_at = self::hours_from_now($chada_travel_hours);
        $chada_travel_now        = self::now();
        $chada_travel_data       = [
            'chada_travel_upload_token_hash'       => $chada_travel_token_pair['hash'],
            'chada_travel_upload_token_expires_at' => $chada_travel_expires_at,
            'chada_travel_updated_at'              => $chada_travel_now,
        ];
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            ['%s', '%s', '%s'],
            ['%d']
        );
        self::log_event($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'], 'chada_travel_upload_token_issued', 'system');
        return ['order' => array_merge($chada_travel_order, $chada_travel_data), 'token' => $chada_travel_token_pair['token']];
    }

    /**
     * @param array<string, mixed> $chada_travel_order Current order row.
     * @return array<string, mixed> Updated order row.
     */
    public static function revoke_upload_token(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        string $chada_travel_actor_type = 'admin',
        ?int $chada_travel_actor_user_id = null
    ): array {
        $chada_travel_data = [
            'chada_travel_upload_token_hash'       => null,
            'chada_travel_upload_token_expires_at' => null,
            'chada_travel_updated_at'              => self::now(),
        ];
        $chada_travel_wpdb->update(
            self::orders_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_order_id' => (int) $chada_travel_order['chada_travel_order_id']],
            ['%s', '%s', '%s'],
            ['%d']
        );
        self::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            'chada_travel_upload_link_revoked',
            $chada_travel_actor_type,
            $chada_travel_actor_user_id !== null ? ['actor_user_id' => $chada_travel_actor_user_id] : []
        );
        return array_merge($chada_travel_order, $chada_travel_data);
    }

    /**
     * Resolves an unexpired visa-document upload token to its order, without disclosing why a lookup fails.
     * Also re-checks the order is currently paid: if a payment is later refunded/reversed, the token stops
     * granting access even before its stored expiry.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_valid_upload_token(object $chada_travel_wpdb, string $chada_travel_token): ?array {
        if ($chada_travel_token === '') {
            return null;
        }
        $chada_travel_hash  = hash('sha256', $chada_travel_token);
        $chada_travel_table = self::orders_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_upload_token_hash = %s LIMIT 1",
            $chada_travel_hash
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        if (!$chada_travel_row || empty($chada_travel_row['chada_travel_upload_token_expires_at'])) {
            return null;
        }
        if (strtotime((string) $chada_travel_row['chada_travel_upload_token_expires_at']) < time()) {
            return null;
        }
        if ((string) $chada_travel_row['chada_travel_payment_status'] !== 'chada_travel_paid') {
            return null;
        }
        return $chada_travel_row;
    }

    private static function hours_from_now(int $chada_travel_hours): string {
        $chada_travel_timestamp = time() + max(1, $chada_travel_hours) * 3600;
        return gmdate('Y-m-d H:i:s', $chada_travel_timestamp);
    }

    public const ACTIVITY_PER_PAGE = 20;

    /**
     * Real server-side search/filter/paginate for one booking's Activity tab: every event tied to the order
     * itself or to any of its applications, newest first.
     *
     * @param list<int> $chada_travel_application_ids
     * @return list<array<string, mixed>>
     */
    public static function search_order_events(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        array $chada_travel_application_ids,
        string $chada_travel_event_type,
        string $chada_travel_actor_type,
        int $chada_travel_page = 1,
        int $chada_travel_per_page = self::ACTIVITY_PER_PAGE
    ): array {
        $chada_travel_where = self::order_events_where(
            $chada_travel_order_id,
            $chada_travel_application_ids,
            $chada_travel_event_type,
            $chada_travel_actor_type
        );
        $chada_travel_page     = max(1, $chada_travel_page);
        $chada_travel_per_page = max(1, min(100, $chada_travel_per_page));
        $chada_travel_offset   = ($chada_travel_page - 1) * $chada_travel_per_page;
        $chada_travel_sql = 'SELECT * FROM ' . self::events_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql']
            . ' ORDER BY chada_travel_created_at DESC, chada_travel_event_id DESC LIMIT %d OFFSET %d';
        $chada_travel_params = array_merge($chada_travel_where['params'], [$chada_travel_per_page, $chada_travel_offset]);
        return $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_params), ARRAY_A) ?: [];
    }

    /** @param list<int> $chada_travel_application_ids */
    public static function count_order_events(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        array $chada_travel_application_ids,
        string $chada_travel_event_type,
        string $chada_travel_actor_type
    ): int {
        $chada_travel_where = self::order_events_where(
            $chada_travel_order_id,
            $chada_travel_application_ids,
            $chada_travel_event_type,
            $chada_travel_actor_type
        );
        $chada_travel_sql = 'SELECT COUNT(*) FROM ' . self::events_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql'];
        return (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']));
    }

    /**
     * @param list<int> $chada_travel_application_ids
     * @return array{sql: string, params: list<mixed>}
     */
    private static function order_events_where(
        int $chada_travel_order_id,
        array $chada_travel_application_ids,
        string $chada_travel_event_type,
        string $chada_travel_actor_type
    ): array {
        $chada_travel_application_ids = array_map('intval', $chada_travel_application_ids);
        if ($chada_travel_application_ids) {
            $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_application_ids), '%d'));
            $chada_travel_scope = "(chada_travel_order_id = %d OR chada_travel_application_id IN ($chada_travel_placeholders))";
            $chada_travel_params = array_merge([$chada_travel_order_id], $chada_travel_application_ids);
        } else {
            $chada_travel_scope  = 'chada_travel_order_id = %d';
            $chada_travel_params = [$chada_travel_order_id];
        }
        $chada_travel_conditions = [$chada_travel_scope];
        if ($chada_travel_event_type !== '') {
            $chada_travel_conditions[] = 'chada_travel_event_type = %s';
            $chada_travel_params[]     = $chada_travel_event_type;
        }
        if ($chada_travel_actor_type !== '') {
            $chada_travel_conditions[] = 'chada_travel_actor_type = %s';
            $chada_travel_params[]     = $chada_travel_actor_type;
        }
        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /** @return list<string> Distinct event types already recorded for this order, for the Activity filter. */
    public static function get_order_event_types(object $chada_travel_wpdb, int $chada_travel_order_id): array {
        $chada_travel_table = self::events_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT DISTINCT chada_travel_event_type FROM {$chada_travel_table} WHERE chada_travel_order_id = %d "
                . 'ORDER BY chada_travel_event_type ASC',
            $chada_travel_order_id
        );
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        return array_values(array_map(
            static fn(array $chada_travel_row): string => (string) $chada_travel_row['chada_travel_event_type'],
            $chada_travel_rows
        ));
    }

    /** @param array<string, mixed> $chada_travel_meta Safe metadata; never secrets or document content. */
    public static function log_event(
        object $chada_travel_wpdb,
        ?int $chada_travel_order_id,
        string $chada_travel_event_type,
        string $chada_travel_actor_type = 'system',
        array $chada_travel_meta = [],
        ?string $chada_travel_message = null
    ): void {
        $chada_travel_wpdb->insert(self::events_table($chada_travel_wpdb), [
            'chada_travel_order_id'      => $chada_travel_order_id,
            'chada_travel_record_type'   => 'order',
            'chada_travel_record_id'     => $chada_travel_order_id,
            'chada_travel_event_type'    => $chada_travel_event_type,
            'chada_travel_event_message' => $chada_travel_message,
            'chada_travel_event_meta'    => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_meta) : json_encode($chada_travel_meta),
            'chada_travel_actor_type'    => $chada_travel_actor_type,
            'chada_travel_created_at'    => self::now(),
        ], ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);
    }
}
