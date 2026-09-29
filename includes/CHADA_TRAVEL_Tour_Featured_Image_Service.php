<?php
/** Backward-compatible Tour Featured Image processing facade. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Featured_Image_Service {
    /**
     * Clones and processes a replacement image, or returns null when the current image is unchanged.
     *
     * @return array<string, mixed>|null
     */
    public static function prepare_for_tour(
        int $chada_travel_selected_attachment_id,
        int $chada_travel_current_attachment_id,
        string $chada_travel_tour_name
    ): ?array {
        if ($chada_travel_selected_attachment_id > 0 && $chada_travel_selected_attachment_id === $chada_travel_current_attachment_id) {
            return null;
        }
        $chada_travel_clone_id = self::clone_attachment($chada_travel_selected_attachment_id);
        try {
            $chada_travel_state = self::prepare($chada_travel_clone_id, $chada_travel_tour_name);
            $chada_travel_state['created_attachment_id'] = $chada_travel_clone_id;
            return $chada_travel_state;
        } catch (\Throwable $chada_travel_exception) {
            self::delete_clone($chada_travel_clone_id);
            throw $chada_travel_exception;
        }
    }

    /** Copies a validated existing Media Library image for isolated Tour processing. */
    public static function clone_attachment(int $chada_travel_attachment_id): int {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        return CHADA_TRAVEL_Image_Processing_Service::clone_attachment(
            $chada_travel_attachment_id,
            max(1, (int) ($chada_travel_settings['chada_travel_tour_featured_image_max_mb']
                ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_MAX_MB)),
            'Featured Image'
        );
    }

    /**
     * Processes a Tour Featured Image using the existing Tour settings.
     *
     * @return array{attachment_id: int, source_path: string, target_path: string, backup_source: string,
     *     backup_target: string, old_relative: string, old_metadata: mixed}
     */
    public static function prepare(int $chada_travel_attachment_id, string $chada_travel_tour_name): array {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_tour_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_tour_name);
        $chada_travel_tour_slug = $chada_travel_tour_slug !== '' ? $chada_travel_tour_slug : 'tour';
        return CHADA_TRAVEL_Image_Processing_Service::prepare(
            $chada_travel_attachment_id,
            $chada_travel_tour_slug,
            max(1, (int) ($chada_travel_settings['chada_travel_tour_featured_image_width']
                ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_WIDTH)),
            max(1, (int) ($chada_travel_settings['chada_travel_tour_featured_image_height']
                ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_HEIGHT)),
            max(1, (int) ($chada_travel_settings['chada_travel_tour_featured_image_max_mb']
                ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_MAX_MB)),
            'Featured Image'
        );
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

    /** Permanently removes a clone when image processing fails before a state is returned. */
    public static function delete_clone(int $chada_travel_attachment_id): void {
        if ($chada_travel_attachment_id > 0) {
            CHADA_TRAVEL_Image_Processing_Service::delete_attachment($chada_travel_attachment_id);
        }
    }
}
