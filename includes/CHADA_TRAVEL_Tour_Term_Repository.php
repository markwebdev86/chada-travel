<?php
/** Persistence for Tour Destinations and Tour Types. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Tour_Term_Repository {
    public const GRID_PER_PAGE = 30;

    public static function table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_tour_terms';
    }

    /** @return list<array<string, mixed>> */
    public static function get_all(object $chada_travel_wpdb, string $chada_travel_taxonomy, bool $chada_travel_include_archived = true): array {
        $chada_travel_where = $chada_travel_include_archived ? '1=1' : 'chada_travel_is_active = 1';
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::table($chada_travel_wpdb) . ' WHERE chada_travel_taxonomy = %s AND ' . $chada_travel_where
                . ' ORDER BY chada_travel_term_name ASC, chada_travel_tour_term_id ASC',
            $chada_travel_taxonomy
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function get_active_default_types(object $chada_travel_wpdb): array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::table($chada_travel_wpdb)
                . ' WHERE chada_travel_taxonomy = %s AND chada_travel_is_active = 1 AND chada_travel_is_default = 1'
                . ' ORDER BY chada_travel_term_name ASC, chada_travel_tour_term_id ASC',
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /**
     * Filters submitted Tour Types for Free while preserving existing custom assignments on an edited Tour.
     *
     * @param list<int|string> $chada_travel_requested_ids
     * @return list<int>
     */
    public static function filter_type_ids_for_free(object $chada_travel_wpdb, array $chada_travel_requested_ids, int $chada_travel_tour_id = 0): array {
        $chada_travel_requested_ids = array_values(array_unique(array_filter(array_map('intval', $chada_travel_requested_ids))));
        $chada_travel_existing_custom_ids = [];
        if ($chada_travel_tour_id > 0) {
            foreach (self::get_term_ids_for_tour($chada_travel_wpdb, $chada_travel_tour_id) as $chada_travel_term) {
                if (empty($chada_travel_term['chada_travel_is_default'])) {
                    $chada_travel_existing_custom_ids[] = (int) $chada_travel_term['chada_travel_tour_term_id'];
                }
            }
        }
        $chada_travel_candidate_ids = array_values(array_unique(array_merge($chada_travel_requested_ids, $chada_travel_existing_custom_ids)));
        if (!$chada_travel_candidate_ids) {
            return [];
        }
        $chada_travel_placeholders = implode(',', array_fill(0, count($chada_travel_candidate_ids), '%d'));
        $chada_travel_sql = 'SELECT chada_travel_tour_term_id, chada_travel_is_default, chada_travel_is_active FROM ' . self::table($chada_travel_wpdb)
            . ' WHERE chada_travel_taxonomy = %s AND chada_travel_tour_term_id IN (' . $chada_travel_placeholders . ')';
        $chada_travel_rows = $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge([CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE], $chada_travel_candidate_ids)),
            ARRAY_A
        ) ?: [];
        $chada_travel_allowed_ids = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_term_id = (int) ($chada_travel_row['chada_travel_tour_term_id'] ?? 0);
            if (!empty($chada_travel_row['chada_travel_is_default']) && !empty($chada_travel_row['chada_travel_is_active'])
                && in_array($chada_travel_term_id, $chada_travel_requested_ids, true)) {
                $chada_travel_allowed_ids[] = $chada_travel_term_id;
            }
            if (in_array($chada_travel_term_id, $chada_travel_existing_custom_ids, true)) {
                $chada_travel_allowed_ids[] = $chada_travel_term_id;
            }
        }
        return array_values(array_unique($chada_travel_allowed_ids));
    }

    /** @return list<array<string, mixed>> */
    private static function get_term_ids_for_tour(object $chada_travel_wpdb, int $chada_travel_tour_id): array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT t.chada_travel_tour_term_id, t.chada_travel_is_default FROM ' . self::table($chada_travel_wpdb) . ' t INNER JOIN '
                . $chada_travel_wpdb->prefix . 'chada_travel_tour_term_relationships r ON r.chada_travel_tour_term_id = t.chada_travel_tour_term_id '
                . 'WHERE r.chada_travel_tour_id = %d AND t.chada_travel_taxonomy = %s',
            $chada_travel_tour_id,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE
        );
        return $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_term_id): ?array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::table($chada_travel_wpdb) . ' WHERE chada_travel_tour_term_id = %d LIMIT 1',
            $chada_travel_term_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Slugs are unique per taxonomy only (see the chada_travel_taxonomy_slug composite key), not globally - a
     * Destination and a Type can legally share identical slug text, so both must be supplied.
     *
     * @return array<string, mixed>|null
     */
    public static function find_by_slug(object $chada_travel_wpdb, string $chada_travel_taxonomy, string $chada_travel_slug): ?array {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT * FROM ' . self::table($chada_travel_wpdb) . ' WHERE chada_travel_taxonomy = %s AND chada_travel_term_slug = %s LIMIT 1',
            $chada_travel_taxonomy,
            $chada_travel_slug
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public static function search_grid(object $chada_travel_wpdb, string $chada_travel_taxonomy, string $chada_travel_search, string $chada_travel_status, int $chada_travel_page): array {
        $chada_travel_where = self::where($chada_travel_wpdb, $chada_travel_taxonomy, $chada_travel_search, $chada_travel_status);
        $chada_travel_offset = (max(1, $chada_travel_page) - 1) * self::GRID_PER_PAGE;
        $chada_travel_relationships_table = $chada_travel_wpdb->prefix . 'chada_travel_tour_term_relationships';
        $chada_travel_sql = 'SELECT terms.*, (SELECT COUNT(*) FROM ' . $chada_travel_relationships_table
            . ' relationships WHERE relationships.chada_travel_tour_term_id = terms.chada_travel_tour_term_id) AS chada_travel_tour_count'
            . ' FROM ' . self::table($chada_travel_wpdb) . ' terms WHERE ' . $chada_travel_where['sql']
            . ' ORDER BY terms.chada_travel_term_name ASC, terms.chada_travel_tour_term_id ASC LIMIT %d OFFSET %d';
        return $chada_travel_wpdb->get_results(
            $chada_travel_wpdb->prepare($chada_travel_sql, array_merge($chada_travel_where['params'], [self::GRID_PER_PAGE, $chada_travel_offset])),
            ARRAY_A
        ) ?: [];
    }

    public static function count_grid(object $chada_travel_wpdb, string $chada_travel_taxonomy, string $chada_travel_search, string $chada_travel_status): int {
        $chada_travel_where = self::where($chada_travel_wpdb, $chada_travel_taxonomy, $chada_travel_search, $chada_travel_status);
        $chada_travel_sql = 'SELECT COUNT(*) FROM ' . self::table($chada_travel_wpdb) . ' WHERE ' . $chada_travel_where['sql'];
        return (int) $chada_travel_wpdb->get_var($chada_travel_where['params']
            ? $chada_travel_wpdb->prepare($chada_travel_sql, $chada_travel_where['params']) : $chada_travel_sql);
    }

    /** @return array{sql: string, params: list<mixed>} */
    private static function where(object $chada_travel_wpdb, string $chada_travel_taxonomy, string $chada_travel_search, string $chada_travel_status): array {
        $chada_travel_conditions = ['chada_travel_taxonomy = %s'];
        $chada_travel_params = [$chada_travel_taxonomy];
        if ($chada_travel_status === 'active') {
            $chada_travel_conditions[] = 'chada_travel_is_active = 1';
        } elseif ($chada_travel_status === 'archived') {
            $chada_travel_conditions[] = 'chada_travel_is_active = 0';
        }
        if ($chada_travel_search !== '') {
            $chada_travel_like = '%' . $chada_travel_wpdb->esc_like($chada_travel_search) . '%';
            $chada_travel_conditions[] = 'chada_travel_term_name LIKE %s';
            $chada_travel_params[] = $chada_travel_like;
        }
        return ['sql' => implode(' AND ', $chada_travel_conditions), 'params' => $chada_travel_params];
    }

    /**
     * Upserts one workbook taxonomy row while the caller owns the surrounding transaction.
     *
     * Imported terms never alter the built-in/default marker of an existing term.
     */
    public static function upsert_imported_in_transaction(
        object $chada_travel_wpdb,
        string $chada_travel_taxonomy,
        string $chada_travel_slug,
        string $chada_travel_name,
        bool $chada_travel_active,
        int $chada_travel_sort_order,
        int $chada_travel_actor_user_id
    ): int {
        if (!in_array($chada_travel_taxonomy, [CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE], true)) {
            throw new \RuntimeException('The workbook contains an unsupported Tour taxonomy.');
        }
        $chada_travel_existing = self::find_by_slug($chada_travel_wpdb, $chada_travel_taxonomy, $chada_travel_slug);
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_data = [
            'chada_travel_taxonomy' => $chada_travel_taxonomy,
            'chada_travel_term_name' => $chada_travel_name,
            'chada_travel_term_slug' => $chada_travel_slug,
            'chada_travel_is_active' => $chada_travel_active ? 1 : 0,
            'chada_travel_sort_order' => max(0, $chada_travel_sort_order),
            'chada_travel_updated_at' => $chada_travel_now,
            'chada_travel_archived_at' => $chada_travel_active ? null : ($chada_travel_existing && empty($chada_travel_existing['chada_travel_is_active'])
                ? ($chada_travel_existing['chada_travel_archived_at'] ?? $chada_travel_now) : $chada_travel_now),
        ];
        if ($chada_travel_existing) {
            $chada_travel_id = (int) $chada_travel_existing['chada_travel_tour_term_id'];
            $chada_travel_updated = $chada_travel_wpdb->update(
                self::table($chada_travel_wpdb),
                $chada_travel_data,
                ['chada_travel_tour_term_id' => $chada_travel_id],
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s'],
                ['%d']
            );
            if ($chada_travel_updated === false) {
                throw new \RuntimeException('Tour taxonomy update failed.');
            }
        } else {
            $chada_travel_data['chada_travel_is_default'] = 0;
            $chada_travel_data['chada_travel_created_at'] = $chada_travel_now;
            $chada_travel_inserted = $chada_travel_wpdb->insert(
                self::table($chada_travel_wpdb),
                $chada_travel_data,
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s']
            );
            if (!$chada_travel_inserted) {
                throw new \RuntimeException('Tour taxonomy creation failed.');
            }
            $chada_travel_id = (int) $chada_travel_wpdb->insert_id;
        }
        if (isset($chada_travel_wpdb->last_error) && (string) $chada_travel_wpdb->last_error !== '') {
            throw new \RuntimeException('Tour taxonomy persistence failed.');
        }
        self::log_event($chada_travel_wpdb, $chada_travel_id, $chada_travel_name, $chada_travel_actor_user_id, 'chada_travel_tour_term_imported', [
            'action' => $chada_travel_existing ? 'updated' : 'created',
            'taxonomy' => $chada_travel_taxonomy,
        ]);
        return $chada_travel_id;
    }

    /** @param array<string, mixed> $chada_travel_term */
    public static function save(object $chada_travel_wpdb, array $chada_travel_term, string $chada_travel_taxonomy, int $chada_travel_actor_user_id): int {
        $chada_travel_name = function_exists('sanitize_text_field')
            ? trim(sanitize_text_field((string) $chada_travel_term['name']))
            : trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', preg_replace('/<[^>]*>/', '', (string) $chada_travel_term['name'])));
        $chada_travel_slug = self::slug($chada_travel_name);
        $chada_travel_id = (int) ($chada_travel_term['term_id'] ?? 0);
        $chada_travel_now = gmdate('Y-m-d H:i:s');
        $chada_travel_data = [
            'chada_travel_taxonomy' => $chada_travel_taxonomy,
            'chada_travel_term_name' => mb_substr($chada_travel_name, 0, 100),
            'chada_travel_term_slug' => $chada_travel_slug,
            'chada_travel_updated_at' => $chada_travel_now,
        ];
        if ($chada_travel_id > 0) {
            $chada_travel_wpdb->update(self::table($chada_travel_wpdb), $chada_travel_data, ['chada_travel_tour_term_id' => $chada_travel_id],
                ['%s', '%s', '%s', '%s'], ['%d']);
        } else {
            $chada_travel_data['chada_travel_is_active'] = 1;
            $chada_travel_data['chada_travel_is_default'] = 0;
            $chada_travel_data['chada_travel_sort_order'] = self::next_sort_order($chada_travel_wpdb, $chada_travel_taxonomy);
            $chada_travel_data['chada_travel_created_at'] = $chada_travel_now;
            $chada_travel_wpdb->insert(self::table($chada_travel_wpdb), $chada_travel_data,
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']);
            $chada_travel_id = (int) $chada_travel_wpdb->insert_id;
        }
        self::log_event($chada_travel_wpdb, $chada_travel_id, $chada_travel_data['chada_travel_term_name'], $chada_travel_actor_user_id,
            $chada_travel_id > 0 ? ($chada_travel_term['term_id'] ?? 0 ? 'chada_travel_tour_term_updated' : 'chada_travel_tour_term_created') : 'chada_travel_tour_term_created');
        return $chada_travel_id;
    }

    private static function next_sort_order(object $chada_travel_wpdb, string $chada_travel_taxonomy): int {
        return (int) $chada_travel_wpdb->get_var($chada_travel_wpdb->prepare(
            'SELECT COALESCE(MAX(chada_travel_sort_order), 0) + 1 FROM ' . self::table($chada_travel_wpdb) . ' WHERE chada_travel_taxonomy = %s',
            $chada_travel_taxonomy
        ));
    }

    /** @param array<string, mixed> $chada_travel_term */
    public static function set_active(object $chada_travel_wpdb, array $chada_travel_term, bool $chada_travel_active, int $chada_travel_actor_user_id): void {
        $chada_travel_wpdb->update(self::table($chada_travel_wpdb), [
            'chada_travel_is_active' => $chada_travel_active ? 1 : 0,
            'chada_travel_archived_at' => $chada_travel_active ? null : gmdate('Y-m-d H:i:s'),
            'chada_travel_updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['chada_travel_tour_term_id' => (int) $chada_travel_term['chada_travel_tour_term_id']], ['%d', '%s', '%s'], ['%d']);
        self::log_event($chada_travel_wpdb, (int) $chada_travel_term['chada_travel_tour_term_id'], (string) $chada_travel_term['chada_travel_term_name'],
            $chada_travel_actor_user_id, $chada_travel_active ? 'chada_travel_tour_term_restored' : 'chada_travel_tour_term_archived');
    }

    public static function count_tours(object $chada_travel_wpdb, int $chada_travel_term_id): int {
        $chada_travel_sql = $chada_travel_wpdb->prepare(
            'SELECT COUNT(DISTINCT chada_travel_tour_id) FROM ' . $chada_travel_wpdb->prefix . 'chada_travel_tour_term_relationships WHERE chada_travel_tour_term_id = %d',
            $chada_travel_term_id
        );
        return (int) $chada_travel_wpdb->get_var($chada_travel_sql);
    }

    /**
     * Permanently deletes one archived, unused Tour taxonomy record and its internal relationships.
     *
     * @param array<string, mixed> $chada_travel_term
     */
    public static function delete_archived(
        object $chada_travel_wpdb,
        array $chada_travel_term,
        int $chada_travel_actor_user_id
    ): void {
        $chada_travel_id = (int) ($chada_travel_term['chada_travel_tour_term_id'] ?? 0);
        if ($chada_travel_id <= 0 || !empty($chada_travel_term['chada_travel_is_active'])) {
            throw new \RuntimeException('Only archived Tour taxonomy records can be deleted.');
        }
        $chada_travel_wpdb->query('START TRANSACTION');
        try {
            if (self::count_tours($chada_travel_wpdb, $chada_travel_id) > 0) {
                throw new \RuntimeException('A Tour taxonomy record used by a Tour cannot be deleted.');
            }
            $chada_travel_wpdb->delete(
                $chada_travel_wpdb->prefix . 'chada_travel_tour_term_relationships',
                ['chada_travel_tour_term_id' => $chada_travel_id],
                ['%d']
            );
            $chada_travel_deleted = $chada_travel_wpdb->delete(self::table($chada_travel_wpdb), [
                'chada_travel_tour_term_id' => $chada_travel_id,
                'chada_travel_is_active' => 0,
            ], ['%d', '%d']);
            if ($chada_travel_deleted !== 1) {
                throw new \RuntimeException('The archived Tour taxonomy record could not be deleted.');
            }
            self::log_event($chada_travel_wpdb, $chada_travel_id, (string) ($chada_travel_term['chada_travel_term_name'] ?? ''), $chada_travel_actor_user_id, 'chada_travel_tour_term_deleted');
            $chada_travel_wpdb->query('COMMIT');
        } catch (\Throwable $chada_travel_exception) {
            $chada_travel_wpdb->query('ROLLBACK');
            throw $chada_travel_exception;
        }
    }

    public static function slug(string $chada_travel_name): string {
        $chada_travel_slug = strtolower(trim($chada_travel_name));
        $chada_travel_slug = preg_replace('/[^a-z0-9]+/', '-', $chada_travel_slug) ?? '';
        return trim($chada_travel_slug, '-');
    }

    /** @param array<string, mixed> $chada_travel_meta */
    private static function log_event(object $chada_travel_wpdb, int $chada_travel_id, string $chada_travel_name, int $chada_travel_actor_user_id, string $chada_travel_type, array $chada_travel_meta = []): void {
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_record_type' => 'tour_term', 'chada_travel_record_id' => $chada_travel_id, 'chada_travel_event_type' => $chada_travel_type,
            'chada_travel_event_meta' => wp_json_encode(array_merge(['name' => $chada_travel_name], $chada_travel_meta)), 'chada_travel_actor_user_id' => $chada_travel_actor_user_id,
            'chada_travel_actor_type' => 'admin', 'chada_travel_created_at' => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
