<?php
/**
 * wpdb-backed persistence for Bank/Digital Wallet payment attempts, proof tokens, and confirmed transaction IDs.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Repository {
    /** Maps each payments column to its $wpdb format specifier so callers never need to repeat it. */
    private const COLUMN_FORMATS = [
        'chada_travel_payment_id'                  => '%d',
        'chada_travel_payment_reference'           => '%s',
        'chada_travel_transaction_id'              => '%s',
        'chada_travel_order_id'                    => '%d',
        'chada_travel_payment_method'              => '%s',
        'chada_travel_payment_provider'            => '%s',
        'chada_travel_provider_transaction_id'     => '%s',
        'chada_travel_provider_event_id'           => '%s',
        'chada_travel_digital_wallet_reference_no' => '%s',
        'chada_travel_amount'                      => '%s',
        'chada_travel_currency'                    => '%s',
        'chada_travel_payment_status'              => '%s',
        'chada_travel_proof_attachment_id'         => '%d',
        'chada_travel_proof_token_hash'            => '%s',
        'chada_travel_proof_token_expires_at'      => '%s',
        'chada_travel_payment_details_snapshot'    => '%s',
        'chada_travel_submitted_at'                => '%s',
        'chada_travel_transaction_id_generated_at' => '%s',
        'chada_travel_verified_by_user_id'         => '%d',
        'chada_travel_verified_at'                 => '%s',
        'chada_travel_rejection_reason'            => '%s',
        'chada_travel_failure_code'                => '%s',
        'chada_travel_failure_message'             => '%s',
        'chada_travel_paid_at'                      => '%s',
        'chada_travel_created_at'                  => '%s',
        'chada_travel_updated_at'                  => '%s',
    ];

    public static function payments_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_payments';
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $chada_travel_data
     * @return list<string>
     */
    private static function formats_for(array $chada_travel_data): array {
        return array_map(
            static fn(string $chada_travel_column): string => self::COLUMN_FORMATS[$chada_travel_column] ?? '%s',
            array_keys($chada_travel_data)
        );
    }

    public static function generate_payment_reference(): string {
        return 'CHADA_TRAVEL-PAY-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /** @return array{token: string, hash: string} Raw token is returned once and never stored. */
    public static function generate_proof_token(): array {
        $chada_travel_raw = bin2hex(random_bytes(32));
        return ['token' => $chada_travel_raw, 'hash' => hash('sha256', $chada_travel_raw)];
    }

    /**
     * @param array<string, mixed> $chada_travel_order Parent order row supplying the amount/currency snapshot.
     * @param array<string, mixed> $chada_travel_extra Additional columns for the initial row.
     * @return array<string, mixed> The inserted payment row.
     */
    public static function create(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        string $chada_travel_method,
        string $chada_travel_status,
        array $chada_travel_extra = []
    ): array {
        $chada_travel_now  = self::now();
        $chada_travel_data = array_merge([
            'chada_travel_payment_reference' => self::generate_payment_reference(),
            'chada_travel_order_id'          => (int) $chada_travel_order['chada_travel_order_id'],
            'chada_travel_payment_method'    => $chada_travel_method,
            'chada_travel_amount'            => $chada_travel_order['chada_travel_total'],
            'chada_travel_currency'          => $chada_travel_order['chada_travel_currency'],
            'chada_travel_payment_status'    => $chada_travel_status,
            'chada_travel_created_at'        => $chada_travel_now,
            'chada_travel_updated_at'        => $chada_travel_now,
        ], $chada_travel_extra);
        $chada_travel_wpdb->insert(self::payments_table($chada_travel_wpdb), $chada_travel_data, self::formats_for($chada_travel_data));
        $chada_travel_data['chada_travel_payment_id'] = (int) $chada_travel_wpdb->insert_id;
        return $chada_travel_data;
    }

    /**
     * @param array<string, mixed> $chada_travel_payment Current payment row.
     * @param array<string, mixed> $chada_travel_data    Columns to update; chada_travel_updated_at is always refreshed.
     * @return array<string, mixed> The full updated payment row.
     */
    public static function update(object $chada_travel_wpdb, array $chada_travel_payment, array $chada_travel_data): array {
        $chada_travel_data['chada_travel_updated_at'] = self::now();
        $chada_travel_wpdb->update(
            self::payments_table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_payment_id' => (int) $chada_travel_payment['chada_travel_payment_id']],
            self::formats_for($chada_travel_data),
            ['%d']
        );
        return array_merge($chada_travel_payment, $chada_travel_data);
    }

    /**
     * Returns the most recent payment attempt for the order, if any.
     *
     * @return array<string, mixed>|null
     */
    public static function get_active_payment(object $chada_travel_wpdb, int $chada_travel_order_id): ?array {
        $chada_travel_table = self::payments_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_order_id = %d",
            $chada_travel_order_id
        );
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        if (!$chada_travel_rows) {
            return null;
        }
        usort($chada_travel_rows, static fn(array $chada_travel_a, array $chada_travel_b): int =>
            (int) $chada_travel_b['chada_travel_payment_id'] <=> (int) $chada_travel_a['chada_travel_payment_id']);
        return $chada_travel_rows[0];
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_payment_id): ?array {
        $chada_travel_table = self::payments_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_payment_id = %d LIMIT 1",
            $chada_travel_payment_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Resolves an unexpired Bank proof link token to its payment attempt without disclosing why a lookup fails.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_valid_proof_token(object $chada_travel_wpdb, string $chada_travel_token): ?array {
        if ($chada_travel_token === '') {
            return null;
        }
        $chada_travel_hash  = hash('sha256', $chada_travel_token);
        $chada_travel_table = self::payments_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_proof_token_hash = %s"
                . " AND chada_travel_payment_method = 'chada_travel_bank'"
                . " AND chada_travel_payment_status = 'chada_travel_awaiting_proof' LIMIT 1",
            $chada_travel_hash
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        if (!$chada_travel_row || empty($chada_travel_row['chada_travel_proof_token_expires_at'])) {
            return null;
        }
        if (strtotime((string) $chada_travel_row['chada_travel_proof_token_expires_at']) < time()) {
            return null;
        }
        return $chada_travel_row;
    }

    /**
     * Resolves a payment by its stored provider transaction ID for an extension-owned payment handler.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_provider_transaction_id(
        object $chada_travel_wpdb,
        string $chada_travel_provider_transaction_id
    ): ?array {
        if ($chada_travel_provider_transaction_id === '') {
            return null;
        }
        $chada_travel_table = self::payments_table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_provider_transaction_id = %s LIMIT 1",
            $chada_travel_provider_transaction_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * @param list<string> $chada_travel_statuses
     * @return list<array<string, mixed>> Payments in the given statuses, most recently submitted first.
     */
    public static function find_by_statuses(object $chada_travel_wpdb, array $chada_travel_statuses): array {
        $chada_travel_table = self::payments_table($chada_travel_wpdb);
        $chada_travel_rows  = [];
        foreach ($chada_travel_statuses as $chada_travel_status) {
            $chada_travel_sql = $chada_travel_wpdb->prepare(
                "SELECT * FROM {$chada_travel_table} WHERE chada_travel_payment_status = %s",
                $chada_travel_status
            );
            array_push($chada_travel_rows, ...($chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: []));
        }
        usort($chada_travel_rows, static fn(array $chada_travel_a, array $chada_travel_b): int =>
            strcmp((string) ($chada_travel_b['chada_travel_submitted_at'] ?? $chada_travel_b['chada_travel_created_at']),
                (string) ($chada_travel_a['chada_travel_submitted_at'] ?? $chada_travel_a['chada_travel_created_at'])));
        return $chada_travel_rows;
    }

    public const REVIEW_QUEUE_PER_PAGE = 20;

    /**
     * Real server-side search/filter/paginate for the standalone Payment Review work queue: manual Bank/Digital
     * Wallet payments in chada_travel_awaiting_verification only. Joined to the parent order for Booker/Booking ID search.
     *
     * @return list<array<string, mixed>> Each row is a payment joined with chada_travel_booking_id/booker fields.
     */
    public static function search_review_queue(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_method,
        int $chada_travel_page = 1,
        int $chada_travel_per_page = self::REVIEW_QUEUE_PER_PAGE
    ): array {
        $chada_travel_where = self::review_queue_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_method);
        $chada_travel_page     = max(1, $chada_travel_page);
        $chada_travel_per_page = max(1, min(100, $chada_travel_per_page));
        $chada_travel_offset   = ($chada_travel_page - 1) * $chada_travel_per_page;

        $chada_travel_orders_table = $chada_travel_wpdb->prefix . 'chada_travel_orders';
        $chada_travel_sql = 'SELECT pay.*, o.chada_travel_booking_id, o.chada_travel_booker_first_name, o.chada_travel_booker_last_name,
                o.chada_travel_booker_email
            FROM ' . self::payments_table($chada_travel_wpdb) . ' pay
            INNER JOIN ' . $chada_travel_orders_table . ' o ON o.chada_travel_order_id = pay.chada_travel_order_id
            WHERE ' . $chada_travel_where['sql'] . '
            ORDER BY pay.chada_travel_submitted_at ASC, pay.chada_travel_payment_id ASC
            LIMIT %d OFFSET %d';
        $chada_travel_params = array_merge($chada_travel_where['params'], [$chada_travel_per_page, $chada_travel_offset]);
        return $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_params), ARRAY_A) ?: [];
    }

    /** Total queue count matching the same filters as search_review_queue(), for pagination. */
    public static function count_review_queue(object $chada_travel_wpdb, string $chada_travel_search, string $chada_travel_method): int {
        $chada_travel_where = self::review_queue_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_method);
        $chada_travel_orders_table = $chada_travel_wpdb->prefix . 'chada_travel_orders';
        $chada_travel_sql = 'SELECT COUNT(*)
            FROM ' . self::payments_table($chada_travel_wpdb) . ' pay
            INNER JOIN ' . $chada_travel_orders_table . ' o ON o.chada_travel_order_id = pay.chada_travel_order_id
            WHERE ' . $chada_travel_where['sql'];
        if (!$chada_travel_where['params']) {
            return (int) $chada_travel_wpdb->get_var($chada_travel_sql);
        }
        return (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']));
    }

    /** @return array{sql: string, params: list<mixed>} */
    private static function review_queue_where(object $chada_travel_wpdb, string $chada_travel_search, string $chada_travel_method): array {
        $chada_travel_conditions = ['pay.chada_travel_payment_status = %s'];
        $chada_travel_params     = ['chada_travel_awaiting_verification'];

        if ($chada_travel_method !== '') {
            $chada_travel_conditions[] = 'pay.chada_travel_payment_method = %s';
            $chada_travel_params[]     = $chada_travel_method;
        }
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = '(o.chada_travel_booking_id LIKE %s OR o.chada_travel_booker_first_name LIKE %s
                OR o.chada_travel_booker_last_name LIKE %s OR pay.chada_travel_payment_reference LIKE %s
                OR pay.chada_travel_digital_wallet_reference_no LIKE %s)';
            array_push($chada_travel_params, ...array_fill(0, 5, $chada_travel_like));
        }

        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /**
     * Returns the most recently created `chada_travel_events` row of the given event type across every payment (e.g.
     * the Email tab's Delivery Status "Last attempted plugin email"), or null when none exists yet.
     *
     * @return array<string, mixed>|null
     */
    public static function find_latest_event_by_type(object $chada_travel_wpdb, string $chada_travel_event_type): ?array {
        $chada_travel_table = $chada_travel_wpdb->prefix . 'chada_travel_events';
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_event_type = %s "
                . 'ORDER BY chada_travel_created_at DESC, chada_travel_event_id DESC LIMIT 1',
            $chada_travel_event_type
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** @param array<string, mixed> $chada_travel_meta */
    public static function log_event(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        int $chada_travel_payment_id,
        string $chada_travel_event_type,
        string $chada_travel_actor_type = 'system',
        array $chada_travel_meta = [],
        ?int $chada_travel_actor_user_id = null
    ): void {
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_order_id'      => $chada_travel_order_id,
            'chada_travel_record_type'   => 'payment',
            'chada_travel_record_id'     => $chada_travel_payment_id,
            'chada_travel_event_type'    => $chada_travel_event_type,
            'chada_travel_event_meta'    => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_meta) : json_encode($chada_travel_meta),
            'chada_travel_actor_user_id' => $chada_travel_actor_user_id,
            'chada_travel_actor_type'    => $chada_travel_actor_type,
            'chada_travel_created_at'    => self::now(),
        ], ['%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
