<?php
/**
 * Normalized reads, truthful status classification, source-precedence URL resolution, and policy-bundle
 * readiness for the three policies defined by CHADA_TRAVEL_Policy_Registry (Privacy, Terms and Conditions,
 * Cancellation and Refund). This is the one authoritative service behind the Settings "Policies & Consent" tab,
 * CHADA_TRAVEL_Readiness's admin notice, the checkout consent rows, and the immutable per-order policy snapshot, so
 * policy rules are never duplicated across rendering, validation, readiness, and snapshot generation.
 *
 * Deliberately independent of CHADA_TRAVEL_Page_Settings: a policy page never requires a plugin shortcode (any
 * published, non-trashed/private WordPress Page is a usable policy source), so the two services classify
 * readiness differently even though both resolve a WordPress Page id to a public URL.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Policy_Settings {
    public const STATUS_READY_PAGE          = 'ready_page';
    public const STATUS_READY_EXTERNAL      = 'ready_external';
    public const STATUS_DRAFT_FALLBACK      = 'draft_fallback';
    public const STATUS_PRIVATE_FALLBACK    = 'private_fallback';
    public const STATUS_MISSING_FALLBACK    = 'missing_fallback';
    public const STATUS_DRAFT_NO_FALLBACK   = 'draft_no_fallback';
    public const STATUS_PRIVATE_NO_FALLBACK = 'private_no_fallback';
    public const STATUS_MISSING_NO_FALLBACK = 'missing_no_fallback';
    public const STATUS_NOT_CONFIGURED      = 'not_configured';

    public const SOURCE_PAGE     = 'page';
    public const SOURCE_EXTERNAL = 'external';
    public const SOURCE_NONE     = 'none';

    private const POLICY_VERSION_MAX_LENGTH = 40;

    /** @return array<string, string> Status constant => translatable, color-independent label. */
    public static function get_status_labels(): array {
        return [
            self::STATUS_READY_PAGE          => __('Ready — WordPress Page', 'chada-travel'),
            self::STATUS_READY_EXTERNAL      => __('Ready — External URL', 'chada-travel'),
            self::STATUS_DRAFT_FALLBACK      => __('Draft Page — Using External Fallback', 'chada-travel'),
            self::STATUS_PRIVATE_FALLBACK    => __('Private Page — Using External Fallback', 'chada-travel'),
            self::STATUS_MISSING_FALLBACK    => __('Missing Page — Using External Fallback', 'chada-travel'),
            self::STATUS_DRAFT_NO_FALLBACK   => __('Draft Page — No Usable Fallback', 'chada-travel'),
            self::STATUS_PRIVATE_NO_FALLBACK => __('Private Page — No Usable Fallback', 'chada-travel'),
            self::STATUS_MISSING_NO_FALLBACK => __('Missing Page — No Usable Fallback', 'chada-travel'),
            self::STATUS_NOT_CONFIGURED      => __('Not Configured', 'chada-travel'),
        ];
    }

    /** True only for a status that resolves to a genuinely usable, publicly reachable policy URL. */
    public static function is_ready_status(string $chada_travel_status): bool {
        return in_array($chada_travel_status, [self::STATUS_READY_PAGE, self::STATUS_READY_EXTERNAL], true);
    }

    /** @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result. */
    public static function get_page_id(string $chada_travel_key, array $chada_travel_settings): int {
        $chada_travel_option = CHADA_TRAVEL_Policy_Registry::get_definitions()[$chada_travel_key]['page_option'] ?? '';
        return $chada_travel_option !== '' ? max(0, (int) ($chada_travel_settings[$chada_travel_option] ?? 0)) : 0;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function get_external_url(string $chada_travel_key, array $chada_travel_settings): string {
        $chada_travel_option = CHADA_TRAVEL_Policy_Registry::get_definitions()[$chada_travel_key]['url_option'] ?? '';
        return $chada_travel_option !== '' ? trim((string) ($chada_travel_settings[$chada_travel_option] ?? '')) : '';
    }

    /**
     * Classifies one policy's truthful current state following the source precedence: a Ready WordPress Page,
     * otherwise a valid external fallback, otherwise no usable source. A policy page never requires a
     * shortcode - only that it resolves, is a real `page` post type, is published, is not trashed/private, and
     * `get_permalink()` returns a usable URL.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{status: string, url: string, page_id: int, source: string}
     */
    public static function resolve_policy_status(string $chada_travel_key, array $chada_travel_settings): array {
        $chada_travel_page_id     = self::get_page_id($chada_travel_key, $chada_travel_settings);
        $chada_travel_external_url = self::get_external_url($chada_travel_key, $chada_travel_settings);
        $chada_travel_has_fallback = self::is_safe_external_url($chada_travel_external_url);

        if ($chada_travel_page_id <= 0) {
            return $chada_travel_has_fallback
                ? ['status' => self::STATUS_READY_EXTERNAL, 'url' => $chada_travel_external_url, 'page_id' => 0,
                    'source' => self::SOURCE_EXTERNAL]
                : ['status' => self::STATUS_NOT_CONFIGURED, 'url' => '', 'page_id' => 0, 'source' => self::SOURCE_NONE];
        }

        $chada_travel_post = self::get_page_post($chada_travel_page_id);
        if (!$chada_travel_post) {
            return self::fallback_or_no_fallback(
                self::STATUS_MISSING_FALLBACK,
                self::STATUS_MISSING_NO_FALLBACK,
                $chada_travel_has_fallback,
                $chada_travel_external_url,
                $chada_travel_page_id
            );
        }

        $chada_travel_post_status = (string) $chada_travel_post->post_status;
        if ($chada_travel_post_status === 'private') {
            return self::fallback_or_no_fallback(
                self::STATUS_PRIVATE_FALLBACK,
                self::STATUS_PRIVATE_NO_FALLBACK,
                $chada_travel_has_fallback,
                $chada_travel_external_url,
                $chada_travel_page_id
            );
        }
        if ($chada_travel_post_status !== 'publish') {
            return self::fallback_or_no_fallback(
                self::STATUS_DRAFT_FALLBACK,
                self::STATUS_DRAFT_NO_FALLBACK,
                $chada_travel_has_fallback,
                $chada_travel_external_url,
                $chada_travel_page_id
            );
        }

        $chada_travel_permalink = self::safe_permalink($chada_travel_page_id);
        if ($chada_travel_permalink === '') {
            // Published, but no resolvable permalink (e.g. this post type/site cannot generate one) - not a
            // usable customer-facing page, so treat exactly like Missing.
            return self::fallback_or_no_fallback(
                self::STATUS_MISSING_FALLBACK,
                self::STATUS_MISSING_NO_FALLBACK,
                $chada_travel_has_fallback,
                $chada_travel_external_url,
                $chada_travel_page_id
            );
        }
        return [
            'status' => self::STATUS_READY_PAGE, 'url' => $chada_travel_permalink, 'page_id' => $chada_travel_page_id,
            'source' => self::SOURCE_PAGE,
        ];
    }

    /** @return array{status: string, url: string, page_id: int, source: string} */
    private static function fallback_or_no_fallback(
        string $chada_travel_fallback_status,
        string $chada_travel_no_fallback_status,
        bool $chada_travel_has_fallback,
        string $chada_travel_external_url,
        int $chada_travel_page_id
    ): array {
        return $chada_travel_has_fallback
            ? ['status' => $chada_travel_fallback_status, 'url' => $chada_travel_external_url, 'page_id' => $chada_travel_page_id,
                'source' => self::SOURCE_EXTERNAL]
            : ['status' => $chada_travel_no_fallback_status, 'url' => '', 'page_id' => $chada_travel_page_id,
                'source' => self::SOURCE_NONE];
    }

    /**
     * Resolves every registered policy's current status, keyed the same as CHADA_TRAVEL_Policy_Registry::get_definitions().
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, array{status: string, url: string, page_id: int, source: string}>
     */
    public static function resolve_effective_bundle(array $chada_travel_settings): array {
        $chada_travel_bundle = [];
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_bundle[$chada_travel_key] = self::resolve_policy_status($chada_travel_key, $chada_travel_settings);
        }
        return $chada_travel_bundle;
    }

    /**
     * Full policy-bundle readiness: Policy Version valid and nonempty, Effective Date valid/nonempty/not in the
     * future, and every registered policy resolves to a usable URL. Existing orders with a valid immutable
     * snapshot are never affected by this - it only gates whether a brand-new checkout draft may be created.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{
     *     ready: bool, version_valid: bool, effective_date_valid: bool,
     *     policies: array<string, array{status: string, url: string, page_id: int, source: string}>
     * }
     */
    public static function bundle_status(array $chada_travel_settings): array {
        $chada_travel_version_valid = self::is_valid_policy_version((string) ($chada_travel_settings['chada_travel_policy_version'] ?? ''));
        $chada_travel_effective_date_valid = self::is_valid_effective_date(
            (string) ($chada_travel_settings['chada_travel_policy_effective_date'] ?? ''),
            $chada_travel_settings
        );
        $chada_travel_policies = self::resolve_effective_bundle($chada_travel_settings);
        $chada_travel_all_ready = true;
        foreach ($chada_travel_policies as $chada_travel_status) {
            if (!self::is_ready_status($chada_travel_status['status'])) {
                $chada_travel_all_ready = false;
                break;
            }
        }
        return [
            'ready'                => $chada_travel_version_valid && $chada_travel_effective_date_valid && $chada_travel_all_ready,
            'version_valid'        => $chada_travel_version_valid,
            'effective_date_valid' => $chada_travel_effective_date_valid,
            'policies'             => $chada_travel_policies,
        ];
    }

    /** Compact identifier: letters, numbers, periods, underscores, and hyphens only, max 40 characters. */
    public static function is_valid_policy_version(string $chada_travel_version): bool {
        return $chada_travel_version !== ''
            && strlen($chada_travel_version) <= self::POLICY_VERSION_MAX_LENGTH
            && (bool) preg_match('/^[A-Za-z0-9._-]+$/', $chada_travel_version);
    }

    /**
     * A real calendar date in `Y-m-d` form, interpreted as "today" using the configured Company Timezone, that
     * is not in the future. Scheduled/future policy activation is not implemented in this initial version.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function is_valid_effective_date(string $chada_travel_date, array $chada_travel_settings): bool {
        if ($chada_travel_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $chada_travel_date)) {
            return false;
        }
        [$chada_travel_year, $chada_travel_month, $chada_travel_day] = array_map('intval', explode('-', $chada_travel_date));
        if (!checkdate($chada_travel_month, $chada_travel_day, $chada_travel_year)) {
            return false;
        }
        return $chada_travel_date <= self::company_today($chada_travel_settings);
    }

    /**
     * Resolves "today" (`Y-m-d`) in the configured Company Timezone, so a future-date rejection is judged from
     * the business's own local calendar day, not the server's.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function company_today(array $chada_travel_settings): string {
        $chada_travel_timezone_name = CHADA_TRAVEL_Config::get_company_timezone($chada_travel_settings);
        try {
            $chada_travel_timezone = new \DateTimeZone($chada_travel_timezone_name);
        } catch (\Exception $chada_travel_exception) {
            $chada_travel_timezone = new \DateTimeZone('UTC');
        }
        return (new \DateTimeImmutable('now', $chada_travel_timezone))->format('Y-m-d');
    }

    /**
     * Sanitizes a submitted page-id field to a nonnegative integer; a negative or non-numeric submission
     * safely becomes 0 (Not Configured) rather than a validation error.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_page_id($chada_travel_value): int {
        return max(0, (int) $chada_travel_value);
    }

    /**
     * Validates a submitted, already-sanitized page id before it may be written to an option. A zero id
     * always passes; a nonzero id must resolve to a real, non-trashed WordPress Page - never an attachment,
     * revision, ordinary post, or an id that does not exist at all. Draft/Private pages are intentionally
     * accepted here (an administrator may prepare a policy page before publishing); only resolve_policy_status()
     * decides whether the result is reported Ready.
     *
     * @return array{valid: bool, error: string}
     */
    public static function validate_page_id(int $chada_travel_page_id): array {
        if ($chada_travel_page_id <= 0) {
            return ['valid' => true, 'error' => ''];
        }
        if (!function_exists('get_post')) {
            // No WordPress loaded (pure-PHP unit test runtime): trust a positive submitted id, matching
            // CHADA_TRAVEL_Page_Settings's identical fallback. Real existence/post-type/trashed-record validation is
            // covered by tests/integration/policy-settings-runtime.php, which stubs get_post().
            return ['valid' => true, 'error' => ''];
        }
        $chada_travel_post = self::get_page_post($chada_travel_page_id);
        if (!$chada_travel_post) {
            return ['valid' => false, 'error' => __('Select an existing WordPress Page.', 'chada-travel')];
        }
        return ['valid' => true, 'error' => ''];
    }

    /** HTTP/HTTPS-only, rejecting `javascript:`, `data:`, malformed, and header/control-character URLs. */
    public static function is_safe_external_url(string $chada_travel_url): bool {
        if ($chada_travel_url === '' || preg_match('/[\x00-\x1F\x7F]/', $chada_travel_url)) {
            return false;
        }
        $chada_travel_scheme = strtolower((string) (function_exists('wp_parse_url')
            ? wp_parse_url($chada_travel_url, PHP_URL_SCHEME)
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Dependency-free validation tests run without WordPress loaded.
            : parse_url($chada_travel_url, PHP_URL_SCHEME)));
        return in_array($chada_travel_scheme, ['http', 'https'], true);
    }

    /**
     * Returns every selectable WordPress Page for the Policies & Consent page dropdowns: Published, Draft,
     * Pending, Private, and Future, excluding Trash and any non-`page` post type - so administrators can
     * prepare a policy page before publishing, matching the existing Pages & Links dropdown's post-status set.
     *
     * @return list<object>
     */
    public static function get_selectable_pages(): array {
        if (!function_exists('get_pages')) {
            return [];
        }
        return get_pages([
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC',
        ]);
    }

    /**
     * Conservative, idempotent same-site URL-to-page discovery for the settings-data migration: resolves
     * $chada_travel_url to a Page id only when it is unambiguously on this site and the id resolves to a real,
     * non-trashed Page. Never creates, publishes, or edits a page; returns 0 for any external, ambiguous, or
     * unresolvable URL.
     */
    public static function discover_page_id_from_url(string $chada_travel_url): int {
        global $wp_rewrite;
        if (!function_exists('url_to_postid') || !function_exists('home_url')
            || !($wp_rewrite instanceof \WP_Rewrite) || $chada_travel_url === ''
        ) {
            return 0;
        }
        $chada_travel_home = rtrim(home_url('/'), '/');
        if (!str_starts_with($chada_travel_url, $chada_travel_home . '/') && $chada_travel_url !== $chada_travel_home) {
            return 0;
        }
        $chada_travel_page_id = (int) url_to_postid($chada_travel_url);
        return $chada_travel_page_id > 0 && self::get_page_post($chada_travel_page_id) !== null ? $chada_travel_page_id : 0;
    }

    /** A real, non-trashed WordPress Page post, or null for any other case (missing, wrong post type, trashed). */
    private static function get_page_post(int $chada_travel_page_id): ?object {
        if ($chada_travel_page_id <= 0 || !function_exists('get_post')) {
            return null;
        }
        $chada_travel_post = get_post($chada_travel_page_id);
        if (!$chada_travel_post || $chada_travel_post->post_type !== 'page' || $chada_travel_post->post_status === 'trash') {
            return null;
        }
        return $chada_travel_post;
    }

    private static function safe_permalink(int $chada_travel_page_id): string {
        if (!function_exists('get_permalink')) {
            return '';
        }
        $chada_travel_permalink = get_permalink($chada_travel_page_id);
        return is_string($chada_travel_permalink) && $chada_travel_permalink !== '' ? $chada_travel_permalink : '';
    }
}
