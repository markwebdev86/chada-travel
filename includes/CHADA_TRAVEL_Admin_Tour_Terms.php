<?php
/** Administrator managers for Tour Destinations and Tour Types. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

final class CHADA_TRAVEL_Admin_Tour_Terms {
    public const DESTINATIONS_MENU_SLUG = 'chada-travel-tour-destinations';
    public const TYPES_MENU_SLUG = 'chada-travel-tour-types';
    private const CAPABILITY = 'manage_chada_travel_tours';
    private const SAVE_ACTION = 'chada_travel_save_tour_term';
    private const TOGGLE_ACTION = 'chada_travel_toggle_tour_term_status';
    private const DELETE_ACTION = 'chada_travel_delete_tour_term';
    private const FORM_STATE_PREFIX = 'chada_travel_tour_term_form_';

    public static function register(): void {
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handle_save']);
        add_action('admin_post_' . self::TOGGLE_ACTION, [self::class, 'handle_toggle']);
        add_action('admin_post_' . self::DELETE_ACTION, [self::class, 'handle_delete']);
    }

    public static function enqueue_assets(string $chada_travel_hook): void {
        if (!str_contains($chada_travel_hook, 'chada-travel-tour-destinations') && !str_contains($chada_travel_hook, 'chada-travel-tour-types')) {
            return;
        }
        wp_enqueue_script('chada-travel-admin-tour-terms', CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-admin-tour-terms.js', [], CHADA_TRAVEL_VERSION, true);
        wp_enqueue_style('chada-travel-admin-tours', CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-admin-tours.css', ['chada-travel-admin'], CHADA_TRAVEL_VERSION);
    }

    public static function render_destinations_page(): void {
        self::render(self::DESTINATIONS_MENU_SLUG, CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION, __('Tour Destinations', 'chada-travel'));
    }

    public static function render_types_page(): void {
        self::render(self::TYPES_MENU_SLUG, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE, __('Tour Types', 'chada-travel'));
    }

    private static function render(string $chada_travel_menu_slug, string $chada_travel_taxonomy, string $chada_travel_label): void {
        self::guard();
        self::guard_types_access($chada_travel_taxonomy);
        global $wpdb;
        $chada_travel_edit_id = self::query_int('edit');
        $chada_travel_selected = $chada_travel_edit_id > 0 ? CHADA_TRAVEL_Tour_Term_Repository::find_by_id($wpdb, $chada_travel_edit_id) : null;
        if ($chada_travel_selected && $chada_travel_selected['chada_travel_taxonomy'] !== $chada_travel_taxonomy) {
            $chada_travel_selected = null;
        }
        $chada_travel_is_archived = is_array($chada_travel_selected) && empty($chada_travel_selected['chada_travel_is_active']);
        $chada_travel_tour_count = $chada_travel_selected ? CHADA_TRAVEL_Tour_Term_Repository::count_tours($wpdb, (int) $chada_travel_selected['chada_travel_tour_term_id']) : 0;
        $chada_travel_state = self::consume_form_state();
        $chada_travel_form_term = $chada_travel_selected ?: [];
        if (!empty($chada_travel_state['input'])) {
            $chada_travel_form_term = [
                'chada_travel_tour_term_id' => (int) ($chada_travel_state['input']['term_id'] ?? 0),
                'chada_travel_taxonomy' => $chada_travel_taxonomy,
                'chada_travel_term_name' => (string) ($chada_travel_state['input']['name'] ?? ''),
            ];
        }
        $chada_travel_search = self::query_string('s');
        $chada_travel_status = self::query_string('status');
        $chada_travel_status = in_array($chada_travel_status, ['active', 'archived'], true) ? $chada_travel_status : 'active';
        $chada_travel_paged = max(1, self::query_int('paged'));
        $chada_travel_rows = CHADA_TRAVEL_Tour_Term_Repository::search_grid($wpdb, $chada_travel_taxonomy, $chada_travel_search, $chada_travel_status, $chada_travel_paged);
        $chada_travel_total = CHADA_TRAVEL_Tour_Term_Repository::count_grid($wpdb, $chada_travel_taxonomy, $chada_travel_search, $chada_travel_status);
        $chada_travel_row_offset = ($chada_travel_paged - 1) * CHADA_TRAVEL_Tour_Term_Repository::GRID_PER_PAGE;
        $chada_travel_records = [];
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_records[] = [
                'id' => (int) $chada_travel_row['chada_travel_tour_term_id'],
                'name' => (string) $chada_travel_row['chada_travel_term_name'],
                'slug' => (string) $chada_travel_row['chada_travel_term_slug'],
                'active' => !empty($chada_travel_row['chada_travel_is_active']),
                'tourCount' => (int) ($chada_travel_row['chada_travel_tour_count'] ?? 0),
            ];
        }
        if ($chada_travel_selected && !array_filter($chada_travel_records, static fn(array $chada_travel_record): bool => $chada_travel_record['id'] === $chada_travel_edit_id)) {
            $chada_travel_records[] = [
                'id' => $chada_travel_edit_id,
                'name' => (string) $chada_travel_selected['chada_travel_term_name'],
                'slug' => (string) $chada_travel_selected['chada_travel_term_slug'],
                'active' => !empty($chada_travel_selected['chada_travel_is_active']),
                'tourCount' => CHADA_TRAVEL_Tour_Term_Repository::count_tours($wpdb, $chada_travel_edit_id),
            ];
        }
        wp_localize_script('chada-travel-admin-tour-terms', 'chadaTravelTourTerms', [
            'records' => $chada_travel_records,
            'taxonomy' => $chada_travel_taxonomy,
            'selectedTermId' => (int) ($chada_travel_form_term['chada_travel_tour_term_id'] ?? 0),
            'strings' => [
                'add' => __('Add Record', 'chada-travel'),
                'edit' => __('Edit Record', 'chada-travel'),
                'view' => __('View Record', 'chada-travel'),
                'save' => __('Save Record', 'chada-travel'),
                'cancel' => __('Cancel', 'chada-travel'),
            ],
        ]);
        $chada_travel_state_term_id = (int) ($chada_travel_state['input']['term_id'] ?? 0);
        $chada_travel_query_mode = self::query_bool('view') && $chada_travel_edit_id > 0 ? 'view' : (($chada_travel_edit_id > 0 || $chada_travel_state_term_id > 0) ? 'edit' : 'add');
        $chada_travel_open_form = $chada_travel_edit_id > 0 || !empty($chada_travel_state['errors']);

        $chada_travel_notice_html = '';
        $chada_travel_notice = self::query_string('chada_travel_term_notice');
        if (in_array($chada_travel_notice, ['error', 'delete_error'], true) || !empty($chada_travel_state['errors'])) {
            $chada_travel_error_message = $chada_travel_notice === 'delete_error'
                ? __('Only an archived, unused record can be deleted.', 'chada-travel')
                : implode(' ', (array) ($chada_travel_state['errors'] ?? []));
            $chada_travel_notice_html = '<div class="notice notice-error"><p>' . esc_html($chada_travel_error_message) . '</p></div>';
        } elseif ($chada_travel_notice !== '') {
            $chada_travel_notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html($chada_travel_notice === 'deleted' ? __('Archived record deleted.', 'chada-travel') : __('Record saved or status updated.', 'chada-travel')) . '</p></div>';
        }
        $chada_travel_form_html = CHADA_TRAVEL_Template::render('admin/tour-terms/form', [
            'chada_travel_menu_slug' => $chada_travel_menu_slug,
            'chada_travel_taxonomy' => $chada_travel_taxonomy,
            'chada_travel_label' => $chada_travel_label,
            'chada_travel_id' => (int) ($chada_travel_form_term['chada_travel_tour_term_id'] ?? 0),
            'chada_travel_term_name' => (string) ($chada_travel_form_term['chada_travel_term_name'] ?? ''),
            'chada_travel_mode' => $chada_travel_query_mode,
            'chada_travel_open' => $chada_travel_open_form,
            'chada_travel_is_archived' => $chada_travel_is_archived,
            'chada_travel_tour_count' => $chada_travel_tour_count,
            'chada_travel_errors' => (array) ($chada_travel_state['errors'] ?? []),
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_save_action' => self::SAVE_ACTION,
            'chada_travel_delete_action' => self::DELETE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_delete_nonce_html' => wp_nonce_field(self::DELETE_ACTION . '_' . (int) ($chada_travel_form_term['chada_travel_tour_term_id'] ?? 0), '_wpnonce', true, false),
        ]);
        $chada_travel_rows_html = '';
        foreach ($chada_travel_rows as $chada_travel_row_index => $chada_travel_row) {
            $chada_travel_row_id = (int) $chada_travel_row['chada_travel_tour_term_id'];
            $chada_travel_rows_html .= CHADA_TRAVEL_Template::render('admin/tour-terms/row', [
                'chada_travel_row_number' => $chada_travel_row_offset + $chada_travel_row_index + 1,
                'chada_travel_id' => $chada_travel_row_id,
                'chada_travel_term_name' => (string) $chada_travel_row['chada_travel_term_name'],
                'chada_travel_slug' => (string) $chada_travel_row['chada_travel_term_slug'],
                'chada_travel_is_active' => !empty($chada_travel_row['chada_travel_is_active']),
                'chada_travel_tour_count' => (int) ($chada_travel_row['chada_travel_tour_count'] ?? 0),
                'chada_travel_toggle_action' => self::TOGGLE_ACTION,
                'chada_travel_toggle_url' => admin_url('admin-post.php'),
                'chada_travel_taxonomy' => $chada_travel_taxonomy,
                'chada_travel_tours_url' => add_query_arg(
                    ['page' => CHADA_TRAVEL_Admin_Tours::MENU_SLUG, $chada_travel_taxonomy => $chada_travel_row_id],
                    admin_url('admin.php')
                ),
                'chada_travel_nonce_html' => wp_nonce_field(self::TOGGLE_ACTION . '_' . $chada_travel_row_id, '_wpnonce', true, false),
                'chada_travel_delete_action' => self::DELETE_ACTION,
                'chada_travel_delete_nonce_html' => wp_nonce_field(self::DELETE_ACTION . '_' . $chada_travel_row_id, '_wpnonce', true, false),
            ]);
        }
        $chada_travel_filters_html = CHADA_TRAVEL_Template::render('admin/tour-terms/filters', [
            'chada_travel_menu_slug' => $chada_travel_menu_slug,
            'chada_travel_search' => $chada_travel_search,
            'chada_travel_status' => $chada_travel_status,
        ]);
        $chada_travel_table_html = CHADA_TRAVEL_Template::render('admin/tour-terms/table', [
            'chada_travel_label' => $chada_travel_label,
            'chada_travel_rows_html' => $chada_travel_rows_html,
        ]);
        CHADA_TRAVEL_Template::output('admin/tour-terms/page', [
            'chada_travel_label' => $chada_travel_label,
            'chada_travel_menu_slug' => $chada_travel_menu_slug,
            'chada_travel_form_html' => $chada_travel_form_html,
            'chada_travel_notice_html' => $chada_travel_notice_html,
            'chada_travel_rows_html' => $chada_travel_rows_html,
            'chada_travel_filters_html' => $chada_travel_filters_html,
            'chada_travel_table_html' => $chada_travel_table_html,
            'chada_travel_search' => $chada_travel_search,
            'chada_travel_status' => $chada_travel_status,
            'chada_travel_delete_modal_html' => self::render_delete_modal(),
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_term
     * @param array<string, string> $chada_travel_errors
     */
    // @phpstan-ignore-next-line
    private static function render_form(string $chada_travel_menu_slug, string $chada_travel_taxonomy, string $chada_travel_label, array $chada_travel_term, bool $chada_travel_open, string $chada_travel_mode, array $chada_travel_errors): void {
        $chada_travel_id = (int) ($chada_travel_term['chada_travel_tour_term_id'] ?? 0);
        $chada_travel_readonly = $chada_travel_mode === 'view' ? ' readonly disabled' : '';
        $chada_travel_title = $chada_travel_id > 0 ? ($chada_travel_mode === 'view' ? __('View Record', 'chada-travel') : __('Edit Record', 'chada-travel')) : __('Add Record', 'chada-travel');
        echo '<div class="chada-travel-term-form-card" id="chada-travel-term-form" data-chada-travel-term-form-mode="' . esc_attr($chada_travel_mode) . '"' . ($chada_travel_open ? '' : ' hidden') . '><h2 id="chada-travel-term-form-title">' . esc_html($chada_travel_title) . '</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" id="chada-travel-term-form-fields"><input type="hidden" name="action" value="' . esc_attr(self::SAVE_ACTION) . '"><input type="hidden" name="term_id" id="chada-travel-term-id" value="' . esc_attr((string) $chada_travel_id) . '"><input type="hidden" name="taxonomy" value="' . esc_attr($chada_travel_taxonomy) . '">';
        wp_nonce_field(self::SAVE_ACTION);
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- readonly/disabled is a code-controlled attribute fragment.
        echo '<p><label for="chada-travel-term-name"><strong>' . esc_html($chada_travel_label) . '</strong></label><br><input class="regular-text" id="chada-travel-term-name" name="name" maxlength="100" value="' . esc_attr((string) ($chada_travel_term['chada_travel_term_name'] ?? '')) . '" required' . $chada_travel_readonly . '></p><p><button type="submit" class="button button-primary" id="chada-travel-save-tour-term">' . esc_html__('Save Record', 'chada-travel') . '</button> <button type="button" class="button" id="chada-travel-edit-tour-term" hidden>' . esc_html__('Edit Record', 'chada-travel') . '</button> <button type="button" class="button" id="chada-travel-cancel-tour-term">' . esc_html__('Cancel', 'chada-travel') . '</button></p></form></div>';
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public static function handle_save(): void {
        self::guard();
        check_admin_referer(self::SAVE_ACTION);
        $chada_travel_taxonomy = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_post('taxonomy'));
        $chada_travel_term_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('term_id', 0));
        $chada_travel_name = trim(CHADA_TRAVEL_Config::sanitize_request_post('name'));
        if (!in_array($chada_travel_taxonomy, [CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE], true)) {
            wp_die(esc_html__('Invalid Tour taxonomy.', 'chada-travel'));
        }
        self::guard_types_access($chada_travel_taxonomy);
        global $wpdb;
        $chada_travel_error = '';
        if ($chada_travel_name === '') {
            $chada_travel_error = 'Enter a name for this Tour taxonomy record.';
        } else {
            $chada_travel_slug = CHADA_TRAVEL_Tour_Term_Repository::slug($chada_travel_name);
            foreach (CHADA_TRAVEL_Tour_Term_Repository::get_all($wpdb, $chada_travel_taxonomy) as $chada_travel_existing_term) {
                if ((int) $chada_travel_existing_term['chada_travel_tour_term_id'] !== $chada_travel_term_id && (string) $chada_travel_existing_term['chada_travel_term_slug'] === $chada_travel_slug) {
                    $chada_travel_error = 'Another Tour taxonomy record already uses this name.';
                    break;
                }
            }
        }
        if ($chada_travel_error !== '') {
            set_transient(self::FORM_STATE_PREFIX . get_current_user_id(), ['errors' => ['name' => $chada_travel_error], 'input' => ['term_id' => $chada_travel_term_id, 'taxonomy' => $chada_travel_taxonomy, 'name' => $chada_travel_name]], MINUTE_IN_SECONDS);
            wp_safe_redirect(self::page_url($chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION ? self::DESTINATIONS_MENU_SLUG : self::TYPES_MENU_SLUG, ['chada_travel_term_notice' => 'error']));
            exit;
        }
        CHADA_TRAVEL_Tour_Term_Repository::save($wpdb, ['term_id' => $chada_travel_term_id, 'name' => $chada_travel_name], $chada_travel_taxonomy, get_current_user_id());
        wp_safe_redirect(self::page_url($chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION ? self::DESTINATIONS_MENU_SLUG : self::TYPES_MENU_SLUG, ['chada_travel_term_notice' => 'saved']));
        exit;
    }

    public static function handle_toggle(): void {
        self::guard();
        $chada_travel_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('term_id', 0));
        check_admin_referer(self::TOGGLE_ACTION . '_' . $chada_travel_id);
        global $wpdb;
        $chada_travel_term = CHADA_TRAVEL_Tour_Term_Repository::find_by_id($wpdb, $chada_travel_id);
        self::guard_types_access((string) ($chada_travel_term['chada_travel_taxonomy'] ?? ''));
        if ($chada_travel_term) {
            CHADA_TRAVEL_Tour_Term_Repository::set_active($wpdb, $chada_travel_term, empty($chada_travel_term['chada_travel_is_active']), get_current_user_id());
        }
        $chada_travel_slug = ($chada_travel_term['chada_travel_taxonomy'] ?? '') === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION ? self::DESTINATIONS_MENU_SLUG : self::TYPES_MENU_SLUG;
        wp_safe_redirect(self::page_url($chada_travel_slug, ['chada_travel_term_notice' => 'updated']));
        exit;
    }

    public static function handle_delete(): void {
        self::guard();
        $chada_travel_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('term_id', 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $chada_travel_id);
        global $wpdb;
        $chada_travel_term = CHADA_TRAVEL_Tour_Term_Repository::find_by_id($wpdb, $chada_travel_id);
        $chada_travel_taxonomy = (string) ($chada_travel_term['chada_travel_taxonomy'] ?? '');
        self::guard_types_access($chada_travel_taxonomy);
        $chada_travel_menu_slug = $chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION
            ? self::DESTINATIONS_MENU_SLUG : self::TYPES_MENU_SLUG;
        if (!$chada_travel_term || !in_array($chada_travel_taxonomy, [CHADA_TRAVEL_Tour_Repository::TAXONOMY_DESTINATION, CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE], true)) {
            wp_safe_redirect(self::page_url($chada_travel_menu_slug, ['chada_travel_term_notice' => 'delete_error']));
            exit;
        }
        try {
            CHADA_TRAVEL_Tour_Term_Repository::delete_archived($wpdb, $chada_travel_term, get_current_user_id());
        } catch (\Throwable $chada_travel_exception) {
            wp_safe_redirect(self::page_url($chada_travel_menu_slug, ['chada_travel_term_notice' => 'delete_error']));
            exit;
        }
        wp_safe_redirect(self::page_url($chada_travel_menu_slug, ['chada_travel_term_notice' => 'deleted']));
        exit;
    }

    private static function render_delete_modal(): string {
        return CHADA_TRAVEL_Template::render('admin/tour-terms/delete-modal');
    }

    private static function guard(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Tour taxonomy records.', 'chada-travel'));
        }
    }

    /** Blocks Tour Type management unless the independent Pro extension is active. */
    private static function guard_types_access(string $chada_travel_taxonomy): void {
        if ($chada_travel_taxonomy === CHADA_TRAVEL_Tour_Repository::TAXONOMY_TYPE && !CHADA_TRAVEL_Extension_Manager::is_pro_active()) {
            wp_die(esc_html__('Tour Type management requires the active Pro extension.', 'chada-travel'));
        }
    }

    /** @param array<string, mixed> $chada_travel_args */
    private static function page_url(string $chada_travel_slug, array $chada_travel_args = []): string {
        return add_query_arg(array_merge(['page' => $chada_travel_slug], $chada_travel_args), admin_url('admin.php'));
    }

    /** @return array<string, mixed> */
    private static function consume_form_state(): array {
        $chada_travel_key = self::FORM_STATE_PREFIX . get_current_user_id();
        $chada_travel_state = get_transient($chada_travel_key);
        delete_transient($chada_travel_key);
        return is_array($chada_travel_state) ? $chada_travel_state : [];
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
}
