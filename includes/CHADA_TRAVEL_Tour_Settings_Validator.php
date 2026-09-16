<?php
/** Validates the dedicated Tours Settings tab. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Settings_Validator {
    public const MIN_IMAGE_DIMENSION = 1;
    public const MAX_IMAGE_DIMENSION = 5000;
    public const MIN_IMAGE_MAX_MB = 1;
    public const MAX_IMAGE_MAX_MB = 100;

    /**
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function validate(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_errors = [];
        $chada_travel_currency = strtoupper(trim((string) ($chada_travel_post['chada_travel_tour_default_currency'] ?? '')));
        if (!in_array($chada_travel_currency, CHADA_TRAVEL_Config::get_tour_currency_options(), true)) {
            $chada_travel_errors['chada_travel_tour_default_currency'] = 'Select a valid default Tour Currency.';
        }
        $chada_travel_width = self::integer_value($chada_travel_post['chada_travel_tour_featured_image_width'] ?? '');
        $chada_travel_height = self::integer_value($chada_travel_post['chada_travel_tour_featured_image_height'] ?? '');
        $chada_travel_max_mb = self::integer_value($chada_travel_post['chada_travel_tour_featured_image_max_mb'] ?? '');
        if ($chada_travel_width < self::MIN_IMAGE_DIMENSION || $chada_travel_width > self::MAX_IMAGE_DIMENSION) {
            $chada_travel_errors['chada_travel_tour_featured_image_width'] =
                'Featured Image Width must be between 1 and 5,000 pixels.';
        }
        if ($chada_travel_height < self::MIN_IMAGE_DIMENSION || $chada_travel_height > self::MAX_IMAGE_DIMENSION) {
            $chada_travel_errors['chada_travel_tour_featured_image_height'] =
                'Featured Image Height must be between 1 and 5,000 pixels.';
        }
        if ($chada_travel_max_mb < self::MIN_IMAGE_MAX_MB || $chada_travel_max_mb > self::MAX_IMAGE_MAX_MB) {
            $chada_travel_errors['chada_travel_tour_featured_image_max_mb'] =
                'Maximum Featured Image File Size must be between 1 and 100 MB.';
        }
        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }
        $chada_travel_clean = [
            'chada_travel_tour_default_currency' => $chada_travel_currency,
            'chada_travel_tour_featured_image_width' => $chada_travel_width,
            'chada_travel_tour_featured_image_height' => $chada_travel_height,
            'chada_travel_tour_featured_image_max_mb' => $chada_travel_max_mb,
        ];
        $chada_travel_changed_options = [];
        foreach ($chada_travel_clean as $chada_travel_option => $chada_travel_value) {
            if ((string) $chada_travel_value !== (string) ($chada_travel_current_settings[$chada_travel_option] ?? '')) {
                $chada_travel_changed_options[] = $chada_travel_option;
            }
        }
        return [
            'errors' => [],
            'clean' => $chada_travel_clean,
            'audit' => ['changed_options' => $chada_travel_changed_options],
        ];
    }

    /** @param mixed $chada_travel_value */
    private static function integer_value($chada_travel_value): int {
        $chada_travel_value = trim((string) $chada_travel_value);
        return preg_match('/^\d+$/', $chada_travel_value) ? (int) $chada_travel_value : 0;
    }
}
