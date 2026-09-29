<?php
/**
 * Pure validation/sanitization for the administrator Add/Edit Visa Country form.
 *
 * Contains no WordPress database access so it can be exercised directly by PHPUnit. The `chada_travel_country_fees`
 * option remains the single source of truth for checkout country/fee data; this class only governs the
 * administrator-entered values and their display order.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Country_Validator {
    public const MAX_NAME_LENGTH = 120;
    public const MAX_CHECKLIST_LENGTH = 40;
    public const MAX_FULL_DETAILS_LENGTH = 50000;

    /**
     * @param array<string, mixed>        $chada_travel_input        Raw submitted form fields.
     * @param list<array<string, mixed>>  $chada_travel_existing     Current country list.
     * @param string                      $chada_travel_editing_code Empty when adding; the original code when editing.
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(
        array $chada_travel_input,
        array $chada_travel_existing,
        string $chada_travel_editing_code = '',
        bool $chada_travel_is_active = false
    ): array {
        $chada_travel_errors  = [];
        $chada_travel_name    = self::clean_text($chada_travel_input['name'] ?? '', self::MAX_NAME_LENGTH);
        $chada_travel_code    = strtoupper(self::clean_text($chada_travel_input['code'] ?? '', 8));
        $chada_travel_fee_raw = $chada_travel_input['processing_fee'] ?? '';
        $chada_travel_checklist = self::clean_text($chada_travel_input['checklist_version'] ?? '', self::MAX_CHECKLIST_LENGTH);
        $chada_travel_full_details = self::sanitize_full_details($chada_travel_input['full_details'] ?? '');
        $chada_travel_guide_attachment_id = max(0, (int) ($chada_travel_input['guide_attachment_id'] ?? 0));
        $chada_travel_checklist_attachment_id = max(0, (int) ($chada_travel_input['checklist_attachment_id'] ?? 0));

        if ($chada_travel_name === '') {
            $chada_travel_errors['name'] = 'Country name is required.';
        }
        if ($chada_travel_code === '' || !preg_match('/^[A-Z]{2}$/', $chada_travel_code)) {
            $chada_travel_errors['code'] = 'Enter a valid 2-letter ISO country code.';
        } elseif (self::code_exists($chada_travel_existing, $chada_travel_code, $chada_travel_editing_code)) {
            $chada_travel_errors['code'] = 'This ISO code is already used by another country.';
        }
        if ($chada_travel_fee_raw === '' || !is_numeric($chada_travel_fee_raw) || (float) $chada_travel_fee_raw < 0) {
            $chada_travel_errors['processing_fee'] = 'Enter a Processing Fee of 0 or more.';
        }
        if ($chada_travel_checklist === '') {
            $chada_travel_errors['checklist_version'] = 'Checklist version is required.';
        }
        if ($chada_travel_is_active && self::is_full_details_empty($chada_travel_full_details)) {
            $chada_travel_errors['full_details'] = 'Full Details are required while this country is active.';
        }
        if ($chada_travel_is_active && $chada_travel_guide_attachment_id <= 0) {
            $chada_travel_errors['guide_attachment_id'] = 'A Step-by-Step Guide image is required while active.';
        }
        if ($chada_travel_is_active && $chada_travel_checklist_attachment_id <= 0) {
            $chada_travel_errors['checklist_attachment_id'] = 'A Documents Checklist file is required while active.';
        }

        return [
            'errors' => $chada_travel_errors,
            'clean'  => [
                'name'              => $chada_travel_name,
                'code'              => $chada_travel_code,
                'processing_fee'    => number_format(max(0, (float) $chada_travel_fee_raw), 2, '.', ''),
                'checklist_version' => $chada_travel_checklist,
                'full_details' => $chada_travel_full_details,
                'guide_attachment_id' => $chada_travel_guide_attachment_id,
                'checklist_attachment_id' => $chada_travel_checklist_attachment_id,
            ],
        ];
    }

    /** @param list<array<string, mixed>> $chada_travel_countries */
    private static function code_exists(array $chada_travel_countries, string $chada_travel_code, string $chada_travel_editing_code): bool {
        foreach ($chada_travel_countries as $chada_travel_country) {
            if ((string) ($chada_travel_country['code'] ?? '') === $chada_travel_code
                && (string) ($chada_travel_country['code'] ?? '') !== $chada_travel_editing_code
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_countries
     * @return list<array<string, mixed>>
     */
    public static function sort_by_order(array $chada_travel_countries): array {
        usort(
            $chada_travel_countries,
            static fn(array $chada_travel_a, array $chada_travel_b): int =>
                ((int) ($chada_travel_a['sort_order'] ?? 0)) <=> ((int) ($chada_travel_b['sort_order'] ?? 0))
        );
        return array_values($chada_travel_countries);
    }

    /** @param list<array<string, mixed>> $chada_travel_countries */
    public static function next_sort_order(array $chada_travel_countries): int {
        $chada_travel_max = 0;
        foreach ($chada_travel_countries as $chada_travel_country) {
            $chada_travel_max = max($chada_travel_max, (int) ($chada_travel_country['sort_order'] ?? 0));
        }
        return $chada_travel_max + 1;
    }

    /**
     * Adds a new country or replaces the one matching $chada_travel_editing_code, preserving its sort order and
     * active status; returns the full list re-sorted by display order.
     *
     * @param list<array<string, mixed>> $chada_travel_countries
     * @param array<string, mixed>       $chada_travel_clean
     * @return list<array<string, mixed>>
     */
    public static function upsert(
        array $chada_travel_countries,
        array $chada_travel_clean,
        string $chada_travel_editing_code,
        string $chada_travel_default_currency = 'USD'
    ): array {
        $chada_travel_found  = false;
        $chada_travel_result = [];
        foreach ($chada_travel_countries as $chada_travel_country) {
            if ($chada_travel_editing_code !== '' && (string) ($chada_travel_country['code'] ?? '') === $chada_travel_editing_code) {
                $chada_travel_result[] = array_merge($chada_travel_country, $chada_travel_clean);
                $chada_travel_found    = true;
                continue;
            }
            $chada_travel_result[] = $chada_travel_country;
        }
        if (!$chada_travel_found) {
            $chada_travel_result[] = array_merge($chada_travel_clean, [
                'currency'   => $chada_travel_default_currency,
                'sort_order' => self::next_sort_order($chada_travel_countries),
                'is_active'  => 0,
            ]);
        }
        return self::sort_by_order($chada_travel_result);
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_countries
     * @return list<array<string, mixed>>
     */
    public static function set_active(array $chada_travel_countries, string $chada_travel_code, bool $chada_travel_active): array {
        foreach ($chada_travel_countries as &$chada_travel_country) {
            if ((string) ($chada_travel_country['code'] ?? '') === $chada_travel_code) {
                $chada_travel_country['is_active'] = $chada_travel_active ? 1 : 0;
            }
        }
        unset($chada_travel_country);
        return $chada_travel_countries;
    }

    /** @param array<string, mixed> $chada_travel_country */
    public static function is_frontend_ready(array $chada_travel_country): bool {
        return !self::is_full_details_empty((string) ($chada_travel_country['full_details'] ?? ''))
            && (int) ($chada_travel_country['guide_attachment_id'] ?? 0) > 0;
    }

    /** @param array<string, mixed> $chada_travel_country */
    public static function is_resource_ready(array $chada_travel_country): bool {
        return self::is_frontend_ready($chada_travel_country)
            && (int) ($chada_travel_country['checklist_attachment_id'] ?? 0) > 0;
    }

    /** @param mixed $chada_travel_value */
    private static function clean_text($chada_travel_value, int $chada_travel_max_length): string {
        if (function_exists('sanitize_text_field')) {
            return mb_substr(sanitize_text_field((string) $chada_travel_value), 0, $chada_travel_max_length);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return mb_substr(trim(strip_tags((string) $chada_travel_value)), 0, $chada_travel_max_length);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_full_details($chada_travel_value): string {
        $chada_travel_value = (string) $chada_travel_value;
        if (function_exists('wp_kses_post')) {
            return mb_substr(wp_kses_post($chada_travel_value), 0, self::MAX_FULL_DETAILS_LENGTH);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free test fallback.
        return mb_substr(trim(strip_tags($chada_travel_value)), 0, self::MAX_FULL_DETAILS_LENGTH);
    }

    /** Returns true when Full Details contains no visible text, including empty TinyMCE paragraph markup. */
    public static function is_full_details_empty(string $chada_travel_value): bool {
        $chada_travel_text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($chada_travel_value) : strip_tags($chada_travel_value);
        $chada_travel_text = html_entity_decode($chada_travel_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\xc2\xa0", ' ', $chada_travel_text)) === '';
    }
}
