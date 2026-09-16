<?php
/** Public Tour Search form and results shortcodes. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Search {
    public const FORM_SHORTCODE_TAG = 'chada_travel_tour_search_form';
    public const RESULTS_SHORTCODE_TAG = 'chada_travel_tour_search_results';
    public const SEARCH_PARAM = 'chada_travel_tour_search';
    public const DESTINATION_PARAM = 'chada_travel_tour_destination';
    public const TYPE_PARAM = 'chada_travel_tour_type';
    public const PAGE_PARAM = 'chada_travel_tour_search_page';

    public static function register(): void {
        add_shortcode(self::FORM_SHORTCODE_TAG, [self::class, 'render_form']);
        add_shortcode(self::RESULTS_SHORTCODE_TAG, [self::class, 'render_results']);
    }

    public static function render_form(): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return '';
        }
        $chada_travel_search = self::request_search();
        $chada_travel_destination_id = self::request_term_id(
            self::DESTINATION_PARAM,
            $wpdb,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
        );
        $chada_travel_type_id = self::request_term_id(
            self::TYPE_PARAM,
            $wpdb,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE
        );
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_action = CHADA_TRAVEL_Page_Settings::resolve_url(CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS, $chada_travel_settings);
        $chada_travel_destinations = CHADA_TRAVEL_Tour_Term_Repository::get_all(
            $wpdb,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION,
            false
        );
        $chada_travel_types = CHADA_TRAVEL_Tour_Term_Repository::get_all($wpdb, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE, false);
        return self::compact(CHADA_TRAVEL_Template::render('frontend/tour-search/form', [
            'chada_travel_action' => $chada_travel_action,
            'chada_travel_search' => $chada_travel_search,
            'chada_travel_destination_id' => $chada_travel_destination_id,
            'chada_travel_type_id' => $chada_travel_type_id,
            'chada_travel_destinations' => $chada_travel_destinations,
            'chada_travel_types' => $chada_travel_types,
        ]));
    }

    public static function render_results(): string {
        global $wpdb;
        if (!is_object($wpdb)) {
            return '';
        }
        $chada_travel_search = self::request_search();
        $chada_travel_destination_id = self::request_term_id(
            self::DESTINATION_PARAM,
            $wpdb,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
        );
        $chada_travel_type_id = self::request_term_id(
            self::TYPE_PARAM,
            $wpdb,
            CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE
        );
        $chada_travel_total = CHADA_TRAVEL_Tour_Repository::count_public(
            $wpdb, $chada_travel_search, $chada_travel_destination_id, $chada_travel_type_id
        );
        $chada_travel_pages = max(1, (int) ceil($chada_travel_total / CHADA_TRAVEL_Tour_Repository::PUBLIC_SEARCH_PER_PAGE));
        $chada_travel_page = min(max(1, self::request_page()), $chada_travel_pages);
        $chada_travel_tours = CHADA_TRAVEL_Tour_Repository::search_public(
            $wpdb,
            $chada_travel_search,
            $chada_travel_destination_id,
            $chada_travel_type_id,
            $chada_travel_page
        );
        $chada_travel_cards = [];
        if ($chada_travel_tours) {
            $chada_travel_ids = array_map(
                static fn(array $chada_travel_tour): int => (int) $chada_travel_tour['chada_travel_tour_id'],
                $chada_travel_tours
            );
            $chada_travel_destinations = CHADA_TRAVEL_Tour_Repository::get_term_details_by_tour_ids(
                $wpdb,
                $chada_travel_ids,
                CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
            );
            $chada_travel_cards = CHADA_TRAVEL_Featured_Tours::prepare_cards(
                $wpdb,
                $chada_travel_tours,
                $chada_travel_destinations,
                CHADA_TRAVEL_Config::get_settings()
            );
        }
        $chada_travel_layout = [
            'design' => CHADA_TRAVEL_Featured_Tours::DESIGN_LEFT_IMAGE,
            'rows' => count($chada_travel_cards),
            'columns' => 1,
            'limit' => count($chada_travel_cards),
        ];
        $chada_travel_markup = '';
        if ($chada_travel_cards) {
            $chada_travel_markup = CHADA_TRAVEL_Featured_Tours::render_cards(
                $chada_travel_cards,
                $chada_travel_layout,
                'tour-search-results',
                __('Tour Search Results', 'chada-travel')
            );
        } else {
            $chada_travel_message = $chada_travel_total > 0
                ? __('No active Tours match your search.', 'chada-travel')
                : ($chada_travel_search !== '' || $chada_travel_destination_id > 0 || $chada_travel_type_id > 0
                    ? __('No active Tours match your selected filters.', 'chada-travel')
                    : __('No active Tours are available yet.', 'chada-travel'));
            $chada_travel_markup = CHADA_TRAVEL_Template::render('frontend/tour-search/empty', [
                'chada_travel_message' => $chada_travel_message,
            ]);
        }
        if ($chada_travel_pages > 1) {
            $chada_travel_markup .= CHADA_TRAVEL_Template::render('frontend/tour-search/pagination', [
                'chada_travel_current_page' => $chada_travel_page,
                'chada_travel_pages' => $chada_travel_pages,
                'chada_travel_search' => $chada_travel_search,
                'chada_travel_destination_id' => $chada_travel_destination_id,
                'chada_travel_type_id' => $chada_travel_type_id,
            ]);
        }
        return self::compact($chada_travel_markup);
    }

    private static function request_search(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only request is sanitized immediately below.
        $chada_travel_value = CHADA_TRAVEL_Config::sanitize_request_get(self::SEARCH_PARAM);
        $chada_travel_value = sanitize_text_field((string) $chada_travel_value);
        return function_exists('mb_substr')
            ? mb_substr($chada_travel_value, 0, 100, 'UTF-8')
            : substr($chada_travel_value, 0, 100);
    }

    private static function request_page(): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public pagination request.
        return max(1, CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get(self::PAGE_PARAM, 1)));
    }

    private static function request_term_id(string $chada_travel_param, object $chada_travel_wpdb, string $chada_travel_taxonomy): int {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public search request.
        $chada_travel_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get($chada_travel_param, 0));
        if ($chada_travel_id <= 0) {
            return self::request_term_id_from_slug($chada_travel_wpdb, $chada_travel_taxonomy);
        }
        $chada_travel_term = CHADA_TRAVEL_Tour_Term_Repository::find_by_id($chada_travel_wpdb, $chada_travel_id);
        $chada_travel_is_valid = $chada_travel_term
            && $chada_travel_term['chada_travel_taxonomy'] === $chada_travel_taxonomy
            && !empty($chada_travel_term['chada_travel_is_active']);
        return $chada_travel_is_valid ? $chada_travel_id : 0;
    }

    /** Falls back to the pretty /tours/destination/<slug>/ or /tours/type/<slug>/ route's own slug, if any. */
    private static function request_term_id_from_slug(object $chada_travel_wpdb, string $chada_travel_taxonomy): int {
        $chada_travel_query_var = $chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
            ? CHADA_TRAVEL_Tour_Term_Public::DESTINATION_SLUG_QUERY_VAR
            : CHADA_TRAVEL_Tour_Term_Public::TYPE_SLUG_QUERY_VAR;
        $chada_travel_slug = (string) get_query_var($chada_travel_query_var, '');
        if ($chada_travel_slug === '') {
            return 0;
        }
        $chada_travel_term = CHADA_TRAVEL_Tour_Term_Repository::find_by_slug($chada_travel_wpdb, $chada_travel_taxonomy, $chada_travel_slug);
        $chada_travel_is_valid = $chada_travel_term && !empty($chada_travel_term['chada_travel_is_active']);
        return $chada_travel_is_valid ? (int) $chada_travel_term['chada_travel_tour_term_id'] : 0;
    }

    /** @param array<string, mixed> $chada_travel_args */
    public static function build_results_url(array $chada_travel_args = []): string {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_url = CHADA_TRAVEL_Page_Settings::resolve_url(CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS, $chada_travel_settings);
        $chada_travel_args = array_filter(
            $chada_travel_args,
            static fn($chada_travel_value): bool => $chada_travel_value !== '' && $chada_travel_value !== 0
        );
        $chada_travel_add_query_arg_available = function_exists('add_query_arg')
            || function_exists(__NAMESPACE__ . '\\add_query_arg');
        return $chada_travel_args && $chada_travel_add_query_arg_available ? add_query_arg($chada_travel_args, $chada_travel_url) : $chada_travel_url;
    }

    private static function compact(string $chada_travel_markup): string {
        $chada_travel_compact = preg_replace('/>\s+</', '><', trim($chada_travel_markup));
        return is_string($chada_travel_compact) ? $chada_travel_compact : trim($chada_travel_markup);
    }
}
