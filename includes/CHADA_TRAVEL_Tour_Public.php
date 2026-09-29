<?php
/** Public Tour permalink resolver and full-detail view data builder. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Public {
    public const QUERY_VAR = 'chada_travel_tour';
    private const ROUTE_PREFIX = 'tour';
    private const REWRITE_VERSION = '3';

    /** Registers the public Tour route and template-rendering hooks. */
    public static function register(): void {
        add_filter('query_vars', [self::class, 'register_query_var']);
        add_action('init', [self::class, 'register_rewrite_rule'], 5);
        add_action('init', [self::class, 'maybe_flush_rewrite_rules'], 6);
        add_action('template_redirect', [self::class, 'render_request']);
        add_filter('pre_get_document_title', [self::class, 'filter_document_title']);
    }

    /**
     * @param list<string> $chada_travel_vars
     * @return list<string>
     */
    public static function register_query_var(array $chada_travel_vars): array {
        $chada_travel_vars[] = self::QUERY_VAR;
        $chada_travel_vars[] = CHADA_TRAVEL_Tour_Download_Service::PUBLIC_TOUR_QUERY_VAR;
        $chada_travel_vars[] = CHADA_TRAVEL_Tour_Download_Service::PUBLIC_FILE_QUERY_VAR;
        return $chada_travel_vars;
    }

    /** Registers the canonical pretty route; established query-variable requests remain supported. */
    public static function register_rewrite_rule(): void {
        add_rewrite_rule(
            '^' . self::ROUTE_PREFIX . '/([^/]+)/?$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    /** Flushes the new route once per installed rewrite version. */
    public static function maybe_flush_rewrite_rules(): void {
        if ((string) get_option('chada_travel_tour_rewrite_version', '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option('chada_travel_tour_rewrite_version', self::REWRITE_VERSION, false);
    }

    /** Returns the canonical pretty public URL for a Tour slug. */
    public static function permalink(string $chada_travel_slug): string {
        $chada_travel_safe_slug = rawurlencode(CHADA_TRAVEL_Tour_Slug_Service::sanitize($chada_travel_slug));
        return home_url('/' . self::ROUTE_PREFIX . '/' . $chada_travel_safe_slug . '/');
    }

    /** @return array{prefix: string, suffix: string} */
    public static function permalink_parts(string $chada_travel_slug = ''): array {
        return ['prefix' => home_url('/' . self::ROUTE_PREFIX . '/'), 'suffix' => '/'];
    }

    /** Returns whether the current request is a Tour detail request. */
    public static function is_detail_request(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only route detection.
        return isset($_GET[self::QUERY_VAR]) || self::request_slug() !== '';
    }

    /** Renders an active Tour detail request and sends a theme-compatible 404 otherwise. */
    public static function render_request(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only download detection.
        if (isset($_GET[CHADA_TRAVEL_Tour_Download_Service::PUBLIC_TOUR_QUERY_VAR])) {
            global $wpdb;
            if (is_object($wpdb)) {
                CHADA_TRAVEL_Tour_Download_Service::handle_public($wpdb);
            }
        }
        if (!self::is_detail_request()) {
            return;
        }
        global $wpdb;
        $chada_travel_slug = self::request_slug();
        $chada_travel_tour = is_object($wpdb) ? CHADA_TRAVEL_Tour_Repository::find_by_slug($wpdb, $chada_travel_slug) : null;
        if (!$chada_travel_tour || empty($chada_travel_tour['chada_travel_is_active'])) {
            self::render_404();
        }
        status_header(200);
        self::render_header((string) $chada_travel_tour['chada_travel_tour_name']);
        CHADA_TRAVEL_Template::output('frontend/tour-details', self::build_detail_data($wpdb, $chada_travel_tour));
        self::render_footer();
        exit;
    }

    /** Sets the document title for a valid active Tour request. */
    public static function filter_document_title(string $chada_travel_title): string {
        if (!self::is_detail_request()) {
            return $chada_travel_title;
        }
        global $wpdb;
        $chada_travel_slug = self::request_slug();
        $chada_travel_tour = is_object($wpdb) ? CHADA_TRAVEL_Tour_Repository::find_by_slug($wpdb, $chada_travel_slug) : null;
        return $chada_travel_tour && !empty($chada_travel_tour['chada_travel_is_active'])
            ? (string) $chada_travel_tour['chada_travel_tour_name'] : $chada_travel_title;
    }

    /**
     * @param array<string, mixed> $chada_travel_tour
     * @return array<string, mixed>
     */
    public static function build_detail_data(object $chada_travel_wpdb, array $chada_travel_tour): array {
        $chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0);
        $chada_travel_destinations = CHADA_TRAVEL_Tour_Repository::get_term_names_by_tour_ids(
            $chada_travel_wpdb, [$chada_travel_id], CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
        )[$chada_travel_id] ?? [];
        $chada_travel_types = CHADA_TRAVEL_Tour_Repository::get_term_names_by_tour_ids(
            $chada_travel_wpdb, [$chada_travel_id], CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE
        )[$chada_travel_id] ?? [];
        $chada_travel_dates = CHADA_TRAVEL_Tour_Repository::get_dates($chada_travel_wpdb, $chada_travel_id);
        $chada_travel_image_id = (int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? 0);
        $chada_travel_image_url = $chada_travel_image_id > 0 && function_exists('wp_get_attachment_image_url')
            ? (string) (wp_get_attachment_image_url($chada_travel_image_id, 'full') ?: '') : '';
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_tabs = [];
        foreach ([
            'trip_includes' => __('Trip Includes', 'chada-travel'),
            'trip_excludes' => __('Trip Excludes', 'chada-travel'),
            'basic_visa_requirements' => __('Visa Requirements', 'chada-travel'),
            'itinerary' => __('Itinerary', 'chada-travel'),
            'booking_conditions' => __('Booking Conditions', 'chada-travel'),
        ] as $chada_travel_field => $chada_travel_label) {
            $chada_travel_tabs[$chada_travel_field] = [
                'label' => $chada_travel_label,
                'html' => self::rich_text_html((string) ($chada_travel_tour['chada_travel_' . $chada_travel_field] ?? '')),
            ];
        }
        $chada_travel_files = [];
        foreach (CHADA_TRAVEL_Tour_Repository::get_files($chada_travel_wpdb, $chada_travel_id) as $chada_travel_file) {
            $chada_travel_attachment_id = (int) ($chada_travel_file['chada_travel_attachment_id'] ?? 0);
            $chada_travel_url = $chada_travel_attachment_id > 0 && function_exists('wp_get_attachment_url')
                ? (string) (wp_get_attachment_url($chada_travel_attachment_id) ?: '') : '';
            if ($chada_travel_url === '') {
                continue;
            }
            $chada_travel_title = function_exists('get_the_title') ? (string) get_the_title($chada_travel_attachment_id) : '';
            $chada_travel_name = $chada_travel_title !== '' ? $chada_travel_title : basename((string) (function_exists('wp_parse_url')
                ? wp_parse_url($chada_travel_url, PHP_URL_PATH)
                // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Dependency-free tests run without WordPress loaded.
                : parse_url($chada_travel_url, PHP_URL_PATH)));
            $chada_travel_type_label = self::file_type_label(
                function_exists('get_post_mime_type') ? (string) get_post_mime_type($chada_travel_attachment_id) : ''
            );
            $chada_travel_files[] = [
                'name' => $chada_travel_name,
                'display_name' => $chada_travel_type_label !== '' ? $chada_travel_name . ' (' . $chada_travel_type_label . ')' : $chada_travel_name,
                'url' => CHADA_TRAVEL_Tour_Download_Service::public_url(
                    (string) ($chada_travel_tour['chada_travel_tour_slug'] ?? ''), $chada_travel_attachment_id
                ),
            ];
        }
        return [
            'name' => (string) ($chada_travel_tour['chada_travel_tour_name'] ?? ''),
            'slug' => (string) ($chada_travel_tour['chada_travel_tour_slug'] ?? ''),
            'permalink' => self::permalink((string) ($chada_travel_tour['chada_travel_tour_slug'] ?? '')),
            'code' => (string) ($chada_travel_tour['chada_travel_tour_code'] ?? ''),
            'price' => CHADA_TRAVEL_Config::format_money(
                (float) ($chada_travel_tour['chada_travel_price'] ?? 0),
                (string) ($chada_travel_tour['chada_travel_currency'] ?? ''),
                $chada_travel_settings
            ),
            'image_url' => $chada_travel_image_url,
            'destinations' => $chada_travel_destinations,
            'types' => $chada_travel_types,
            'description_html' => self::rich_text_html((string) ($chada_travel_tour['chada_travel_description'] ?? '')),
            'dates' => array_map(static fn(array $chada_travel_date): array => [
                'label' => self::format_date((string) ($chada_travel_date['chada_travel_start_date'] ?? '')) . ' – '
                    . self::format_date((string) ($chada_travel_date['chada_travel_end_date'] ?? '')),
            ], $chada_travel_dates),
            'duration' => CHADA_TRAVEL_Tour_Duration::format($chada_travel_dates),
            'tabs' => $chada_travel_tabs,
            'files' => $chada_travel_files,
        ];
    }

    /** Returns the public label for supported downloadable attachment MIME types. */
    private static function file_type_label(string $chada_travel_mime): string {
        if ($chada_travel_mime === 'application/pdf') {
            return __('PDF', 'chada-travel');
        }
        if (in_array($chada_travel_mime, ['image/jpeg', 'image/png'], true)) {
            return __('Image', 'chada-travel');
        }
        return '';
    }

    private static function rich_text_html(string $chada_travel_value): string {
        $chada_travel_html = function_exists('wpautop') ? wpautop($chada_travel_value) : $chada_travel_value;
        return function_exists('wp_kses_post') ? wp_kses_post($chada_travel_html) : esc_html($chada_travel_html);
    }

    /** Uses the active theme shell, with a minimal safe shell for block themes without header.php/footer.php. */
    private static function render_header(string $chada_travel_title): void {
        $chada_travel_header_template = function_exists('locate_template') ? (string) locate_template('header.php') : '';
        if ($chada_travel_header_template !== '' && !str_contains($chada_travel_header_template, '/theme-compat/')) {
            get_header();
            return;
        }
        echo '<!doctype html><html';
        if (function_exists('language_attributes')) {
            language_attributes();
        }
        echo '><head><title>' . esc_html($chada_travel_title) . '</title>';
        if (function_exists('wp_head')) {
            wp_head();
        }
        echo '</head><body ';
        if (function_exists('body_class')) {
            body_class('chada-travel-tour-detail-page');
        }
        echo '>';
        if (function_exists('wp_body_open')) {
            wp_body_open();
        }
    }

    /** Completes the active theme shell or the minimal fallback shell. */
    private static function render_footer(): void {
        $chada_travel_footer_template = function_exists('locate_template') ? (string) locate_template('footer.php') : '';
        if ($chada_travel_footer_template !== '' && !str_contains($chada_travel_footer_template, '/theme-compat/')) {
            get_footer();
            return;
        }
        if (function_exists('wp_footer')) {
            wp_footer();
        }
        echo '</body></html>';
    }

    /** Returns the sanitized Tour slug supplied through the public query variable. */
    private static function request_slug(): string {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $chada_travel_query_slug = CHADA_TRAVEL_Config::sanitize_request_get(self::QUERY_VAR);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $chada_travel_query_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize((string) $chada_travel_query_slug);
        $chada_travel_query_var = get_query_var(self::QUERY_VAR, $chada_travel_query_slug);
        $chada_travel_query_var_slug = CHADA_TRAVEL_Tour_Slug_Service::sanitize((string) $chada_travel_query_var);
        return $chada_travel_query_var_slug !== '' ? $chada_travel_query_var_slug : self::request_path_slug();
    }

    /** Reads the canonical /tours/<slug>/ path when Plain permalinks bypass WordPress rewrite rules. */
    private static function request_path_slug(): string {
        $chada_travel_request_uri = CHADA_TRAVEL_Config::sanitize_server_value('REQUEST_URI');
        $chada_travel_request_path = function_exists('wp_parse_url')
            ? wp_parse_url($chada_travel_request_uri, PHP_URL_PATH)
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Dependency-free tests run without WordPress loaded.
            : parse_url($chada_travel_request_uri, PHP_URL_PATH);
        if (!is_string($chada_travel_request_path)) {
            return '';
        }
        $chada_travel_request_path = trim(rawurldecode($chada_travel_request_path), '/');
        $chada_travel_home_url = function_exists('home_url') ? home_url('/') : '/';
        $chada_travel_home_path = function_exists('wp_parse_url')
            ? wp_parse_url($chada_travel_home_url, PHP_URL_PATH)
            // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Dependency-free tests run without WordPress loaded.
            : parse_url($chada_travel_home_url, PHP_URL_PATH);
        $chada_travel_home_path = is_string($chada_travel_home_path) ? trim($chada_travel_home_path, '/') : '';
        if ($chada_travel_home_path !== '') {
            $chada_travel_home_prefix = $chada_travel_home_path . '/';
            if (strpos($chada_travel_request_path, $chada_travel_home_prefix) !== 0) {
                return '';
            }
            $chada_travel_request_path = substr($chada_travel_request_path, strlen($chada_travel_home_prefix));
        }
        if (!preg_match('#^' . self::ROUTE_PREFIX . '/([^/]+)$#', $chada_travel_request_path, $chada_travel_matches)) {
            return '';
        }
        return CHADA_TRAVEL_Tour_Slug_Service::sanitize((string) $chada_travel_matches[1]);
    }

    private static function format_date(string $chada_travel_date): string {
        $chada_travel_timestamp = strtotime($chada_travel_date);
        if (!$chada_travel_timestamp) {
            return $chada_travel_date;
        }
        return function_exists('wp_date') ? wp_date('M. j, Y', $chada_travel_timestamp) : gmdate('M. j, Y', $chada_travel_timestamp);
    }

    private static function render_404(): void {
        global $wp_query;
        if (is_object($wp_query) && method_exists($wp_query, 'set_404')) {
            $wp_query->set_404();
        }
        status_header(404);
        nocache_headers();
        $chada_travel_template = function_exists('get_404_template') ? get_404_template() : '';
        if ($chada_travel_template !== '' && is_readable($chada_travel_template)) {
            include $chada_travel_template;
        } else {
            wp_die(esc_html__('Tour not found.', 'chada-travel'), '', ['response' => 404]);
        }
        exit;
    }
}
