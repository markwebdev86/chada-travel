<?php
/** Isolates and normalizes Visa Country Step-by-Step Guide images. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Visa_Guide_Image_Service {
    /**
     * Clones and processes a replacement Guide image, or returns null when the current image is unchanged.
     *
     * @return array<string, mixed>|null
     */
    public static function prepare_for_country(
        int $chada_travel_selected_attachment_id,
        int $chada_travel_current_attachment_id,
        string $chada_travel_country_name
    ): ?array {
        if ($chada_travel_selected_attachment_id <= 0 || $chada_travel_selected_attachment_id === $chada_travel_current_attachment_id) {
            return null;
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_max_mb = min(
            CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_MAX_MB,
            max(1, (int) ($chada_travel_settings['chada_travel_visa_guide_image_max_mb']
                ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_MAX_MB))
        );
        $chada_travel_clone_id = CHADA_TRAVEL_Image_Processing_Service::clone_attachment(
            $chada_travel_selected_attachment_id,
            $chada_travel_max_mb,
            'Step-by-Step Guide image'
        );

        try {
            $chada_travel_state = CHADA_TRAVEL_Image_Processing_Service::prepare(
                $chada_travel_clone_id,
                CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_country_name),
                (int) ($chada_travel_settings['chada_travel_visa_guide_image_width']
                    ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_WIDTH),
                (int) ($chada_travel_settings['chada_travel_visa_guide_image_height']
                    ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_HEIGHT),
                $chada_travel_max_mb,
                'Step-by-Step Guide image'
            );
            $chada_travel_state['created_attachment_id'] = $chada_travel_clone_id;
            return $chada_travel_state;
        } catch (\Throwable $chada_travel_exception) {
            CHADA_TRAVEL_Image_Processing_Service::delete_attachment($chada_travel_clone_id);
            throw $chada_travel_exception;
        }
    }

    /** @param array<string, mixed> $chada_travel_state */
    public static function finalize(array $chada_travel_state): void {
        CHADA_TRAVEL_Image_Processing_Service::finalize($chada_travel_state);
    }

    /** @param array<string, mixed> $chada_travel_state */
    public static function rollback(array $chada_travel_state): void {
        CHADA_TRAVEL_Image_Processing_Service::rollback($chada_travel_state);
        $chada_travel_clone_id = (int) ($chada_travel_state['created_attachment_id'] ?? 0);
        if ($chada_travel_clone_id > 0) {
            CHADA_TRAVEL_Image_Processing_Service::delete_attachment($chada_travel_clone_id);
        }
    }

    /** Permanently removes a Guide clone when processing fails before a state is returned. */
    public static function delete_clone(int $chada_travel_attachment_id): void {
        if ($chada_travel_attachment_id > 0) {
            CHADA_TRAVEL_Image_Processing_Service::delete_attachment($chada_travel_attachment_id);
        }
    }
}
