<?php
/** Generates unique Tour duplicate labels and delegates transactional persistence. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Tour_Duplicate_Service {
    private const NAME_SUFFIX = '-DUPLICATE';
    private const CODE_SUFFIX = '-DUP-';

    /**
     * Duplicates a Tour with all child relationships and returns the new Tour row.
     *
     * @return array<string, mixed>
     */
    public static function duplicate(object $chada_travel_wpdb, int $chada_travel_source_id, int $chada_travel_actor_user_id): array {
        $chada_travel_source = CHADA_TRAVEL_Tour_Repository::find_by_id($chada_travel_wpdb, $chada_travel_source_id);
        if (!$chada_travel_source) {
            throw new \RuntimeException('The source Tour could not be found.');
        }
        $chada_travel_existing = CHADA_TRAVEL_Tour_Repository::get_all($chada_travel_wpdb);
        $chada_travel_name = self::unique_name((string) ($chada_travel_source['chada_travel_tour_name'] ?? ''), $chada_travel_existing);
        $chada_travel_code = self::unique_code((string) ($chada_travel_source['chada_travel_tour_code'] ?? ''), $chada_travel_existing);
        return CHADA_TRAVEL_Tour_Repository::duplicate(
            $chada_travel_wpdb,
            $chada_travel_source,
            $chada_travel_name,
            $chada_travel_code,
            $chada_travel_actor_user_id
        );
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_existing
     */
    public static function unique_name(string $chada_travel_source_name, array $chada_travel_existing): string {
        $chada_travel_base = preg_replace('/-DUPLICATE(?:-\d+)?$/i', '', trim($chada_travel_source_name)) ?: trim($chada_travel_source_name);
        $chada_travel_existing_names = [];
        foreach ($chada_travel_existing as $chada_travel_tour) {
            $chada_travel_existing_names[strtolower((string) ($chada_travel_tour['chada_travel_tour_name'] ?? ''))] = true;
        }
        for ($chada_travel_number = 1; $chada_travel_number <= 9999; $chada_travel_number++) {
            $chada_travel_suffix = self::NAME_SUFFIX . ($chada_travel_number > 1 ? '-' . $chada_travel_number : '');
            $chada_travel_candidate = self::fit_suffix($chada_travel_base, $chada_travel_suffix, CHADA_TRAVEL_Tour_Validator::MAX_NAME_LENGTH);
            if (!isset($chada_travel_existing_names[strtolower($chada_travel_candidate)])) {
                return $chada_travel_candidate;
            }
        }
        throw new \RuntimeException('A unique duplicate Tour Name could not be generated.');
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_existing
     */
    public static function unique_code(string $chada_travel_source_code, array $chada_travel_existing): string {
        $chada_travel_normalized_code = strtoupper(trim($chada_travel_source_code));
        $chada_travel_base = preg_replace('/-DUP-\d+$/i', '', $chada_travel_normalized_code) ?: $chada_travel_normalized_code;
        $chada_travel_existing_codes = [];
        foreach ($chada_travel_existing as $chada_travel_tour) {
            $chada_travel_existing_codes[strtoupper((string) ($chada_travel_tour['chada_travel_tour_code'] ?? ''))] = true;
        }
        for ($chada_travel_number = 1; $chada_travel_number <= 9999; $chada_travel_number++) {
            $chada_travel_suffix = self::CODE_SUFFIX . $chada_travel_number;
            $chada_travel_candidate = self::fit_suffix($chada_travel_base, $chada_travel_suffix, CHADA_TRAVEL_Tour_Validator::MAX_CODE_LENGTH);
            if (!isset($chada_travel_existing_codes[$chada_travel_candidate])) {
                return $chada_travel_candidate;
            }
        }
        throw new \RuntimeException('A unique duplicate Tour Code could not be generated.');
    }

    private static function fit_suffix(string $chada_travel_base, string $chada_travel_suffix, int $chada_travel_max_length): string {
        $chada_travel_available = max(0, $chada_travel_max_length - strlen($chada_travel_suffix));
        $chada_travel_prefix = $chada_travel_available > 0 ? substr($chada_travel_base, 0, $chada_travel_available) : '';
        return $chada_travel_prefix . $chada_travel_suffix;
    }
}
