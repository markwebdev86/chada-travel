<?php
/** Secure public and administrator delivery for Tour Downloadable Files. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Download_Service {
    public const PUBLIC_TOUR_QUERY_VAR = 'chada_travel_tour_download';
    public const PUBLIC_FILE_QUERY_VAR = 'chada_travel_tour_file';
    public const ADMIN_ACTION = 'chada_travel_download_tour_file';

    /** Returns the public forced-download URL for one Tour attachment relationship. */
    public static function public_url(string $chada_travel_slug, int $chada_travel_attachment_id): string {
        $chada_travel_url = home_url('/');
        $chada_travel_safe_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_slug);
        $chada_travel_url = add_query_arg(self::PUBLIC_TOUR_QUERY_VAR, $chada_travel_safe_slug, $chada_travel_url);
        return add_query_arg(self::PUBLIC_FILE_QUERY_VAR, (string) $chada_travel_attachment_id, $chada_travel_url);
    }

    /** Returns a nonce-protected administrator forced-download URL. */
    public static function admin_url(int $chada_travel_tour_id, int $chada_travel_attachment_id): string {
        $chada_travel_url = add_query_arg('action', self::ADMIN_ACTION, admin_url('admin-post.php'));
        $chada_travel_url = add_query_arg('tour_id', (string) $chada_travel_tour_id, $chada_travel_url);
        $chada_travel_url = add_query_arg('attachment_id', (string) $chada_travel_attachment_id, $chada_travel_url);
        // wp_nonce_url() HTML-escapes ampersands, which corrupts URLs when this value is localized to JavaScript.
        return add_query_arg(
            '_wpnonce',
            wp_create_nonce(self::admin_nonce_action($chada_travel_tour_id, $chada_travel_attachment_id)),
            $chada_travel_url
        );
    }

    /** Returns the nonce action used by administrator file links and handlers. */
    public static function admin_nonce_action(int $chada_travel_tour_id, int $chada_travel_attachment_id): string {
        return self::ADMIN_ACTION . '_' . $chada_travel_tour_id . '_' . $chada_travel_attachment_id;
    }

    /** Handles a public forced-download request for an active Tour. */
    public static function handle_public(object $chada_travel_wpdb): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $chada_travel_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize(
            CHADA_TRAVEL_Config::sanitize_request_get(self::PUBLIC_TOUR_QUERY_VAR)
        );
        $chada_travel_attachment_id = CHADA_TRAVEL_Config::sanitize_request_int(
            CHADA_TRAVEL_Config::sanitize_request_get(self::PUBLIC_FILE_QUERY_VAR, 0)
        );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $chada_travel_tour = $chada_travel_slug !== '' ? CHADA_TRAVEL_Tour_Repository::find_by_slug($chada_travel_wpdb, $chada_travel_slug) : null;
        $chada_travel_is_related = $chada_travel_tour && self::has_relationship($chada_travel_wpdb, $chada_travel_tour, $chada_travel_attachment_id);
        if (!$chada_travel_tour || empty($chada_travel_tour['chada_travel_is_active']) || !$chada_travel_is_related) {
            self::not_found();
        }
        self::stream_attachment($chada_travel_attachment_id);
    }

    /** Handles an authorized administrator forced-download request for an active or archived Tour. */
    public static function handle_admin(object $chada_travel_wpdb, int $chada_travel_tour_id, int $chada_travel_attachment_id): void {
        $chada_travel_tour = CHADA_TRAVEL_Tour_Repository::find_by_id($chada_travel_wpdb, $chada_travel_tour_id);
        if (!$chada_travel_tour || !self::has_relationship($chada_travel_wpdb, $chada_travel_tour, $chada_travel_attachment_id)) {
            self::not_found();
        }
        self::stream_attachment($chada_travel_attachment_id);
    }

    /**
     * Returns whether the attachment is currently related to the given Tour.
     *
     * @param array<string, mixed> $chada_travel_tour
     */
    private static function has_relationship(object $chada_travel_wpdb, array $chada_travel_tour, int $chada_travel_attachment_id): bool {
        if ($chada_travel_attachment_id <= 0) {
            return false;
        }
        $chada_travel_tour_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0);
        foreach (CHADA_TRAVEL_Tour_Repository::get_files($chada_travel_wpdb, $chada_travel_tour_id) as $chada_travel_file) {
            if ((int) ($chada_travel_file['chada_travel_attachment_id'] ?? 0) === $chada_travel_attachment_id) {
                return true;
            }
        }
        return false;
    }

    /** Streams one WordPress Media Library attachment with forced-download headers. */
    private static function stream_attachment(int $chada_travel_attachment_id): void {
        $chada_travel_path = function_exists('get_attached_file') ? (string) get_attached_file($chada_travel_attachment_id) : '';
        if ($chada_travel_path === '' || !is_readable($chada_travel_path) || !is_file($chada_travel_path)) {
            self::not_found();
        }
        $chada_travel_mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($chada_travel_attachment_id) : '';
        $chada_travel_title = function_exists('get_the_title') ? (string) get_the_title($chada_travel_attachment_id) : '';
        $chada_travel_name = self::download_filename($chada_travel_path, $chada_travel_title);
        foreach (self::build_headers($chada_travel_mime, $chada_travel_name, (int) filesize($chada_travel_path)) as $chada_travel_header) {
            header($chada_travel_header);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a verified Media Library file.
        readfile($chada_travel_path);
        exit;
    }

    /** Returns a safe download filename while preserving the Media Library file extension. */
    public static function download_filename(string $chada_travel_path, string $chada_travel_title = ''): string {
        $chada_travel_file_name = basename($chada_travel_path);
        $chada_travel_extension = strtolower((string) pathinfo($chada_travel_file_name, PATHINFO_EXTENSION));
        $chada_travel_name = $chada_travel_title !== '' ? $chada_travel_title : $chada_travel_file_name;
        if (function_exists('sanitize_file_name')) {
            $chada_travel_name = sanitize_file_name($chada_travel_name);
        }
        if ($chada_travel_name === '') {
            $chada_travel_name = $chada_travel_file_name;
        }
        $chada_travel_name_extension = strtolower((string) pathinfo($chada_travel_name, PATHINFO_EXTENSION));
        if ($chada_travel_extension !== '' && $chada_travel_name_extension !== $chada_travel_extension) {
            $chada_travel_name = pathinfo($chada_travel_name, PATHINFO_FILENAME) . '.' . $chada_travel_extension;
        }
        return $chada_travel_name;
    }

    /** @return list<string> */
    public static function build_headers(string $chada_travel_mime, string $chada_travel_name, int $chada_travel_size = 0): array {
        $chada_travel_headers = [
            'Content-Type: ' . ($chada_travel_mime !== '' ? $chada_travel_mime : 'application/octet-stream'),
            'Content-Disposition: attachment; filename="' . rawurlencode($chada_travel_name) . '"',
            'X-Content-Type-Options: nosniff',
            'Cache-Control: private, no-store',
        ];
        if ($chada_travel_size > 0) {
            $chada_travel_headers[] = 'Content-Length: ' . $chada_travel_size;
        }
        return $chada_travel_headers;
    }

    /** Ends an invalid download request without disclosing file or relationship details. */
    private static function not_found(): void {
        if (function_exists('status_header')) {
            status_header(404);
        }
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (function_exists('wp_die')) {
            wp_die(esc_html__('File not found.', 'chada-travel'), '', ['response' => 404]);
        }
        exit;
    }
}
