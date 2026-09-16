<?php
/** Public routing and rendering for Tour Destination/Type archive pages. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Term_Public {
    public const DESTINATION_SLUG_QUERY_VAR = 'chada_travel_tour_destination_slug';
    public const TYPE_SLUG_QUERY_VAR = 'chada_travel_tour_type_slug';
    private const DESTINATION_ROUTE_PREFIX = 'tours/destination';
    private const REWRITE_VERSION = '1';
    private const REWRITE_VERSION_OPTION = 'chada_travel_tour_taxonomy_rewrite_version';

    /** @var array<string, mixed>|null Resolved by guard_request(), reused by the title/heading filters. */
    private static ?array $chada_travel_resolved_term = null;
    private static bool $chada_travel_heading_rendered = false;

    /** Registers the public Tour Destination/Type archive routes and their rendering hooks. */
    public static function register(): void {
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_action('init', [self::class, 'register_rewrite_rules'], 5);
        add_action('init', [self::class, 'maybe_flush_rewrite_rules'], 6);
        add_filter('request', [self::class, 'inject_page_id']);
        add_filter('redirect_canonical', [self::class, 'skip_canonical_redirect'], 10, 2);
        add_action('template_redirect', [self::class, 'guard_request']);
        add_filter('pre_get_document_title', [self::class, 'filter_document_title']);
        add_filter('the_content', [self::class, 'prepend_heading']);
    }

    /**
     * @param list<string> $chada_travel_vars
     * @return list<string>
     */
    public static function register_query_vars(array $chada_travel_vars): array {
        $chada_travel_vars[] = self::DESTINATION_SLUG_QUERY_VAR;
        $chada_travel_vars[] = self::TYPE_SLUG_QUERY_VAR;
        return $chada_travel_vars;
    }

    /** Registers both archive routes at 'top' priority, ahead of WordPress's own generic page rules. */
    public static function register_rewrite_rules(): void {
        add_rewrite_rule(
            '^' . self::DESTINATION_ROUTE_PREFIX . '/([^/]+)/?$',
            'index.php?' . self::DESTINATION_SLUG_QUERY_VAR . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^tours/type/([^/]+)/?$',
            'index.php?' . self::TYPE_SLUG_QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /** Flushes the new routes once per installed rewrite version; independent of the Tour detail route's own. */
    public static function maybe_flush_rewrite_rules(): void {
        if ((string) get_option(self::REWRITE_VERSION_OPTION, '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::REWRITE_VERSION_OPTION, self::REWRITE_VERSION, false);
    }

    /** Returns the canonical public URL for a Tour Destination slug. */
    public static function destination_permalink(string $chada_travel_slug): string {
        $chada_travel_safe_slug = rawurlencode(CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_slug));
        return home_url('/' . self::DESTINATION_ROUTE_PREFIX . '/' . $chada_travel_safe_slug . '/');
    }

    /**
     * Makes an archive-route request render the real Tours Page (full theme chrome, its other shortcodes/
     * blocks) instead of falling through to the site's default query. Resolves the Page id fresh every
     * request rather than baking it into the cached rewrite rule, so this never goes stale if the Tours
     * Page assignment or slug changes later.
     *
     * @param array<string, mixed> $chada_travel_query_vars
     * @return array<string, mixed>
     */
    public static function inject_page_id(array $chada_travel_query_vars): array {
        if (!self::has_slug_query_var($chada_travel_query_vars)) {
            return $chada_travel_query_vars;
        }
        $chada_travel_page_id = CHADA_TRAVEL_Page_Settings::get_page_id(
            CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS,
            CHADA_TRAVEL_Config::get_settings()
        );
        if ($chada_travel_page_id > 0) {
            $chada_travel_query_vars['page_id'] = $chada_travel_page_id;
        }
        return $chada_travel_query_vars;
    }

    /**
     * Prevents WordPress core from 301-redirecting the pretty archive URL back to the Tours Page's own
     * plain permalink - core's canonical-URL logic has no notion of the extra path segments.
     *
     * @param string|bool $chada_travel_redirect_url
     * @return string|bool
     */
    public static function skip_canonical_redirect($chada_travel_redirect_url, string $chada_travel_requested_url) {
        return self::request_slug_taxonomy() !== '' ? false : $chada_travel_redirect_url;
    }

    /** Renders a real 404 for an unconfigured Tours Page or an unknown/inactive Destination or Type slug. */
    public static function guard_request(): void {
        $chada_travel_taxonomy = self::request_slug_taxonomy();
        if ($chada_travel_taxonomy === '') {
            return;
        }
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        if (CHADA_TRAVEL_Page_Settings::get_page_id(CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS, $chada_travel_settings) <= 0) {
            self::render_404();
            return;
        }
        global $wpdb;
        $chada_travel_slug = self::request_slug($chada_travel_taxonomy);
        $chada_travel_term = is_object($wpdb)
            ? CHADA_TRAVEL_Tour_Term_Repository::find_by_slug($wpdb, $chada_travel_taxonomy, $chada_travel_slug) : null;
        if (!$chada_travel_term || empty($chada_travel_term['chada_travel_is_active'])) {
            self::render_404();
            return;
        }
        self::$chada_travel_resolved_term = $chada_travel_term;
    }

    /** Customizes the browser tab title on a resolved archive route. */
    public static function filter_document_title(string $chada_travel_title): string {
        if (!self::$chada_travel_resolved_term) {
            return $chada_travel_title;
        }
        $chada_travel_name = (string) self::$chada_travel_resolved_term['chada_travel_term_name'];
        // Every Tour Type name already ends in "Tour" (e.g. "City Tour"); Destination names never do (e.g.
        // "Europe") - only append the suffix when it wouldn't produce an awkward "City Tour Tours" repeat.
        if (str_ends_with(strtolower($chada_travel_name), 'tour')) {
            return $chada_travel_name;
        }
        return sprintf(
            /* translators: %s: Tour Destination name. */
            __('%s Tours', 'chada-travel'),
            $chada_travel_name
        );
    }

    /** Prepends a heading naming the selected Destination/Type ahead of the Tours Page's own content. */
    public static function prepend_heading(string $chada_travel_content): string {
        if (!self::$chada_travel_resolved_term || self::$chada_travel_heading_rendered || !is_main_query() || !in_the_loop()) {
            return $chada_travel_content;
        }
        self::$chada_travel_heading_rendered = true;
        $chada_travel_heading = '<h1 class="chada-travel-tour-term-archive__heading">'
            . esc_html((string) self::$chada_travel_resolved_term['chada_travel_term_name']) . '</h1>';
        return $chada_travel_heading . $chada_travel_content;
    }

    /** @param array<string, mixed> $chada_travel_query_vars */
    private static function has_slug_query_var(array $chada_travel_query_vars): bool {
        return !empty($chada_travel_query_vars[self::DESTINATION_SLUG_QUERY_VAR])
            || !empty($chada_travel_query_vars[self::TYPE_SLUG_QUERY_VAR]);
    }

    /** Returns the active route's taxonomy, or '' when the current request isn't one of our routes. */
    private static function request_slug_taxonomy(): string {
        if ((string) get_query_var(self::DESTINATION_SLUG_QUERY_VAR, '') !== '') {
            return CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION;
        }
        if ((string) get_query_var(self::TYPE_SLUG_QUERY_VAR, '') !== '') {
            return CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE;
        }
        return '';
    }

    private static function request_slug(string $chada_travel_taxonomy): string {
        $chada_travel_query_var = $chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
            ? self::DESTINATION_SLUG_QUERY_VAR : self::TYPE_SLUG_QUERY_VAR;
        return (string) get_query_var($chada_travel_query_var, '');
    }

    private static function render_404(): void {
        global $wp_query;
        if (is_object($wp_query) && method_exists($wp_query, 'set_404')) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
    }
}
