<?php
/** Administrator manager for Tours and their repeatable Travel Dates. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Admin_Tours {
    public const MENU_SLUG = 'chada-travel-tours';
    private const CAPABILITY = 'manage_chada_travel_tours';
    private const SAVE_ACTION = 'chada_travel_save_tour';
    private const DUPLICATE_ACTION = 'chada_travel_duplicate_tour';
    private const TOGGLE_ACTION = 'chada_travel_toggle_tour_status';
    private const DELETE_ACTION = 'chada_travel_delete_tour';
    private const FORM_STATE_PREFIX = 'chada_travel_tour_form_';

    public static function register(): void {
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handle_save']);
        add_action('admin_post_' . self::DUPLICATE_ACTION, [self::class, 'handle_duplicate']);
        add_action('admin_post_' . self::TOGGLE_ACTION, [self::class, 'handle_toggle']);
        add_action('admin_post_' . self::DELETE_ACTION, [self::class, 'handle_delete']);
        add_action('admin_post_' . CHADA_TRAVEL_Tour_Download_Service::ADMIN_ACTION, [self::class, 'handle_download']);
    }

    public static function enqueue_assets(string $chada_travel_hook): void {
        if (!str_contains($chada_travel_hook, self::MENU_SLUG)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_editor();
        $chada_travel_script = 'assets/js/chada-travel-admin-tours.js';
        $chada_travel_style = 'assets/css/chada-travel-admin-tours.css';
        wp_enqueue_script('chada-travel-admin-tours', CHADA_TRAVEL_PLUGIN_URL . $chada_travel_script, [], self::asset_version($chada_travel_script), true);
        wp_enqueue_style('chada-travel-admin-tours', CHADA_TRAVEL_PLUGIN_URL . $chada_travel_style, ['chada-travel-admin'], self::asset_version($chada_travel_style));
    }

    private static function asset_version(string $chada_travel_relative_path): string {
        $chada_travel_path = CHADA_TRAVEL_PLUGIN_DIR . ltrim($chada_travel_relative_path, '/\\');
        $chada_travel_modified = is_file($chada_travel_path) ? filemtime($chada_travel_path) : false;
        return $chada_travel_modified ? CHADA_TRAVEL_VERSION . '.' . $chada_travel_modified : CHADA_TRAVEL_VERSION;
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Tours.', 'chada-travel'));
        }
        global $wpdb;
        $chada_travel_edit_id = self::query_int('edit');
        $chada_travel_view = self::query_bool('view');
        $chada_travel_tour = $chada_travel_edit_id > 0 ? CHADA_TRAVEL_Tour_Repository::find_by_id($wpdb, $chada_travel_edit_id) : null;
        if ($chada_travel_edit_id > 0 && !$chada_travel_tour) {
            wp_die(esc_html__('The requested Tour was not found.', 'chada-travel'));
        }
        $chada_travel_dates = $chada_travel_tour ? CHADA_TRAVEL_Tour_Repository::get_dates($wpdb, $chada_travel_edit_id) : [];
        $chada_travel_files = $chada_travel_tour ? CHADA_TRAVEL_Tour_Repository::get_files($wpdb, $chada_travel_edit_id) : [];
        $chada_travel_destinations = CHADA_TRAVEL_Tour_Term_Repository::get_all($wpdb, CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION);
        $chada_travel_types = [];
        $chada_travel_destination_ids = $chada_travel_tour ? CHADA_TRAVEL_Tour_Repository::get_term_ids($wpdb, $chada_travel_edit_id, CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION) : [];
        $chada_travel_type_ids = $chada_travel_tour ? CHADA_TRAVEL_Tour_Repository::get_term_ids($wpdb, $chada_travel_edit_id, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE) : [];
        $chada_travel_file_ids = array_map('intval', array_column($chada_travel_files, 'chada_travel_attachment_id'));
        $chada_travel_is_archived = is_array($chada_travel_tour) && empty($chada_travel_tour['chada_travel_is_active']);
        $chada_travel_original_tour = $chada_travel_tour;
        $chada_travel_state = self::consume_form_state();
        if (!empty($chada_travel_state['input'])) {
            $chada_travel_tour = $chada_travel_state['input'];
            $chada_travel_dates = (array) ($chada_travel_state['input']['travel_dates'] ?? []);
            $chada_travel_destination_ids = (array) ($chada_travel_state['input']['destination_ids'] ?? []);
            $chada_travel_type_ids = (array) ($chada_travel_state['input']['type_ids'] ?? []);
            $chada_travel_file_ids = array_map('intval', (array) ($chada_travel_state['input']['downloadable_file_ids'] ?? []));
        }
        $chada_travel_types = self::get_type_terms_for_form($wpdb, $chada_travel_edit_id);
        $chada_travel_search = self::query_string('s');
        $chada_travel_status = self::query_string('status');
        $chada_travel_status = in_array($chada_travel_status, ['all', 'active', 'archived'], true) ? $chada_travel_status : 'active';
        $chada_travel_featured = self::query_string('featured');
        $chada_travel_featured = in_array($chada_travel_featured, ['all', 'featured', 'not_featured'], true) ? $chada_travel_featured : 'all';
        $chada_travel_destination_filter = self::query_int('destination');
        $chada_travel_type_filter = self::query_int('type');
        $chada_travel_paged = max(1, self::query_int('paged'));
        $chada_travel_rows = CHADA_TRAVEL_Tour_Repository::search_grid($wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_paged, $chada_travel_featured, $chada_travel_destination_filter, $chada_travel_type_filter);
        $chada_travel_total = CHADA_TRAVEL_Tour_Repository::count_grid($wpdb, $chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination_filter, $chada_travel_type_filter);
        $chada_travel_transfer_context = [
            'page' => self::MENU_SLUG,
            'filters' => [
                'search' => $chada_travel_search,
                'status' => $chada_travel_status,
                'featured' => $chada_travel_featured,
                'destination' => $chada_travel_destination_filter,
                'type' => $chada_travel_type_filter,
            ],
        ];
        $chada_travel_transfer_html = (string) apply_filters('chada_travel_admin_tours_transfer_html', '', $chada_travel_transfer_context);
        $chada_travel_open_form = $chada_travel_edit_id > 0 || !empty($chada_travel_state['errors']);
        $chada_travel_form_mode = $chada_travel_view && $chada_travel_edit_id > 0 ? 'view' : ($chada_travel_edit_id > 0 ? 'edit' : 'add');
        self::localize_tour_data($chada_travel_rows, $wpdb, $chada_travel_tour);

        CHADA_TRAVEL_Template::output('admin/tours/page', [
            'chada_travel_form_html' => self::render_form($chada_travel_tour ?: [], $chada_travel_dates, $chada_travel_destination_ids, $chada_travel_type_ids, $chada_travel_file_ids, $chada_travel_destinations, $chada_travel_types, $chada_travel_state['errors'] ?? [], $chada_travel_view, $chada_travel_open_form, $chada_travel_form_mode, $chada_travel_is_archived, (string) ($chada_travel_original_tour['chada_travel_created_at'] ?? ''), (string) ($chada_travel_original_tour['chada_travel_updated_at'] ?? '')),
            'chada_travel_notice_html' => self::render_notice(),
            'chada_travel_duplicate_modal_html' => self::render_duplicate_modal(),
            'chada_travel_delete_modal_html' => self::render_delete_modal(),
            'chada_travel_transfer_html' => $chada_travel_transfer_html,
            'chada_travel_filters_html' => self::render_filters($chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination_filter, $chada_travel_type_filter, $chada_travel_destinations, $chada_travel_types),
            'chada_travel_table_html' => self::render_table($chada_travel_rows, $wpdb, $chada_travel_paged),
            'chada_travel_pagination_html' => self::render_pagination($chada_travel_total, $chada_travel_paged, $chada_travel_search, $chada_travel_status, $chada_travel_featured, $chada_travel_destination_filter, $chada_travel_type_filter),
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_tour
     * @param list<array<string, mixed>> $chada_travel_dates
     * @param list<int|string> $chada_travel_destination_ids
     * @param list<int|string> $chada_travel_type_ids
     * @param list<int|string> $chada_travel_file_ids
     * @param list<array<string, mixed>> $chada_travel_destinations
     * @param list<array<string, mixed>> $chada_travel_types
     * @param array<string, string> $chada_travel_errors
     */
    private static function render_form(array $chada_travel_tour, array $chada_travel_dates, array $chada_travel_destination_ids, array $chada_travel_type_ids, array $chada_travel_file_ids, array $chada_travel_destinations, array $chada_travel_types, array $chada_travel_errors, bool $chada_travel_view, bool $chada_travel_open, string $chada_travel_mode, bool $chada_travel_is_archived, string $chada_travel_created_at = '', string $chada_travel_updated_at = ''): string {
        $chada_travel_tour_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? $chada_travel_tour['tour_id'] ?? 0);
        $chada_travel_slug = (string) ($chada_travel_tour['chada_travel_tour_slug'] ?? $chada_travel_tour['tour_slug'] ?? '');
        $chada_travel_permalink_parts = CHADA_TRAVEL_Tour_Public::permalink_parts($chada_travel_slug);
        $chada_travel_image_id = (int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? $chada_travel_tour['feature_image_attachment_id'] ?? 0);
        $chada_travel_files = [];
        foreach (array_values(array_map('intval', $chada_travel_file_ids)) as $chada_travel_index => $chada_travel_file_id) {
            $chada_travel_file = self::downloadable_file_data($chada_travel_file_id, $chada_travel_index, $chada_travel_tour_id);
            if ($chada_travel_file['id'] > 0) {
                $chada_travel_files[] = $chada_travel_file;
            }
        }
        $chada_travel_rules = CHADA_TRAVEL_Config::get_upload_rules()['documents'] ?? [];
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_default_currency = (string) ($chada_travel_settings['chada_travel_tour_default_currency'] ?? CHADA_TRAVEL_Config::TOUR_DEFAULT_CURRENCY);
        $chada_travel_created_display = CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_created_at, $chada_travel_settings);
        $chada_travel_updated_display = CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_updated_at, $chada_travel_settings);
        $chada_travel_render_dates = $chada_travel_dates ?: [['chada_travel_start_date' => self::tomorrow_date(), 'chada_travel_end_date' => '']];
        return CHADA_TRAVEL_Template::render('admin/tours/form', [
            'chada_travel_tour' => $chada_travel_tour,
            'chada_travel_dates' => $chada_travel_render_dates,
            'chada_travel_duration' => self::calculate_duration($chada_travel_dates),
            'chada_travel_destination_ids' => $chada_travel_destination_ids,
            'chada_travel_type_ids' => $chada_travel_type_ids,
            'chada_travel_destinations' => $chada_travel_destinations,
            'chada_travel_types' => $chada_travel_types,
            'chada_travel_free_type_mode' => !CHADA_TRAVEL_Extension_Manager::is_pro_active(),
            'chada_travel_files' => $chada_travel_files,
            'chada_travel_errors' => $chada_travel_errors,
            'chada_travel_currency' => (string) ($chada_travel_tour['chada_travel_currency'] ?? $chada_travel_tour['currency'] ?? $chada_travel_default_currency),
            'chada_travel_currency_options' => CHADA_TRAVEL_Config::get_tour_currency_options(),
            'chada_travel_mode' => $chada_travel_mode,
            'chada_travel_open' => $chada_travel_open,
            'chada_travel_is_archived' => $chada_travel_is_archived,
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_save_action' => self::SAVE_ACTION,
            'chada_travel_delete_action' => self::DELETE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_delete_nonce_html' => wp_nonce_field(self::DELETE_ACTION . '_' . (int) ($chada_travel_tour['chada_travel_tour_id'] ?? $chada_travel_tour['tour_id'] ?? 0), '_wpnonce', true, false),
            'chada_travel_image_url' => self::feature_image_url($chada_travel_image_id),
            'chada_travel_image_id' => $chada_travel_image_id,
            'chada_travel_image_width' => (int) $chada_travel_settings['chada_travel_tour_featured_image_width'],
            'chada_travel_image_height' => (int) $chada_travel_settings['chada_travel_tour_featured_image_height'],
            'chada_travel_image_max_mb' => (int) $chada_travel_settings['chada_travel_tour_featured_image_max_mb'],
            'chada_travel_tour_permalink' => $chada_travel_tour_id > 0 && $chada_travel_slug !== '' ? CHADA_TRAVEL_Tour_Public::permalink($chada_travel_slug) : '',
            'chada_travel_created_at' => $chada_travel_created_display !== '' ? $chada_travel_created_display : '—',
            'chada_travel_updated_at' => $chada_travel_updated_display !== '' ? $chada_travel_updated_display : '—',
            'chada_travel_show_metadata' => $chada_travel_tour_id > 0,
            'chada_travel_permalink_prefix' => $chada_travel_permalink_parts['prefix'],
            'chada_travel_permalink_suffix' => $chada_travel_permalink_parts['suffix'],
            'chada_travel_readonly' => $chada_travel_view ? ' readonly disabled' : '',
            'chada_travel_mime_types' => array_values(array_filter((array) ($chada_travel_rules['mime_types'] ?? []), 'is_string')),
        ]);
        // Deprecated inline rendering is unreachable after the template renderer returns.
        // @phpstan-ignore-next-line
        $chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? $chada_travel_tour['tour_id'] ?? 0);
        $chada_travel_readonly = $chada_travel_view ? ' readonly disabled' : '';
        $chada_travel_title = $chada_travel_id > 0 ? ($chada_travel_view ? __('View Tour', 'chada-travel') : __('Edit Tour', 'chada-travel')) : __('Add Tour', 'chada-travel');
        echo '<div class="chada-travel-tour-form-card" id="chada-travel-tour-form" data-chada-travel-tour="tour-form" data-chada-travel-tour-form-mode="' . esc_attr($chada_travel_mode) . '"' . ($chada_travel_open ? '' : ' hidden') . '>';
        echo '<h2 id="chada-travel-tour-form-title">' . esc_html($chada_travel_title) . '</h2>';
        if ($chada_travel_errors) {
            echo '<div class="notice notice-error"><p>' . esc_html(implode(' ', $chada_travel_errors)) . '</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="chada-travel-tour-form-fields">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::SAVE_ACTION) . '">';
        echo '<input type="hidden" name="tour_id" id="chada-travel-tour-id" value="' . esc_attr((string) $chada_travel_id) . '">';
        wp_nonce_field(self::SAVE_ACTION);
        self::render_image_field((int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? $chada_travel_tour['feature_image_attachment_id'] ?? 0), $chada_travel_readonly);
        self::render_text_field('tour_name', __('Tour Name', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_tour_name'] ?? $chada_travel_tour['tour_name'] ?? ''), self::MAX_NAME_ATTRIBUTES, $chada_travel_readonly);
        self::render_editor('description', __('Description', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_description'] ?? $chada_travel_tour['description'] ?? ''), $chada_travel_readonly);
        self::render_dates($chada_travel_dates, $chada_travel_readonly);
        self::render_editor('trip_includes', __('Trip Includes', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_trip_includes'] ?? $chada_travel_tour['trip_includes'] ?? ''), $chada_travel_readonly);
        self::render_editor('trip_excludes', __('Trip Excludes', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_trip_excludes'] ?? $chada_travel_tour['trip_excludes'] ?? ''), $chada_travel_readonly);
        self::render_editor('itinerary', __('Itinerary', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_itinerary'] ?? $chada_travel_tour['itinerary'] ?? ''), $chada_travel_readonly);
        self::render_editor('booking_conditions', __('Booking Conditions', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_booking_conditions'] ?? $chada_travel_tour['booking_conditions'] ?? ''), $chada_travel_readonly);
        self::render_text_field('tour_code', __('Tour Code', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_tour_code'] ?? $chada_travel_tour['tour_code'] ?? ''), ['maxlength' => 20], $chada_travel_readonly);
        $chada_travel_currency = (string) ($chada_travel_tour['chada_travel_currency'] ?? CHADA_TRAVEL_Config::get_currency_code(CHADA_TRAVEL_Config::get_settings()));
        echo '<p><strong>' . esc_html__('Currency', 'chada-travel') . ':</strong> ' . esc_html($chada_travel_currency) . '</p>';
        self::render_text_field('price', __('Price', 'chada-travel'), (string) ($chada_travel_tour['chada_travel_price'] ?? $chada_travel_tour['price'] ?? ''), ['type' => 'number', 'step' => '0.01', 'inputmode' => 'decimal', 'placeholder' => '0.00', 'min' => '0'], $chada_travel_readonly);
        self::render_terms('destination_ids', __('Tour Destinations', 'chada-travel'), $chada_travel_destinations, $chada_travel_destination_ids, $chada_travel_readonly);
        self::render_terms('type_ids', __('Tour Types', 'chada-travel'), $chada_travel_types, $chada_travel_type_ids, $chada_travel_readonly);
        self::render_downloadable_files($chada_travel_file_ids, $chada_travel_readonly, $chada_travel_id);
        echo '<p class="chada-travel-tour-form-actions"><button type="submit" class="button button-primary" id="chada-travel-save-tour">' . esc_html__('Save Tour', 'chada-travel') . '</button> <button type="button" class="button" id="chada-travel-duplicate-tour"' . ($chada_travel_id > 0 && $chada_travel_mode === 'edit' ? '' : ' hidden') . '>' . esc_html__('Duplicate', 'chada-travel') . '</button> <button type="button" class="button" id="chada-travel-edit-tour" hidden>' . esc_html__('Edit Tour', 'chada-travel') . '</button> <button type="button" class="button" id="chada-travel-cancel-tour">' . esc_html__('Cancel', 'chada-travel') . '</button></p></form>';
        echo '</div>';
    }

    /** @return list<array<string, mixed>> */
    private static function get_type_terms_for_form(object $chada_travel_wpdb, int $chada_travel_tour_id): array {
        $chada_travel_is_pro = CHADA_TRAVEL_Extension_Manager::is_pro_active();
        $chada_travel_terms = $chada_travel_is_pro
            ? CHADA_TRAVEL_Tour_Term_Repository::get_all($chada_travel_wpdb, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE, false)
            : CHADA_TRAVEL_Tour_Term_Repository::get_active_default_types($chada_travel_wpdb);
        if ($chada_travel_tour_id <= 0) {
            return $chada_travel_terms;
        }
        foreach (CHADA_TRAVEL_Tour_Repository::get_term_ids($chada_travel_wpdb, $chada_travel_tour_id, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE) as $chada_travel_existing_id) {
            $chada_travel_term = CHADA_TRAVEL_Tour_Term_Repository::find_by_id($chada_travel_wpdb, $chada_travel_existing_id);
            if (!$chada_travel_term || (!$chada_travel_is_pro && !empty($chada_travel_term['chada_travel_is_default']))) {
                continue;
            }
            $chada_travel_existing_ids = array_column($chada_travel_terms, 'chada_travel_tour_term_id');
            if (!in_array($chada_travel_existing_id, $chada_travel_existing_ids, true)) {
                $chada_travel_terms[] = $chada_travel_term;
            }
        }
        usort($chada_travel_terms, static fn(array $chada_travel_left, array $chada_travel_right): int => strcasecmp(
            (string) ($chada_travel_left['chada_travel_term_name'] ?? ''),
            (string) ($chada_travel_right['chada_travel_term_name'] ?? '')
        ));
        return $chada_travel_terms;
    }

    private static function render_duplicate_modal(): string {
        return CHADA_TRAVEL_Template::render('admin/tours/duplicate-modal', [
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_duplicate_action' => self::DUPLICATE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::DUPLICATE_ACTION, '_wpnonce', true, false),
        ]);
        // Deprecated inline rendering is unreachable after the template renderer returns.
        // @phpstan-ignore-next-line
        echo '<div id="chada-travel-tour-duplicate-modal" class="chada-travel-confirmation-modal" hidden><div class="chada-travel-confirmation-modal__backdrop" data-chada-travel-duplicate-cancel></div><div class="chada-travel-confirmation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="chada-travel-tour-duplicate-title" tabindex="-1"><h2 id="chada-travel-tour-duplicate-title">' . esc_html__('Duplicate Tour?', 'chada-travel') . '</h2><p>' . esc_html__('This will create an active copy of the Tour with a unique duplicate name and code. Continue?', 'chada-travel') . '</p><p><button type="button" class="button" data-chada-travel-duplicate-cancel>' . esc_html__('Cancel', 'chada-travel') . '</button> <button type="button" class="button button-primary" data-chada-travel-duplicate-confirm>' . esc_html__('Duplicate Tour', 'chada-travel') . '</button></p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="chada-travel-tour-duplicate-form" hidden><input type="hidden" name="action" value="' . esc_attr(self::DUPLICATE_ACTION) . '"><input type="hidden" name="tour_id" id="chada-travel-tour-duplicate-id" value="0">';
        wp_nonce_field(self::DUPLICATE_ACTION);
        echo '</form></div></div>';
    }

    private static function render_delete_modal(): string {
        return CHADA_TRAVEL_Template::render('admin/tours/delete-modal');
    }

    /** @var array<string, int> */
    // @phpstan-ignore-next-line
    private const MAX_NAME_ATTRIBUTES = ['maxlength' => 100];

    // @phpstan-ignore-next-line
    private static function render_image_field(int $chada_travel_attachment_id, string $chada_travel_readonly): void {
        $chada_travel_url = $chada_travel_attachment_id > 0 ? (string) wp_get_attachment_image_url($chada_travel_attachment_id, 'thumbnail') : '';
        echo '<div class="chada-travel-tour-field"><label><strong>' . esc_html__('Featured Image', 'chada-travel') . '</strong></label><div class="chada-travel-tour-media">';
        echo '<input type="hidden" name="feature_image_attachment_id" id="chada-travel-tour-image-id" value="' . esc_attr((string) $chada_travel_attachment_id) . '">';
        if ($chada_travel_url !== '') {
            echo '<img id="chada-travel-tour-image-preview" src="' . esc_url($chada_travel_url) . '" alt="" width="120" height="120">';
        }
        echo '<button type="button" class="button chada-travel-tour-image-control" id="chada-travel-tour-select-image"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . '>' . esc_html__('Select Image', 'chada-travel') . '</button> <button type="button" class="button chada-travel-tour-image-control" id="chada-travel-tour-remove-image"' . ($chada_travel_attachment_id > 0 && $chada_travel_readonly === '' ? '' : ' hidden') . '>' . esc_html__('Remove Image', 'chada-travel') . '</button>';
        echo '<p class="description chada-travel-tour-image-control"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . '>' . esc_html__('JPG, JPEG, or PNG. One image only.', 'chada-travel') . '</p>';
        echo '</div></div>';
    }

    /** @param list<int|string> $chada_travel_file_ids */
    // @phpstan-ignore-next-line
    private static function render_downloadable_files(array $chada_travel_file_ids, string $chada_travel_readonly, int $chada_travel_tour_id = 0): void {
        $chada_travel_rules = CHADA_TRAVEL_Config::get_upload_rules()['documents'] ?? [];
        $chada_travel_mime_types = array_values(array_filter((array) ($chada_travel_rules['mime_types'] ?? []), 'is_string'));
        echo '<div class="chada-travel-tour-field chada-travel-tour-files-field"><label><strong>' . esc_html__('Downloadable Files', 'chada-travel') . '</strong></label>';
        echo '<div id="chada-travel-tour-files" data-mime-types="' . esc_attr(wp_json_encode($chada_travel_mime_types)) . '">';
        foreach (array_values(array_map('intval', $chada_travel_file_ids)) as $chada_travel_index => $chada_travel_attachment_id) {
            $chada_travel_file = self::downloadable_file_data($chada_travel_attachment_id, $chada_travel_index, $chada_travel_tour_id);
            if ($chada_travel_file['id'] <= 0) {
                continue;
            }
            self::render_downloadable_file_row($chada_travel_file, $chada_travel_readonly);
        }
        echo '</div><p class="chada-travel-tour-files-actions"><button type="button" class="button chada-travel-tour-file-control" id="chada-travel-tour-select-files"' . ($chada_travel_readonly !== '' ? ' disabled aria-disabled="true"' : '') . '>' . esc_html__('Select Files', 'chada-travel') . '</button> <span id="chada-travel-tour-files-count" class="description"></span></p>';
        echo '<p class="description">' . esc_html__('Select one or more files. File type and maximum file size follow Documents & Uploads settings.', 'chada-travel') . '</p>';
        echo '</div>';
    }

    /** @param array{id: int, name: string, url: string, mime: string, size: int, order: int} $chada_travel_file */
    private static function render_downloadable_file_row(array $chada_travel_file, string $chada_travel_readonly): void {
        echo '<div class="chada-travel-tour-file-row" data-attachment-id="' . esc_attr((string) $chada_travel_file['id']) . '">';
        echo '<input type="hidden" name="downloadable_file_ids[]" value="' . esc_attr((string) $chada_travel_file['id']) . '">';
        if ($chada_travel_file['url'] !== '') {
            echo '<a href="' . esc_url($chada_travel_file['url']) . '" download="' . esc_attr($chada_travel_file['name']) . '">' . esc_html($chada_travel_file['name']) . '</a>';
        } else {
            echo '<span>' . esc_html($chada_travel_file['name']) . '</span>';
        }
        echo ' <span class="description">' . esc_html($chada_travel_file['mime']) . '</span>';
        echo ' <button type="button" class="button-link-delete chada-travel-remove-tour-file"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . '>' . esc_html__('Remove', 'chada-travel') . '</button>';
        echo ' <button type="button" class="button-link chada-travel-move-tour-file chada-travel-move-tour-file-up"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . ' aria-label="' . esc_attr__('Move file up', 'chada-travel') . '">↑</button> <button type="button" class="button-link chada-travel-move-tour-file chada-travel-move-tour-file-down"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . ' aria-label="' . esc_attr__('Move file down', 'chada-travel') . '">↓</button>';
        echo '</div>';
    }

    /** @param array<string, int|string> $chada_travel_attributes */
    // @phpstan-ignore-next-line
    private static function render_text_field(string $chada_travel_name, string $chada_travel_label, string $chada_travel_value, array $chada_travel_attributes, string $chada_travel_readonly): void {
        $chada_travel_attribute_html = '';
        foreach ($chada_travel_attributes as $chada_travel_key => $chada_travel_value_attribute) {
            $chada_travel_attribute_html .= ' ' . esc_attr($chada_travel_key) . '="' . esc_attr((string) $chada_travel_value_attribute) . '"';
        }
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- fragments are escaped above or code-controlled.
        echo '<div class="chada-travel-tour-field"><label for="chada-travel-tour-' . esc_attr($chada_travel_name) . '"><strong>' . esc_html($chada_travel_label) . '</strong></label><input class="regular-text" id="chada-travel-tour-' . esc_attr($chada_travel_name) . '" name="' . esc_attr($chada_travel_name) . '" value="' . esc_attr($chada_travel_value) . '"' . $chada_travel_attribute_html . $chada_travel_readonly . '></div>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @return array<string, mixed> Shared TinyMCE settings for all Tour WYSIWYG fields. */
    public static function editor_settings(): array {
        return [
            'textarea_rows' => 30,
            'media_buttons' => false,
            'quicktags' => false,
            'teeny' => false,
            'tinymce' => [
                'wpautop' => true,
                'paste_remove_styles' => true,
                'paste_webkit_styles' => 'none',
                'paste_retain_style_properties' => 'font-family',
                'toolbar1' => 'formatselect,fontselect,fontsizeselect,bold,italic,alignleft,aligncenter,alignright,alignjustify,'
                    . 'bullist,numlist,outdent,indent,link,unlink',
                'toolbar2' => '',
                'fontsize_formats' => '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
                'font_formats' => 'Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;'
                    . 'Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;'
                    . 'Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;'
                    . 'Georgia=georgia,palatino;Helvetica=helvetica,arial,sans-serif;Impact=impact,chicago;'
                    . 'Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;'
                    . 'Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;'
                    . 'Verdana=verdana,geneva;Webdings=webdings;Calibri 10=calibri,sans-serif;'
                    . 'Poppins=poppins,sans-serif',
            ],
        ];
    }

    // @phpstan-ignore-next-line
    private static function render_editor(string $chada_travel_name, string $chada_travel_label, string $chada_travel_value, string $chada_travel_readonly): void {
        echo '<div class="chada-travel-tour-field chada-travel-tour-editor-field" data-chada-travel-editor-field="' . esc_attr($chada_travel_name) . '"><label><strong>' . esc_html($chada_travel_label) . '</strong></label><div class="chada-travel-tour-editor-control"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . '>';
        $chada_travel_editor_settings = self::editor_settings();
        $chada_travel_editor_settings['textarea_name'] = $chada_travel_name;
        wp_editor($chada_travel_value, 'chada-travel-tour-editor-' . $chada_travel_name, $chada_travel_editor_settings);
        echo '</div><div class="chada-travel-tour-content chada-travel-tour-editor-preview" data-chada-travel-editor-preview="' . esc_attr($chada_travel_name) . '"' . ($chada_travel_readonly === '' ? ' hidden' : '') . '>' . wp_kses_post(wpautop($chada_travel_value)) . '</div>';
        echo '</div>';
    }

    /** @param list<array<string, mixed>> $chada_travel_dates */
    // @phpstan-ignore-next-line
    private static function render_dates(array $chada_travel_dates, string $chada_travel_readonly): void {
        if ($chada_travel_dates === []) {
            $chada_travel_dates = [['chada_travel_start_date' => self::tomorrow_date(), 'chada_travel_end_date' => '']];
        }
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- date fragments are escaped or code-controlled.
        echo '<div class="chada-travel-tour-field"><label><strong>' . esc_html__('Travel Dates', 'chada-travel') . '</strong></label><div id="chada-travel-tour-dates">';
        foreach ($chada_travel_dates as $chada_travel_index => $chada_travel_date) {
            $chada_travel_start = (string) ($chada_travel_date['chada_travel_start_date'] ?? $chada_travel_date['start_date'] ?? '');
            $chada_travel_end = (string) ($chada_travel_date['chada_travel_end_date'] ?? $chada_travel_date['end_date'] ?? '');
            echo '<div class="chada-travel-tour-date-row"><input type="date" name="travel_dates[' . (int) $chada_travel_index . '][start_date]" value="' . esc_attr($chada_travel_start) . '"' . $chada_travel_readonly . '> <span>–</span> <input type="date" name="travel_dates[' . (int) $chada_travel_index . '][end_date]" value="' . esc_attr($chada_travel_end) . '"' . $chada_travel_readonly . '>';
            echo ' <button type="button" class="button-link-delete chada-travel-remove-tour-date"' . ($chada_travel_readonly !== '' ? ' hidden' : '') . '>' . esc_html__('Remove', 'chada-travel') . '</button>';
            echo '</div>';
        }
        echo '</div><button type="button" class="button" id="chada-travel-add-tour-date"' . ($chada_travel_readonly !== '' ? ' disabled aria-disabled="true"' : '') . '>' . esc_html__('Add Travel Date', 'chada-travel') . '</button>';
        echo '<p class="description">' . esc_html__('Travel Date values must be valid ISO dates; overlapping and reverse-ordered ranges are allowed.', 'chada-travel') . '</p>';
        echo '</div>';
        self::render_duration($chada_travel_dates);
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @param list<array<string, mixed>> $chada_travel_dates */
    private static function render_duration(array $chada_travel_dates): void {
        $chada_travel_duration = self::calculate_duration($chada_travel_dates);
        echo '<div class="chada-travel-tour-field"><label for="chada-travel-tour-duration"><strong>' . esc_html__('Duration', 'chada-travel')
            . '</strong></label><output id="chada-travel-tour-duration" aria-live="polite" aria-readonly="true">'
            . esc_html($chada_travel_duration !== '' ? $chada_travel_duration : '—') . '</output></div>';
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_terms
     * @param list<int|string> $chada_travel_selected
     */
    // @phpstan-ignore-next-line
    private static function render_terms(string $chada_travel_name, string $chada_travel_label, array $chada_travel_terms, array $chada_travel_selected, string $chada_travel_readonly): void {
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- checkbox fragments are escaped or code-controlled.
        echo '<div class="chada-travel-tour-field"><fieldset><legend><strong>' . esc_html($chada_travel_label) . '</strong></legend><div class="chada-travel-tour-checkboxes">';
        foreach ($chada_travel_terms as $chada_travel_term) {
            $chada_travel_id = (int) $chada_travel_term['chada_travel_tour_term_id'];
            $chada_travel_checked = in_array($chada_travel_id, array_map('intval', $chada_travel_selected), true) ? ' checked' : '';
            $chada_travel_archived = empty($chada_travel_term['chada_travel_is_active']);
            if ($chada_travel_archived && $chada_travel_checked === '') {
                continue;
            }
            echo '<label><input type="checkbox" name="' . esc_attr($chada_travel_name) . '[]" value="' . esc_attr((string) $chada_travel_id) . '"' . $chada_travel_checked . $chada_travel_readonly . '> ' . esc_html($chada_travel_term['chada_travel_term_name']) . ($chada_travel_archived ? ' (' . esc_html__('Archived', 'chada-travel') . ')' : '') . '</label>';
        }
        echo '</div></fieldset></div>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /** @param list<array<string, mixed>> $chada_travel_rows */
    private static function render_table(array $chada_travel_rows, object $chada_travel_wpdb, int $chada_travel_paged): string {
        $chada_travel_row_offset = (max(1, $chada_travel_paged) - 1) * CHADA_TRAVEL_Tour_Repository::GRID_PER_PAGE;
        $chada_travel_rows_html = '';
        foreach ($chada_travel_rows as $chada_travel_row_index => $chada_travel_row) {
            $chada_travel_id = (int) $chada_travel_row['chada_travel_tour_id'];
            $chada_travel_dates = CHADA_TRAVEL_Tour_Repository::get_dates($chada_travel_wpdb, $chada_travel_id);
            $chada_travel_date_labels = [];
            foreach ($chada_travel_dates as $chada_travel_date) {
                $chada_travel_date_labels[] = esc_html(self::format_date((string) $chada_travel_date['chada_travel_start_date']) . ' – ' . self::format_date((string) $chada_travel_date['chada_travel_end_date']));
            }
            $chada_travel_is_active = !empty($chada_travel_row['chada_travel_is_active']);
            $chada_travel_is_featured = !empty($chada_travel_row['chada_travel_is_featured']);
            $chada_travel_status = $chada_travel_is_active
                ? ($chada_travel_is_featured ? __('Active (Featured)', 'chada-travel') : __('Active', 'chada-travel'))
                : __('Archived', 'chada-travel');
            $chada_travel_rows_html .= CHADA_TRAVEL_Template::render('admin/tours/row', [
                'chada_travel_id' => $chada_travel_id,
                'chada_travel_row_number' => $chada_travel_row_offset + $chada_travel_row_index + 1,
                'chada_travel_tour_name' => (string) $chada_travel_row['chada_travel_tour_name'],
                'chada_travel_permalink' => (string) $chada_travel_row['chada_travel_tour_slug'] !== ''
                    ? CHADA_TRAVEL_Tour_Public::permalink((string) $chada_travel_row['chada_travel_tour_slug']) : '',
                'chada_travel_code' => (string) $chada_travel_row['chada_travel_tour_code'],
                'chada_travel_image_url' => self::feature_image_url((int) ($chada_travel_row['chada_travel_feature_image_attachment_id'] ?? 0)),
                'chada_travel_dates_html' => implode('<br>', $chada_travel_date_labels),
                'chada_travel_price' => CHADA_TRAVEL_Config::format_money((float) $chada_travel_row['chada_travel_price'], (string) $chada_travel_row['chada_travel_currency'], CHADA_TRAVEL_Config::get_settings()),
                'chada_travel_status' => $chada_travel_status,
                'chada_travel_is_active' => $chada_travel_is_active,
                'chada_travel_is_featured' => $chada_travel_is_featured,
                'chada_travel_toggle_action' => self::TOGGLE_ACTION,
                'chada_travel_toggle_url' => admin_url('admin-post.php'),
                'chada_travel_toggle_nonce_html' => wp_nonce_field(self::TOGGLE_ACTION . '_' . $chada_travel_id, '_wpnonce', true, false),
                'chada_travel_delete_action' => self::DELETE_ACTION,
                'chada_travel_delete_nonce_html' => wp_nonce_field(self::DELETE_ACTION . '_' . $chada_travel_id, '_wpnonce', true, false),
            ]);
        }
        return CHADA_TRAVEL_Template::render('admin/tours/table', ['chada_travel_rows_html' => $chada_travel_rows_html]);
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_destinations
     * @param list<array<string, mixed>> $chada_travel_types
     */
    private static function render_filters(string $chada_travel_search, string $chada_travel_status, string $chada_travel_featured, int $chada_travel_destination, int $chada_travel_type, array $chada_travel_destinations, array $chada_travel_types): string {
        return CHADA_TRAVEL_Template::render('admin/tours/filters', [
            'chada_travel_menu_slug' => self::MENU_SLUG,
            'chada_travel_search' => $chada_travel_search,
            'chada_travel_status' => $chada_travel_status,
            'chada_travel_featured' => $chada_travel_featured,
            'chada_travel_destination' => $chada_travel_destination,
            'chada_travel_type' => $chada_travel_type,
            'chada_travel_destinations' => $chada_travel_destinations,
            'chada_travel_types' => $chada_travel_types,
        ]);
        // @phpstan-ignore-next-line
        echo '<form class="chada-travel-toolbar" method="get"><input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '"><label>' . esc_html__('Search', 'chada-travel') . '<input type="search" name="s" value="' . esc_attr($chada_travel_search) . '" placeholder="' . esc_attr__('Tour name or code', 'chada-travel') . '"></label><label>' . esc_html__('Status', 'chada-travel') . '<select name="status"><option value="active"' . selected($chada_travel_status, 'active', false) . '>' . esc_html__('Active', 'chada-travel') . '</option><option value="archived"' . selected($chada_travel_status, 'archived', false) . '>' . esc_html__('Archived', 'chada-travel') . '</option></select></label><button class="button" type="submit">' . esc_html__('Filter', 'chada-travel') . '</button></form>';
    }

    private static function render_pagination(int $chada_travel_total, int $chada_travel_paged, string $chada_travel_search, string $chada_travel_status, string $chada_travel_featured, int $chada_travel_destination, int $chada_travel_type): string {
        $chada_travel_pages = [];
        $chada_travel_page_count = (int) ceil($chada_travel_total / CHADA_TRAVEL_Tour_Repository::GRID_PER_PAGE);
        for ($chada_travel_page = 1; $chada_travel_page <= $chada_travel_page_count; $chada_travel_page++) {
            $chada_travel_pages[] = [
                'page' => $chada_travel_page,
                'url' => self::page_url(['s' => $chada_travel_search, 'status' => $chada_travel_status, 'featured' => $chada_travel_featured, 'destination' => $chada_travel_destination, 'type' => $chada_travel_type, 'paged' => $chada_travel_page]),
                'is_current' => $chada_travel_page === $chada_travel_paged,
            ];
        }
        return CHADA_TRAVEL_Template::render('admin/tours/pagination', ['chada_travel_pages' => $chada_travel_pages]);
        // @phpstan-ignore-next-line
        $chada_travel_pages = (int) ceil($chada_travel_total / CHADA_TRAVEL_Tour_Repository::GRID_PER_PAGE);
        if ($chada_travel_pages < 2) {
            return '';
        }
        echo '<div class="tablenav"><div class="tablenav-pages">';
        for ($chada_travel_page = 1; $chada_travel_page <= $chada_travel_pages; $chada_travel_page++) {
            $chada_travel_class = $chada_travel_page === $chada_travel_paged ? ' class="page-numbers current"' : ' class="page-numbers"';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- class is selected from code-controlled values.
            echo '<a' . esc_attr($chada_travel_class) . ' href="' . esc_url(self::page_url(['s' => $chada_travel_search, 'status' => $chada_travel_status, 'paged' => $chada_travel_page])) . '">' . (int) $chada_travel_page . '</a> ';
        }
        echo '</div></div>';
    }

    /**
     * Localizes the current grid page and any explicitly selected Tour for the shared client-side form.
     *
     * @param list<array<string, mixed>> $chada_travel_rows
     * @param array<string, mixed>|null $chada_travel_selected_tour
     */
    private static function localize_tour_data(array $chada_travel_rows, object $chada_travel_wpdb, ?array $chada_travel_selected_tour): void {
        $chada_travel_records = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_record = self::build_client_record($chada_travel_row, $chada_travel_wpdb);
            $chada_travel_records[(string) $chada_travel_record['id']] = $chada_travel_record;
        }
        if (is_array($chada_travel_selected_tour)) {
            $chada_travel_record = self::build_client_record($chada_travel_selected_tour, $chada_travel_wpdb);
            $chada_travel_records[(string) $chada_travel_record['id']] = $chada_travel_record;
        }
        $chada_travel_rules = CHADA_TRAVEL_Config::get_upload_rules()['documents'] ?? [];
        wp_localize_script('chada-travel-admin-tours', 'chadaTravelTours', [
            'records' => array_values($chada_travel_records),
            'selectedTourId' => is_array($chada_travel_selected_tour)
                ? (int) ($chada_travel_selected_tour['chada_travel_tour_id'] ?? $chada_travel_selected_tour['tour_id'] ?? 0) : 0,
            'newStartDate' => self::tomorrow_date(),
            'defaultTourCurrency' => (string) CHADA_TRAVEL_Config::get_settings()['chada_travel_tour_default_currency'],
            'permalinkPrefix' => CHADA_TRAVEL_Tour_Public::permalink_parts()['prefix'],
            'permalinkSuffix' => CHADA_TRAVEL_Tour_Public::permalink_parts()['suffix'],
            'downloadableMimeTypes' => array_values((array) ($chada_travel_rules['mime_types'] ?? [])),
            'strings' => [
                'selectTour' => __('Select a Tour record first.', 'chada-travel'),
                'selectFiles' => __('Select Files', 'chada-travel'),
                'useFiles' => __('Use Files', 'chada-travel'),
                'remove' => __('Remove', 'chada-travel'),
                'moveUp' => __('Move file up', 'chada-travel'),
                'moveDown' => __('Move file down', 'chada-travel'),
                'unnamedFile' => __('Unnamed file', 'chada-travel'),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_tour
     * @return array<string, mixed>
     */
    private static function build_client_record(array $chada_travel_tour, object $chada_travel_wpdb): array {
        $chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? $chada_travel_tour['tour_id'] ?? 0);
        $chada_travel_fields = ['description', 'trip_includes', 'trip_excludes', 'basic_visa_requirements', 'itinerary', 'booking_conditions'];
        $chada_travel_record = [
            'id' => $chada_travel_id,
            'tour_name' => (string) ($chada_travel_tour['chada_travel_tour_name'] ?? $chada_travel_tour['tour_name'] ?? ''),
            'tour_slug' => (string) ($chada_travel_tour['chada_travel_tour_slug'] ?? $chada_travel_tour['tour_slug'] ?? ''),
            'tour_code' => (string) ($chada_travel_tour['chada_travel_tour_code'] ?? $chada_travel_tour['tour_code'] ?? ''),
            'price' => (string) ($chada_travel_tour['chada_travel_price'] ?? $chada_travel_tour['price'] ?? ''),
            'currency' => (string) ($chada_travel_tour['chada_travel_currency'] ?? $chada_travel_tour['currency']
                ?? (CHADA_TRAVEL_Config::get_settings()['chada_travel_tour_default_currency'] ?? CHADA_TRAVEL_Config::TOUR_DEFAULT_CURRENCY)),
            'feature_image_attachment_id' => (int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? $chada_travel_tour['feature_image_attachment_id'] ?? 0),
            'feature_image_url' => self::feature_image_url((int) ($chada_travel_tour['chada_travel_feature_image_attachment_id'] ?? $chada_travel_tour['feature_image_attachment_id'] ?? 0)),
            'featured' => !empty($chada_travel_tour['chada_travel_is_featured']) || !empty($chada_travel_tour['is_featured']),
            'permalink' => ($chada_travel_tour['chada_travel_tour_slug'] ?? $chada_travel_tour['tour_slug'] ?? '') !== ''
                ? CHADA_TRAVEL_Tour_Public::permalink((string) ($chada_travel_tour['chada_travel_tour_slug'] ?? $chada_travel_tour['tour_slug'])) : '',
            'travel_dates' => [],
            'destination_ids' => [],
            'type_ids' => [],
            'downloadable_files' => [],
            'editor_html' => [],
        ];
        foreach ($chada_travel_fields as $chada_travel_field) {
            $chada_travel_value = (string) ($chada_travel_tour['chada_travel_' . $chada_travel_field] ?? $chada_travel_tour[$chada_travel_field] ?? '');
            $chada_travel_record[$chada_travel_field] = $chada_travel_value;
            $chada_travel_record['editor_html'][$chada_travel_field] = wpautop(wp_kses_post($chada_travel_value));
        }
        if ($chada_travel_id > 0) {
            foreach (CHADA_TRAVEL_Tour_Repository::get_dates($chada_travel_wpdb, $chada_travel_id) as $chada_travel_date) {
                $chada_travel_client_date = [
                    'start_date' => (string) ($chada_travel_date['chada_travel_start_date'] ?? $chada_travel_date['start_date'] ?? ''),
                    'end_date' => (string) ($chada_travel_date['chada_travel_end_date'] ?? $chada_travel_date['end_date'] ?? ''),
                ];
                $chada_travel_date_id = (int) ($chada_travel_date['chada_travel_tour_date_id'] ?? 0);
                if ($chada_travel_date_id > 0) {
                    $chada_travel_client_date['date_id'] = $chada_travel_date_id;
                }
                $chada_travel_record['travel_dates'][] = $chada_travel_client_date;
            }
            $chada_travel_record['destination_ids'] = array_map('intval', CHADA_TRAVEL_Tour_Repository::get_term_ids($chada_travel_wpdb, $chada_travel_id, CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION));
            $chada_travel_record['type_ids'] = array_map('intval', CHADA_TRAVEL_Tour_Repository::get_term_ids($chada_travel_wpdb, $chada_travel_id, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE));
            foreach (CHADA_TRAVEL_Tour_Repository::get_files($chada_travel_wpdb, $chada_travel_id) as $chada_travel_file) {
                $chada_travel_record['downloadable_files'][] = self::downloadable_file_data((int) ($chada_travel_file['chada_travel_attachment_id'] ?? 0), (int) ($chada_travel_file['chada_travel_sort_order'] ?? 0), $chada_travel_id);
            }
        } else {
            foreach ((array) ($chada_travel_tour['travel_dates'] ?? []) as $chada_travel_date) {
                $chada_travel_client_date = [
                    'start_date' => (string) ($chada_travel_date['start_date'] ?? $chada_travel_date['chada_travel_start_date'] ?? ''),
                    'end_date' => (string) ($chada_travel_date['end_date'] ?? $chada_travel_date['chada_travel_end_date'] ?? ''),
                ];
                $chada_travel_date_id = (int) ($chada_travel_date['chada_travel_tour_date_id'] ?? $chada_travel_date['date_id'] ?? 0);
                if ($chada_travel_date_id > 0) {
                    $chada_travel_client_date['date_id'] = $chada_travel_date_id;
                }
                $chada_travel_record['travel_dates'][] = $chada_travel_client_date;
            }
            $chada_travel_record['destination_ids'] = array_map('intval', (array) ($chada_travel_tour['destination_ids'] ?? []));
            $chada_travel_record['type_ids'] = array_map('intval', (array) ($chada_travel_tour['type_ids'] ?? []));
            foreach (array_values(array_map('intval', (array) ($chada_travel_tour['downloadable_file_ids'] ?? []))) as $chada_travel_index => $chada_travel_attachment_id) {
                $chada_travel_record['downloadable_files'][] = self::downloadable_file_data($chada_travel_attachment_id, $chada_travel_index);
            }
        }
        return $chada_travel_record;
    }

    private static function feature_image_url(int $chada_travel_attachment_id): string {
        if ($chada_travel_attachment_id <= 0 || !function_exists('wp_get_attachment_image_url')) {
            return '';
        }
        $chada_travel_url = wp_get_attachment_image_url($chada_travel_attachment_id, 'thumbnail');
        return is_string($chada_travel_url) ? $chada_travel_url : '';
    }

    /** @return array{id: int, name: string, url: string, mime: string, size: int, order: int} */
    private static function downloadable_file_data(int $chada_travel_attachment_id, int $chada_travel_order = 0, int $chada_travel_tour_id = 0): array {
        $chada_travel_name = '';
        $chada_travel_url = '';
        $chada_travel_mime = function_exists('get_post_mime_type') ? (string) get_post_mime_type($chada_travel_attachment_id) : '';
        $chada_travel_size = 0;
        if ($chada_travel_attachment_id > 0 && function_exists('get_attached_file')) {
            $chada_travel_path = get_attached_file($chada_travel_attachment_id);
            if (is_string($chada_travel_path) && $chada_travel_path !== '') {
                $chada_travel_name = basename($chada_travel_path);
                if (is_file($chada_travel_path)) {
                    $chada_travel_size = (int) filesize($chada_travel_path);
                }
            }
        }
        if ($chada_travel_name === '' && $chada_travel_attachment_id > 0 && function_exists('get_the_title')) {
            $chada_travel_name = (string) get_the_title($chada_travel_attachment_id);
        }
        if ($chada_travel_attachment_id > 0 && function_exists('wp_get_attachment_url')) {
            $chada_travel_candidate_url = wp_get_attachment_url($chada_travel_attachment_id);
            $chada_travel_url = is_string($chada_travel_candidate_url) ? $chada_travel_candidate_url : '';
        }
        if ($chada_travel_tour_id > 0 && function_exists('wp_nonce_url') && function_exists('admin_url')) {
            $chada_travel_url = CHADA_TRAVEL_Tour_Download_Service::admin_url($chada_travel_tour_id, $chada_travel_attachment_id);
        }
        return [
            'id' => $chada_travel_attachment_id,
            'name' => $chada_travel_name !== '' ? $chada_travel_name : __('Unnamed file', 'chada-travel'),
            'url' => $chada_travel_url,
            'mime' => $chada_travel_mime,
            'size' => $chada_travel_size,
            'order' => $chada_travel_order,
        ];
    }

    private static function tomorrow_date(): string {
        return current_datetime()->modify('+1 day')->format('Y-m-d');
    }

    public static function handle_save(): void {
        self::guard();
        check_admin_referer(self::SAVE_ACTION);
        global $wpdb;
        $chada_travel_input = self::input();
        $chada_travel_existing_tour = $chada_travel_input['tour_id'] > 0
            ? CHADA_TRAVEL_Tour_Repository::find_by_id($wpdb, $chada_travel_input['tour_id']) : null;
        if (!CHADA_TRAVEL_Extension_Manager::is_pro_active()) {
            $chada_travel_input['type_ids'] = CHADA_TRAVEL_Tour_Term_Repository::filter_type_ids_for_free(
                $wpdb,
                $chada_travel_input['type_ids'],
                (int) $chada_travel_input['tour_id']
            );
        }
        $chada_travel_existing_dates = $chada_travel_existing_tour
            ? CHADA_TRAVEL_Tour_Repository::get_dates($wpdb, (int) $chada_travel_input['tour_id']) : [];
        $chada_travel_existing_file_ids = $chada_travel_existing_tour
            ? array_map('intval', array_column(
                CHADA_TRAVEL_Tour_Repository::get_files($wpdb, (int) $chada_travel_input['tour_id']),
                'chada_travel_attachment_id'
            )) : [];
        $chada_travel_result = CHADA_TRAVEL_Tour_Validator::validate(
            $chada_travel_input,
            CHADA_TRAVEL_Tour_Repository::get_all($wpdb),
            $chada_travel_existing_dates,
            $chada_travel_existing_file_ids
        );
        if ((int) $chada_travel_input['feature_image_attachment_id'] > 0 && !self::valid_feature_image((int) $chada_travel_input['feature_image_attachment_id'])) {
            $chada_travel_result['errors']['feature_image_attachment_id'] = 'Select a JPG, JPEG, or PNG image.';
        }
        $chada_travel_downloadable_error = self::downloadable_files_error($chada_travel_result['clean']['downloadable_file_ids']);
        if ($chada_travel_downloadable_error !== '') {
            $chada_travel_result['errors']['downloadable_file_ids'] = $chada_travel_downloadable_error;
        }
        if ($chada_travel_result['errors']) {
            set_transient(self::FORM_STATE_PREFIX . get_current_user_id(), ['errors' => $chada_travel_result['errors'], 'input' => $chada_travel_input], MINUTE_IN_SECONDS);
            wp_safe_redirect(self::page_url($chada_travel_input['tour_id'] > 0 ? ['edit' => $chada_travel_input['tour_id']] : []));
            exit;
        }
        $chada_travel_image_state = null;
        $chada_travel_selected_image_id = (int) $chada_travel_result['clean']['feature_image_attachment_id'];
        $chada_travel_current_image_id = (int) ($chada_travel_existing_tour['chada_travel_feature_image_attachment_id'] ?? 0);
        try {
            $chada_travel_image_state = CHADA_TRAVEL_Tour_Featured_Image_Service::prepare_for_tour(
                $chada_travel_selected_image_id,
                $chada_travel_current_image_id,
                (string) $chada_travel_result['clean']['tour_name']
            );
            if (is_array($chada_travel_image_state)) {
                $chada_travel_result['clean']['feature_image_attachment_id'] = (int) $chada_travel_image_state['attachment_id'];
            }
        } catch (\Throwable $chada_travel_exception) {
            $chada_travel_result['errors']['feature_image_attachment_id'] = $chada_travel_exception->getMessage();
            set_transient(self::FORM_STATE_PREFIX . get_current_user_id(), ['errors' => $chada_travel_result['errors'], 'input' => $chada_travel_input], MINUTE_IN_SECONDS);
            wp_safe_redirect(self::page_url($chada_travel_input['tour_id'] > 0 ? ['edit' => $chada_travel_input['tour_id']] : []));
            exit;
        }
        try {
            $chada_travel_event_meta = [];
            $chada_travel_old_slug = (string) ($chada_travel_existing_tour['chada_travel_tour_slug'] ?? '');
            $chada_travel_new_slug = (string) ($chada_travel_result['clean']['tour_slug'] ?? '');
            if ($chada_travel_existing_tour && $chada_travel_old_slug !== $chada_travel_new_slug) {
                $chada_travel_event_meta = ['old_slug' => $chada_travel_old_slug, 'new_slug' => $chada_travel_new_slug];
            }
            CHADA_TRAVEL_Tour_Repository::save(
                $wpdb,
                $chada_travel_result['clean'],
                (string) ($chada_travel_result['clean']['currency'] ?? CHADA_TRAVEL_Config::TOUR_DEFAULT_CURRENCY),
                get_current_user_id(),
                null,
                $chada_travel_event_meta
            );
            if (is_array($chada_travel_image_state)) {
                CHADA_TRAVEL_Tour_Featured_Image_Service::finalize($chada_travel_image_state);
            }
        } catch (\Throwable $chada_travel_exception) {
            if (is_array($chada_travel_image_state)) {
                CHADA_TRAVEL_Tour_Featured_Image_Service::rollback($chada_travel_image_state);
            }
            $chada_travel_tour_id = (int) ($chada_travel_input['tour_id'] ?? 0);
            $chada_travel_error_message = $chada_travel_exception->getMessage() !== ''
                ? $chada_travel_exception->getMessage() : __('The Tour could not be saved. Please try again.', 'chada-travel');
            error_log(sprintf(
                '[chada-travel] Tour save failed. tour_id=%d operation=admin_save error=%s',
                $chada_travel_tour_id,
                $chada_travel_error_message
            ));
            /* translators: %s: technical save failure message for an authorized administrator. */
            $chada_travel_error_notice = sprintf(__('The Tour could not be saved: %s', 'chada-travel'), $chada_travel_error_message);
            set_transient(self::FORM_STATE_PREFIX . get_current_user_id(), [
                'errors' => ['save' => $chada_travel_error_notice],
                'input' => $chada_travel_input,
            ], MINUTE_IN_SECONDS);
            wp_safe_redirect(self::page_url($chada_travel_tour_id > 0 ? ['edit' => $chada_travel_tour_id] : []));
            exit;
        }
        wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'saved']));
        exit;
    }

    public static function handle_duplicate(): void {
        self::guard();
        check_admin_referer(self::DUPLICATE_ACTION);
        $chada_travel_source_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('tour_id', 0));
        global $wpdb;
        try {
            $chada_travel_duplicate = CHADA_TRAVEL_Tour_Duplicate_Service::duplicate($wpdb, $chada_travel_source_id, get_current_user_id());
        } catch (\Throwable $chada_travel_exception) {
            wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'duplicate_error']));
            exit;
        }
        wp_safe_redirect(self::page_url([
            'edit' => (int) ($chada_travel_duplicate['chada_travel_tour_id'] ?? 0),
            'chada_travel_tour_notice' => 'duplicated',
        ]));
        exit;
    }

    public static function handle_toggle(): void {
        self::guard();
        $chada_travel_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('tour_id', 0));
        check_admin_referer(self::TOGGLE_ACTION . '_' . $chada_travel_id);
        global $wpdb;
        $chada_travel_tour = CHADA_TRAVEL_Tour_Repository::find_by_id($wpdb, $chada_travel_id);
        if ($chada_travel_tour) {
            CHADA_TRAVEL_Tour_Repository::set_active($wpdb, $chada_travel_tour, empty($chada_travel_tour['chada_travel_is_active']), get_current_user_id());
        }
        wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'updated']));
        exit;
    }

    public static function handle_delete(): void {
        self::guard();
        $chada_travel_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('tour_id', 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $chada_travel_id);
        global $wpdb;
        $chada_travel_tour = CHADA_TRAVEL_Tour_Repository::find_by_id($wpdb, $chada_travel_id);
        if (!$chada_travel_tour || !empty($chada_travel_tour['chada_travel_is_active'])) {
            wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'delete_error']));
            exit;
        }
        try {
            CHADA_TRAVEL_Tour_Repository::delete_archived($wpdb, $chada_travel_tour, get_current_user_id());
        } catch (\Throwable $chada_travel_exception) {
            wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'delete_error']));
            exit;
        }
        wp_safe_redirect(self::page_url(['chada_travel_tour_notice' => 'deleted']));
        exit;
    }

    public static function handle_download(): void {
        self::guard();
        $chada_travel_tour_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('tour_id', 0));
        $chada_travel_attachment_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('attachment_id', 0));
        check_admin_referer(CHADA_TRAVEL_Tour_Download_Service::admin_nonce_action($chada_travel_tour_id, $chada_travel_attachment_id));
        global $wpdb;
        CHADA_TRAVEL_Tour_Download_Service::handle_admin($wpdb, $chada_travel_tour_id, $chada_travel_attachment_id);
    }

    private static function guard(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Tours.', 'chada-travel'));
        }
    }

    /** @return array<string, mixed> */
    private static function input(): array {
        $chada_travel_dates = [];
        foreach (CHADA_TRAVEL_Config::get_request_post_array('travel_dates') as $chada_travel_date) {
            if (!is_array($chada_travel_date)) {
                continue;
            }
            $chada_travel_dates[] = [
                'date_id' => CHADA_TRAVEL_Config::sanitize_request_int($chada_travel_date['date_id'] ?? 0),
                'start_date' => CHADA_TRAVEL_Config::sanitize_request_text($chada_travel_date['start_date'] ?? ''),
                'end_date' => CHADA_TRAVEL_Config::sanitize_request_text($chada_travel_date['end_date'] ?? ''),
            ];
        }
        $chada_travel_id_list = static function ($chada_travel_values): array {
            return array_values(array_map(
                [CHADA_TRAVEL_Config::class, 'sanitize_request_int'],
                is_array($chada_travel_values) ? $chada_travel_values : []
            ));
        };
        return [
            'tour_id' => CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('tour_id', 0)),
            'tour_slug' => CHADA_TRAVEL_Config::sanitize_request_post('tour_slug'),
            'feature_image_attachment_id' => CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('feature_image_attachment_id', 0)),
            'is_featured' => CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_post('is_featured')) !== '' ? 1 : 0,
            'tour_name' => CHADA_TRAVEL_Config::sanitize_request_post('tour_name'),
            'description' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('description'),
            'trip_includes' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('trip_includes'),
            'trip_excludes' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('trip_excludes'),
            'basic_visa_requirements' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('basic_visa_requirements'),
            'itinerary' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('itinerary'),
            'booking_conditions' => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('booking_conditions'),
            'tour_code' => CHADA_TRAVEL_Config::sanitize_request_post('tour_code'),
            'currency' => CHADA_TRAVEL_Config::sanitize_request_post('currency'),
            'price' => CHADA_TRAVEL_Config::sanitize_request_post('price'),
            'travel_dates' => $chada_travel_dates,
            'destination_ids' => $chada_travel_id_list(CHADA_TRAVEL_Config::get_request_post_array('destination_ids')),
            'type_ids' => $chada_travel_id_list(CHADA_TRAVEL_Config::get_request_post_array('type_ids')),
            'downloadable_file_ids' => $chada_travel_id_list(CHADA_TRAVEL_Config::get_request_post_array('downloadable_file_ids')),
        ];
    }

    private static function valid_feature_image(int $chada_travel_attachment_id): bool {
        return CHADA_TRAVEL_Media_Validator::is_valid_attachment($chada_travel_attachment_id, CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES);
    }

    /** @param list<int> $chada_travel_attachment_ids */
    private static function downloadable_files_error(array $chada_travel_attachment_ids): string {
        $chada_travel_rules = CHADA_TRAVEL_Config::get_upload_rules()['documents'] ?? [];
        $chada_travel_allowed_mimes = (array) ($chada_travel_rules['mime_types'] ?? []);
        $chada_travel_max_bytes = (int) ($chada_travel_rules['max_bytes'] ?? 0);
        foreach ($chada_travel_attachment_ids as $chada_travel_attachment_id) {
            if (!CHADA_TRAVEL_Media_Validator::is_valid_attachment($chada_travel_attachment_id, $chada_travel_allowed_mimes)) {
                return 'Select only PDF, JPEG/JPG, or PNG files allowed by Documents & Uploads settings.';
            }
            if (function_exists('get_attached_file')) {
                $chada_travel_path = get_attached_file($chada_travel_attachment_id);
                $chada_travel_size = is_string($chada_travel_path) && is_file($chada_travel_path) ? (int) filesize($chada_travel_path) : 0;
                if ($chada_travel_max_bytes > 0 && $chada_travel_size > $chada_travel_max_bytes) {
                    return 'Each Downloadable File must be within the configured maximum file size.';
                }
            }
        }
        return '';
    }

    /** @return array<string, mixed> */
    private static function consume_form_state(): array {
        $chada_travel_key = self::FORM_STATE_PREFIX . get_current_user_id();
        $chada_travel_state = get_transient($chada_travel_key);
        delete_transient($chada_travel_key);
        return is_array($chada_travel_state) ? $chada_travel_state : [];
    }

    private static function render_notice(): string {
        $chada_travel_notice = self::query_string('chada_travel_tour_notice');
        $chada_travel_message = $chada_travel_notice === 'saved' ? __('Tour saved.', 'chada-travel')
            : ($chada_travel_notice === 'duplicated' ? __('Tour duplicated. Review the new Tour and save any changes.', 'chada-travel')
            : ($chada_travel_notice === 'duplicate_error' ? __('The Tour could not be duplicated.', 'chada-travel')
            : ($chada_travel_notice === 'deleted' ? __('Archived Tour deleted.', 'chada-travel')
            : ($chada_travel_notice === 'delete_error' ? __('Only an archived Tour can be deleted.', 'chada-travel') : __('Tour status updated.', 'chada-travel')))));
        return CHADA_TRAVEL_Template::render('admin/tours/notice', [
            'chada_travel_notice' => $chada_travel_notice,
            'chada_travel_message' => $chada_travel_message,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_args */
    private static function page_url(array $chada_travel_args = []): string {
        return add_query_arg(array_merge(['page' => self::MENU_SLUG], $chada_travel_args), admin_url('admin.php'));
    }

    private static function query_string(string $chada_travel_key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing/filter value.
        return CHADA_TRAVEL_Config::sanitize_request_get($chada_travel_key);
    }

    private static function query_int(string $chada_travel_key): int {
        return absint(self::query_string($chada_travel_key));
    }

    private static function query_bool(string $chada_travel_key): bool {
        return self::query_string($chada_travel_key) === '1';
    }

    private static function format_date(string $chada_travel_date): string {
        $chada_travel_parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $chada_travel_date);
        return $chada_travel_parsed ? $chada_travel_parsed->format('M. j, Y') : $chada_travel_date;
    }

    /** @param list<array<string, mixed>> $chada_travel_dates */
    private static function calculate_duration(array $chada_travel_dates): string {
        return CHADA_TRAVEL_Tour_Duration::format($chada_travel_dates);
    }
}
