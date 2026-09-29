<?php
/**
 * Pure validation/sanitization for the administrator Add/Edit Requirement form. Contains no WordPress database
 * access so it can be exercised directly by PHPUnit, mirroring CHADA_TRAVEL_Country_Validator/CHADA_TRAVEL_Tag_Validator.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Requirement_Validator {
    public const MAX_LABEL_LENGTH = 190;
    public const MAX_DESCRIPTION_LENGTH = 2000;
    public const MAX_CHECKLIST_LENGTH = 40;
    public const MIN_MAX_BYTES = 1024;
    public const MAX_MAX_BYTES = 52428800;

    /** @return list<string> The allowed document MIME-type choices offered by the Add/Edit Requirement form. */
    public static function get_allowed_mime_choices(): array {
        return ['application/pdf', 'image/jpeg', 'image/png'];
    }

    /**
     * @param array<string, mixed> $chada_travel_input Raw submitted form fields.
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate(array $chada_travel_input): array {
        $chada_travel_errors     = [];
        $chada_travel_country_id = (int) ($chada_travel_input['country_id'] ?? 0);
        $chada_travel_checklist  = self::clean_text($chada_travel_input['checklist_version'] ?? '', self::MAX_CHECKLIST_LENGTH);
        $chada_travel_label      = self::clean_text($chada_travel_input['label'] ?? '', self::MAX_LABEL_LENGTH);
        $chada_travel_description = self::clean_textarea($chada_travel_input['description'] ?? '');
        $chada_travel_mime_types  = self::clean_mime_types($chada_travel_input['mime_types'] ?? []);
        $chada_travel_max_bytes_raw = $chada_travel_input['max_bytes'] ?? '';
        $chada_travel_sort_order  = max(0, (int) ($chada_travel_input['sort_order'] ?? 0));
        $chada_travel_form_attachment_id = max(0, (int) ($chada_travel_input['form_attachment_id'] ?? 0));

        if ($chada_travel_country_id <= 0) {
            $chada_travel_errors['country_id'] = 'Choose a country.';
        }
        if ($chada_travel_checklist === '') {
            $chada_travel_errors['checklist_version'] = 'Checklist version is required.';
        }
        if ($chada_travel_label === '') {
            $chada_travel_errors['label'] = 'A requirement label is required.';
        }
        if (!$chada_travel_mime_types) {
            $chada_travel_errors['mime_types'] = 'Choose at least one allowed file type.';
        }
        $chada_travel_max_bytes = (int) $chada_travel_max_bytes_raw;
        if ($chada_travel_max_bytes_raw === '' || $chada_travel_max_bytes < self::MIN_MAX_BYTES
            || $chada_travel_max_bytes > self::MAX_MAX_BYTES
        ) {
            $chada_travel_errors['max_bytes'] = 'Enter a maximum file size between 1 KB and 50 MB.';
        }

        return [
            'errors' => $chada_travel_errors,
            'clean'  => [
                'country_id'         => $chada_travel_country_id,
                'checklist_version'  => $chada_travel_checklist,
                'label'              => $chada_travel_label,
                'description'        => $chada_travel_description,
                'is_required'        => !empty($chada_travel_input['is_required']),
                'mime_types'         => $chada_travel_mime_types,
                'max_bytes'          => max(self::MIN_MAX_BYTES, min(self::MAX_MAX_BYTES, $chada_travel_max_bytes)),
                'sort_order'         => $chada_travel_sort_order,
                'form_attachment_id' => $chada_travel_form_attachment_id,
            ],
        ];
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<string>
     */
    private static function clean_mime_types($chada_travel_value): array {
        $chada_travel_submitted = is_array($chada_travel_value) ? $chada_travel_value : [];
        return array_values(array_intersect(self::get_allowed_mime_choices(), $chada_travel_submitted));
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
    private static function clean_textarea($chada_travel_value): string {
        if (function_exists('sanitize_textarea_field')) {
            return mb_substr(sanitize_textarea_field((string) $chada_travel_value), 0, self::MAX_DESCRIPTION_LENGTH);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return mb_substr(trim(strip_tags((string) $chada_travel_value)), 0, self::MAX_DESCRIPTION_LENGTH);
    }
}
