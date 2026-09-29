<?php
/**
 * Single authoritative definition/resolver for the Documents & Uploads Settings tab: the fixed Visa Document and
 * Bank Payment Proof MIME registries (in their stable canonical order), MB/byte conversion, the current server
 * (`wp_max_upload_size()`) upload ceiling, and the effective runtime rules every upload consumer (Settings
 * rendering, browser localization, REST validation, CHADA_TRAVEL_Upload_Service) must agree on:
 *
 * ```
 * effective_global_max_bytes      = min(configured chada_travel_upload_max_bytes, current wp_max_upload_size())
 * effective_requirement_max_bytes = min(requirement max, configured chada_travel_upload_max_bytes, server limit)
 * effective_proof_mime_types      = configured chada_travel_payment_proof_mime_types
 * effective_requirement_mime_types= intersection(requirement mime types, configured document mime types)
 * ```
 *
 * Never rewrites a stored option and never reads/writes a link-expiry token - those remain governed entirely by
 * CHADA_TRAVEL_Order_Repository::issue_upload_token()/CHADA_TRAVEL_Payment_Repository::generate_proof_token() at issuance time.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Upload_Settings {
    /** Named byte-conversion constant; every MB<->byte conversion in this feature goes through this. */
    public const BYTES_PER_MB = 1048576;

    public const MIN_EXPIRY_HOURS = 1;
    public const MAX_EXPIRY_HOURS = 8760;

    /** Maximum decimal places accepted on the Maximum File Size (MB) input; more is "excessively precise". */
    private const MAX_MB_INPUT_DECIMALS = 4;
    /** Maximum digits accepted before the decimal point; bounds the input against absurd/overflow values. */
    private const MAX_MB_INPUT_INTEGER_DIGITS = 9;

    /**
     * Fixed Visa Document MIME choices in their stable display/storage order. Structural only - no translation
     * call - so this stays safely readable from pure-PHP contexts (validators, PHPUnit) with no WordPress i18n
     * runtime loaded; CHADA_TRAVEL_Admin_Settings renders its own translated labels alongside these.
     *
     * @return list<array{mime: string, extensions: list<string>, label: string}>
     */
    public static function get_visa_document_mime_registry(): array {
        return [
            ['mime' => 'application/pdf', 'extensions' => ['pdf'], 'label' => 'PDF'],
            ['mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg'], 'label' => 'JPEG'],
            ['mime' => 'image/png', 'extensions' => ['png'], 'label' => 'PNG'],
        ];
    }

    /**
     * Fixed Bank Payment Proof MIME choices in their stable display/storage order. Deliberately excludes PDF.
     *
     * @return list<array{mime: string, extensions: list<string>, label: string}>
     */
    public static function get_bank_proof_mime_registry(): array {
        return [
            ['mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg'], 'label' => 'JPEG'],
            ['mime' => 'image/png', 'extensions' => ['png'], 'label' => 'PNG'],
        ];
    }

    /** @return list<string> Canonical-order MIME values only, for allowlist/intersection checks. */
    public static function get_visa_document_mime_values(): array {
        return array_column(self::get_visa_document_mime_registry(), 'mime');
    }

    /** @return list<string> Canonical-order MIME values only, for allowlist/intersection checks. */
    public static function get_bank_proof_mime_values(): array {
        return array_column(self::get_bank_proof_mime_registry(), 'mime');
    }

    /**
     * Resolves the current effective WordPress/PHP upload ceiling. `wp_max_upload_size()` is the one
     * authoritative source - it already folds in PHP's `upload_max_filesize`/`post_max_size` and any
     * WordPress/multisite upload-size filtering. Returns 0 (never negative) when no usable positive limit is
     * reported, which every caller below must treat as "fail closed", never as an unbounded value.
     */
    public static function get_server_max_upload_bytes(): int {
        if (!function_exists('wp_max_upload_size')) {
            return 0;
        }
        $chada_travel_bytes = (int) wp_max_upload_size();
        return $chada_travel_bytes > 0 ? $chada_travel_bytes : 0;
    }

    /** Deterministic MB -> byte conversion; the one place this multiplication happens. */
    public static function mb_to_bytes(float $chada_travel_mb): int {
        return (int) round($chada_travel_mb * self::BYTES_PER_MB);
    }

    /** Deterministic byte -> MB conversion for display, rounded to a stable, round-trippable precision. */
    public static function bytes_to_mb(int $chada_travel_bytes): float {
        return round($chada_travel_bytes / self::BYTES_PER_MB, self::MAX_MB_INPUT_DECIMALS);
    }

    /**
     * Parses a submitted human-readable MB size into a deterministic positive integer byte value, or null for
     * anything not a clean, finite, appropriately-precise positive number: empty, nonnumeric, negative, zero,
     * scientific notation, a sign, extra decimal places, an array, or an absurdly large integer part.
     *
     * @param mixed $chada_travel_raw
     */
    public static function parse_mb_input($chada_travel_raw): ?int {
        if (is_array($chada_travel_raw) || is_bool($chada_travel_raw)) {
            return null;
        }
        $chada_travel_value = trim((string) $chada_travel_raw);
        if ($chada_travel_value === '') {
            return null;
        }
        $chada_travel_pattern = '/^\d{1,' . self::MAX_MB_INPUT_INTEGER_DIGITS . '}(\.\d{1,' . self::MAX_MB_INPUT_DECIMALS
            . '})?$/';
        if (!preg_match($chada_travel_pattern, $chada_travel_value)) {
            return null;
        }
        $chada_travel_mb = (float) $chada_travel_value;
        if (!is_finite($chada_travel_mb) || $chada_travel_mb <= 0) {
            return null;
        }
        $chada_travel_bytes = self::mb_to_bytes($chada_travel_mb);
        if ($chada_travel_bytes <= 0 || $chada_travel_bytes > PHP_INT_MAX / 2) {
            return null;
        }
        return $chada_travel_bytes;
    }

    /**
     * Strictly validates a submitted link-expiry hours value: a plain nonnegative-digit string only (never a
     * decimal, sign, scientific notation, empty string, or array), within [MIN_EXPIRY_HOURS, MAX_EXPIRY_HOURS].
     * Zero is always rejected - it must never be interpreted as "never expires".
     *
     * @param mixed $chada_travel_raw
     */
    public static function is_valid_expiry_hours($chada_travel_raw): bool {
        if (is_array($chada_travel_raw) || is_bool($chada_travel_raw) || is_float($chada_travel_raw)) {
            return false;
        }
        $chada_travel_value = trim((string) $chada_travel_raw);
        if ($chada_travel_value === '' || !preg_match('/^\d+$/', $chada_travel_value)) {
            return false;
        }
        $chada_travel_hours = (int) $chada_travel_value;
        return $chada_travel_hours >= self::MIN_EXPIRY_HOURS && $chada_travel_hours <= self::MAX_EXPIRY_HOURS;
    }

    /**
     * Validates a submitted checkbox-group MIME selection against a fixed canonical allowlist. Returns
     * `valid => false` for a non-array payload, any non-string/nested element, an unknown or wrong-context
     * value, a duplicate, or an empty selection - the whole-form save must reject on any of these rather than
     * silently stripping the offending values. `reason` distinguishes "nothing selected" from "an invalid value
     * was submitted" only for a clearer administrator-facing message; both are equally a whole-payload rejection.
     *
     * @param mixed $chada_travel_raw
     * @param list<string> $chada_travel_canonical_order
     * @return array{valid: bool, clean: list<string>, reason: string}
     */
    public static function validate_mime_checkbox_selection($chada_travel_raw, array $chada_travel_canonical_order): array {
        if (!is_array($chada_travel_raw)) {
            return ['valid' => false, 'clean' => [], 'reason' => 'malformed'];
        }
        $chada_travel_seen = [];
        foreach ($chada_travel_raw as $chada_travel_value) {
            if (!is_string($chada_travel_value) || !in_array($chada_travel_value, $chada_travel_canonical_order, true)) {
                return ['valid' => false, 'clean' => [], 'reason' => 'invalid'];
            }
            if (isset($chada_travel_seen[$chada_travel_value])) {
                return ['valid' => false, 'clean' => [], 'reason' => 'invalid'];
            }
            $chada_travel_seen[$chada_travel_value] = true;
        }
        if (!$chada_travel_seen) {
            return ['valid' => false, 'clean' => [], 'reason' => 'empty'];
        }
        return [
            'valid' => true,
            'clean' => self::normalize_order(array_keys($chada_travel_seen), $chada_travel_canonical_order),
            'reason' => '',
        ];
    }

    /**
     * @param array<int|string, mixed> $chada_travel_values
     * @param list<string> $chada_travel_canonical_order
     * @return list<string>
     */
    private static function normalize_order(array $chada_travel_values, array $chada_travel_canonical_order): array {
        return array_values(array_intersect($chada_travel_canonical_order, $chada_travel_values));
    }

    /**
     * The shared effective maximum: min(configured plugin-wide byte limit, current server byte limit). Returns
     * 0 (fail closed - never unbounded) when either input is not a genuine positive value.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_effective_global_max_bytes(array $chada_travel_settings, ?int $chada_travel_server_max = null): int {
        $chada_travel_server_max = $chada_travel_server_max ?? self::get_server_max_upload_bytes();
        $chada_travel_configured = max(0, (int) ($chada_travel_settings['chada_travel_upload_max_bytes'] ?? 0));
        if ($chada_travel_configured <= 0 || $chada_travel_server_max <= 0) {
            return 0;
        }
        return min($chada_travel_configured, $chada_travel_server_max);
    }

    /**
     * Effective Bank Payment Proof rules: the configured proof MIME allowlist as-is, and the shared effective
     * maximum byte size. Bank proof has no per-record override, unlike a Visa document requirement.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{mime_types: list<string>, max_bytes: int, max_files: int}
     */
    public static function get_effective_proof_rules(array $chada_travel_settings, ?int $chada_travel_server_max = null): array {
        return [
            'mime_types' => self::normalize_order(
                (array) ($chada_travel_settings['chada_travel_payment_proof_mime_types'] ?? []),
                self::get_bank_proof_mime_values()
            ),
            'max_bytes' => self::get_effective_global_max_bytes($chada_travel_settings, $chada_travel_server_max),
            'max_files' => 1,
        ];
    }

    /**
     * Effective rules for one Visa Document requirement: the intersection of the requirement's own allowed MIME
     * types with the configured global allowlist (never a union - removing a type globally makes it
     * unavailable even if an older requirement still lists it; adding a type globally never broadens a
     * requirement that does not itself allow it), and the minimum of the requirement's own byte ceiling, the
     * configured plugin-wide ceiling, and the current server ceiling.
     *
     * @param list<string>          $chada_travel_requirement_mime_types
     * @param array<string, mixed>  $chada_travel_settings
     * @return array{mime_types: list<string>, max_bytes: int, max_files: int}
     */
    public static function get_effective_requirement_rules(
        array $chada_travel_requirement_mime_types,
        int $chada_travel_requirement_max_bytes,
        array $chada_travel_settings,
        ?int $chada_travel_server_max = null
    ): array {
        $chada_travel_configured_types = (array) ($chada_travel_settings['chada_travel_document_upload_mime_types'] ?? []);
        $chada_travel_effective_types  = self::normalize_order(
            array_intersect($chada_travel_requirement_mime_types, $chada_travel_configured_types),
            self::get_visa_document_mime_values()
        );
        $chada_travel_global_effective = self::get_effective_global_max_bytes($chada_travel_settings, $chada_travel_server_max);
        $chada_travel_requirement_max  = max(0, $chada_travel_requirement_max_bytes);
        $chada_travel_max_bytes = ($chada_travel_requirement_max > 0 && $chada_travel_global_effective > 0)
            ? min($chada_travel_requirement_max, $chada_travel_global_effective) : 0;
        return ['mime_types' => $chada_travel_effective_types, 'max_bytes' => $chada_travel_max_bytes, 'max_files' => 1];
    }

    /**
     * Safe, administrator-facing status data for the Settings page: configured/effective ceilings, whether the
     * configured value currently exceeds the server limit (a later hosting reduction, never silently rewritten),
     * and the raw PHP ini values shown only as explanatory detail alongside the authoritative effective value.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{
     *     configured_max_bytes: int, server_max_bytes: int, effective_max_bytes: int, over_server_limit: bool,
     *     server_limit_unavailable: bool, php_upload_max_filesize: string, php_post_max_size: string
     * }
     */
    public static function get_status(array $chada_travel_settings, ?int $chada_travel_server_max = null): array {
        $chada_travel_server_max = $chada_travel_server_max ?? self::get_server_max_upload_bytes();
        $chada_travel_configured = max(0, (int) ($chada_travel_settings['chada_travel_upload_max_bytes'] ?? 0));
        return [
            'configured_max_bytes'     => $chada_travel_configured,
            'server_max_bytes'         => $chada_travel_server_max,
            'effective_max_bytes'      => self::get_effective_global_max_bytes($chada_travel_settings, $chada_travel_server_max),
            'over_server_limit'        => $chada_travel_server_max > 0 && $chada_travel_configured > $chada_travel_server_max,
            'server_limit_unavailable' => $chada_travel_server_max <= 0,
            'php_upload_max_filesize'  => function_exists('ini_get') ? (string) ini_get('upload_max_filesize') : '',
            'php_post_max_size'        => function_exists('ini_get') ? (string) ini_get('post_max_size') : '',
        ];
    }
}
