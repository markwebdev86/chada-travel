<?php
/** Tour slug normalization, collision handling, and schema backfill. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Slug_Service {
    public const MAX_LENGTH = 200;

    /** Returns a WordPress-compatible, bounded Tour slug. */
    public static function sanitize(string $chada_travel_value): string {
        $chada_travel_value = trim($chada_travel_value);
        $chada_travel_slug = function_exists('sanitize_title')
            ? sanitize_title($chada_travel_value) : self::fallback_slug($chada_travel_value);
        return self::truncate(trim((string) $chada_travel_slug, '-'));
    }

    /**
     * Returns a unique slug from a base value and an existing case-insensitive slug map.
     *
     * @param array<string, bool> $chada_travel_used
     */
    public static function unique(string $chada_travel_base, array $chada_travel_used): string {
        $chada_travel_base = self::sanitize($chada_travel_base);
        if ($chada_travel_base === '') {
            $chada_travel_base = 'tour';
        }
        $chada_travel_used = array_fill_keys(array_map('strtolower', array_keys($chada_travel_used)), true);
        $chada_travel_candidate = $chada_travel_base;
        $chada_travel_suffix = 1;
        while (isset($chada_travel_used[strtolower($chada_travel_candidate)])) {
            $chada_travel_suffix++;
            $chada_travel_suffix_text = '-' . $chada_travel_suffix;
            $chada_travel_candidate = self::truncate(
                $chada_travel_base,
                self::MAX_LENGTH - strlen($chada_travel_suffix_text)
            ) . $chada_travel_suffix_text;
        }
        return $chada_travel_candidate;
    }

    /**
     * Returns whether a slug is used by another Tour.
     *
     * @param list<array<string, mixed>> $chada_travel_existing_tours
     */
    public static function exists(array $chada_travel_existing_tours, string $chada_travel_slug, int $chada_travel_current_id = 0): bool {
        $chada_travel_slug = strtolower($chada_travel_slug);
        foreach ($chada_travel_existing_tours as $chada_travel_tour) {
            if ((int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0) === $chada_travel_current_id) {
                continue;
            }
            if (strtolower((string) ($chada_travel_tour['chada_travel_tour_slug'] ?? '')) === $chada_travel_slug) {
                return true;
            }
        }
        return false;
    }

    /** Adds a temporary nullable column before dbDelta processes an upgrade with existing rows. */
    public static function prepare_existing_schema(object $chada_travel_wpdb): void {
        if (!method_exists($chada_travel_wpdb, 'get_var') || !method_exists($chada_travel_wpdb, 'query')) {
            return;
        }
        // @phpstan-ignore-next-line
        $chada_travel_table = $chada_travel_wpdb->prefix . 'chada_travel_tours';
        // @phpstan-ignore-next-line
        $chada_travel_exists = $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare('SHOW TABLES LIKE %s', $chada_travel_table));
        if ($chada_travel_exists !== $chada_travel_table) {
            return;
        }
        $chada_travel_column = $chada_travel_wpdb->get_var("SHOW COLUMNS FROM {$chada_travel_table} LIKE 'chada_travel_tour_slug'");
        if (!$chada_travel_column) {
            $chada_travel_wpdb->query(
                "ALTER TABLE {$chada_travel_table} ADD chada_travel_tour_slug varchar(200) NULL AFTER chada_travel_tour_name"
            );
        }
        self::backfill($chada_travel_wpdb);
    }

    /** Finalizes the column shape and unique index after the main installer schema pass. */
    public static function finalize_schema(object $chada_travel_wpdb): void {
        if (!method_exists($chada_travel_wpdb, 'get_var') || !method_exists($chada_travel_wpdb, 'query')) {
            return;
        }
        // @phpstan-ignore-next-line
        $chada_travel_table = $chada_travel_wpdb->prefix . 'chada_travel_tours';
        // @phpstan-ignore-next-line
        $chada_travel_exists = $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare('SHOW TABLES LIKE %s', $chada_travel_table));
        if ($chada_travel_exists !== $chada_travel_table) {
            return;
        }
        self::backfill($chada_travel_wpdb);
        $chada_travel_wpdb->query("ALTER TABLE {$chada_travel_table} MODIFY chada_travel_tour_slug varchar(200) NOT NULL");
        $chada_travel_index = $chada_travel_wpdb->get_var("SHOW INDEX FROM {$chada_travel_table} WHERE Key_name = 'chada_travel_tour_slug'");
        if (!$chada_travel_index) {
            $chada_travel_wpdb->query("ALTER TABLE {$chada_travel_table} ADD UNIQUE KEY chada_travel_tour_slug (chada_travel_tour_slug)");
        }
    }

    /** Backfills missing or duplicate slugs while preserving row identity and all other fields. */
    public static function backfill(object $chada_travel_wpdb): void {
        if (!method_exists($chada_travel_wpdb, 'get_results') || !method_exists($chada_travel_wpdb, 'update')) {
            return;
        }
        // @phpstan-ignore-next-line
        $chada_travel_table = $chada_travel_wpdb->prefix . 'chada_travel_tours';
        $chada_travel_rows = $chada_travel_wpdb->get_results(
            "SELECT chada_travel_tour_id, chada_travel_tour_name, chada_travel_tour_slug "
                . "FROM {$chada_travel_table} ORDER BY chada_travel_tour_id ASC",
            ARRAY_A
        ) ?: [];
        $chada_travel_used = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_current = self::sanitize((string) ($chada_travel_row['chada_travel_tour_slug'] ?? ''));
            $chada_travel_base = $chada_travel_current !== '' ? $chada_travel_current : (string) ($chada_travel_row['chada_travel_tour_name'] ?? '');
            $chada_travel_slug = self::unique($chada_travel_base, $chada_travel_used);
            $chada_travel_used[$chada_travel_slug] = true;
            if ((string) ($chada_travel_row['chada_travel_tour_slug'] ?? '') !== $chada_travel_slug) {
                $chada_travel_wpdb->update(
                    $chada_travel_table,
                    ['chada_travel_tour_slug' => $chada_travel_slug],
                    ['chada_travel_tour_id' => (int) $chada_travel_row['chada_travel_tour_id']],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    private static function fallback_slug(string $chada_travel_value): string {
        $chada_travel_slug = strtolower(trim($chada_travel_value));
        $chada_travel_slug = preg_replace('/[^a-z0-9]+/', '-', $chada_travel_slug) ?? '';
        return trim($chada_travel_slug, '-');
    }

    private static function truncate(string $chada_travel_value, int $chada_travel_length = self::MAX_LENGTH): string {
        return function_exists('mb_substr')
            ? mb_substr($chada_travel_value, 0, $chada_travel_length, 'UTF-8') : substr($chada_travel_value, 0, $chada_travel_length);
    }
}
