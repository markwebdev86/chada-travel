<?php
/**
 * Stores the Bank Deposit Slip proof outside the public uploads path and streams it back only to callers
 * the REST controller has already authorized.
 *
 * True private object storage (malware scanning, dedicated storage) remains a documented post-MVP
 * improvement; this MVP protection relies on a non-public storage folder plus capability-gated streaming.
 *
 * Storage keys persisted to the database are always portable (forward-slash, e.g.
 * `chada-travel-private/proofs/<file>`) so a database created on Windows stays valid on Linux/Docker and vice
 * versa. Physical filesystem paths built from those keys always use DIRECTORY_SEPARATOR so the same code
 * works unmodified on Windows, Linux, Docker, Apache, and IIS.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Upload_Service {
    /** Leading segment of every portable storage key; matches the `<uploads basedir>/chada-travel-private` directory. */
    private const PRIVATE_ROOT_SEGMENT = 'chada-travel-private';

    /**
     * Validates and stores an uploaded file outside the public uploads path.
     *
     * @param array{name?:string, type?:string, tmp_name?:string, size?:int, error?:int} $chada_travel_file
     * @param array{mime_types: list<string>, max_bytes: int}                            $chada_travel_rules
     * @return array{
     *     errors: list<string>,
     *     storage_path?: string,
     *     mime_type?: string,
     *     original_filename?: string,
     *     size?: int
     * }
     */
    public static function store_upload(array $chada_travel_file, array $chada_travel_rules, string $chada_travel_subdir): array {
        $chada_travel_errors = CHADA_TRAVEL_Upload_Validator::validate_upload($chada_travel_file, $chada_travel_rules);
        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        $chada_travel_tmp_name      = (string) ($chada_travel_file['tmp_name'] ?? '');
        $chada_travel_original_name = (string) ($chada_travel_file['name'] ?? '');
        $chada_travel_detected_mime = self::detect_mime_type($chada_travel_tmp_name, $chada_travel_original_name);
        $chada_travel_mime_errors   = CHADA_TRAVEL_Upload_Validator::validate_detected_mime(
            $chada_travel_detected_mime,
            $chada_travel_rules['mime_types']
        );
        if ($chada_travel_mime_errors) {
            return ['errors' => $chada_travel_mime_errors];
        }

        $chada_travel_extension    = strtolower((string) pathinfo($chada_travel_original_name, PATHINFO_EXTENSION));
        $chada_travel_subdir_clean = self::clean_subdir($chada_travel_subdir);
        $chada_travel_directory    = self::private_upload_dir($chada_travel_subdir_clean);
        $chada_travel_filename     = bin2hex(random_bytes(16)) . '.' . $chada_travel_extension;
        $chada_travel_destination  = $chada_travel_directory . DIRECTORY_SEPARATOR . $chada_travel_filename;
        $chada_travel_stored_path  = self::store_file(
            $chada_travel_file,
            $chada_travel_tmp_name,
            $chada_travel_subdir_clean,
            $chada_travel_filename,
            $chada_travel_destination
        );
        if ($chada_travel_stored_path === '') {
            return ['errors' => ['The upload could not be saved. Try again.']];
        }
        $chada_travel_filename = basename($chada_travel_stored_path);

        return [
            'errors'            => [],
            // Portable DB storage key: always forward-slash, independent of the host OS path separator.
            'storage_path'      => self::PRIVATE_ROOT_SEGMENT . '/' . $chada_travel_subdir_clean . '/' . $chada_travel_filename,
            'mime_type'         => $chada_travel_detected_mime,
            'original_filename' => self::sanitize_filename($chada_travel_original_name),
            'size'              => (int) ($chada_travel_file['size'] ?? 0),
        ];
    }

    /**
     * Streams a stored file; callers must complete capability/nonce authorization before calling this.
     * $chada_travel_force_download renders a real "Download" action (Content-Disposition: attachment) distinct from
     * the browser-previewed "View" action (Content-Disposition: inline). Returns HTTP 404 - without ever
     * disclosing the physical path or the reason - for anything missing, malformed, or outside the private
     * upload base; see resolve_private_path().
     */
    public static function stream_private_file(
        string $chada_travel_storage_path,
        string $chada_travel_mime_type,
        string $chada_travel_download_name,
        bool $chada_travel_force_download = false
    ): void {
        $chada_travel_path = self::resolve_private_path($chada_travel_storage_path);
        if ($chada_travel_path === null) {
            self::send_not_found();
        }

        $chada_travel_headers = self::build_stream_headers($chada_travel_mime_type, $chada_travel_download_name, $chada_travel_force_download);
        foreach ($chada_travel_headers as $chada_travel_header) {
            header($chada_travel_header);
        }
        $chada_travel_size = @filesize($chada_travel_path);
        if ($chada_travel_size !== false) {
            header('Content-Length: ' . $chada_travel_size);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming a private, already-authorized file.
        readfile($chada_travel_path);
        exit;
    }

    /**
     * Pure header construction, isolated from stream_private_file()'s exit() so View/Download disposition
     * and MIME-type behavior can be asserted directly in a test without triggering the real streaming exit.
     *
     * @return list<string> Each entry is a full "Name: value" header string, in the order they are sent.
     */
    public static function build_stream_headers(
        string $chada_travel_mime_type,
        string $chada_travel_download_name,
        bool $chada_travel_force_download
    ): array {
        return [
            'Content-Type: ' . $chada_travel_mime_type,
            'Content-Disposition: ' . ($chada_travel_force_download ? 'attachment' : 'inline')
                . '; filename="' . rawurlencode($chada_travel_download_name) . '"',
            'X-Content-Type-Options: nosniff',
            'Cache-Control: private, no-store',
        ];
    }

    /**
     * Deletes a booking-owned protected file for an authorized cleanup workflow. Callers
     * must already have proven the storage key belongs to the eligible document row via its database
     * application/order relationship - this method performs no ownership check of its own beyond
     * resolve_private_path()'s own root/traversal/symlink-escape safety. An empty/already-null key is treated as
     * `already_missing` without touching the filesystem (idempotent: a rerun after a prior successful delete
     * must not fail). `resolve_private_path()` deliberately cannot distinguish "unsafe" from "missing" (so a
     * caller never learns why an untrusted lookup failed); because $chada_travel_storage_key here always originates
     * from our own trusted document row rather than user input, a null resolution is safely treated the same
     * way - "nothing left to delete" - rather than as a security failure.
     *
     * @return 'already_missing'|'deleted'|'failed'
     */
    public static function delete_owned_private_file(string $chada_travel_storage_key): string {
        if (trim($chada_travel_storage_key) === '') {
            return 'already_missing';
        }
        $chada_travel_path = self::resolve_private_path($chada_travel_storage_key);
        if ($chada_travel_path === null) {
            return 'already_missing';
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- verified plugin-managed private-file deletion, not a public upload.
        return @unlink($chada_travel_path) ? 'deleted' : 'failed';
    }

    /**
     * The single canonical, security-conscious resolver from a portable database storage key to a verified
     * absolute physical path. Accepts the standard `chada-travel-private/proofs|documents/<file>` key, an older or
     * test record's bare `proofs|documents/<file>` (relative-to-base) form, and either `/` or `\` input
     * separators - then normalizes, validates, and canonicalizes before ever touching the filesystem.
     *
     * Returns null (never a path, never a reason) for: an empty key, a null byte, an absolute path, a
     * drive-letter path, a stream-wrapper/URI path, any `.`/`..` segment, a missing/unreadable file, or a
     * resolved file that escapes the real private upload base. Every caller must treat null identically to
     * "not found".
     */
    public static function resolve_private_path(string $chada_travel_storage_key): ?string {
        if ($chada_travel_storage_key === '' || str_contains($chada_travel_storage_key, "\0")) {
            return null;
        }
        // Accept a Windows-authored or mixed-separator key on input; only '/' is ever stored in the database.
        $chada_travel_normalized = str_replace('\\', '/', $chada_travel_storage_key);
        if (self::has_unsafe_path_form($chada_travel_normalized)) {
            return null;
        }

        $chada_travel_segments = [];
        foreach (explode('/', $chada_travel_normalized) as $chada_travel_segment) {
            if ($chada_travel_segment === '') {
                continue;
            }
            if ($chada_travel_segment === '.' || $chada_travel_segment === '..') {
                return null;
            }
            $chada_travel_segments[] = $chada_travel_segment;
        }
        if (!$chada_travel_segments) {
            return null;
        }

        // Remove at most one leading canonical root segment; never produces a duplicated
        // `chada-travel-private/chada-travel-private/...` path.
        if ($chada_travel_segments[0] === self::PRIVATE_ROOT_SEGMENT) {
            array_shift($chada_travel_segments);
        }
        if (!$chada_travel_segments) {
            return null;
        }
        $chada_travel_relative = implode(DIRECTORY_SEPARATOR, $chada_travel_segments);

        return self::resolve_within_base(self::private_upload_base(), $chada_travel_relative);
    }

    /** Resolves $chada_travel_relative against $chada_travel_base, returning the real path only if it is a readable file inside it. */
    private static function resolve_within_base(string $chada_travel_base, string $chada_travel_relative): ?string {
        $chada_travel_real_base = realpath($chada_travel_base);
        if ($chada_travel_real_base === false) {
            return null;
        }
        $chada_travel_candidate      = $chada_travel_real_base . DIRECTORY_SEPARATOR . $chada_travel_relative;
        $chada_travel_real_candidate = realpath($chada_travel_candidate);
        if ($chada_travel_real_candidate === false || !is_file($chada_travel_real_candidate) || !is_readable($chada_travel_real_candidate)) {
            return null;
        }
        if (!self::is_within_private_base($chada_travel_real_candidate, $chada_travel_real_base)) {
            return null;
        }
        return $chada_travel_real_candidate;
    }

    /**
     * Boundary-safe "is this real path a descendant of this real base" check. The base is compared with its
     * directory boundary included (a trailing separator), so a sibling directory whose name merely starts
     * with the base name (e.g. `chada-travel-private-backup` next to `chada-travel-private`) is never mistaken for a
     * child. Falls back to a case-insensitive comparison on filesystems where that is the norm (Windows),
     * since realpath() there may normalize drive/directory casing differently than the input.
     */
    public static function is_within_private_base(string $chada_travel_real_path, string $chada_travel_real_base): bool {
        $chada_travel_boundary = rtrim($chada_travel_real_base, '/\\') . DIRECTORY_SEPARATOR;
        if (self::is_case_insensitive_filesystem()) {
            return str_starts_with(strtolower($chada_travel_real_path), strtolower($chada_travel_boundary));
        }
        return str_starts_with($chada_travel_real_path, $chada_travel_boundary);
    }

    /** Resolves the real, canonical private-upload base directory (for tests and the resolver alike). */
    public static function private_upload_base(): string {
        return function_exists('wp_upload_dir')
            ? rtrim((string) wp_upload_dir()['basedir'], '/\\') . DIRECTORY_SEPARATOR . self::PRIVATE_ROOT_SEGMENT
            : rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . self::PRIVATE_ROOT_SEGMENT;
    }

    /** True on filesystems where path comparison is commonly case-insensitive (Windows). */
    private static function is_case_insensitive_filesystem(): bool {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Rejects input forms that must never reach filesystem resolution: an absolute Unix/UNC-style path
     * (leading `/`), a Windows drive-letter path (`C:...`), or a stream-wrapper/URI path (`php://`,
     * `phar://`, `file://`, ...). Operates on the already `/`-normalized key.
     */
    private static function has_unsafe_path_form(string $chada_travel_normalized): bool {
        if ($chada_travel_normalized[0] === '/') {
            return true;
        }
        if (preg_match('/^[A-Za-z]:/', $chada_travel_normalized) === 1) {
            return true;
        }
        if (preg_match('#^[A-Za-z][A-Za-z0-9+.\-]*://#', $chada_travel_normalized) === 1) {
            return true;
        }
        return false;
    }

    /** @return never */
    private static function send_not_found() {
        if (function_exists('status_header')) {
            status_header(404);
        }
        exit;
    }

    private static function private_upload_dir(string $chada_travel_subdir): string {
        $chada_travel_physical_subdir = str_replace('/', DIRECTORY_SEPARATOR, $chada_travel_subdir);
        $chada_travel_dir             = self::private_upload_base() . DIRECTORY_SEPARATOR . $chada_travel_physical_subdir;
        if (!is_dir($chada_travel_dir)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- dependency-free fallback for tests without WordPress loaded.
            function_exists('wp_mkdir_p') ? wp_mkdir_p($chada_travel_dir) : mkdir($chada_travel_dir, 0755, true);
        }
        self::protect_directory($chada_travel_dir);
        return $chada_travel_dir;
    }

    /** Blocks direct web access to the private uploads tree on hosts that serve it (Apache/IIS conventions). */
    private static function protect_directory(string $chada_travel_dir): void {
        $chada_travel_index_file = $chada_travel_dir . DIRECTORY_SEPARATOR . 'index.php';
        if (!file_exists($chada_travel_index_file)) {
            file_put_contents($chada_travel_index_file, "<?php\n// Silence is golden.\n");
        }
        $chada_travel_htaccess = $chada_travel_dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($chada_travel_htaccess)) {
            file_put_contents(
                $chada_travel_htaccess,
                "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Deny from all\n"
                . "</IfModule>\n"
            );
        }
    }

    /** Normalizes a fixed configured subdir (e.g. "proofs"/"documents") into a safe, portable ('/') form. */
    private static function clean_subdir(string $chada_travel_subdir): string {
        $chada_travel_normalized = str_replace('\\', '/', trim($chada_travel_subdir, "/\\"));
        $chada_travel_segments   = array_filter(
            explode('/', $chada_travel_normalized),
            static fn(string $chada_travel_segment): bool => $chada_travel_segment !== '' && $chada_travel_segment !== '.'
                && $chada_travel_segment !== '..'
        );
        return implode('/', $chada_travel_segments);
    }

    private static function detect_mime_type(string $chada_travel_tmp_name, string $chada_travel_original_name): string {
        if ($chada_travel_tmp_name === '' || !is_readable($chada_travel_tmp_name)) {
            return '';
        }
        if (function_exists('finfo_open')) {
            $chada_travel_finfo = finfo_open(FILEINFO_MIME_TYPE);
            $chada_travel_type  = $chada_travel_finfo ? finfo_file($chada_travel_finfo, $chada_travel_tmp_name) : false;
            if ($chada_travel_finfo) {
                finfo_close($chada_travel_finfo);
            }
            if ($chada_travel_type) {
                return (string) $chada_travel_type;
            }
        }
        if (function_exists('wp_check_filetype_and_ext')) {
            // WordPress core's image probe can emit a PHP warning for malformed content; never let that warning corrupt
            // the JSON REST response used by the customer upload page.
            set_error_handler(static function (): bool {
                return true;
            }, E_WARNING | E_NOTICE);
            try {
                $chada_travel_checked = wp_check_filetype_and_ext($chada_travel_tmp_name, $chada_travel_original_name);
            } finally {
                restore_error_handler();
            }
            if (!empty($chada_travel_checked['type'])) {
                return (string) $chada_travel_checked['type'];
            }
        }
        return '';
    }

    /**
     * Stores an HTTP upload through WordPress's upload API while keeping the final path private. Fixture files used
     * by non-HTTP test harnesses use a copy fallback because is_uploaded_file() intentionally rejects them.
     *
     * @param array{name?:string, type?:string, tmp_name?:string, size?:int, error?:int} $chada_travel_file
     */
    private static function store_file(
        array $chada_travel_file,
        string $chada_travel_source,
        string $chada_travel_subdir,
        string $chada_travel_filename,
        string $chada_travel_destination
    ): string {
        if ($chada_travel_source === '' || !is_readable($chada_travel_source)) {
            return '';
        }
        if (function_exists('is_uploaded_file') && is_uploaded_file($chada_travel_source)) {
            if (!function_exists('wp_handle_upload') && defined('ABSPATH')) {
                $chada_travel_upload_api_file = ABSPATH . 'wp-admin/includes/file.php';
                if (is_file($chada_travel_upload_api_file)) {
                    require_once $chada_travel_upload_api_file;
                }
            }
            if (function_exists('wp_handle_upload')) {
                $chada_travel_private_base = self::private_upload_base();
                self::protect_directory(self::private_upload_dir($chada_travel_subdir));
                $chada_travel_upload_filter = static function (array $chada_travel_uploads) use (
                    $chada_travel_private_base,
                    $chada_travel_subdir
                ): array {
                    $chada_travel_path = $chada_travel_private_base . DIRECTORY_SEPARATOR
                        . str_replace('/', DIRECTORY_SEPARATOR, $chada_travel_subdir);
                    return [
                        'path'    => $chada_travel_path,
                        'url'     => '',
                        'subdir'  => '/' . self::PRIVATE_ROOT_SEGMENT . '/' . $chada_travel_subdir,
                        'basedir' => $chada_travel_private_base,
                        'baseurl' => '',
                        'error'   => false,
                    ];
                };
                add_filter('upload_dir', $chada_travel_upload_filter);
                $chada_travel_result = wp_handle_upload($chada_travel_file, [
                    'test_form'                => false,
                    'test_type'                => true,
                    'unique_filename_callback' => static function () use ($chada_travel_filename): string {
                        return $chada_travel_filename;
                    },
                ]);
                remove_filter('upload_dir', $chada_travel_upload_filter);
                if (!is_array($chada_travel_result) || !empty($chada_travel_result['error']) || empty($chada_travel_result['file'])) {
                    return '';
                }
                return (string) $chada_travel_result['file'];
            }
        }
        $chada_travel_copied = copy($chada_travel_source, $chada_travel_destination);
        return $chada_travel_copied ? $chada_travel_destination : '';
    }

    private static function sanitize_filename(string $chada_travel_name): string {
        if (function_exists('sanitize_file_name')) {
            return sanitize_file_name($chada_travel_name);
        }
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $chada_travel_name) ?: 'upload';
    }
}
