<?php
/**
 * wpdb access to `chada_travel_requirements`: the checkout-facing read methods used since Phase 4, plus the
 * administrator Requirements grid's search/count query and Add/Edit CRUD used by Visa Countries.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Requirement_Repository {
    public static function table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_requirements';
    }

    /**
     * Returns the active requirement rows for a country/checklist_version, in display order.
     *
     * @return list<array<string, mixed>>
     */
    public static function get_active_for_country(
        object $chada_travel_wpdb,
        int $chada_travel_country_id,
        string $chada_travel_checklist_version
    ): array {
        if ($chada_travel_country_id <= 0) {
            return [];
        }
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_country_id = %d AND chada_travel_checklist_version = %s "
                . 'AND chada_travel_is_active = 1 ORDER BY chada_travel_sort_order ASC',
            $chada_travel_country_id,
            $chada_travel_checklist_version
        );
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        usort($chada_travel_rows, static fn(array $chada_travel_a, array $chada_travel_b): int =>
            ((int) $chada_travel_a['chada_travel_sort_order']) <=> ((int) $chada_travel_b['chada_travel_sort_order']));
        return $chada_travel_rows;
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_requirement_id): ?array {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_requirement_id = %d LIMIT 1",
            $chada_travel_requirement_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** True when no requirement rows exist yet for this country/checklist_version (used to seed idempotently). */
    public static function version_has_requirements(
        object $chada_travel_wpdb,
        int $chada_travel_country_id,
        string $chada_travel_checklist_version
    ): bool {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT chada_travel_requirement_id FROM {$chada_travel_table} "
                . 'WHERE chada_travel_country_id = %d AND chada_travel_checklist_version = %s LIMIT 1',
            $chada_travel_country_id,
            $chada_travel_checklist_version
        );
        return (bool) $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
    }

    /** @param array<string, mixed> $chada_travel_requirement */
    public static function insert(object $chada_travel_wpdb, int $chada_travel_country_id, array $chada_travel_requirement): void {
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_wpdb->insert(self::table($chada_travel_wpdb), [
            'chada_travel_country_id'              => $chada_travel_country_id,
            'chada_travel_checklist_version'       => (string) $chada_travel_requirement['checklist_version'],
            'chada_travel_requirement_label'       => (string) $chada_travel_requirement['label'],
            'chada_travel_requirement_description' => (string) ($chada_travel_requirement['description'] ?? ''),
            'chada_travel_form_attachment_id'      => null,
            'chada_travel_is_required'             => empty($chada_travel_requirement['is_required']) ? 0 : 1,
            'chada_travel_allowed_mime_types'      => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_requirement['mime_types']) : json_encode($chada_travel_requirement['mime_types']),
            'chada_travel_max_file_bytes'          => (int) $chada_travel_requirement['max_bytes'],
            'chada_travel_sort_order'              => (int) ($chada_travel_requirement['sort_order'] ?? 0),
            'chada_travel_is_active'               => 1,
            'chada_travel_created_at'              => $chada_travel_now,
            'chada_travel_updated_at'              => $chada_travel_now,
        ], ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s']);
    }

    /**
     * Returns every currently active requirement across all countries, with its decoded MIME allowlist and
     * country name - used only by CHADA_TRAVEL_Documents_Uploads_Settings_Validator to detect whether a proposed
     * global Allowed Visa Document Types selection would strand a requirement with zero effective MIME types.
     * Never used to render or expose customer/application data.
     *
     * @return list<array{requirement_id: int, label: string, country_name: string, mime_types: list<string>}>
     */
    public static function get_active_requirements(object $chada_travel_wpdb): array {
        $chada_travel_table            = self::table($chada_travel_wpdb);
        $chada_travel_countries_table  = $chada_travel_wpdb->prefix . 'chada_travel_countries';
        $chada_travel_sql = 'SELECT r.chada_travel_requirement_id, r.chada_travel_requirement_label, r.chada_travel_allowed_mime_types,
                c.chada_travel_country_name
            FROM ' . $chada_travel_table . ' r
            INNER JOIN ' . $chada_travel_countries_table . ' c ON c.chada_travel_country_id = r.chada_travel_country_id
            WHERE r.chada_travel_is_active = 1';
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        return array_map(static fn(array $chada_travel_row): array => [
            'requirement_id' => (int) $chada_travel_row['chada_travel_requirement_id'],
            'label'          => (string) $chada_travel_row['chada_travel_requirement_label'],
            'country_name'   => (string) $chada_travel_row['chada_travel_country_name'],
            'mime_types'     => self::decode_mime_types((string) $chada_travel_row['chada_travel_allowed_mime_types']),
        ], $chada_travel_rows);
    }

    /** @return list<string> */
    private static function decode_mime_types(string $chada_travel_json): array {
        $chada_travel_decoded = json_decode($chada_travel_json, true);
        return is_array($chada_travel_decoded) ? array_values(array_map('strval', $chada_travel_decoded)) : [];
    }

    public const GRID_PER_PAGE = 20;

    /**
     * Administrator Add Requirement create, using validated fields from CHADA_TRAVEL_Requirement_Validator.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @return array<string, mixed> The inserted row.
     */
    public static function create(object $chada_travel_wpdb, array $chada_travel_clean): array {
        $chada_travel_now  = gmdate('Y-m-d H:i:s');
        $chada_travel_data = [
            'chada_travel_country_id'              => $chada_travel_clean['country_id'],
            'chada_travel_checklist_version'       => $chada_travel_clean['checklist_version'],
            'chada_travel_requirement_label'       => $chada_travel_clean['label'],
            'chada_travel_requirement_description' => $chada_travel_clean['description'] !== '' ? $chada_travel_clean['description'] : null,
            'chada_travel_form_attachment_id'      => $chada_travel_clean['form_attachment_id'] > 0
                ? $chada_travel_clean['form_attachment_id'] : null,
            'chada_travel_is_required'             => $chada_travel_clean['is_required'] ? 1 : 0,
            'chada_travel_allowed_mime_types'      => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_clean['mime_types']) : json_encode($chada_travel_clean['mime_types']),
            'chada_travel_max_file_bytes'          => $chada_travel_clean['max_bytes'],
            'chada_travel_sort_order'              => $chada_travel_clean['sort_order'],
            'chada_travel_is_active'               => 1,
            'chada_travel_created_at'              => $chada_travel_now,
            'chada_travel_updated_at'              => $chada_travel_now,
        ];
        $chada_travel_wpdb->insert(self::table($chada_travel_wpdb), $chada_travel_data, [
            '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s',
        ]);
        $chada_travel_data['chada_travel_requirement_id'] = (int) $chada_travel_wpdb->insert_id;
        self::log_event($chada_travel_wpdb, $chada_travel_data['chada_travel_requirement_id'], 'chada_travel_requirement_created');
        return $chada_travel_data;
    }

    /**
     * Administrator Edit Requirement update. Only ever changes this exact requirement row for its checklist
     * version; it never rewrites requirement rows belonging to an older/different checklist-version snapshot,
     * preserving already-submitted applications' frozen requirement set.
     *
     * @param array<string, mixed> $chada_travel_requirement Current row.
     * @param array<string, mixed> $chada_travel_clean        Validated fields.
     * @return array<string, mixed> Updated row.
     */
    public static function update(object $chada_travel_wpdb, array $chada_travel_requirement, array $chada_travel_clean): array {
        $chada_travel_data = [
            'chada_travel_country_id'              => $chada_travel_clean['country_id'],
            'chada_travel_checklist_version'       => $chada_travel_clean['checklist_version'],
            'chada_travel_requirement_label'       => $chada_travel_clean['label'],
            'chada_travel_requirement_description' => $chada_travel_clean['description'] !== '' ? $chada_travel_clean['description'] : null,
            'chada_travel_form_attachment_id'      => $chada_travel_clean['form_attachment_id'] > 0
                ? $chada_travel_clean['form_attachment_id'] : null,
            'chada_travel_is_required'             => $chada_travel_clean['is_required'] ? 1 : 0,
            'chada_travel_allowed_mime_types'      => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_clean['mime_types']) : json_encode($chada_travel_clean['mime_types']),
            'chada_travel_max_file_bytes'          => $chada_travel_clean['max_bytes'],
            'chada_travel_sort_order'              => $chada_travel_clean['sort_order'],
            'chada_travel_updated_at'              => gmdate('Y-m-d H:i:s'),
        ];
        $chada_travel_wpdb->update(
            self::table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_requirement_id' => (int) $chada_travel_requirement['chada_travel_requirement_id']],
            ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s'],
            ['%d']
        );
        self::log_event($chada_travel_wpdb, (int) $chada_travel_requirement['chada_travel_requirement_id'], 'chada_travel_requirement_updated');
        return array_merge($chada_travel_requirement, $chada_travel_data);
    }

    /**
     * @param array<string, mixed> $chada_travel_requirement Current row.
     * @return array<string, mixed> Updated row.
     */
    public static function set_active(object $chada_travel_wpdb, array $chada_travel_requirement, bool $chada_travel_active): array {
        $chada_travel_data = ['chada_travel_is_active' => $chada_travel_active ? 1 : 0, 'chada_travel_updated_at' => gmdate('Y-m-d H:i:s')];
        $chada_travel_wpdb->update(
            self::table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_requirement_id' => (int) $chada_travel_requirement['chada_travel_requirement_id']],
            ['%d', '%s'],
            ['%d']
        );
        self::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_requirement['chada_travel_requirement_id'],
            $chada_travel_active ? 'chada_travel_requirement_restored' : 'chada_travel_requirement_archived'
        );
        return array_merge($chada_travel_requirement, $chada_travel_data);
    }

    /**
     * Real server-side search/filter/paginate for Visa Countries' Requirements tab, joined with the
     * country name.
     *
     * @return list<array<string, mixed>>
     */
    public static function search_grid(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_status,
        int $chada_travel_page = 1,
        int $chada_travel_per_page = self::GRID_PER_PAGE
    ): array {
        $chada_travel_page     = max(1, $chada_travel_page);
        $chada_travel_per_page = max(1, min(100, $chada_travel_per_page));
        $chada_travel_offset   = ($chada_travel_page - 1) * $chada_travel_per_page;
        $chada_travel_where    = self::grid_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_status);
        $chada_travel_countries_table = $chada_travel_wpdb->prefix . 'chada_travel_countries';

        $chada_travel_sql = 'SELECT r.*, c.chada_travel_country_name, c.chada_travel_country_code
            FROM ' . self::table($chada_travel_wpdb) . ' r
            INNER JOIN ' . $chada_travel_countries_table . ' c ON c.chada_travel_country_id = r.chada_travel_country_id
            WHERE ' . $chada_travel_where['sql'] . '
            ORDER BY c.chada_travel_country_name ASC, r.chada_travel_sort_order ASC
            LIMIT %d OFFSET %d';
        $chada_travel_params = array_merge($chada_travel_where['params'], [$chada_travel_per_page, $chada_travel_offset]);
        return $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_params), ARRAY_A) ?: [];
    }

    public static function count_grid(object $chada_travel_wpdb, string $chada_travel_search, string $chada_travel_status): int {
        $chada_travel_where = self::grid_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_status);
        $chada_travel_countries_table = $chada_travel_wpdb->prefix . 'chada_travel_countries';
        $chada_travel_sql = 'SELECT COUNT(*) FROM ' . self::table($chada_travel_wpdb) . ' r
            INNER JOIN ' . $chada_travel_countries_table . ' c ON c.chada_travel_country_id = r.chada_travel_country_id
            WHERE ' . $chada_travel_where['sql'];
        if (!$chada_travel_where['params']) {
            return (int) $chada_travel_wpdb->get_var($chada_travel_sql);
        }
        return (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']));
    }

    /** @return array{sql: string, params: list<mixed>} */
    private static function grid_where(object $chada_travel_wpdb, string $chada_travel_search, string $chada_travel_status): array {
        $chada_travel_conditions = ['1=1'];
        $chada_travel_params     = [];
        if ($chada_travel_status === 'active') {
            $chada_travel_conditions[] = 'r.chada_travel_is_active = 1';
        } elseif ($chada_travel_status === 'archived') {
            $chada_travel_conditions[] = 'r.chada_travel_is_active = 0';
        }
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = '(r.chada_travel_requirement_label LIKE %s OR c.chada_travel_country_name LIKE %s
                OR c.chada_travel_country_code LIKE %s)';
            array_push($chada_travel_params, ...array_fill(0, 3, $chada_travel_like));
        }
        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /** @param array<string, mixed> $chada_travel_meta */
    private static function log_event(
        object $chada_travel_wpdb,
        int $chada_travel_requirement_id,
        string $chada_travel_event_type,
        array $chada_travel_meta = []
    ): void {
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_record_type'   => 'requirement',
            'chada_travel_record_id'     => $chada_travel_requirement_id,
            'chada_travel_event_type'    => $chada_travel_event_type,
            'chada_travel_event_meta'    => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_meta) : json_encode($chada_travel_meta),
            'chada_travel_actor_type'    => 'admin',
            'chada_travel_created_at'    => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%d', '%s', '%s', '%s', '%s']);
    }
}
