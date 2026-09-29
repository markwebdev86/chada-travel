<?php
/**
 * wpdb-backed mirror of the `chada_travel_country_fees` option into the `chada_travel_countries` table, giving each country a
 * stable numeric ID that `chada_travel_requirements` and `chada_travel_applications.chada_travel_country_id` can reference.
 *
 * The option remains the single source of truth for Stage 2 checkout country/fee data
 * (see CHADA_TRAVEL_Checkout_Validator::get_active_countries()); this table only exists so document requirements have a
 * real foreign key to attach to instead of the placeholder chada_travel_country_id = 0 used before Phase 4.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Country_Repository {
    public static function table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_countries';
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_country_id): ?array {
        if ($chada_travel_country_id <= 0) {
            return null;
        }
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_country_id = %d LIMIT 1",
            $chada_travel_country_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function find_by_code(object $chada_travel_wpdb, string $chada_travel_code): ?array {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_country_code = %s LIMIT 1",
            strtoupper($chada_travel_code)
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** Returns 0 when the country has not been synced into the table yet. */
    public static function find_id_by_code(object $chada_travel_wpdb, string $chada_travel_code): int {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT chada_travel_country_id FROM {$chada_travel_table} WHERE chada_travel_country_code = %s LIMIT 1",
            $chada_travel_code
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ? (int) $chada_travel_row['chada_travel_country_id'] : 0;
    }

    /**
     * Inserts or updates the row matching this country's ISO code and returns its ID.
     *
     * @param array<string, mixed> $chada_travel_country Row shaped like a `chada_travel_country_fees` entry
     *     (code/name/processing_fee/currency/checklist_version).
     */
    public static function upsert(object $chada_travel_wpdb, array $chada_travel_country): int {
        $chada_travel_code = strtoupper((string) ($chada_travel_country['code'] ?? ''));
        if ($chada_travel_code === '') {
            return 0;
        }
        $chada_travel_now        = self::now();
        $chada_travel_table      = self::table($chada_travel_wpdb);
        $chada_travel_existing_id = self::find_id_by_code($chada_travel_wpdb, $chada_travel_code);
        $chada_travel_data = [
            'chada_travel_country_name'      => (string) ($chada_travel_country['name'] ?? $chada_travel_code),
            'chada_travel_processing_fee'    => (string) ($chada_travel_country['processing_fee'] ?? '0.00'),
            'chada_travel_currency'          => (string) ($chada_travel_country['currency'] ?? CHADA_TRAVEL_Config::DEFAULT_CURRENCY),
            'chada_travel_checklist_version' => (string) ($chada_travel_country['checklist_version'] ?? 'mvp-1'),
            'chada_travel_is_active'         => empty($chada_travel_country['is_active']) ? 0 : 1,
            'chada_travel_sort_order'        => (int) ($chada_travel_country['sort_order'] ?? 0),
            'chada_travel_updated_at'        => $chada_travel_now,
        ];
        $chada_travel_optional_columns = [
            'full_details'            => ['chada_travel_full_details', '%s', static fn($chada_travel_value): string =>
                (string) $chada_travel_value],
            'guide_attachment_id'     => ['chada_travel_guide_attachment_id', '%d', static fn($chada_travel_value): int =>
                max(0, (int) $chada_travel_value)],
            'checklist_attachment_id' => ['chada_travel_checklist_attachment_id', '%d', static fn($chada_travel_value): int =>
                max(0, (int) $chada_travel_value)],
        ];
        $chada_travel_formats = ['%s', '%s', '%s', '%s', '%d', '%d', '%s'];
        foreach ($chada_travel_optional_columns as $chada_travel_key => [$chada_travel_column, $chada_travel_format, $chada_travel_normalize]) {
            if (array_key_exists($chada_travel_key, $chada_travel_country)) {
                $chada_travel_data[$chada_travel_column] = $chada_travel_normalize($chada_travel_country[$chada_travel_key]);
                $chada_travel_formats[]           = $chada_travel_format;
            }
        }

        if ($chada_travel_existing_id > 0) {
            $chada_travel_wpdb->update(
                $chada_travel_table,
                $chada_travel_data,
                ['chada_travel_country_id' => $chada_travel_existing_id],
                $chada_travel_formats,
                ['%d']
            );
            return $chada_travel_existing_id;
        }

        $chada_travel_data['chada_travel_country_code'] = $chada_travel_code;
        $chada_travel_data['chada_travel_created_at']   = $chada_travel_now;
        $chada_travel_formats[] = '%s';
        $chada_travel_formats[] = '%s';
        $chada_travel_wpdb->insert($chada_travel_table, $chada_travel_data, $chada_travel_formats);
        return (int) $chada_travel_wpdb->insert_id;
    }

    /**
     * Mirrors every row of the `chada_travel_country_fees` option into the table. Safe to call repeatedly (activation,
     * reactivation, and after every admin save) since upsert() never duplicates a code.
     *
     * @param list<array<string, mixed>> $chada_travel_country_fees
     */
    public static function sync_all(object $chada_travel_wpdb, array $chada_travel_country_fees): void {
        foreach ($chada_travel_country_fees as $chada_travel_country) {
            if (is_array($chada_travel_country)) {
                self::upsert($chada_travel_wpdb, $chada_travel_country);
                if ((string) ($chada_travel_wpdb->last_error ?? '') !== '') {
                    throw new \RuntimeException('Visa Country synchronization failed.');
                }
            }
        }
    }

    /**
     * Adds Phase 2 option fields without overwriting existing option values or table-managed guide associations.
     *
     * @param list<array<string, mixed>> $chada_travel_countries
     * @return list<array<string, mixed>>
     */
    public static function migrate_option_shape(object $chada_travel_wpdb, array $chada_travel_countries): array {
        foreach ($chada_travel_countries as &$chada_travel_country) {
            $chada_travel_row = self::find_by_code($chada_travel_wpdb, (string) ($chada_travel_country['code'] ?? ''));
            if (!array_key_exists('full_details', $chada_travel_country)) {
                $chada_travel_country['full_details'] = (string) ($chada_travel_row['chada_travel_full_details'] ?? '');
            }
            if (!array_key_exists('guide_attachment_id', $chada_travel_country)) {
                $chada_travel_country['guide_attachment_id'] = (int) ($chada_travel_row['chada_travel_guide_attachment_id'] ?? 0);
            }
            if (!array_key_exists('checklist_attachment_id', $chada_travel_country)) {
                $chada_travel_country['checklist_attachment_id'] = (int) (
                    $chada_travel_row['chada_travel_checklist_attachment_id'] ?? 0
                );
            }
        }
        unset($chada_travel_country);
        return array_values($chada_travel_countries);
    }

    /**
     * Records only safe country field/readiness changes, never descriptions, paths, URLs, or file contents.
     *
     * @param array<string, mixed> $chada_travel_meta
     */
    public static function log_event(
        object $chada_travel_wpdb,
        int $chada_travel_country_id,
        string $chada_travel_event_type,
        array $chada_travel_meta = []
    ): void {
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_record_type' => 'country', 'chada_travel_record_id' => $chada_travel_country_id,
            'chada_travel_event_type' => $chada_travel_event_type,
            'chada_travel_event_meta' => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_meta) : json_encode($chada_travel_meta),
            'chada_travel_actor_user_id' => function_exists('get_current_user_id') ? get_current_user_id() : null,
            'chada_travel_actor_type' => 'admin', 'chada_travel_created_at' => self::now(),
        ], ['%s', '%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
