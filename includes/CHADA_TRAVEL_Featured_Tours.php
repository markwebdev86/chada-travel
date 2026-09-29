<?php
/** Public Featured Tours shortcode. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Featured_Tours {
    public const SHORTCODE_TAG = 'chada_travel_featured_tours';
    public const SIDEBAR_SHORTCODE_TAG = 'chada_travel_featured_tours_sidebar';
    public const DESIGN_BG_IMAGE = 'bg-image';
    public const DESIGN_TOP_IMAGE = 'top-image';
    public const DESIGN_LEFT_IMAGE = 'left-image';
    private const DEFAULT_ROWS = 1;
    private const MAX_ROWS = 2;
    private const LEFT_IMAGE_MAX_ROWS = 3;
    private const LEFT_IMAGE_COLUMNS = 1;
    private const COLUMNS = 3;
    private const DESCRIPTION_MAX_LENGTH = 180;
    private const SIDEBAR_DEFAULT_ROWS = 1;
    private const SIDEBAR_MAX_ROWS = 10;
    private const DESCRIPTION_ELLIPSIS = '…';

    public static function register(): void {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render']);
        add_shortcode(self::SIDEBAR_SHORTCODE_TAG, [self::class, 'render_sidebar']);
    }

    /**
     * @param array<string, mixed>|string $chada_travel_atts
     * @return array{rows:int,columns:int,limit:int}
     */
    public static function normalize_sidebar_attributes($chada_travel_atts = []): array {
        $chada_travel_atts = is_array($chada_travel_atts) ? $chada_travel_atts : [];
        $chada_travel_rows = (int) ($chada_travel_atts['rows'] ?? self::SIDEBAR_DEFAULT_ROWS);
        $chada_travel_rows = min(self::SIDEBAR_MAX_ROWS, max(1, $chada_travel_rows));
        return ['rows' => $chada_travel_rows, 'columns' => 1, 'limit' => $chada_travel_rows];
    }

    /**
     * @param array<string, mixed>|string $chada_travel_atts
     * @return array{design: string, rows: int, columns: int, limit: int}
     */
    public static function normalize_attributes($chada_travel_atts = []): array {
        $chada_travel_atts = is_array($chada_travel_atts) ? $chada_travel_atts : [];
        $chada_travel_design = strtolower(trim((string) ($chada_travel_atts['design'] ?? self::DESIGN_BG_IMAGE)));
        $chada_travel_design = in_array(
            $chada_travel_design,
            [self::DESIGN_BG_IMAGE, self::DESIGN_TOP_IMAGE, self::DESIGN_LEFT_IMAGE],
            true
        ) ? $chada_travel_design : self::DESIGN_BG_IMAGE;
        $chada_travel_max_rows = $chada_travel_design === self::DESIGN_LEFT_IMAGE ? self::LEFT_IMAGE_MAX_ROWS : self::MAX_ROWS;
        $chada_travel_columns = $chada_travel_design === self::DESIGN_LEFT_IMAGE ? self::LEFT_IMAGE_COLUMNS : self::COLUMNS;
        $chada_travel_rows = (int) ($chada_travel_atts['rows'] ?? self::DEFAULT_ROWS);
        $chada_travel_rows = min($chada_travel_max_rows, max(1, $chada_travel_rows));
        return [
            'design' => $chada_travel_design,
            'rows' => $chada_travel_rows,
            'columns' => $chada_travel_columns,
            'limit' => $chada_travel_rows * $chada_travel_columns,
        ];
    }

    /** Returns a plain-text, display-safe Tour Description excerpt. */
    public static function format_description_excerpt(string $chada_travel_description): string {
        $chada_travel_plain_text = function_exists('wp_strip_all_tags')
            ? wp_strip_all_tags($chada_travel_description) : strip_tags($chada_travel_description);
        $chada_travel_plain_text = html_entity_decode($chada_travel_plain_text, ENT_QUOTES, 'UTF-8');
        $chada_travel_plain_text = preg_replace('/\s+/u', ' ', trim($chada_travel_plain_text)) ?: '';
        $chada_travel_ellipsis_length = function_exists('mb_strlen')
            ? mb_strlen(self::DESCRIPTION_ELLIPSIS, 'UTF-8') : strlen(self::DESCRIPTION_ELLIPSIS);
        $chada_travel_text_length = function_exists('mb_strlen')
            ? mb_strlen($chada_travel_plain_text, 'UTF-8') : strlen($chada_travel_plain_text);
        if ($chada_travel_text_length <= self::DESCRIPTION_MAX_LENGTH) {
            return $chada_travel_plain_text;
        }
        $chada_travel_limit = self::DESCRIPTION_MAX_LENGTH - $chada_travel_ellipsis_length;
        return function_exists('mb_substr')
            ? mb_substr($chada_travel_plain_text, 0, $chada_travel_limit, 'UTF-8') . self::DESCRIPTION_ELLIPSIS
            : substr($chada_travel_plain_text, 0, $chada_travel_limit) . self::DESCRIPTION_ELLIPSIS;
    }

    /** @param array<string, mixed>|string $chada_travel_atts */
    public static function render($chada_travel_atts = []): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return '';
        }
        $chada_travel_layout = self::normalize_attributes($chada_travel_atts);
        $chada_travel_tours = CHADA_TRAVEL_Tour_Repository::get_featured_active($wpdb, $chada_travel_layout['limit']);
        if (!$chada_travel_tours) {
            return '';
        }
        $chada_travel_tour_ids = array_map(
            static fn(array $chada_travel_tour): int => (int) $chada_travel_tour['chada_travel_tour_id'],
            $chada_travel_tours
        );
        $chada_travel_destinations = CHADA_TRAVEL_Tour_Repository::get_term_details_by_tour_ids(
            $wpdb,
            $chada_travel_tour_ids,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
        );
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_cards = self::prepare_cards($wpdb, $chada_travel_tours, $chada_travel_destinations, $chada_travel_settings);
        return self::render_cards($chada_travel_cards, $chada_travel_layout);
    }

    /** @param array<string, mixed>|string $chada_travel_atts */
    public static function render_sidebar($chada_travel_atts = []): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return '';
        }
        $chada_travel_layout = self::normalize_sidebar_attributes($chada_travel_atts);
        $chada_travel_tours = CHADA_TRAVEL_Tour_Repository::get_featured_active($wpdb, $chada_travel_layout['limit']);
        if (!$chada_travel_tours) {
            return '';
        }
        $chada_travel_tour_ids = array_map(
            static fn(array $chada_travel_tour): int => (int) $chada_travel_tour['chada_travel_tour_id'],
            $chada_travel_tours
        );
        $chada_travel_destinations = CHADA_TRAVEL_Tour_Repository::get_term_details_by_tour_ids(
            $wpdb,
            $chada_travel_tour_ids,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
        );
        $chada_travel_cards = self::prepare_cards($wpdb, $chada_travel_tours, $chada_travel_destinations, CHADA_TRAVEL_Config::get_settings());
        $chada_travel_markup = CHADA_TRAVEL_Template::render('frontend/featured-tours/sidebar', [
            'chada_travel_cards' => $chada_travel_cards,
            'chada_travel_rows' => $chada_travel_layout['rows'],
            'chada_travel_columns' => $chada_travel_layout['columns'],
        ]);
        $chada_travel_compact_markup = preg_replace('/>\s+</', '><', trim($chada_travel_markup));
        return is_string($chada_travel_compact_markup) ? $chada_travel_compact_markup : trim($chada_travel_markup);
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_tours
     * @param array<int, list<array{id: int, name: string, slug: string, is_active: bool}>> $chada_travel_destinations
     * @param array<string, mixed> $chada_travel_settings
     * @return list<array<string, string>>
     */
    public static function prepare_cards(
        object $chada_travel_wpdb,
        array $chada_travel_tours,
        array $chada_travel_destinations,
        array $chada_travel_settings
    ): array {
        $chada_travel_cards = [];
        foreach ($chada_travel_tours as $chada_travel_tour) {
            $chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? 0);
            $chada_travel_image_id = (int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? 0);
            $chada_travel_image_function = function_exists('wp_get_attachment_image_url')
                ? 'wp_get_attachment_image_url' : __NAMESPACE__ . '\\wp_get_attachment_image_url';
            $chada_travel_image_url = $chada_travel_image_id > 0 && function_exists($chada_travel_image_function)
                ? (string) (call_user_func($chada_travel_image_function, $chada_travel_image_id, 'large') ?: '') : '';
            $chada_travel_dates = CHADA_TRAVEL_Tour_Repository::get_dates($chada_travel_wpdb, $chada_travel_id);
            $chada_travel_destination_terms = $chada_travel_destinations[$chada_travel_id] ?? [];
            $chada_travel_cards[] = [
                'name' => (string) ($chada_travel_tour['chada_travel_tour_name'] ?? ''),
                'permalink' => ($chada_travel_tour['chada_travel_tour_slug'] ?? '') !== ''
                    ? CHADA_TRAVEL_Tour_Public::permalink((string) $chada_travel_tour['chada_travel_tour_slug']) : '',
                'description' => self::format_description_excerpt((string) ($chada_travel_tour['chada_travel_description'] ?? '')),
                'duration' => CHADA_TRAVEL_Tour_Duration::format($chada_travel_dates),
                'days' => CHADA_TRAVEL_Tour_Duration::days_only($chada_travel_dates),
                'code' => (string) ($chada_travel_tour['chada_travel_tour_code'] ?? ''),
                'destination' => self::format_destination_links(array_slice($chada_travel_destination_terms, 0, 1)),
                'destinations' => self::format_destination_links($chada_travel_destination_terms),
                'price' => CHADA_TRAVEL_Config::format_money(
                    (float) ($chada_travel_tour['chada_travel_price'] ?? 0),
                    (string) ($chada_travel_tour['chada_travel_currency'] ?? ''),
                    $chada_travel_settings
                ),
                'image_url' => $chada_travel_image_url,
            ];
        }
        return $chada_travel_cards;
    }

    /**
     * Builds a comma-joined, pre-escaped HTML fragment linking each active Destination to its archive page
     * (`/tours/destination/<slug>/`); an inactive Destination's name still appears (matching today's
     * unlinked behavior) but is never linked, since its archive page would 404.
     *
     * @param list<array{id: int, name: string, slug: string, is_active: bool}> $chada_travel_terms
     */
    private static function format_destination_links(array $chada_travel_terms): string {
        $chada_travel_links = [];
        foreach ($chada_travel_terms as $chada_travel_term) {
            $chada_travel_name = $chada_travel_term['name'];
            if ($chada_travel_name === '') {
                continue;
            }
            $chada_travel_slug = $chada_travel_term['slug'];
            $chada_travel_links[] = ($chada_travel_slug !== '' && $chada_travel_term['is_active'])
                ? '<a href="' . esc_url(CHADA_TRAVEL_Tour_Term_Public::destination_permalink($chada_travel_slug)) . '">'
                    . esc_html($chada_travel_name) . '</a>'
                : esc_html($chada_travel_name);
        }
        return implode(', ', $chada_travel_links);
    }

    /**
     * @param list<array<string, string>> $chada_travel_cards
     * @param array{design:string,rows:int,columns:int,limit:int} $chada_travel_layout
     */
    public static function render_cards(
        array $chada_travel_cards,
        array $chada_travel_layout,
        string $chada_travel_component = 'featured-tours',
        string $chada_travel_label = 'Featured Tours'
    ): string {
        $chada_travel_template = 'frontend/featured-tours/bg-image';
        if ($chada_travel_layout['design'] === self::DESIGN_TOP_IMAGE) {
            $chada_travel_template = 'frontend/featured-tours/top-image';
        } elseif ($chada_travel_layout['design'] === self::DESIGN_LEFT_IMAGE) {
            $chada_travel_template = 'frontend/featured-tours/left-image';
        }
        $chada_travel_markup = CHADA_TRAVEL_Template::render($chada_travel_template, [
            'chada_travel_cards' => $chada_travel_cards,
            'chada_travel_design' => $chada_travel_layout['design'],
            'chada_travel_rows' => $chada_travel_layout['rows'],
            'chada_travel_columns' => $chada_travel_layout['columns'],
            'chada_travel_component' => $chada_travel_component,
            'chada_travel_label' => $chada_travel_label,
        ]);
        $chada_travel_compact_markup = preg_replace('/>\s+</', '><', trim($chada_travel_markup));
        return is_string($chada_travel_compact_markup) ? $chada_travel_compact_markup : trim($chada_travel_markup);
    }
}
