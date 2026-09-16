<?php
/**
 * wpdb-backed persistence for uploaded visa-document versions. Enforces "one current file per requirement" by
 * always superseding the prior current row before inserting a new version; previous versions stay in the table
 * (auditable) but are excluded from find_current_for_application()/find_current().
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Document_Repository {
    public static function table(object $chada_travel_wpdb): string {
        return $chada_travel_wpdb->prefix . 'chada_travel_documents';
    }

    private static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Returns the current (non-superseded) document for one application/requirement pair, if any.
     *
     * @return array<string, mixed>|null
     */
    public static function find_current(
        object $chada_travel_wpdb,
        int $chada_travel_application_id,
        int $chada_travel_requirement_id
    ): ?array {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_application_id = %d AND chada_travel_requirement_id = %d "
                . "AND chada_travel_document_status != 'chada_travel_superseded' ORDER BY chada_travel_file_version DESC LIMIT 1",
            $chada_travel_application_id,
            $chada_travel_requirement_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Returns every current (non-superseded) document for an application, keyed by requirement ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function find_current_for_application(object $chada_travel_wpdb, int $chada_travel_application_id): array {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_application_id = %d "
                . "AND chada_travel_document_status != 'chada_travel_superseded'",
            $chada_travel_application_id
        );
        $chada_travel_rows = $chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [];
        $chada_travel_current = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_requirement_id = (int) $chada_travel_row['chada_travel_requirement_id'];
            $chada_travel_existing = $chada_travel_current[$chada_travel_requirement_id] ?? null;
            $chada_travel_is_newer = !$chada_travel_existing
                || (int) $chada_travel_row['chada_travel_file_version'] > (int) $chada_travel_existing['chada_travel_file_version'];
            if ($chada_travel_is_newer) {
                $chada_travel_current[$chada_travel_requirement_id] = $chada_travel_row;
            }
        }
        return $chada_travel_current;
    }

    /** @return array<string, mixed>|null */
    public static function find_by_id(object $chada_travel_wpdb, int $chada_travel_document_id): ?array {
        $chada_travel_table = self::table($chada_travel_wpdb);
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_document_id = %d LIMIT 1",
            $chada_travel_document_id
        );
        $chada_travel_row = $chada_travel_wpdb->get_row($chada_travel_sql, ARRAY_A);
        return $chada_travel_row ?: null;
    }

    /**
     * Supersedes the current version for this requirement (if any) and inserts the new one as
     * chada_travel_uploaded, incrementing chada_travel_file_version.
     *
     * @param array{storage_path: string, mime_type: string, original_filename: string, size: int} $chada_travel_stored
     * @return array<string, mixed> The newly inserted document row.
     */
    public static function create_version(
        object $chada_travel_wpdb,
        int $chada_travel_application_id,
        int $chada_travel_requirement_id,
        array $chada_travel_stored,
        ?int $chada_travel_uploaded_by_user_id
    ): array {
        $chada_travel_now     = self::now();
        $chada_travel_current = self::find_current($chada_travel_wpdb, $chada_travel_application_id, $chada_travel_requirement_id);
        $chada_travel_next_version = $chada_travel_current ? (int) $chada_travel_current['chada_travel_file_version'] + 1 : 1;

        if ($chada_travel_current) {
            $chada_travel_wpdb->update(
                self::table($chada_travel_wpdb),
                ['chada_travel_document_status' => 'chada_travel_superseded', 'chada_travel_superseded_at' => $chada_travel_now],
                ['chada_travel_document_id' => (int) $chada_travel_current['chada_travel_document_id']],
                ['%s', '%s'],
                ['%d']
            );
        }

        $chada_travel_data = [
            'chada_travel_application_id'      => $chada_travel_application_id,
            'chada_travel_requirement_id'      => $chada_travel_requirement_id,
            'chada_travel_storage_key'         => $chada_travel_stored['storage_path'],
            'chada_travel_original_filename'   => $chada_travel_stored['original_filename'],
            'chada_travel_mime_type'           => $chada_travel_stored['mime_type'],
            'chada_travel_file_size'           => $chada_travel_stored['size'],
            'chada_travel_file_version'        => $chada_travel_next_version,
            'chada_travel_document_status'     => 'chada_travel_uploaded',
            'chada_travel_uploaded_by_user_id' => $chada_travel_uploaded_by_user_id,
            'chada_travel_uploaded_at'         => $chada_travel_now,
        ];
        $chada_travel_wpdb->insert(self::table($chada_travel_wpdb), $chada_travel_data, [
            '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s',
        ]);
        $chada_travel_data['chada_travel_document_id'] = (int) $chada_travel_wpdb->insert_id;
        return $chada_travel_data;
    }

    /**
     * @param array<string, mixed> $chada_travel_document
     * @return array<string, mixed> The updated document row.
     */
    public static function update_status(
        object $chada_travel_wpdb,
        array $chada_travel_document,
        string $chada_travel_status,
        ?string $chada_travel_review_note,
        int $chada_travel_reviewed_by_user_id
    ): array {
        $chada_travel_data = [
            'chada_travel_document_status'     => $chada_travel_status,
            'chada_travel_review_note'         => $chada_travel_review_note,
            'chada_travel_reviewed_by_user_id' => $chada_travel_reviewed_by_user_id,
            'chada_travel_reviewed_at'         => self::now(),
        ];
        $chada_travel_wpdb->update(
            self::table($chada_travel_wpdb),
            $chada_travel_data,
            ['chada_travel_document_id' => (int) $chada_travel_document['chada_travel_document_id']],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
        return array_merge($chada_travel_document, $chada_travel_data);
    }

    /** @param array<string, mixed> $chada_travel_meta Safe metadata; never secrets or document content. */
    public static function log_event(
        object $chada_travel_wpdb,
        ?int $chada_travel_order_id,
        ?int $chada_travel_application_id,
        int $chada_travel_document_id,
        string $chada_travel_event_type,
        string $chada_travel_actor_type = 'system',
        array $chada_travel_meta = [],
        ?int $chada_travel_actor_user_id = null
    ): void {
        $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
            'chada_travel_order_id'       => $chada_travel_order_id,
            'chada_travel_application_id' => $chada_travel_application_id,
            'chada_travel_record_type'    => 'document',
            'chada_travel_record_id'      => $chada_travel_document_id,
            'chada_travel_event_type'     => $chada_travel_event_type,
            'chada_travel_event_meta'     => function_exists('wp_json_encode')
                ? wp_json_encode($chada_travel_meta) : json_encode($chada_travel_meta),
            'chada_travel_actor_user_id'  => $chada_travel_actor_user_id,
            'chada_travel_actor_type'     => $chada_travel_actor_type,
            'chada_travel_created_at'     => self::now(),
        ], ['%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s']);
    }
}
