<?php
/**
 * Builds, encodes, and safely decodes the immutable per-order policy snapshot stored in
 * `chada_travel_orders.chada_travel_policy_snapshot`. The snapshot freezes the Policy Version, Effective Date, and each
 * policy's resolved source/page id/URL at the moment a Booker accepts them, so a later Settings change can
 * never silently alter what an existing order legally recorded. Never trusts a snapshot, version, effective
 * date, page id, or policy URL submitted by the browser - always built server-side from resolved Settings via
 * build().
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Policy_Snapshot {
    public const SCHEMA_VERSION = 1;

    /**
     * Builds a versioned, normalized snapshot from the current resolved Settings. Callers must only call this
     * after confirming CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings)['ready'] is true - this method
     * does not itself re-check readiness, since some callers (e.g. a future scheduled-activation feature) may
     * need to build a snapshot from a bundle validated slightly differently.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, mixed>
     */
    public static function build(array $chada_travel_settings): array {
        $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::resolve_effective_bundle($chada_travel_settings);
        $chada_travel_data = [
            'schema_version' => self::SCHEMA_VERSION,
            'policy_version' => (string) ($chada_travel_settings['chada_travel_policy_version'] ?? ''),
            'effective_date' => (string) ($chada_travel_settings['chada_travel_policy_effective_date'] ?? ''),
            'captured_at'    => gmdate('Y-m-d H:i:s'),
        ];
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_resolved = $chada_travel_bundle[$chada_travel_key] ?? ['source' => 'none', 'page_id' => 0, 'url' => ''];
            $chada_travel_data[$chada_travel_definition['snapshot_key']] = [
                'source'  => (string) $chada_travel_resolved['source'],
                'page_id' => (int) $chada_travel_resolved['page_id'],
                'url'     => (string) $chada_travel_resolved['url'],
            ];
        }
        return $chada_travel_data;
    }

    /** @param array<string, mixed> $chada_travel_data */
    public static function encode(array $chada_travel_data): string {
        return function_exists('wp_json_encode') ? (string) wp_json_encode($chada_travel_data)
            : (string) json_encode($chada_travel_data);
    }

    /**
     * Defensively decodes a stored snapshot. Returns null - never throws, never fatals - for an empty/null
     * column (an order predating this column), malformed JSON, a non-array payload, an unrecognized
     * schema_version, or a payload missing any registered policy key, so callers can safely branch on "existing
     * record, no detailed snapshot available" without special-casing every failure mode individually.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(?string $chada_travel_json): ?array {
        if (!$chada_travel_json) {
            return null;
        }
        $chada_travel_decoded = json_decode($chada_travel_json, true);
        if (!is_array($chada_travel_decoded) || (int) ($chada_travel_decoded['schema_version'] ?? 0) !== self::SCHEMA_VERSION) {
            return null;
        }
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_definition) {
            $chada_travel_key = $chada_travel_definition['snapshot_key'];
            if (!isset($chada_travel_decoded[$chada_travel_key]) || !is_array($chada_travel_decoded[$chada_travel_key])) {
                return null;
            }
        }
        return $chada_travel_decoded;
    }
}
