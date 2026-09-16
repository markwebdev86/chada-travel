<?php
/** Persistence and claiming helpers for the plugin-owned email queue. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Email_Queue_Repository {
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const MAX_ATTEMPTS = 5;
    public const LEASE_SECONDS = 600;

    public static function table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_email_queue';
    }

    /** @param array<string, mixed> $chada_travel_context */
    public static function enqueue(
        object $chada_travel_wpdb,
        string $chada_travel_event,
        int $chada_travel_payment_id,
        string $chada_travel_audience,
        int $chada_travel_country_id,
        array $chada_travel_context
    ): int {
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_inserted = $chada_travel_wpdb->insert(self::table($chada_travel_wpdb), [
            'chada_travel_event' => $chada_travel_event,
            'chada_travel_payment_id' => $chada_travel_payment_id,
            'chada_travel_audience' => $chada_travel_audience,
            'chada_travel_country_id' => $chada_travel_country_id,
            'chada_travel_context' => function_exists('wp_json_encode') ? wp_json_encode($chada_travel_context) : json_encode($chada_travel_context),
            'chada_travel_status' => self::STATUS_PENDING,
            'chada_travel_attempts' => 0,
            'chada_travel_available_at' => $chada_travel_now,
            'chada_travel_created_at' => $chada_travel_now,
            'chada_travel_updated_at' => $chada_travel_now,
        ], ['%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']);
        return $chada_travel_inserted ? (int) $chada_travel_wpdb->insert_id : 0;
    }

    /** @return list<array<string, mixed>> */
    public static function claim_due(object $chada_travel_wpdb, int $chada_travel_limit = 10): array {
        $chada_travel_limit = max(1, min(50, $chada_travel_limit));
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_expired = gmdate('Y-m-d H:i:s', time() - self::LEASE_SECONDS);
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_select_query = "SELECT * FROM {$chada_travel_table} "
            . 'WHERE (chada_travel_status = %s AND chada_travel_available_at <= %s) '
            . 'OR (chada_travel_status = %s AND chada_travel_claimed_at < %s) '
            . 'ORDER BY chada_travel_id ASC LIMIT %d';
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            $chada_travel_select_query,
            self::STATUS_PENDING, $chada_travel_now, self::STATUS_PROCESSING, $chada_travel_expired, $chada_travel_limit
        );
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        $chada_travel_claimed = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_id = (int) ($chada_travel_row['chada_travel_id'] ?? 0);
            $chada_travel_claim_query = "UPDATE {$chada_travel_table} "
                . 'SET chada_travel_status = %s, chada_travel_attempts = chada_travel_attempts + 1, '
                . 'chada_travel_claimed_at = %s, chada_travel_updated_at = %s '
                . 'WHERE chada_travel_id = %d AND (chada_travel_status = %s '
                . 'OR (chada_travel_status = %s AND chada_travel_claimed_at < %s))';
            if ($chada_travel_id <= 0 || !$chada_travel_wpdb->query($chada_travel_wpdb->prepare(
                $chada_travel_claim_query,
                self::STATUS_PROCESSING,
                $chada_travel_now,
                $chada_travel_now,
                $chada_travel_id,
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                $chada_travel_expired
            ))) {
                continue;
            }
            $chada_travel_row['chada_travel_attempts'] = (int) ($chada_travel_row['chada_travel_attempts'] ?? 0) + 1;
            $chada_travel_claimed[] = $chada_travel_row;
        }
        return $chada_travel_claimed;
    }

    public static function complete(object $chada_travel_wpdb, int $chada_travel_id): void {
        $chada_travel_wpdb->update(self::table($chada_travel_wpdb), [
            'chada_travel_status' => self::STATUS_COMPLETED,
            'chada_travel_claimed_at' => null,
            'chada_travel_updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['chada_travel_id' => $chada_travel_id], ['%s', '%s', '%s'], ['%d']);
    }

    public static function fail(object $chada_travel_wpdb, int $chada_travel_id, int $chada_travel_attempts, string $chada_travel_code = ''): void {
        $chada_travel_terminal = $chada_travel_attempts >= self::MAX_ATTEMPTS;
        $chada_travel_backoff = [1 => 60, 2 => 300, 3 => 900, 4 => 3600];
        $chada_travel_status = $chada_travel_terminal ? self::STATUS_FAILED : self::STATUS_PENDING;
        $chada_travel_delay = $chada_travel_backoff[$chada_travel_attempts] ?? 3600;
        $chada_travel_available = gmdate('Y-m-d H:i:s', time() + $chada_travel_delay);
        $chada_travel_wpdb->update(self::table($chada_travel_wpdb), [
            'chada_travel_status' => $chada_travel_status,
            'chada_travel_available_at' => $chada_travel_available,
            'chada_travel_claimed_at' => null,
            'chada_travel_last_error_code' => substr($chada_travel_code, 0, 30),
            'chada_travel_updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['chada_travel_id' => $chada_travel_id], ['%s', '%s', '%s', '%s', '%s'], ['%d']);
    }

    public static function has_active_items(object $chada_travel_wpdb): bool {
        $chada_travel_table = self::table($chada_travel_wpdb);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- code-controlled table and status values.
        return (int) $chada_travel_wpdb->get_var(
            "SELECT COUNT(*) FROM {$chada_travel_table} WHERE chada_travel_status IN ('pending','processing')"
        ) > 0;
    }
}
