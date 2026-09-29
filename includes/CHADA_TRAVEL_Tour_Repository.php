<?php
/**
 * Persistence for Tours, their child Travel Dates, and Tour taxonomy relationships.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

require_once __DIR__ . '/CHADA_TRAVEL_Extension_Manager.php';

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Tour_Repository {
    public const GRID_PER_PAGE = 20;
    public const PUBLIC_SEARCH_PER_PAGE = 10;
    public const BATCH_QUERY_CHUNK_SIZE = 500;
    public const TAXONOMY_DESTINATION = 'destination';
    public const TAXONOMY_TYPE = 'type';

    public static function tours_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_tours';
    }

    public static function dates_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_tour_dates';
    }

    public static function files_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_tour_files';
    }

    public static function relationships_table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_tour_term_relationships';
    }

    /** @return list<array<string, mixed>> */
    public static function get_all(object $chada_travel_wpdb): array {
        return $chada_travel_wpdb->get_results(
            'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' ORDER BY chada_travel_tour_name ASC',
            ARRAY_A
        ) ?: [];
    }

    /** Returns the number of non-archived Tours for the administrator menu badge. */
    public static function count_active(object $chada_travel_wpdb): int {
        return (int) $chada_travel_wpdb->get_var(
            'SELECT COUNT(*) FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE chada_travel_is_active = 1'
        );
    }

    /** Returns the number of active Tours marked Featured. */
    public static function count_featured_active(object $chada_travel_wpdb): int {
        return (int) $chada_travel_wpdb->get_var(
            'SELECT COUNT(*) FROM ' . self::tours_table($chada_travel_wpdb)
                . ' WHERE chada_travel_is_active = 1 AND chada_travel_is_featured = 1'
        );
    }

    /** @return list<array<string, mixed>> */
    public static function get_latest_active(object $chada_travel_wpdb, int $chada_travel_limit): array {
        $chada_travel_limit = max(1, $chada_travel_limit);
        $chada_travel_sql = 'SELECT * FROM ' . self::tours_table($chada_travel_wpdb)
            . ' WHERE chada_travel_is_active = 1 ORDER BY chada_travel_updated_at DESC, chada_travel_tour_id DESC LIMIT %d';
        return $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_limit), ARRAY_A) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function get_featured_active(object $chada_travel_wpdb, int $chada_travel_limit): array {
        $chada_travel_limit = max(1, $chada_travel_limit);
        $chada_travel_sql = 'SELECT * FROM ' . self::tours_table($chada_travel_wpdb)
            . ' WHERE chada_travel_is_active = 1 AND chada_travel_is_featured = 1'
            . ' ORDER BY chada_travel_updated_at DESC, chada_travel_tour_id DESC LIMIT %d';
        return $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_limit), ARRAY_A) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function search_public(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        int $chada_travel_destination_id,
        int $chada_travel_type_id,
        int $chada_travel_page,
        int $chada_travel_per_page = self::PUBLIC_SEARCH_PER_PAGE
    ): array {
        $chada_travel_where = self::public_search_where(
            $chada_travel_wpdb,
            $chada_travel_search,
            $chada_travel_destination_id,
            $chada_travel_type_id
        );
        $chada_travel_per_page = max(1, $chada_travel_per_page);
        $chada_travel_offset = (max(1, $chada_travel_page) - 1) * $chada_travel_per_page;
        $chada_travel_sql = 'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql']
            . ' ORDER BY chada_travel_tour_name ASC, chada_travel_tour_id ASC LIMIT %d OFFSET %d';
        return $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge($chada_travel_where['params'], [$chada_travel_per_page, $chada_travel_offset])),
            ARRAY_A
        ) ?: [];
    }

    public static function count_public(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        int $chada_travel_destination_id,
        int $chada_travel_type_id
    ): int {
        $chada_travel_where = self::public_search_where(
            $chada_travel_wpdb,
            $chada_travel_search,
            $chada_travel_destination_id,
            $chada_travel_type_id
        );
        $chada_travel_sql = 'SELECT COUNT(*) FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql'];
        return (int) $chada_travel_wpdb->get_var($chada_travel_where['params']
            ? $chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']) : $chada_travel_sql);
    }

    /** @return array{sql: string, params: list<mixed>} */
    private static function public_search_where(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        int $chada_travel_destination_id,
        int $chada_travel_type_id
    ): array {
        $chada_travel_conditions = ['chada_travel_is_active = 1'];
        $chada_travel_params = [];
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = '(chada_travel_tour_name LIKE %s OR chada_travel_description LIKE %s)';
            $chada_travel_params[] = $chada_travel_like;
            $chada_travel_params[] = $chada_travel_like;
        }
        foreach ([
            [$chada_travel_destination_id, self::TAXONOMY_DESTINATION],
            [$chada_travel_type_id, self::TAXONOMY_TYPE],
        ] as $chada_travel_filter) {
            if ($chada_travel_filter[0] <= 0) {
                continue;
            }
            $chada_travel_conditions[] = 'EXISTS (SELECT 1 FROM ' . self::relationships_table($chada_travel_wpdb)
                . ' AS search_relationship INNER JOIN ' . $chada_travel_wpdb->prefix . 'chada_travel_tour_terms AS search_term'
                . ' ON search_term.chada_travel_tour_term_id = search_relationship.chada_travel_tour_term_id'
                . ' WHERE search_relationship.chada_travel_tour_id = ' . self::tours_table($chada_travel_wpdb) . '.chada_travel_tour_id'
                . ' AND search_relationship.chada_travel_tour_term_id = %d AND search_term.chada_travel_taxonomy = %s'
                . ' AND search_term.chada_travel_is_active = 1)';
            $chada_travel_params[] = (int) $chada_travel_filter[0];
            $chada_travel_params[] = (string) $chada_travel_filter[1];
        }
        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_tour_id): ?array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE chada_travel_tour_id = %d LIMIT 1',
            $chada_travel_tour_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** @return array<string, mixed>|null */
    public static function find_by_slug(object $chada_travel_wpdb, string $chada_travel_slug): ?array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE chada_travel_tour_slug = %s LIMIT 1',
            $chada_travel_slug
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function get_dates(object $chada_travel_wpdb, int $chada_travel_tour_id): array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::dates_table($chada_travel_wpdb)
                . ' WHERE chada_travel_tour_id = %d ORDER BY chada_travel_sort_order ASC, chada_travel_start_date ASC',
            $chada_travel_tour_id
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function get_files(object $chada_travel_wpdb, int $chada_travel_tour_id): array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::files_table($chada_travel_wpdb)
                . ' WHERE chada_travel_tour_id = %d ORDER BY chada_travel_sort_order ASC, chada_travel_tour_file_id ASC',
            $chada_travel_tour_id
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /**
     * Loads Travel Dates for multiple Tours with bounded IN queries and the same per-Tour ordering as get_dates().
     *
     * @param list<int> $chada_travel_tour_ids
     * @return array<int, list<array<string, mixed>>>
     */
    public static function get_dates_by_tour_ids(object $chada_travel_wpdb, array $chada_travel_tour_ids): array {
        $chada_travel_tour_ids = array_values(array_unique(array_filter(array_map('intval', $chada_travel_tour_ids))));
        $chada_travel_grouped = [];
        foreach ($chada_travel_tour_ids as $chada_travel_tour_id) { $chada_travel_grouped[$chada_travel_tour_id] = []; }
        foreach (array_chunk($chada_travel_tour_ids, self::BATCH_QUERY_CHUNK_SIZE) as $chada_travel_batch_ids) {
            $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_batch_ids), '%d'));
            $chada_travel_sql = 'SELECT * FROM ' . self::dates_table($chada_travel_wpdb) . ' WHERE chada_travel_tour_id IN ('
                . $chada_travel_placeholders . ') ORDER BY chada_travel_tour_id ASC, chada_travel_sort_order ASC, chada_travel_start_date ASC';
            $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_batch_ids), ARRAY_A) ?: [];
            foreach ($chada_travel_rows as $chada_travel_row) {
                $chada_travel_tour_id = (int) ($chada_travel_row['chada_travel_tour_id'] ?? 0);
                if (isset($chada_travel_grouped[$chada_travel_tour_id])) { $chada_travel_grouped[$chada_travel_tour_id][] = $chada_travel_row; }
            }
        }
        return $chada_travel_grouped;
    }

    /**
     * Loads Downloadable Files for multiple Tours with bounded IN queries and the same per-Tour ordering as get_files().
     *
     * @param list<int> $chada_travel_tour_ids
     * @return array<int, list<array<string, mixed>>>
     */
    public static function get_files_by_tour_ids(object $chada_travel_wpdb, array $chada_travel_tour_ids): array {
        $chada_travel_tour_ids = array_values(array_unique(array_filter(array_map('intval', $chada_travel_tour_ids))));
        $chada_travel_grouped = [];
        foreach ($chada_travel_tour_ids as $chada_travel_tour_id) { $chada_travel_grouped[$chada_travel_tour_id] = []; }
        foreach (array_chunk($chada_travel_tour_ids, self::BATCH_QUERY_CHUNK_SIZE) as $chada_travel_batch_ids) {
            $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_batch_ids), '%d'));
            $chada_travel_sql = 'SELECT * FROM ' . self::files_table($chada_travel_wpdb) . ' WHERE chada_travel_tour_id IN ('
                . $chada_travel_placeholders . ') ORDER BY chada_travel_tour_id ASC, chada_travel_sort_order ASC, chada_travel_tour_file_id ASC';
            $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_batch_ids), ARRAY_A) ?: [];
            foreach ($chada_travel_rows as $chada_travel_row) {
                $chada_travel_tour_id = (int) ($chada_travel_row['chada_travel_tour_id'] ?? 0);
                if (isset($chada_travel_grouped[$chada_travel_tour_id])) { $chada_travel_grouped[$chada_travel_tour_id][] = $chada_travel_row; }
            }
        }
        return $chada_travel_grouped;
    }

    /** @return list<int> */
    public static function get_term_ids(object $chada_travel_wpdb, int $chada_travel_tour_id, string $chada_travel_taxonomy): array {
        $chada_travel_terms_table = $chada_travel_wpdb->prefix . 'chada_travel_tour_terms';
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            "SELECT r.chada_travel_tour_term_id FROM {$chada_travel_terms_table} t INNER JOIN "
                . self::relationships_table($chada_travel_wpdb) . ' r ON r.chada_travel_tour_term_id = t.chada_travel_tour_term_id '
                . 'WHERE r.chada_travel_tour_id = %d AND t.chada_travel_taxonomy = %s',
            $chada_travel_tour_id,
            $chada_travel_taxonomy
        );
        return array_map('intval', $chada_travel_wpdb->get_col($chada_travel_sql) ?: []);
    }

    /**
     * @param list<int> $chada_travel_tour_ids
     * @return array<int, list<string>>
     */
    public static function get_term_names_by_tour_ids(
        object $chada_travel_wpdb,
        array $chada_travel_tour_ids,
        string $chada_travel_taxonomy
    ): array {
        $chada_travel_tour_ids = array_values(array_unique(array_filter(array_map('intval', $chada_travel_tour_ids))));
        if (!$chada_travel_tour_ids) {
            return [];
        }
        $chada_travel_terms_table = $chada_travel_wpdb->prefix . 'chada_travel_tour_terms';
        $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_tour_ids), '%d'));
        $chada_travel_sql = 'SELECT r.chada_travel_tour_id, t.chada_travel_term_name FROM ' . $chada_travel_terms_table . ' t INNER JOIN '
            . self::relationships_table($chada_travel_wpdb) . ' r ON r.chada_travel_tour_term_id = t.chada_travel_tour_term_id '
            . 'WHERE r.chada_travel_tour_id IN (' . $chada_travel_placeholders . ') AND t.chada_travel_taxonomy = %s '
            . 'ORDER BY t.chada_travel_term_name ASC, t.chada_travel_tour_term_id ASC';
        $chada_travel_rows = $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge($chada_travel_tour_ids, [$chada_travel_taxonomy])),
            ARRAY_A
        ) ?: [];
        $chada_travel_names = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_tour_id = (int) ($chada_travel_row['chada_travel_tour_id'] ?? 0);
            $chada_travel_name = (string) ($chada_travel_row['chada_travel_term_name'] ?? '');
            if ($chada_travel_tour_id > 0 && $chada_travel_name !== '') {
                $chada_travel_names[$chada_travel_tour_id][] = $chada_travel_name;
            }
        }
        return $chada_travel_names;
    }

    /**
     * Like get_term_names_by_tour_ids(), but also returns each term's id, slug, and active status - for
     * callers that need to build a public link to the term (e.g. a Destination archive page), not just
     * display its name.
     *
     * @param list<int> $chada_travel_tour_ids
     * @return array<int, list<array{id: int, name: string, slug: string, is_active: bool}>>
     */
    public static function get_term_details_by_tour_ids(
        object $chada_travel_wpdb,
        array $chada_travel_tour_ids,
        string $chada_travel_taxonomy
    ): array {
        $chada_travel_tour_ids = array_values(array_unique(array_filter(array_map('intval', $chada_travel_tour_ids))));
        if (!$chada_travel_tour_ids) {
            return [];
        }
        $chada_travel_terms_table = $chada_travel_wpdb->prefix . 'chada_travel_tour_terms';
        $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_tour_ids), '%d'));
        $chada_travel_sql = 'SELECT r.chada_travel_tour_id, t.chada_travel_tour_term_id, t.chada_travel_term_name, t.chada_travel_term_slug,'
            . ' t.chada_travel_is_active FROM ' . $chada_travel_terms_table . ' t INNER JOIN '
            . self::relationships_table($chada_travel_wpdb) . ' r ON r.chada_travel_tour_term_id = t.chada_travel_tour_term_id '
            . 'WHERE r.chada_travel_tour_id IN (' . $chada_travel_placeholders . ') AND t.chada_travel_taxonomy = %s '
            . 'ORDER BY t.chada_travel_term_name ASC, t.chada_travel_tour_term_id ASC';
        $chada_travel_rows = $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge($chada_travel_tour_ids, [$chada_travel_taxonomy])),
            ARRAY_A
        ) ?: [];
        $chada_travel_terms = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_tour_id = (int) ($chada_travel_row['chada_travel_tour_id'] ?? 0);
            $chada_travel_name = (string) ($chada_travel_row['chada_travel_term_name'] ?? '');
            if ($chada_travel_tour_id > 0 && $chada_travel_name !== '') {
                $chada_travel_terms[$chada_travel_tour_id][] = [
                    'id' => (int) ($chada_travel_row['chada_travel_tour_term_id'] ?? 0),
                    'name' => $chada_travel_name,
                    'slug' => (string) ($chada_travel_row['chada_travel_term_slug'] ?? ''),
                    'is_active' => !empty($chada_travel_row['chada_travel_is_active']),
                ];
            }
        }
        return $chada_travel_terms;
    }

    /** @return list<array<string, mixed>> */
    public static function search_grid(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_status,
        int $chada_travel_page,
        string $chada_travel_featured = 'all',
        int $chada_travel_destination = 0,
        int $chada_travel_type = 0
    ): array {
        $chada_travel_where = self::grid_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination, $chada_travel_type);
        $chada_travel_offset = (max(1, $chada_travel_page) - 1) * self::GRID_PER_PAGE;
        $chada_travel_sql = 'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql']
            . ' ORDER BY chada_travel_tour_name ASC LIMIT %d OFFSET %d';
        return $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge($chada_travel_where['params'], [self::GRID_PER_PAGE, $chada_travel_offset])),
            ARRAY_A
        ) ?: [];
    }

    public static function count_grid(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_featured = 'all',
        int $chada_travel_destination = 0,
        int $chada_travel_type = 0
    ): int {
        $chada_travel_where = self::grid_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination, $chada_travel_type);
        $chada_travel_sql = 'SELECT COUNT(*) FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql'];
        return (int) $chada_travel_wpdb->get_var($chada_travel_where['params']
            ? $chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']) : $chada_travel_sql);
    }

    /** @return list<array<string, mixed>> */
    public static function search_export(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_featured = 'all',
        int $chada_travel_destination = 0,
        int $chada_travel_type = 0
    ): array {
        $chada_travel_where = self::grid_where($chada_travel_wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination, $chada_travel_type);
        $chada_travel_sql = 'SELECT * FROM ' . self::tours_table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql']
            . ' ORDER BY chada_travel_tour_name ASC, chada_travel_tour_id ASC';
        return $chada_travel_wpdb->get_results(
            $chada_travel_where['params'] ? $chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']) : $chada_travel_sql,
            ARRAY_A
        ) ?: [];
    }

    /** @return array{sql: string, params: list<mixed>} */
    private static function grid_where(
        object $chada_travel_wpdb,
        string $chada_travel_search,
        string $chada_travel_status,
        string $chada_travel_featured,
        int $chada_travel_destination = 0,
        int $chada_travel_type = 0
    ): array {
        $chada_travel_conditions = ['1=1'];
        $chada_travel_params = [];
        if ($chada_travel_status === 'active') {
            $chada_travel_conditions[] = 'chada_travel_is_active = 1';
        } elseif ($chada_travel_status === 'archived') {
            $chada_travel_conditions[] = 'chada_travel_is_active = 0';
        }
        if ($chada_travel_featured === 'featured') {
            $chada_travel_conditions[] = 'chada_travel_is_featured = 1';
        } elseif ($chada_travel_featured === 'not_featured') {
            $chada_travel_conditions[] = 'chada_travel_is_featured = 0';
        }
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = '(chada_travel_tour_name LIKE %s OR chada_travel_tour_code LIKE %s)';
            $chada_travel_params[] = $chada_travel_like;
            $chada_travel_params[] = $chada_travel_like;
        }
        foreach ([
            [$chada_travel_destination, self::TAXONOMY_DESTINATION],
            [$chada_travel_type, self::TAXONOMY_TYPE],
        ] as $chada_travel_filter) {
            if ($chada_travel_filter[0] <= 0) {
                continue;
            }
            $chada_travel_conditions[] = 'EXISTS (SELECT 1 FROM ' . self::relationships_table($chada_travel_wpdb)
                . ' AS grid_relationship INNER JOIN ' . $chada_travel_wpdb->prefix . 'chada_travel_tour_terms AS grid_term'
                . ' ON grid_term.chada_travel_tour_term_id = grid_relationship.chada_travel_tour_term_id'
                . ' WHERE grid_relationship.chada_travel_tour_id = ' . self::tours_table($chada_travel_wpdb) . '.chada_travel_tour_id'
                . ' AND grid_relationship.chada_travel_tour_term_id = %d AND grid_term.chada_travel_taxonomy = %s)';
            $chada_travel_params[] = (int) $chada_travel_filter[0];
            $chada_travel_params[] = (string) $chada_travel_filter[1];
        }
        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /**
     * Persists one complete Tour while the caller owns the surrounding transaction.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_event_meta
     * @return array<string, mixed>
     */
    public static function save_in_transaction(
        object $chada_travel_wpdb,
        array $chada_travel_clean,
        string $chada_travel_currency,
        int $chada_travel_actor_user_id,
        ?string $chada_travel_event_type = null,
        array $chada_travel_event_meta = [],
        ?bool $chada_travel_is_active = null
    ): array {
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_id = (int) ($chada_travel_clean['tour_id'] ?? 0);
        $chada_travel_data = [
            'chada_travel_feature_image_attachment_id' => (int) ($chada_travel_clean['feature_image_attachment_id'] ?? 0) ?: null,
            'chada_travel_is_featured' => !empty($chada_travel_clean['is_featured']) ? 1 : 0,
            'chada_travel_tour_name' => $chada_travel_clean['tour_name'],
            'chada_travel_tour_slug' => $chada_travel_clean['tour_slug'],
            'chada_travel_description' => $chada_travel_clean['description'],
            'chada_travel_trip_includes' => $chada_travel_clean['trip_includes'],
            'chada_travel_trip_excludes' => $chada_travel_clean['trip_excludes'],
            'chada_travel_basic_visa_requirements' => $chada_travel_clean['basic_visa_requirements'] ?? '',
            'chada_travel_itinerary' => $chada_travel_clean['itinerary'],
            'chada_travel_booking_conditions' => $chada_travel_clean['booking_conditions'],
            'chada_travel_tour_code' => $chada_travel_clean['tour_code'],
            'chada_travel_price' => $chada_travel_clean['price'],
            'chada_travel_currency' => $chada_travel_clean['currency'] ?? $chada_travel_currency,
            'chada_travel_updated_at' => $chada_travel_now,
        ];
        $chada_travel_formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
        if ($chada_travel_id > 0) {
            if ($chada_travel_is_active !== null) {
                $chada_travel_existing = self::find_by_id($chada_travel_wpdb, $chada_travel_id);
                $chada_travel_existing_active = $chada_travel_existing && !empty($chada_travel_existing['chada_travel_is_active']);
                if ($chada_travel_existing && $chada_travel_existing_active !== $chada_travel_is_active) {
                    $chada_travel_data['chada_travel_is_active'] = $chada_travel_is_active ? 1 : 0;
                    $chada_travel_data['chada_travel_archived_at'] = $chada_travel_is_active ? null : $chada_travel_now;
                    $chada_travel_formats[] = '%d';
                    $chada_travel_formats[] = '%s';
                }
            }
            $chada_travel_updated = $chada_travel_wpdb->update(
                self::tours_table($chada_travel_wpdb),
                $chada_travel_data,
                ['chada_travel_tour_id' => $chada_travel_id],
                $chada_travel_formats,
                ['%d']
            );
            if ($chada_travel_updated === false) {
                throw new \RuntimeException('Tour update failed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Tour update failed.');
        } else {
            $chada_travel_active = $chada_travel_is_active === null ? true : $chada_travel_is_active;
            $chada_travel_data['chada_travel_is_active'] = $chada_travel_active ? 1 : 0;
            $chada_travel_data['chada_travel_archived_at'] = $chada_travel_active ? null : $chada_travel_now;
            $chada_travel_data['chada_travel_created_at'] = $chada_travel_now;
            $chada_travel_inserted = $chada_travel_wpdb->insert(
                self::tours_table($chada_travel_wpdb),
                $chada_travel_data,
                array_merge($chada_travel_formats, ['%d', '%s', '%s'])
            );
            if (!$chada_travel_inserted) {
                throw new \RuntimeException('Tour creation failed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Tour creation failed.');
            $chada_travel_id = (int) $chada_travel_wpdb->insert_id;
        }
        self::replace_dates($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['travel_dates'], $chada_travel_now);
        self::replace_terms($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['destination_ids'], $chada_travel_clean['type_ids'], $chada_travel_now);
        self::replace_files($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['downloadable_file_ids'], $chada_travel_now);
        self::log_event(
            $chada_travel_wpdb,
            $chada_travel_id,
            $chada_travel_clean['tour_name'],
            $chada_travel_actor_user_id,
            $chada_travel_event_type ?? (($chada_travel_clean['tour_id'] ?? 0) > 0 ? 'chada_travel_tour_updated' : 'chada_travel_tour_created'),
            $chada_travel_event_meta
        );
        return self::find_by_id($chada_travel_wpdb, $chada_travel_id) ?: [];
    }

    /**
     * Saves a complete Tour and owns the surrounding database transaction.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_event_meta
     * @return array<string, mixed>
     */
    public static function save(
        object $chada_travel_wpdb,
        array $chada_travel_clean,
        string $chada_travel_currency,
        int $chada_travel_actor_user_id,
        ?string $chada_travel_event_type = null,
        array $chada_travel_event_meta = []
    ): array {
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_id = (int) ($chada_travel_clean['tour_id'] ?? 0);
        $chada_travel_data = [
            'chada_travel_feature_image_attachment_id' => (int) ($chada_travel_clean['feature_image_attachment_id'] ?? 0) ?: null,
            'chada_travel_is_featured' => !empty($chada_travel_clean['is_featured']) ? 1 : 0,
            'chada_travel_tour_name' => $chada_travel_clean['tour_name'],
            'chada_travel_tour_slug' => $chada_travel_clean['tour_slug'],
            'chada_travel_description' => $chada_travel_clean['description'],
            'chada_travel_trip_includes' => $chada_travel_clean['trip_includes'],
            'chada_travel_trip_excludes' => $chada_travel_clean['trip_excludes'],
            'chada_travel_basic_visa_requirements' => $chada_travel_clean['basic_visa_requirements'] ?? '',
            'chada_travel_itinerary' => $chada_travel_clean['itinerary'],
            'chada_travel_booking_conditions' => $chada_travel_clean['booking_conditions'],
            'chada_travel_tour_code' => $chada_travel_clean['tour_code'],
            'chada_travel_price' => $chada_travel_clean['price'],
            'chada_travel_currency' => $chada_travel_clean['currency'] ?? $chada_travel_currency,
            'chada_travel_updated_at' => $chada_travel_now,
        ];
        $chada_travel_formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];
        if ($chada_travel_wpdb->query('START TRANSACTION') === false) {
            throw new \RuntimeException('Tour save transaction could not be started.');
        }
        try {
            if ($chada_travel_id > 0) {
                $chada_travel_updated = $chada_travel_wpdb->update(
                    self::tours_table($chada_travel_wpdb),
                    $chada_travel_data,
                    ['chada_travel_tour_id' => $chada_travel_id],
                    $chada_travel_formats,
                    ['%d']
                );
                if ($chada_travel_updated === false) {
                    throw new \RuntimeException('Tour update failed.');
                }
                self::assert_no_database_error($chada_travel_wpdb, 'Tour update failed.');
            } else {
                $chada_travel_data['chada_travel_is_active'] = 1;
                $chada_travel_data['chada_travel_created_at'] = $chada_travel_now;
                $chada_travel_inserted = $chada_travel_wpdb->insert(
                    self::tours_table($chada_travel_wpdb),
                    $chada_travel_data,
                    array_merge($chada_travel_formats, ['%d', '%s'])
                );
                if (!$chada_travel_inserted) {
                    throw new \RuntimeException('Tour creation failed.');
                }
                self::assert_no_database_error($chada_travel_wpdb, 'Tour creation failed.');
                $chada_travel_id = (int) $chada_travel_wpdb->insert_id;
            }
            self::replace_dates($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['travel_dates'], $chada_travel_now);
            self::replace_terms($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['destination_ids'], $chada_travel_clean['type_ids'], $chada_travel_now);
            self::replace_files($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['downloadable_file_ids'], $chada_travel_now);
            if ($chada_travel_wpdb->query('COMMIT') === false) {
                throw new \RuntimeException('Tour save transaction could not be committed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Tour save transaction could not be committed.');
        } catch (\Throwable $chada_travel_exception) {
            $chada_travel_wpdb->query('ROLLBACK');
            throw $chada_travel_exception;
        }
        self::log_event($chada_travel_wpdb, $chada_travel_id, $chada_travel_clean['tour_name'], $chada_travel_actor_user_id,
            $chada_travel_event_type ?? ($chada_travel_clean['tour_id'] > 0 ? 'chada_travel_tour_updated' : 'chada_travel_tour_created'), $chada_travel_event_meta);
        return self::find_by_id($chada_travel_wpdb, $chada_travel_id) ?: [];
    }

    /**
     * Copies a Tour and all of its child records into a new active Tour.
     *
     * @param array<string, mixed> $chada_travel_source
     * @return array<string, mixed>
     */
    public static function duplicate(
        object $chada_travel_wpdb,
        array $chada_travel_source,
        string $chada_travel_name,
        string $chada_travel_code,
        int $chada_travel_actor_user_id
    ): array {
        $chada_travel_source_id = (int) ($chada_travel_source['chada_travel_tour_id'] ?? 0);
        if ($chada_travel_source_id <= 0) {
            throw new \RuntimeException('The source Tour could not be found.');
        }
        $chada_travel_used_slugs = [];
        foreach (self::get_all($chada_travel_wpdb) as $chada_travel_existing_tour) {
            $chada_travel_existing_slug = (string) ($chada_travel_existing_tour['chada_travel_tour_slug'] ?? '');
            if ($chada_travel_existing_slug !== '') {
                $chada_travel_used_slugs[$chada_travel_existing_slug] = true;
            }
        }
        $chada_travel_source_dates = self::get_dates($chada_travel_wpdb, $chada_travel_source_id);
        $chada_travel_source_files = self::get_files($chada_travel_wpdb, $chada_travel_source_id);
        $chada_travel_dates = $chada_travel_source_dates;
        $chada_travel_files = $chada_travel_source_files;
        $chada_travel_clean = [
            'tour_id' => 0,
            'feature_image_attachment_id' => (int) ($chada_travel_source['chada_travel_feature_image_attachment_id'] ?? 0),
            'is_featured' => !empty($chada_travel_source['chada_travel_is_featured']) ? 1 : 0,
            'tour_name' => $chada_travel_name,
            'tour_slug' => CHADA_TRAVEL_Tour_Slug_Service::unique($chada_travel_name, $chada_travel_used_slugs),
            'description' => (string) ($chada_travel_source['chada_travel_description'] ?? ''),
            'trip_includes' => (string) ($chada_travel_source['chada_travel_trip_includes'] ?? ''),
            'trip_excludes' => (string) ($chada_travel_source['chada_travel_trip_excludes'] ?? ''),
            'basic_visa_requirements' => (string) ($chada_travel_source['chada_travel_basic_visa_requirements'] ?? ''),
            'itinerary' => (string) ($chada_travel_source['chada_travel_itinerary'] ?? ''),
            'booking_conditions' => (string) ($chada_travel_source['chada_travel_booking_conditions'] ?? ''),
            'tour_code' => $chada_travel_code,
            'price' => (string) ($chada_travel_source['chada_travel_price'] ?? '0.00'),
            'travel_dates' => array_map(static fn(array $chada_travel_date): array => [
                'start_date' => (string) ($chada_travel_date['chada_travel_start_date'] ?? ''),
                'end_date' => (string) ($chada_travel_date['chada_travel_end_date'] ?? ''),
                'sort_order' => (int) ($chada_travel_date['chada_travel_sort_order'] ?? 0),
            ], $chada_travel_dates),
            'destination_ids' => self::get_term_ids($chada_travel_wpdb, $chada_travel_source_id, self::TAXONOMY_DESTINATION),
            'type_ids' => self::get_term_ids($chada_travel_wpdb, $chada_travel_source_id, self::TAXONOMY_TYPE),
            'downloadable_file_ids' => array_map(
                'intval',
                array_column($chada_travel_files, 'chada_travel_attachment_id')
            ),
        ];
        $chada_travel_event_meta = [
            'source_tour_id' => $chada_travel_source_id,
            'travel_dates_copied' => count($chada_travel_dates),
            'travel_dates_truncated' => count($chada_travel_source_dates) > count($chada_travel_dates),
            'downloadable_files_copied' => count($chada_travel_files),
            'downloadable_files_truncated' => count($chada_travel_source_files) > count($chada_travel_files),
        ];
        return self::save(
            $chada_travel_wpdb,
            $chada_travel_clean,
            (string) ($chada_travel_source['chada_travel_currency'] ?? ''),
            $chada_travel_actor_user_id,
            'chada_travel_tour_duplicated',
            $chada_travel_event_meta
        );
    }

    /** @param list<int> $chada_travel_attachment_ids */
    private static function replace_files(object $chada_travel_wpdb, int $chada_travel_tour_id, array $chada_travel_attachment_ids, string $chada_travel_now): void {
        if ($chada_travel_wpdb->delete(self::files_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_tour_id], ['%d']) === false) {
            throw new \RuntimeException('Downloadable Files could not be replaced.');
        }
        self::assert_no_database_error($chada_travel_wpdb, 'Downloadable Files could not be replaced.');
        foreach (array_values($chada_travel_attachment_ids) as $chada_travel_sort_order => $chada_travel_attachment_id) {
            $chada_travel_inserted = $chada_travel_wpdb->insert(self::files_table($chada_travel_wpdb), [
                'chada_travel_tour_id' => $chada_travel_tour_id,
                'chada_travel_attachment_id' => $chada_travel_attachment_id,
                'chada_travel_sort_order' => $chada_travel_sort_order,
                'chada_travel_created_at' => $chada_travel_now,
                'chada_travel_updated_at' => $chada_travel_now,
            ], ['%d', '%d', '%d', '%s', '%s']);
            if (!$chada_travel_inserted) {
                throw new \RuntimeException('Downloadable Files save failed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Downloadable Files save failed.');
        }
    }

    /** @param list<array{start_date: string, end_date: string, sort_order: int}> $chada_travel_dates */
    private static function replace_dates(object $chada_travel_wpdb, int $chada_travel_tour_id, array $chada_travel_dates, string $chada_travel_now): void {
        if ($chada_travel_wpdb->delete(self::dates_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_tour_id], ['%d']) === false) {
            throw new \RuntimeException('Travel Dates could not be replaced.');
        }
        self::assert_no_database_error($chada_travel_wpdb, 'Travel Dates could not be replaced.');
        foreach ($chada_travel_dates as $chada_travel_date) {
            $chada_travel_inserted = $chada_travel_wpdb->insert(self::dates_table($chada_travel_wpdb), [
                'chada_travel_tour_id' => $chada_travel_tour_id,
                'chada_travel_start_date' => $chada_travel_date['start_date'],
                'chada_travel_end_date' => $chada_travel_date['end_date'],
                'chada_travel_sort_order' => $chada_travel_date['sort_order'],
                'chada_travel_created_at' => $chada_travel_now,
                'chada_travel_updated_at' => $chada_travel_now,
            ], ['%d', '%s', '%s', '%d', '%s', '%s']);
            if (!$chada_travel_inserted) {
                throw new \RuntimeException('Travel Date save failed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Travel Date save failed.');
        }
    }

    /**
     * @param list<int> $chada_travel_destination_ids
     * @param list<int> $chada_travel_type_ids
     */
    private static function replace_terms(
        object $chada_travel_wpdb,
        int $chada_travel_tour_id,
        array $chada_travel_destination_ids,
        array $chada_travel_type_ids,
        string $chada_travel_now
    ): void {
        if ($chada_travel_wpdb->delete(self::relationships_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_tour_id], ['%d']) === false) {
            throw new \RuntimeException('Tour term assignments could not be replaced.');
        }
        self::assert_no_database_error($chada_travel_wpdb, 'Tour term assignments could not be replaced.');
        foreach (array_merge($chada_travel_destination_ids, $chada_travel_type_ids) as $chada_travel_term_id) {
            $chada_travel_inserted = $chada_travel_wpdb->insert(self::relationships_table($chada_travel_wpdb), [
                'chada_travel_tour_id' => $chada_travel_tour_id,
                'chada_travel_tour_term_id' => $chada_travel_term_id,
                'chada_travel_created_at' => $chada_travel_now,
            ], ['%d', '%d', '%s']);
            if (!$chada_travel_inserted) {
                throw new \RuntimeException('Tour term assignment save failed.');
            }
            self::assert_no_database_error($chada_travel_wpdb, 'Tour term assignment save failed.');
        }
    }

    private static function assert_no_database_error(object $chada_travel_wpdb, string $chada_travel_message): void {
        $chada_travel_properties = get_object_vars($chada_travel_wpdb);
        if (isset($chada_travel_properties['last_error']) && (string) $chada_travel_properties['last_error'] !== '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal diagnostic message.
            throw new \RuntimeException($chada_travel_message);
        }
    }

    /** @param array<string, mixed> $chada_travel_tour */
    public static function set_active(object $chada_travel_wpdb, array $chada_travel_tour, bool $chada_travel_active, int $chada_travel_actor_user_id): void {
        $chada_travel_wpdb->update(self::tours_table($chada_travel_wpdb), [
            'chada_travel_is_active' => $chada_travel_active ? 1 : 0,
            'chada_travel_archived_at' => $chada_travel_active ? null : gmdate('Y-m-d H:i:s'),
            'chada_travel_updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['chada_travel_tour_id' => (int) $chada_travel_tour['chada_travel_tour_id']], ['%d', '%s', '%s'], ['%d']);
        self::log_event($chada_travel_wpdb, (int) $chada_travel_tour['chada_travel_tour_id'], (string) $chada_travel_tour['chada_travel_tour_name'],
            $chada_travel_actor_user_id, $chada_travel_active ? 'chada_travel_tour_restored' : 'chada_travel_tour_archived');
    }

    /**
     * Permanently deletes one archived Tour and its internal child relationships transactionally.
     *
     * @param array<string, mixed> $chada_travel_tour
     */
    public static function delete_archived(object $chada_travel_wpdb, array $chada_travel_tour, int $chada_travel_actor_user_id): void {
        $chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0);
        if ($chada_travel_id <= 0 || !empty($chada_travel_tour['chada_travel_is_active'])) {
            throw new \RuntimeException('Only archived Tours can be deleted.');
        }
        $chada_travel_wpdb->query('START TRANSACTION');
        try {
            $chada_travel_wpdb->delete(self::dates_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_id], ['%d']);
            $chada_travel_wpdb->delete(self::files_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_id], ['%d']);
            $chada_travel_wpdb->delete(self::relationships_table($chada_travel_wpdb), ['chada_travel_tour_id' => $chada_travel_id], ['%d']);
            $chada_travel_deleted = $chada_travel_wpdb->delete(
                self::tours_table($chada_travel_wpdb),
                ['chada_travel_tour_id' => $chada_travel_id, 'chada_travel_is_active' => 0],
                ['%d', '%d']
            );
            if ($chada_travel_deleted !== 1) {
                throw new \RuntimeException('The archived Tour could not be deleted.');
            }
            self::log_event(
                $chada_travel_wpdb,
                $chada_travel_id,
                (string) ($chada_travel_tour['chada_travel_tour_name'] ?? ''),
                $chada_travel_actor_user_id,
                'chada_travel_tour_deleted',
                ['featured' => !empty($chada_travel_tour['chada_travel_is_featured'])]
            );
            $chada_travel_wpdb->query('COMMIT');
        } catch (\Throwable $chada_travel_exception) {
            $chada_travel_wpdb->query('ROLLBACK');
            throw $chada_travel_exception;
        }
    }

    /** @param array<string, mixed> $chada_travel_meta */
    private static function log_event(object $chada_travel_wpdb, int $chada_travel_tour_id, string $chada_travel_name, int $chada_travel_actor_user_id, string $chada_travel_type, array $chada_travel_meta = []): void {
        $chada_travel_meta['name'] = $chada_travel_name;
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_record_type' => 'tour',
            'chada_travel_record_id' => $chada_travel_tour_id,
            'chada_travel_event_type' => $chada_travel_type,
            'chada_travel_event_meta' => wp_json_encode($chada_travel_meta),
            'chada_travel_actor_user_id' => $chada_travel_actor_user_id,
            'chada_travel_actor_type' => 'admin',
            'chada_travel_created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
