<?php
/**
 * Pure request-level upload validation shared by the Bank Deposit Slip and future document uploads.
 *
 * Contains no filesystem or WordPress access so it can be exercised directly by PHPUnit. Detected-MIME
 * checks are validated separately by CHADA_TRAVEL_Upload_Service once the file content has actually been read.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Upload_Validator {
    /** @var array<string, string> */
    private const EXTENSION_MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
    ];

    /**
     * Validates the request-level $_FILES-shaped entry: upload error code, size, and filename extension.
     *
     * @param array{name?:string, size?:int, error?:int} $chada_travel_file  Raw uploaded-file entry.
     * @param array{mime_types: list<string>, max_bytes: int}         $chada_travel_rules PHP-authoritative upload rules.
     * @return list<string> Human-readable errors; empty when the request-level checks pass.
     */
    public static function validate_upload(array $chada_travel_file, array $chada_travel_rules): array {
        $chada_travel_error_code = (int) ($chada_travel_file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($chada_travel_error_code === UPLOAD_ERR_NO_FILE) {
            return ['Choose a file to upload.'];
        }
        if ($chada_travel_error_code !== UPLOAD_ERR_OK) {
            return ['The upload failed. Try again.'];
        }

        $chada_travel_errors = [];
        $chada_travel_size      = (int) ($chada_travel_file['size'] ?? 0);
        $chada_travel_max_bytes = $chada_travel_rules['max_bytes'];
        if ($chada_travel_size <= 0) {
            $chada_travel_errors[] = 'The uploaded file is empty.';
        } elseif ($chada_travel_max_bytes > 0 && $chada_travel_size > $chada_travel_max_bytes) {
            $chada_travel_errors[] = 'The file exceeds the maximum allowed size of '
                . self::format_bytes($chada_travel_max_bytes) . '.';
        }

        $chada_travel_allowed_extensions = self::extensions_for($chada_travel_rules['mime_types']);
        $chada_travel_extension = strtolower((string) pathinfo((string) ($chada_travel_file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($chada_travel_extension === '' || !in_array($chada_travel_extension, $chada_travel_allowed_extensions, true)) {
            $chada_travel_errors[] = 'Upload a file in an allowed format: '
                . strtoupper(implode(', ', $chada_travel_allowed_extensions)) . '.';
        }

        return $chada_travel_errors;
    }

    /**
     * @param list<string> $chada_travel_allowed_mime_types Server-configured allowlist.
     * @return list<string> Empty when the actually-detected content type is allowed.
     */
    public static function validate_detected_mime(string $chada_travel_detected_mime, array $chada_travel_allowed_mime_types): array {
        return in_array($chada_travel_detected_mime, $chada_travel_allowed_mime_types, true)
            ? [] : ['The file content does not match an allowed format.'];
    }

    /**
     * @param list<string> $chada_travel_mime_types
     * @return list<string>
     */
    public static function extensions_for(array $chada_travel_mime_types): array {
        $chada_travel_extensions = [];
        foreach (self::EXTENSION_MIME_MAP as $chada_travel_extension => $chada_travel_mime) {
            if (in_array($chada_travel_mime, $chada_travel_mime_types, true)) {
                $chada_travel_extensions[] = $chada_travel_extension;
            }
        }
        return $chada_travel_extensions;
    }

    public static function format_bytes(int $chada_travel_bytes): string {
        return $chada_travel_bytes >= 1048576 ? rtrim(rtrim(number_format($chada_travel_bytes / 1048576, 1), '0'), '.') . ' MB'
            : number_format($chada_travel_bytes / 1024) . ' KB';
    }
}
