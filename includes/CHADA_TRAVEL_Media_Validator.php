<?php
/**
 * Validates Visa Country Media Library associations and resolves safe public URLs or local mail attachments.
 * Attachment IDs are the only persisted authority; URLs and paths are resolved at the consumer boundary.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Media_Validator {
    public const IMAGE_MIME_TYPES = ['image/jpeg', 'image/png'];
    public const CHECKLIST_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** @param list<string> $chada_travel_allowed_mimes Returns empty when media cannot be safely exposed. */
    public static function public_url(int $chada_travel_attachment_id, array $chada_travel_allowed_mimes): string {
        if (!self::is_valid_attachment($chada_travel_attachment_id, $chada_travel_allowed_mimes)
            || !function_exists('wp_get_attachment_url')) {
            return '';
        }
        $chada_travel_url = wp_get_attachment_url($chada_travel_attachment_id);
        return is_string($chada_travel_url) ? $chada_travel_url : '';
    }

    /** @param list<string> $chada_travel_allowed_mimes Verifies an attachment and explicit MIME allowlist. */
    public static function is_valid_attachment(int $chada_travel_attachment_id, array $chada_travel_allowed_mimes): bool {
        if ($chada_travel_attachment_id <= 0) {
            return false;
        }
        if (!function_exists('get_post')) {
            return true;
        }
        $chada_travel_attachment = get_post($chada_travel_attachment_id);
        return $chada_travel_attachment && $chada_travel_attachment->post_type === 'attachment'
            && in_array((string) $chada_travel_attachment->post_mime_type, $chada_travel_allowed_mimes, true);
    }

    /**
     * Resolves a readable canonical path below the current uploads root for use as a wp_mail() attachment.
     *
     * @param list<string> $chada_travel_allowed_mimes
     * @return array{valid: bool, path: string, mime: string, name: string, reason: string}
     */
    public static function local_attachment(int $chada_travel_attachment_id, array $chada_travel_allowed_mimes): array {
        $chada_travel_invalid = ['valid' => false, 'path' => '', 'mime' => '', 'name' => '', 'reason' => 'invalid_media'];
        if (!self::is_valid_attachment($chada_travel_attachment_id, $chada_travel_allowed_mimes)
            || !function_exists('get_attached_file') || !function_exists('wp_upload_dir')) {
            return $chada_travel_invalid;
        }
        $chada_travel_attachment = get_post($chada_travel_attachment_id);
        $chada_travel_path       = get_attached_file($chada_travel_attachment_id, true);
        $chada_travel_uploads    = wp_upload_dir(null, false);
        $chada_travel_root       = realpath($chada_travel_uploads['basedir']);
        $chada_travel_real_path  = is_string($chada_travel_path) ? realpath($chada_travel_path) : false;
        if ($chada_travel_root === false || $chada_travel_real_path === false || !is_file($chada_travel_real_path)
            || !is_readable($chada_travel_real_path)) {
            return array_merge($chada_travel_invalid, ['reason' => 'unreadable_media']);
        }
        $chada_travel_prefix = rtrim($chada_travel_root, '\\/') . DIRECTORY_SEPARATOR;
        if (!str_starts_with($chada_travel_real_path, $chada_travel_prefix)) {
            return array_merge($chada_travel_invalid, ['reason' => 'outside_uploads_root']);
        }
        return [
            'valid'  => true,
            'path'   => $chada_travel_real_path,
            'mime'   => (string) $chada_travel_attachment->post_mime_type,
            'name'   => basename($chada_travel_real_path),
            'reason' => '',
        ];
    }
}
