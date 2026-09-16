<?php
/** Validates, normalizes, and safely replaces JPEG/PNG Media Library attachments. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Image_Processing_Service {
    /**
     * Copies a validated Media Library image into a new attachment for isolated processing.
     */
    public static function clone_attachment(
        int $chada_travel_attachment_id,
        int $chada_travel_max_mb,
        string $chada_travel_label = 'Image'
    ): int {
        CHADA_TRAVEL_Runtime_Limits::apply();
        $chada_travel_max_bytes = max(1, $chada_travel_max_mb) * 1024 * 1024;
        if (!CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            $chada_travel_attachment_id,
            CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
        )) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('Select a valid JPEG, JPG, or PNG %s.', $chada_travel_label));
        }
        $chada_travel_source_path = function_exists('get_attached_file') ? get_attached_file($chada_travel_attachment_id) : '';
        if (!is_string($chada_travel_source_path) || !is_file($chada_travel_source_path) || !is_readable($chada_travel_source_path)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s file could not be read.', $chada_travel_label));
        }
        $chada_travel_source_size = filesize($chada_travel_source_path);
        if ($chada_travel_source_size === false || $chada_travel_source_size > $chada_travel_max_bytes) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- integer and label are diagnostics.
            throw new \RuntimeException(sprintf('The %s must not exceed %d MB.', $chada_travel_label, max(1, $chada_travel_max_mb)));
        }
        $chada_travel_mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($chada_travel_attachment_id) : '';
        if (!in_array($chada_travel_mime, CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES, true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s MIME type is not supported.', $chada_travel_label));
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local validated media file.
        $chada_travel_contents = file_get_contents($chada_travel_source_path);
        if ($chada_travel_contents === false || !function_exists('wp_upload_bits')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s could not be copied.', $chada_travel_label));
        }
        $chada_travel_upload = wp_upload_bits(basename($chada_travel_source_path), null, $chada_travel_contents);
        if (!empty($chada_travel_upload['error']) || empty($chada_travel_upload['file'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s could not be copied.', $chada_travel_label));
        }
        $chada_travel_upload_path = (string) $chada_travel_upload['file'];
        $chada_travel_source_post = function_exists('get_post')
            ? get_post($chada_travel_attachment_id) : null;
        $chada_travel_attachment = [
            'post_mime_type' => $chada_travel_mime,
            'post_title' => $chada_travel_source_post
                ? (string) $chada_travel_source_post->post_title : pathinfo($chada_travel_source_path, PATHINFO_FILENAME),
            'post_excerpt' => $chada_travel_source_post ? (string) $chada_travel_source_post->post_excerpt : '',
            'post_content' => $chada_travel_source_post ? (string) $chada_travel_source_post->post_content : '',
            'post_status' => 'inherit',
            'post_parent' => 0,
        ];
        $chada_travel_clone_id = function_exists('wp_insert_attachment')
            ? wp_insert_attachment($chada_travel_attachment, $chada_travel_upload_path, 0, true) : 0;
        if (is_wp_error($chada_travel_clone_id) || (int) $chada_travel_clone_id <= 0) {
            self::delete_file($chada_travel_upload_path);
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s could not be copied.', $chada_travel_label));
        }
        $chada_travel_clone_id = (int) $chada_travel_clone_id;
        if (function_exists('update_attached_file')) {
            update_attached_file($chada_travel_clone_id, $chada_travel_upload_path);
        }
        if (function_exists('get_post_meta') && function_exists('update_post_meta')) {
            $chada_travel_alt = (string) get_post_meta($chada_travel_attachment_id, '_wp_attachment_image_alt', true);
            if ($chada_travel_alt !== '') {
                update_post_meta($chada_travel_clone_id, '_wp_attachment_image_alt', $chada_travel_alt);
            }
        }
        return $chada_travel_clone_id;
    }

    /**
     * Processes an attachment in place and returns rollback state for the enclosing save.
     *
     * @return array{attachment_id: int, source_path: string, target_path: string, backup_source: string,
     *     backup_target: string, old_relative: string, old_metadata: mixed}
     */
    public static function prepare(
        int $chada_travel_attachment_id,
        string $chada_travel_slug,
        int $chada_travel_width,
        int $chada_travel_height,
        int $chada_travel_max_mb,
        string $chada_travel_label = 'Image'
    ): array {
        CHADA_TRAVEL_Runtime_Limits::apply();
        $chada_travel_max_mb = max(1, $chada_travel_max_mb);
        $chada_travel_width = max(1, $chada_travel_width);
        $chada_travel_height = max(1, $chada_travel_height);
        $chada_travel_max_bytes = $chada_travel_max_mb * 1024 * 1024;
        if (!CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            $chada_travel_attachment_id,
            CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
        )) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('Select a valid JPEG, JPG, or PNG %s.', $chada_travel_label));
        }
        $chada_travel_source_path = function_exists('get_attached_file') ? get_attached_file($chada_travel_attachment_id) : '';
        if (!is_string($chada_travel_source_path) || !is_file($chada_travel_source_path) || !is_readable($chada_travel_source_path)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s file could not be read.', $chada_travel_label));
        }
        $chada_travel_source_size = filesize($chada_travel_source_path);
        if ($chada_travel_source_size === false || $chada_travel_source_size > $chada_travel_max_bytes) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- integer-only diagnostic exception.
            throw new \RuntimeException(sprintf('The %s must not exceed %d MB.', $chada_travel_label, $chada_travel_max_mb));
        }
        $chada_travel_mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($chada_travel_attachment_id) : '';
        if (!in_array($chada_travel_mime, CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES, true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf('The selected %s MIME type is not supported.', $chada_travel_label));
        }
        $chada_travel_extension = strtolower((string) pathinfo($chada_travel_source_path, PATHINFO_EXTENSION));
        if (!in_array($chada_travel_extension, ['jpg', 'jpeg', 'png'], true)) {
            $chada_travel_extension = $chada_travel_mime === 'image/png' ? 'png' : 'jpg';
        }
        $chada_travel_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_slug);
        $chada_travel_slug = $chada_travel_slug !== '' ? $chada_travel_slug : 'image';
        $chada_travel_target_path = self::resolve_target_path(
            dirname($chada_travel_source_path),
            $chada_travel_slug,
            $chada_travel_extension,
            $chada_travel_attachment_id
        );
        $chada_travel_old_metadata = function_exists('wp_get_attachment_metadata')
            ? wp_get_attachment_metadata($chada_travel_attachment_id) : [];
        $chada_travel_old_relative = function_exists('get_post_meta')
            ? (string) get_post_meta($chada_travel_attachment_id, '_wp_attached_file', true) : '';
        $chada_travel_backup_source = '';
        $chada_travel_backup_target = '';
        try {
            $chada_travel_backup_source = self::backup_file($chada_travel_source_path, $chada_travel_label);
            $chada_travel_backup_target = $chada_travel_target_path !== $chada_travel_source_path && is_file($chada_travel_target_path)
                ? self::backup_file($chada_travel_target_path, $chada_travel_label) : '';
            $chada_travel_editor = function_exists('wp_get_image_editor') ? wp_get_image_editor($chada_travel_source_path) : null;
            if (!$chada_travel_editor || is_wp_error($chada_travel_editor)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
                throw new \RuntimeException(sprintf(
                    'The %s could not be opened by the WordPress image editor.',
                    $chada_travel_label
                ));
            }
            $chada_travel_resize = self::resize_and_center_crop(
                $chada_travel_editor,
                $chada_travel_width,
                $chada_travel_height
            );
            $chada_travel_saved = $chada_travel_resize
                ? $chada_travel_editor->save($chada_travel_target_path, $chada_travel_mime)
                : self::resize_with_gd(
                    $chada_travel_source_path,
                    $chada_travel_target_path,
                    $chada_travel_mime,
                    $chada_travel_width,
                    $chada_travel_height
                );
            if (is_wp_error($chada_travel_saved) || !$chada_travel_saved) {
                throw new \RuntimeException(sprintf('The %s could not be resized and cropped.', $chada_travel_label));
            }
            if ($chada_travel_target_path !== $chada_travel_source_path && function_exists('update_attached_file')) {
                update_attached_file($chada_travel_attachment_id, $chada_travel_target_path);
            }
            if (function_exists('wp_generate_attachment_metadata')
                && function_exists('wp_update_attachment_metadata')
            ) {
                $chada_travel_metadata = wp_generate_attachment_metadata($chada_travel_attachment_id, $chada_travel_target_path);
                if (is_array($chada_travel_metadata)) {
                    wp_update_attachment_metadata($chada_travel_attachment_id, $chada_travel_metadata);
                }
            }
        } catch (\Throwable $chada_travel_exception) {
            self::rollback([
                'attachment_id' => $chada_travel_attachment_id,
                'source_path' => $chada_travel_source_path,
                'target_path' => $chada_travel_target_path,
                'backup_source' => $chada_travel_backup_source,
                'backup_target' => $chada_travel_backup_target,
                'old_relative' => $chada_travel_old_relative,
                'old_metadata' => $chada_travel_old_metadata,
            ]);
            throw $chada_travel_exception;
        }
        return [
            'attachment_id' => $chada_travel_attachment_id,
            'source_path' => $chada_travel_source_path,
            'target_path' => $chada_travel_target_path,
            'backup_source' => $chada_travel_backup_source,
            'backup_target' => $chada_travel_backup_target,
            'old_relative' => $chada_travel_old_relative,
            'old_metadata' => $chada_travel_old_metadata,
        ];
    }

    /** Resizes proportionally until the target is covered, then center-crops to the exact dimensions. */
    private static function resize_and_center_crop(object $chada_travel_editor, int $chada_travel_width, int $chada_travel_height): bool {
        $chada_travel_size = method_exists($chada_travel_editor, 'get_size') ? $chada_travel_editor->get_size() : null;
        if (!is_array($chada_travel_size) || (int) ($chada_travel_size['width'] ?? 0) < 1
            || (int) ($chada_travel_size['height'] ?? 0) < 1 || !method_exists($chada_travel_editor, 'crop')) {
            return !is_wp_error($chada_travel_editor->resize($chada_travel_width, $chada_travel_height, true));
        }
        $chada_travel_source_width = (int) $chada_travel_size['width'];
        $chada_travel_source_height = (int) $chada_travel_size['height'];
        $chada_travel_scale = max(
            $chada_travel_width / $chada_travel_source_width,
            $chada_travel_height / $chada_travel_source_height
        );
        $chada_travel_resize_width = max($chada_travel_width, (int) ceil($chada_travel_source_width * $chada_travel_scale));
        $chada_travel_resize_height = max($chada_travel_height, (int) ceil($chada_travel_source_height * $chada_travel_scale));
        /* @phpstan-ignore-next-line */
        $chada_travel_resize = $chada_travel_editor->resize($chada_travel_resize_width, $chada_travel_resize_height, false);
        if (is_wp_error($chada_travel_resize)) {
            return false;
        }
        $chada_travel_crop = $chada_travel_editor->crop(
            (int) floor(($chada_travel_resize_width - $chada_travel_width) / 2),
            (int) floor(($chada_travel_resize_height - $chada_travel_height) / 2),
            $chada_travel_width,
            $chada_travel_height
        );
        return !is_wp_error($chada_travel_crop);
    }

    /** Uses GD when WordPress refuses an upscale, then applies the same centered-cover calculation. */
    private static function resize_with_gd(
        string $chada_travel_source_path,
        string $chada_travel_target_path,
        string $chada_travel_mime,
        int $chada_travel_width,
        int $chada_travel_height
    ): bool {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopyresampled')) {
            return false;
        }
        $chada_travel_source = $chada_travel_mime === 'image/png' && function_exists('imagecreatefrompng')
            ? imagecreatefrompng($chada_travel_source_path)
            : (function_exists('imagecreatefromjpeg') ? imagecreatefromjpeg($chada_travel_source_path) : false);
        if (!$chada_travel_source) {
            return false;
        }
        $chada_travel_source_width = imagesx($chada_travel_source);
        $chada_travel_source_height = imagesy($chada_travel_source);
        $chada_travel_scale = max($chada_travel_width / $chada_travel_source_width, $chada_travel_height / $chada_travel_source_height);
        $chada_travel_resize_width = max($chada_travel_width, (int) ceil($chada_travel_source_width * $chada_travel_scale));
        $chada_travel_resize_height = max($chada_travel_height, (int) ceil($chada_travel_source_height * $chada_travel_scale));
        $chada_travel_resized = imagecreatetruecolor($chada_travel_resize_width, $chada_travel_resize_height);
        $chada_travel_cropped = imagecreatetruecolor($chada_travel_width, $chada_travel_height);
        if (!$chada_travel_resized || !$chada_travel_cropped) {
            imagedestroy($chada_travel_source);
            return false;
        }
        if ($chada_travel_mime === 'image/png') {
            foreach ([$chada_travel_resized, $chada_travel_cropped] as $chada_travel_image) {
                imagealphablending($chada_travel_image, false);
                imagesavealpha($chada_travel_image, true);
                $chada_travel_background = imagecolorallocatealpha($chada_travel_image, 255, 255, 255, 127);
                imagefilledrectangle(
                    $chada_travel_image,
                    0,
                    0,
                    imagesx($chada_travel_image),
                    imagesy($chada_travel_image),
                    $chada_travel_background
                );
            }
        }
        imagecopyresampled(
            $chada_travel_resized,
            $chada_travel_source,
            0,
            0,
            0,
            0,
            $chada_travel_resize_width,
            $chada_travel_resize_height,
            $chada_travel_source_width,
            $chada_travel_source_height
        );
        imagecopy(
            $chada_travel_cropped,
            $chada_travel_resized,
            0,
            0,
            (int) floor(($chada_travel_resize_width - $chada_travel_width) / 2),
            (int) floor(($chada_travel_resize_height - $chada_travel_height) / 2),
            $chada_travel_width,
            $chada_travel_height
        );
        $chada_travel_saved = $chada_travel_mime === 'image/png'
            ? imagepng($chada_travel_cropped, $chada_travel_target_path)
            : imagejpeg($chada_travel_cropped, $chada_travel_target_path, 90);
        imagedestroy($chada_travel_source);
        imagedestroy($chada_travel_resized);
        imagedestroy($chada_travel_cropped);
        return (bool) $chada_travel_saved;
    }

    /** @param array{attachment_id: int, source_path: string, target_path: string, backup_source: string,
     *     backup_target: string, old_relative: string, old_metadata: mixed} $chada_travel_state */
    public static function finalize(array $chada_travel_state): void {
        foreach (['backup_source', 'backup_target'] as $chada_travel_key) {
            if (!empty($chada_travel_state[$chada_travel_key]) && is_file($chada_travel_state[$chada_travel_key])) {
                self::delete_file($chada_travel_state[$chada_travel_key]);
            }
        }
        if ($chada_travel_state['source_path'] !== $chada_travel_state['target_path']
            && $chada_travel_state['source_path'] !== '' && is_file($chada_travel_state['source_path'])) {
            self::delete_file($chada_travel_state['source_path']);
        }
    }

    /** @param array{attachment_id: int, source_path: string, target_path: string, backup_source: string,
     *     backup_target: string, old_relative: string, old_metadata: mixed} $chada_travel_state */
    public static function rollback(array $chada_travel_state): void {
        $chada_travel_source = $chada_travel_state['source_path'];
        $chada_travel_target = $chada_travel_state['target_path'];
        if ($chada_travel_target !== '' && $chada_travel_target !== $chada_travel_source && is_file($chada_travel_target)) {
            self::delete_file($chada_travel_target);
        }
        if ($chada_travel_state['backup_source'] !== '' && is_file($chada_travel_state['backup_source'])) {
            copy($chada_travel_state['backup_source'], $chada_travel_source);
        }
        if ($chada_travel_state['backup_target'] !== '' && is_file($chada_travel_state['backup_target'])) {
            copy($chada_travel_state['backup_target'], $chada_travel_target);
        }
        if ($chada_travel_state['attachment_id'] > 0 && function_exists('update_attached_file')) {
            update_attached_file($chada_travel_state['attachment_id'], $chada_travel_state['old_relative']);
        }
        if ($chada_travel_state['attachment_id'] > 0 && function_exists('wp_update_attachment_metadata')) {
            wp_update_attachment_metadata($chada_travel_state['attachment_id'], $chada_travel_state['old_metadata']);
        }
        foreach (['backup_source', 'backup_target'] as $chada_travel_key) {
            if (!empty($chada_travel_state[$chada_travel_key]) && is_file($chada_travel_state[$chada_travel_key])) {
                self::delete_file($chada_travel_state[$chada_travel_key]);
            }
        }
    }

    /** Permanently removes an isolated clone and its file during rollback. */
    public static function delete_attachment(int $chada_travel_attachment_id): void {
        $chada_travel_path = function_exists('get_attached_file') ? get_attached_file($chada_travel_attachment_id) : '';
        if (function_exists('wp_delete_attachment')) {
            wp_delete_attachment($chada_travel_attachment_id, true);
        }
        if (is_string($chada_travel_path) && $chada_travel_path !== '' && is_file($chada_travel_path)) {
            self::delete_file($chada_travel_path);
        }
    }

    private static function backup_file(string $chada_travel_path, string $chada_travel_label): string {
        $chada_travel_backup = function_exists('wp_tempnam')
            ? wp_tempnam(basename($chada_travel_path)) : tempnam(sys_get_temp_dir(), 'chada-travel-');
        if (!is_string($chada_travel_backup) || $chada_travel_backup === '' || !copy($chada_travel_path, $chada_travel_backup)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
            throw new \RuntimeException(sprintf(
                'The %s could not be safely backed up before processing.',
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- label is an internal diagnostic.
                $chada_travel_label
            ));
        }
        return $chada_travel_backup;
    }

    private static function resolve_target_path(
        string $chada_travel_directory,
        string $chada_travel_slug,
        string $chada_travel_extension,
        int $chada_travel_attachment_id
    ): string {
        $chada_travel_prefix = 'featured-image-' . $chada_travel_slug;
        for ($chada_travel_suffix_number = 1; $chada_travel_suffix_number <= 1000; $chada_travel_suffix_number++) {
            $chada_travel_suffix = $chada_travel_suffix_number === 1 ? '' : '-' . $chada_travel_suffix_number;
            $chada_travel_candidate = $chada_travel_directory . DIRECTORY_SEPARATOR
                . $chada_travel_prefix . $chada_travel_suffix . '.' . $chada_travel_extension;
            $chada_travel_owners = self::attachment_owners($chada_travel_candidate);
            $chada_travel_has_other_owner = (bool) array_filter(
                $chada_travel_owners,
                static fn(int $chada_travel_owner_id): bool => $chada_travel_owner_id !== $chada_travel_attachment_id
            );
            if ($chada_travel_has_other_owner || (file_exists($chada_travel_candidate) && !$chada_travel_owners)) {
                continue;
            }
            return $chada_travel_candidate;
        }
        throw new \RuntimeException('A unique Featured Image filename could not be generated.');
    }

    /** @return list<int> */
    private static function attachment_owners(string $chada_travel_path): array {
        if (!function_exists('get_posts') || !function_exists('wp_upload_dir')) {
            return [];
        }
        $chada_travel_uploads = wp_upload_dir();
        $chada_travel_base_dir = rtrim(str_replace('\\', '/', $chada_travel_uploads['basedir']), '/');
        $chada_travel_normalized_path = str_replace('\\', '/', $chada_travel_path);
        if ($chada_travel_base_dir === '' || strpos($chada_travel_normalized_path, $chada_travel_base_dir . '/') !== 0) {
            return [];
        }
        $chada_travel_relative_path = ltrim(substr($chada_travel_normalized_path, strlen($chada_travel_base_dir)), '/');
        $chada_travel_posts = get_posts([
            'post_type' => 'attachment', 'post_status' => 'inherit', 'fields' => 'ids',
            'posts_per_page' => -1, 'no_found_rows' => true,
            'meta_query' => [['key' => '_wp_attached_file', 'value' => $chada_travel_relative_path, 'compare' => '=']],
        ]);
        return array_values(array_filter(array_map('intval', $chada_travel_posts)));
    }

    private static function delete_file(string $chada_travel_path): void {
        if (function_exists('wp_delete_file')) {
            wp_delete_file($chada_travel_path);
        } elseif (is_file($chada_travel_path)) {
            unlink($chada_travel_path);
        }
    }
}
