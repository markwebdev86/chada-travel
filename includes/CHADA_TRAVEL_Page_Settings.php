<?php
/**
 * Normalized reads, truthful status classification, save-time validation, idempotent migration discovery, and
 * public URL resolution for the three workflow pages defined by CHADA_TRAVEL_Page_Registry (Checkout, Bank Payment
 * Proof, Visa Document Upload). This is the one authoritative service behind the Settings "Pages & Links" tab,
 * CHADA_TRAVEL_Readiness's admin notice, CHADA_TRAVEL_Settings_Repository's settings-data migration, and CHADA_TRAVEL_Config's public
 * get_checkout_url()/get_payment_proof_url()/get_document_upload_url() helpers, so page rules are never
 * duplicated across rendering, validation, readiness, URL generation, and migration.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Page_Settings {
    public const STATUS_NOT_SELECTED     = 'not_selected';
    public const STATUS_MISSING          = 'missing';
    public const STATUS_DRAFT            = 'draft';
    public const STATUS_PRIVATE          = 'private';
    public const STATUS_MISSING_SHORTCODE= 'missing_shortcode';
    public const STATUS_READY            = 'ready';

    /** @return array<string, string> Status constant => translatable, color-independent label. */
    public static function get_status_labels(): array {
        return [
            self::STATUS_NOT_SELECTED      => __('Not Selected', 'chada-travel'),
            self::STATUS_MISSING           => __('Missing Page', 'chada-travel'),
            self::STATUS_DRAFT             => __('Draft', 'chada-travel'),
            self::STATUS_PRIVATE           => __('Private', 'chada-travel'),
            self::STATUS_MISSING_SHORTCODE => __('Missing Shortcode', 'chada-travel'),
            self::STATUS_READY             => __('Ready', 'chada-travel'),
        ];
    }

    /** @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result. */
    public static function get_page_id(string $chada_travel_key, array $chada_travel_settings): int {
        $chada_travel_option = CHADA_TRAVEL_Page_Registry::get_definitions()[$chada_travel_key]['option'] ?? '';
        return $chada_travel_option !== '' ? max(0, (int) ($chada_travel_settings[$chada_travel_option] ?? 0)) : 0;
    }

    /**
     * Classifies the assigned page's truthful current state; never claims Ready unless the page genuinely
     * exists, is a WordPress page, is published, contains the required shortcode, and resolves a permalink.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{status: string, page_id: int, url: string}
     */
    public static function resolve_status(string $chada_travel_key, array $chada_travel_settings): array {
        $chada_travel_page_id = self::get_page_id($chada_travel_key, $chada_travel_settings);
        if ($chada_travel_page_id <= 0) {
            return ['status' => self::STATUS_NOT_SELECTED, 'page_id' => 0, 'url' => ''];
        }

        $chada_travel_post = self::get_page_post($chada_travel_page_id);
        if (!$chada_travel_post) {
            return ['status' => self::STATUS_MISSING, 'page_id' => $chada_travel_page_id, 'url' => ''];
        }

        $chada_travel_permalink = self::safe_permalink($chada_travel_page_id);
        if ((string) $chada_travel_post->post_status === 'private') {
            return ['status' => self::STATUS_PRIVATE, 'page_id' => $chada_travel_page_id, 'url' => $chada_travel_permalink];
        }
        if ((string) $chada_travel_post->post_status !== 'publish') {
            return ['status' => self::STATUS_DRAFT, 'page_id' => $chada_travel_page_id, 'url' => $chada_travel_permalink];
        }

        $chada_travel_shortcode = CHADA_TRAVEL_Page_Registry::get_definitions()[$chada_travel_key]['shortcode'] ?? '';
        if (!self::content_has_shortcode((string) $chada_travel_post->post_content, $chada_travel_shortcode)) {
            return ['status' => self::STATUS_MISSING_SHORTCODE, 'page_id' => $chada_travel_page_id, 'url' => $chada_travel_permalink];
        }
        if ($chada_travel_permalink === '') {
            // Published, correct shortcode, but no resolvable permalink (e.g. this post type/site cannot
            // generate one) - treated the same as Missing, since it is not a usable customer-facing page.
            return ['status' => self::STATUS_MISSING, 'page_id' => $chada_travel_page_id, 'url' => ''];
        }
        return ['status' => self::STATUS_READY, 'page_id' => $chada_travel_page_id, 'url' => $chada_travel_permalink];
    }

    /**
     * True only when View Page can safely be offered: a real, non-trashed page record exists. A Draft/Private
     * permalink is still safe to show to the administrator already authenticated on this exact Settings screen.
     */
    public static function can_offer_view_link(string $chada_travel_status): bool {
        return !in_array($chada_travel_status, [self::STATUS_NOT_SELECTED, self::STATUS_MISSING], true);
    }

    /**
     * Sanitizes a submitted page-id field to a nonnegative integer; a negative or non-numeric submission
     * safely becomes 0 (Not Selected) rather than a validation error, matching CHADA_TRAVEL_Config's other
     * nonnegative-integer settings fields.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_page_id($chada_travel_value): int {
        return max(0, (int) $chada_travel_value);
    }

    /**
     * Validates a submitted, already-sanitized page id before it may be written to an option. A zero id
     * ("Not Selected") always passes; a nonzero id must resolve to a real, non-trashed WordPress Page - never
     * an attachment, revision, ordinary post, or an id that does not exist at all. Draft/Private/Missing
     * Shortcode pages are intentionally accepted here (an administrator may save a page while still preparing
     * it); only resolve_status() decides whether the result is reported Ready.
     *
     * @return array{valid: bool, error: string}
     */
    public static function validate_page_id(int $chada_travel_page_id): array {
        if ($chada_travel_page_id <= 0) {
            return ['valid' => true, 'error' => ''];
        }
        if (!function_exists('get_post')) {
            // No WordPress loaded (pure-PHP unit test runtime): trust a positive submitted id, matching
            // CHADA_TRAVEL_General_Settings_Validator's identical Company Logo/Digital Wallet QR attachment fallback. Real
            // existence/post-type/trashed-record validation is covered by
            // tests/integration/page-settings-runtime.php, which stubs get_post().
            return ['valid' => true, 'error' => ''];
        }
        $chada_travel_post = self::get_page_post($chada_travel_page_id);
        if (!$chada_travel_post) {
            return ['valid' => false, 'error' => __('Select an existing WordPress Page.', 'chada-travel')];
        }
        return ['valid' => true, 'error' => ''];
    }

    /**
     * Resolves the public operational URL for one workflow page: the assigned page's own permalink once it is
     * genuinely Ready, otherwise the stable fallback path - so migration/administrator-preparation never
     * produces a broken customer-facing link. A nonempty token is appended with add_query_arg() so it is
     * encoded exactly once, preserves any existing query string get_permalink() already returned (e.g. a plain
     * permalink's `?page_id=123`), and works identically for pretty and plain permalink structures.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function resolve_url(string $chada_travel_key, array $chada_travel_settings, string $chada_travel_token = ''): string {
        $chada_travel_definition = CHADA_TRAVEL_Page_Registry::get_definitions()[$chada_travel_key] ?? null;
        if (!$chada_travel_definition) {
            return '';
        }
        $chada_travel_status = self::resolve_status($chada_travel_key, $chada_travel_settings);
        $chada_travel_base = $chada_travel_status['status'] === self::STATUS_READY && $chada_travel_status['url'] !== ''
            ? $chada_travel_status['url']
            : self::fallback_url($chada_travel_definition['fallback_path']);

        if ($chada_travel_token === '') {
            return $chada_travel_base;
        }
        return function_exists('add_query_arg')
            ? add_query_arg('token', $chada_travel_token, $chada_travel_base)
            : $chada_travel_base . (str_contains($chada_travel_base, '?') ? '&' : '?') . 'token=' . rawurlencode($chada_travel_token);
    }

    private static function fallback_url(string $chada_travel_fallback_path): string {
        return function_exists('home_url') ? home_url($chada_travel_fallback_path) : $chada_travel_fallback_path;
    }

    /**
     * Idempotent, non-destructive migration discovery for one workflow page: preserves any existing nonzero
     * assignment (callers must check that before calling this), otherwise tries the configured fallback path
     * (resolved through the real rewrite rules via url_to_postid(), not a slug guess) and accepts it only when
     * that page actually contains the required shortcode; otherwise searches all non-trashed Pages for the
     * shortcode and auto-assigns only when exactly one unambiguous match exists. Never creates, publishes, or
     * edits a page; returns 0 (leave unassigned) when zero or multiple candidates are found.
     */
    public static function discover_page_id(string $chada_travel_fallback_path, string $chada_travel_shortcode): int {
        $chada_travel_fallback_page_id = self::fallback_path_page_id($chada_travel_fallback_path);
        if ($chada_travel_fallback_page_id > 0 && self::page_has_shortcode($chada_travel_fallback_page_id, $chada_travel_shortcode)) {
            return $chada_travel_fallback_page_id;
        }
        $chada_travel_matches = self::find_pages_with_shortcode($chada_travel_shortcode);
        return count($chada_travel_matches) === 1 ? $chada_travel_matches[0] : 0;
    }

    /**
     * url_to_postid() dereferences the global $wp_rewrite object, which WordPress has not yet created this
     * early in the request lifecycle (migration runs from CHADA_TRAVEL_Installer::maybe_upgrade() on `plugins_loaded`,
     * before `$wp_rewrite = new WP_Rewrite()`); calling it before that would fatal the entire request. Skipping
     * the fallback-path shortcut in that case is always safe - discover_page_id() still falls through to the
     * shortcode-wide search below, which does not depend on rewrite rules.
     */
    private static function fallback_path_page_id(string $chada_travel_fallback_path): int {
        global $wp_rewrite;
        $chada_travel_rewrite_ready = $wp_rewrite instanceof \WP_Rewrite;
        if (!function_exists('url_to_postid') || !function_exists('home_url') || !$chada_travel_rewrite_ready) {
            return 0;
        }
        return (int) url_to_postid(home_url($chada_travel_fallback_path));
    }

    /** @return list<int> Ids of every non-trashed Page whose content contains $chada_travel_shortcode. */
    private static function find_pages_with_shortcode(string $chada_travel_shortcode): array {
        if (!function_exists('get_posts')) {
            return [];
        }
        $chada_travel_page_ids = get_posts([
            'post_type'      => 'page',
            'post_status'    => ['publish', 'draft', 'pending', 'private', 'future'],
            'numberposts'    => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);
        $chada_travel_matches = [];
        foreach ((array) $chada_travel_page_ids as $chada_travel_page_id) {
            if (self::page_has_shortcode((int) $chada_travel_page_id, $chada_travel_shortcode)) {
                $chada_travel_matches[] = (int) $chada_travel_page_id;
            }
        }
        return $chada_travel_matches;
    }

    private static function page_has_shortcode(int $chada_travel_page_id, string $chada_travel_shortcode): bool {
        $chada_travel_post = self::get_page_post($chada_travel_page_id);
        return $chada_travel_post !== null
            && self::content_has_shortcode((string) $chada_travel_post->post_content, $chada_travel_shortcode);
    }

    private static function content_has_shortcode(string $chada_travel_content, string $chada_travel_shortcode): bool {
        if ($chada_travel_shortcode === '') {
            return false;
        }
        return function_exists('has_shortcode')
            ? has_shortcode($chada_travel_content, $chada_travel_shortcode)
            : str_contains($chada_travel_content, '[' . $chada_travel_shortcode);
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
