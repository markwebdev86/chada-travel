<?php
/**
 * Pure validation and normalization for administrator-managed Tours.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Validator {
    public const MAX_NAME_LENGTH = 100;
    public const MAX_CODE_LENGTH = 20;
    public const MAX_RICH_TEXT_LENGTH = 200000;

    /**
     * @param array<string, mixed> $chada_travel_input
     * @param list<array<string, mixed>> $chada_travel_existing_tours
     * @param list<array<string, mixed>> $chada_travel_existing_dates Persisted dates for the Tour being edited, if any.
     * @param list<int> $chada_travel_existing_file_ids Persisted attachment IDs for the Tour being edited, if any.
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(
        array $chada_travel_input,
        array $chada_travel_existing_tours = [],
        array $chada_travel_existing_dates = [],
        array $chada_travel_existing_file_ids = []
    ): array {
        $chada_travel_errors = [];
        $chada_travel_name = self::clean_text($chada_travel_input['tour_name'] ?? '', self::MAX_NAME_LENGTH);
        $chada_travel_code = self::clean_code($chada_travel_input['tour_code'] ?? '');
        $chada_travel_slug_input = trim((string) ($chada_travel_input['tour_slug'] ?? ''));
        $chada_travel_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize(
            $chada_travel_slug_input !== '' ? $chada_travel_slug_input : $chada_travel_name
        );
        $chada_travel_currency_input = array_key_exists('currency', $chada_travel_input)
            ? trim((string) $chada_travel_input['currency']) : CHADA_TRAVEL_Config::TOUR_DEFAULT_CURRENCY;
        $chada_travel_currency = strtoupper($chada_travel_currency_input);
        $chada_travel_price = self::clean_price($chada_travel_input['price'] ?? '');
        $chada_travel_is_featured = !empty($chada_travel_input['is_featured']) ? 1 : 0;
        $chada_travel_feature_image_id = function_exists('absint')
            ? absint($chada_travel_input['feature_image_attachment_id'] ?? 0)
            : max(0, (int) ($chada_travel_input['feature_image_attachment_id'] ?? 0));
        $chada_travel_description = self::clean_rich_text($chada_travel_input['description'] ?? '');
        $chada_travel_trip_includes = self::clean_rich_text($chada_travel_input['trip_includes'] ?? '');
        $chada_travel_trip_excludes = self::clean_rich_text($chada_travel_input['trip_excludes'] ?? '');
        $chada_travel_basic_visa_requirements = self::clean_rich_text($chada_travel_input['basic_visa_requirements'] ?? '');
        $chada_travel_itinerary = self::clean_rich_text($chada_travel_input['itinerary'] ?? '');
        $chada_travel_booking_conditions = self::clean_rich_text($chada_travel_input['booking_conditions'] ?? '');
        $chada_travel_raw_dates = $chada_travel_input['travel_dates'] ?? [];
        $chada_travel_dates = self::clean_dates($chada_travel_raw_dates);
        $chada_travel_destination_ids = self::clean_ids($chada_travel_input['destination_ids'] ?? []);
        $chada_travel_type_ids = self::clean_ids($chada_travel_input['type_ids'] ?? []);
        $chada_travel_downloadable_file_ids = self::clean_ids($chada_travel_input['downloadable_file_ids'] ?? []);

        if ($chada_travel_name === '') {
            $chada_travel_errors['tour_name'] = 'A Tour Name is required.';
        }
        if (!in_array($chada_travel_currency, CHADA_TRAVEL_Config::get_tour_currency_options(), true)) {
            $chada_travel_errors['currency'] = 'Select a valid Tour Currency.';
        }
        if ($chada_travel_slug === '') {
            $chada_travel_errors['tour_slug'] = 'A valid Tour URL slug is required.';
        } elseif (CHADA_TRAVEL_Tour_Slug_Service::exists(
            $chada_travel_existing_tours,
            $chada_travel_slug,
            (int) ($chada_travel_input['tour_id'] ?? 0)
        )) {
            $chada_travel_errors['tour_slug'] = 'This Tour URL slug is already used by another Tour.';
        }
        if ($chada_travel_feature_image_id <= 0) {
            $chada_travel_errors['feature_image_attachment_id'] = 'A Featured Image is required.';
        }
        if (self::is_rich_text_empty($chada_travel_description)) {
            $chada_travel_errors['description'] = 'A Description is required.';
        }
        if ($chada_travel_code === '') {
            $chada_travel_errors['tour_code'] = 'A Tour Code is required.';
        } elseif (self::code_exists($chada_travel_existing_tours, $chada_travel_code, (int) ($chada_travel_input['tour_id'] ?? 0))) {
            $chada_travel_errors['tour_code'] = 'This Tour Code is already used by another Tour.';
        }
        if ($chada_travel_price === null) {
            $chada_travel_errors['price'] = 'Enter a valid non-negative price with up to two decimal places.';
        }
        if (!is_array($chada_travel_raw_dates) || $chada_travel_raw_dates === []) {
            $chada_travel_errors['travel_dates'] = 'Add at least one Travel Date.';
        } elseif (self::has_invalid_date_entries($chada_travel_raw_dates)) {
            $chada_travel_errors['travel_dates'] = 'Every Travel Date must contain valid ISO dates.';
        } elseif ($chada_travel_dates === []) {
            $chada_travel_errors['travel_dates'] = 'Add at least one Travel Date.';
        }
        if (self::is_rich_text_empty($chada_travel_itinerary)) {
            $chada_travel_errors['itinerary'] = 'An Itinerary is required.';
        }
        if ($chada_travel_destination_ids === []) {
            $chada_travel_errors['destination_ids'] = 'Select at least one Tour Destination.';
        }
        if ($chada_travel_type_ids === []) {
            $chada_travel_errors['type_ids'] = 'Select at least one Tour Type.';
        }
        return [
            'errors' => $chada_travel_errors,
            'clean' => [
                'tour_id' => (int) ($chada_travel_input['tour_id'] ?? 0),
                'feature_image_attachment_id' => $chada_travel_feature_image_id,
                'is_featured' => $chada_travel_is_featured,
                'tour_name' => $chada_travel_name,
                'tour_slug' => $chada_travel_slug,
                'description' => $chada_travel_description,
                'trip_includes' => $chada_travel_trip_includes,
                'trip_excludes' => $chada_travel_trip_excludes,
                'basic_visa_requirements' => $chada_travel_basic_visa_requirements,
                'itinerary' => $chada_travel_itinerary,
                'booking_conditions' => $chada_travel_booking_conditions,
                'tour_code' => $chada_travel_code,
                'currency' => $chada_travel_currency,
                'price' => $chada_travel_price ?? '0.00',
                'travel_dates' => $chada_travel_dates,
                'destination_ids' => $chada_travel_destination_ids,
                'type_ids' => $chada_travel_type_ids,
                'downloadable_file_ids' => $chada_travel_downloadable_file_ids,
            ],
        ];
    }

    /** @param list<array<string, mixed>> $chada_travel_existing_tours */
    private static function code_exists(array $chada_travel_existing_tours, string $chada_travel_code, int $chada_travel_tour_id): bool {
        foreach ($chada_travel_existing_tours as $chada_travel_tour) {
            if ((int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0) !== $chada_travel_tour_id
                && strtoupper((string) ($chada_travel_tour['chada_travel_tour_code'] ?? '')) === $chada_travel_code) {
                return true;
            }
        }
        return false;
    }

    /** @param mixed $chada_travel_value */
    private static function clean_text($chada_travel_value, int $chada_travel_max_length): string {
        $chada_travel_cleaned_value = function_exists('sanitize_text_field')
            ? sanitize_text_field((string) $chada_travel_value)
            : trim((string) preg_replace('/[\x00-\x1F\x7F]/', '', preg_replace('/<[^>]*>/', '', (string) $chada_travel_value)));
        return mb_substr(trim($chada_travel_cleaned_value), 0, $chada_travel_max_length);
    }

    /** @param mixed $chada_travel_value */
    private static function clean_code($chada_travel_value): string {
        $chada_travel_code = strtoupper(trim(self::clean_text($chada_travel_value, self::MAX_CODE_LENGTH)));
        $chada_travel_code = preg_replace('/[^A-Z0-9_-]/', '', $chada_travel_code) ?? '';
        return mb_substr($chada_travel_code, 0, self::MAX_CODE_LENGTH);
    }

    /** @param mixed $chada_travel_value */
    private static function clean_price($chada_travel_value): ?string {
        $chada_travel_price = trim((string) $chada_travel_value);
        if ($chada_travel_price === '' || !preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $chada_travel_price)) {
            return null;
        }
        return number_format((float) $chada_travel_price, 2, '.', '');
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<int>
     */
    private static function clean_ids($chada_travel_value): array {
        if (!is_array($chada_travel_value)) {
            return [];
        }
        $chada_travel_ids = array_map(static fn($chada_travel_id): int => max(0, (int) $chada_travel_id), $chada_travel_value);
        return array_values(array_unique(array_filter($chada_travel_ids)));
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<array{start_date: string, end_date: string, sort_order: int}>
     */
    private static function clean_dates($chada_travel_value): array {
        if (!is_array($chada_travel_value)) {
            return [];
        }
        $chada_travel_dates = [];
        foreach ($chada_travel_value as $chada_travel_date) {
            if (!is_array($chada_travel_date)) {
                continue;
            }
            $chada_travel_start = self::normalize_date($chada_travel_date['start_date'] ?? '');
            $chada_travel_end = self::normalize_date($chada_travel_date['end_date'] ?? '');
            if ($chada_travel_start === null || $chada_travel_end === null) {
                continue;
            }
            $chada_travel_dates[] = [
                'date_id' => max(0, (int) ($chada_travel_date['date_id'] ?? $chada_travel_date['chada_travel_tour_date_id'] ?? 0)),
                'start_date' => $chada_travel_start,
                'end_date' => $chada_travel_end,
                'sort_order' => 0,
            ];
        }
        usort($chada_travel_dates, static fn(array $chada_travel_a, array $chada_travel_b): int =>
            [$chada_travel_a['start_date'], $chada_travel_a['end_date']] <=> [$chada_travel_b['start_date'], $chada_travel_b['end_date']]
        );
        foreach ($chada_travel_dates as $chada_travel_index => &$chada_travel_date) {
            $chada_travel_date['sort_order'] = $chada_travel_index + 1;
        }
        unset($chada_travel_date);
        return $chada_travel_dates;
    }

    /** @param mixed $chada_travel_value */
    private static function normalize_date($chada_travel_value): ?string {
        $chada_travel_date = trim((string) $chada_travel_value);
        $chada_travel_parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $chada_travel_date);
        return $chada_travel_parsed && $chada_travel_parsed->format('Y-m-d') === $chada_travel_date ? $chada_travel_date : null;
    }

    /** @param mixed $chada_travel_value */
    private static function has_invalid_date_entries($chada_travel_value): bool {
        if (!is_array($chada_travel_value) || $chada_travel_value === []) {
            return true;
        }
        foreach ($chada_travel_value as $chada_travel_date) {
            if (!is_array($chada_travel_date)) {
                return true;
            }
            $chada_travel_start = self::normalize_date($chada_travel_date['start_date'] ?? '');
            $chada_travel_end = self::normalize_date($chada_travel_date['end_date'] ?? '');
            if ($chada_travel_start === null || $chada_travel_end === null) {
                return true;
            }
        }
        return false;
    }

    /** @param mixed $chada_travel_value */
    private static function clean_rich_text($chada_travel_value): string {
        $chada_travel_text = CHADA_TRAVEL_Tour_Rich_Text_Cleaner::clean($chada_travel_value);
        return mb_substr($chada_travel_text, 0, self::MAX_RICH_TEXT_LENGTH);
    }

    /** Returns true when rich-text markup contains no visible text. */
    private static function is_rich_text_empty(string $chada_travel_value): bool {
        $chada_travel_text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($chada_travel_value)
            : (string) preg_replace('/<[^>]*>/', '', $chada_travel_value);
        $chada_travel_text = html_entity_decode($chada_travel_text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\xc2\xa0", ' ', $chada_travel_text)) === '';
    }
}
