<?php
/**
 * Minimum capability-protected administrator screen for the Visa Countries catalog.
 *
 * `chada_travel_country_fees` remains the settings-backed source for current country configuration. The database table mirrors
 * those values for relational checkout snapshots. This administrator surface manages add/edit/search/sort/availability,
 * full details, and guide/checklist Media Library associations.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Visa_Countries {
    public const MENU_SLUG = 'chada-travel-visa-countries';
    private const CAPABILITY = 'manage_chada_travel_visa_settings';
    private const SAVE_ACTION = 'chada_travel_save_country';
    private const TOGGLE_ACTION = 'chada_travel_toggle_country_status';
    private const MEDIA_NAME_MAX_LENGTH = 40;

    public static function register(): void {
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handle_save']);
        add_action('admin_post_' . self::TOGGLE_ACTION, [self::class, 'handle_toggle']);
    }

    public static function enqueue_assets(string $chada_travel_hook): void {
        if (!str_contains($chada_travel_hook, self::MENU_SLUG)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_editor();
        wp_enqueue_script(
            'chada-travel-admin-visa-countries',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-admin-visa-countries.js',
            [],
            CHADA_TRAVEL_VERSION,
            true
        );
        wp_enqueue_style(
            'chada-travel-admin-visa-countries',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-admin-visa-countries.css',
            [],
            CHADA_TRAVEL_VERSION
        );
        $chada_travel_settings  = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_countries = CHADA_TRAVEL_Country_Validator::sort_by_order(
            (array) $chada_travel_settings['chada_travel_country_fees']
        );
        $chada_travel_countries = array_map(
            static fn(array $chada_travel_country): array => self::add_media_preview_data($chada_travel_country, $chada_travel_settings),
            $chada_travel_countries
        );
        wp_localize_script('chada-travel-admin-visa-countries', 'chadaTravelVisaCountries', [
            'countries' => $chada_travel_countries,
            'strings'   => [
                'confirmCancel' => __('Discard the unsaved country changes?', 'chada-travel'),
                'confirmRemove' => __('Remove this file association from the country?', 'chada-travel'),
                'noFileSelected' => __('No file selected', 'chada-travel'),
                'pdfLabel'       => __('PDF document', 'chada-travel'),
                'notAvailable'   => __('Not available', 'chada-travel'),
            ],
            'pdfIconUrl' => self::get_pdf_icon_url(),
        ]);
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Countries.', 'chada-travel'));
        }

        $chada_travel_settings   = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_countries  = CHADA_TRAVEL_Country_Validator::sort_by_order((array) $chada_travel_settings['chada_travel_country_fees']);
        $chada_travel_currency   = CHADA_TRAVEL_Config::get_currency_code($chada_travel_settings);
        $chada_travel_form_state = self::consume_form_state();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,Generic.Files.LineLength.TooLong -- read-only notice flag.
        $chada_travel_notice = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('chada_travel_country_notice'));

        CHADA_TRAVEL_Template::output('admin/visa-countries/page-header');
        CHADA_TRAVEL_Template::output('admin/visa-countries/notices', [
            'chada_travel_notice'     => $chada_travel_notice,
            'chada_travel_has_errors' => !empty($chada_travel_form_state['errors']),
        ]);
        self::render_currency_notice($chada_travel_currency);

        self::render_form($chada_travel_form_state);
        self::render_filters();
        self::render_table($chada_travel_countries, $chada_travel_settings);

        echo '</div>';
    }

    /** Displays the one operational currency used for current country fees and new checkout transactions. */
    private static function render_currency_notice(string $chada_travel_currency): void {
        /* translators: %s: current General Settings currency code. */
        $chada_travel_message = __('All new Visa Country fees and checkout transactions use General Currency %s. After changing General Currency, review every numeric country fee; amounts are not converted automatically.', 'chada-travel'); // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded
        CHADA_TRAVEL_Template::output('admin/visa-countries/currency-notice', [
            'chada_travel_message' => sprintf($chada_travel_message, $chada_travel_currency),
        ]);
    }

    /** @param array{errors?: array<string, string>, input?: array<string, mixed>} $chada_travel_form_state */
    private static function render_form(array $chada_travel_form_state): void {
        $chada_travel_errors = $chada_travel_form_state['errors'] ?? [];
        $chada_travel_input  = $chada_travel_form_state['input'] ?? [];
        $chada_travel_reopen = (bool) $chada_travel_errors;
        $chada_travel_original_code = (string) ($chada_travel_input['original_code'] ?? '');
        $chada_travel_metadata = self::get_country_metadata($chada_travel_original_code, CHADA_TRAVEL_Config::get_settings());

        $chada_travel_fields_html = self::build_field_html(
            'name',
            'chada-travel-country-name',
            __('Country name', 'chada-travel'),
            'text',
            (string) ($chada_travel_input['name'] ?? ''),
            $chada_travel_errors['name'] ?? ''
        );
        $chada_travel_fields_html .= self::build_textarea_field_html(
            'full_details',
            'chada-travel-country-full-details',
            __('Full Details', 'chada-travel'),
            (string) ($chada_travel_input['full_details'] ?? ''),
            $chada_travel_errors['full_details'] ?? ''
        );
        $chada_travel_fields_html .= self::build_media_field_html(
            'guide_attachment_id',
            'chada-travel-country-guide',
            __('Step-by-Step Guide', 'chada-travel'),
            (int) ($chada_travel_input['guide_attachment_id'] ?? 0),
            $chada_travel_errors['guide_attachment_id'] ?? '',
            'image'
        );
        $chada_travel_fields_html .= self::build_media_field_html(
            'checklist_attachment_id',
            'chada-travel-country-documents-checklist',
            __('Documents Checklist', 'chada-travel'),
            (int) ($chada_travel_input['checklist_attachment_id'] ?? 0),
            $chada_travel_errors['checklist_attachment_id'] ?? '',
            'checklist'
        );
        $chada_travel_fields_html .= self::build_field_html(
            'code',
            'chada-travel-country-code',
            __('ISO code', 'chada-travel'),
            'text',
            (string) ($chada_travel_input['code'] ?? ''),
            $chada_travel_errors['code'] ?? '',
            ['maxlength' => '2', 'placeholder' => 'US']
        );
        $chada_travel_fields_html .= self::build_field_html(
            'processing_fee',
            'chada-travel-country-fee',
            __('Processing fee', 'chada-travel'),
            'number',
            (string) ($chada_travel_input['processing_fee'] ?? ''),
            $chada_travel_errors['processing_fee'] ?? '',
            ['min' => '0', 'step' => '0.01', 'placeholder' => '0.00']
        );
        $chada_travel_fields_html .= self::build_field_html(
            'checklist_version',
            'chada-travel-country-checklist',
            __('Checklist version', 'chada-travel'),
            'text',
            (string) ($chada_travel_input['checklist_version'] ?? ''),
            $chada_travel_errors['checklist_version'] ?? '',
            ['placeholder' => 'US-2026.1']
        );

        CHADA_TRAVEL_Template::output('admin/visa-countries/form', [
            'chada_travel_reopen'        => $chada_travel_reopen,
            'chada_travel_action_url'    => admin_url('admin-post.php'),
            'chada_travel_save_action'   => self::SAVE_ACTION,
            'chada_travel_original_code' => $chada_travel_original_code,
            'chada_travel_created_at'    => $chada_travel_metadata['created_at'],
            'chada_travel_updated_at'    => $chada_travel_metadata['updated_at'],
            'chada_travel_show_metadata' => $chada_travel_original_code !== '',
            'chada_travel_nonce_html'    => wp_nonce_field(self::SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_fields_html'   => $chada_travel_fields_html,
        ]);
    }

    /** @param array<string, string> $chada_travel_attributes */
    private static function build_field_html(
        string $chada_travel_name,
        string $chada_travel_id,
        string $chada_travel_label,
        string $chada_travel_type,
        string $chada_travel_value,
        string $chada_travel_error,
        array $chada_travel_attributes = []
    ): string {
        $chada_travel_attribute_html = '';
        foreach ($chada_travel_attributes as $chada_travel_key => $chada_travel_attribute_value) {
            $chada_travel_attribute_html .= ' ' . esc_attr($chada_travel_key) . '="' . esc_attr($chada_travel_attribute_value) . '"';
        }
        return CHADA_TRAVEL_Template::render('admin/visa-countries/field', [
            'chada_travel_id'             => $chada_travel_id,
            'chada_travel_label'          => $chada_travel_label,
            'chada_travel_type'           => $chada_travel_type,
            // Not "chada_travel_name": CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter would EXTR_SKIP-shadow it.
            'chada_travel_field_name'     => $chada_travel_name,
            'chada_travel_value'          => $chada_travel_value,
            'chada_travel_error'          => $chada_travel_error,
            'chada_travel_attribute_html' => $chada_travel_attribute_html,
        ]);
    }

    private static function build_textarea_field_html(
        string $chada_travel_name,
        string $chada_travel_id,
        string $chada_travel_label,
        string $chada_travel_value,
        string $chada_travel_error
    ): string {
        return CHADA_TRAVEL_Template::render('admin/visa-countries/textarea-field', [
            'chada_travel_id'         => $chada_travel_id,
            'chada_travel_label'      => $chada_travel_label,
            // Not "chada_travel_name": CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter would EXTR_SKIP-shadow it.
            'chada_travel_field_name' => $chada_travel_name,
            'chada_travel_value'      => $chada_travel_value,
            'chada_travel_error'      => $chada_travel_error,
            'chada_travel_max_length' => CHADA_TRAVEL_Country_Validator::MAX_FULL_DETAILS_LENGTH,
        ]);
    }

    private static function build_media_field_html(
        string $chada_travel_name,
        string $chada_travel_id,
        string $chada_travel_label,
        int $chada_travel_attachment_id,
        string $chada_travel_error,
        string $chada_travel_kind
    ): string {
        $chada_travel_title = $chada_travel_attachment_id > 0 && function_exists('get_the_title')
            ? (string) get_the_title($chada_travel_attachment_id) : '';
        $chada_travel_preview = self::get_media_preview_data($chada_travel_attachment_id);
        if ($chada_travel_preview['is_pdf']) {
            $chada_travel_preview_alt = __('PDF document', 'chada-travel');
        } else {
            /* translators: %s: media field label. */
            $chada_travel_preview_alt = sprintf(__('%s thumbnail', 'chada-travel'), $chada_travel_label);
        }
        return CHADA_TRAVEL_Template::render('admin/visa-countries/media-field', [
            // Not "chada_travel_name": CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter would EXTR_SKIP-shadow it.
            'chada_travel_field_name'    => $chada_travel_name,
            'chada_travel_id'            => $chada_travel_id,
            'chada_travel_label'         => $chada_travel_label,
            'chada_travel_attachment_id' => $chada_travel_attachment_id,
            'chada_travel_error'         => $chada_travel_error,
            'chada_travel_kind'          => $chada_travel_kind,
            'chada_travel_title'         => self::truncate_media_name($chada_travel_title),
            'chada_travel_preview_url'   => $chada_travel_preview['preview_url'],
            'chada_travel_preview_alt'   => $chada_travel_preview_alt,
            'chada_travel_instruction'   => $chada_travel_kind === 'image' ? self::get_guide_image_instruction()
                : __('Choose one PDF, JPEG/JPG, or PNG file from the Media Library.', 'chada-travel'),
        ]);
    }

    /** Returns the current dynamic Step-by-Step Guide processing instructions. */
    private static function get_guide_image_instruction(): string {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_width = (int) ($chada_travel_settings['chada_travel_visa_guide_image_width']
            ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_WIDTH);
        $chada_travel_height = (int) ($chada_travel_settings['chada_travel_visa_guide_image_height']
            ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_HEIGHT);
        $chada_travel_max_mb = min(
            CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_MAX_MB,
            (int) ($chada_travel_settings['chada_travel_visa_guide_image_max_mb']
                ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_MAX_MB)
        );
        $chada_travel_limit_text = sprintf(
            /* translators: %d: maximum file size in MB. */
            __('Choose one JPEG/JPG or PNG image. Maximum %d MB.', 'chada-travel'),
            $chada_travel_max_mb
        );
        $chada_travel_processing_text = sprintf(
            /* translators: 1: target width, 2: target height. */
            __('Image: %1$d × %2$d pixels; centered crop; featured-image-{slug}; suffix on collision.', 'chada-travel'),
            $chada_travel_width,
            $chada_travel_height
        );
        return $chada_travel_limit_text . ' ' . $chada_travel_processing_text;
    }

    /** @return array{preview_url: string, is_pdf: bool} */
    private static function get_media_preview_data(int $chada_travel_attachment_id): array {
        $chada_travel_preview = ['preview_url' => '', 'is_pdf' => false];
        if ($chada_travel_attachment_id <= 0) {
            return $chada_travel_preview;
        }

        $chada_travel_mime = function_exists('get_post_mime_type')
            ? (string) get_post_mime_type($chada_travel_attachment_id) : '';
        if ($chada_travel_mime === 'application/pdf') {
            $chada_travel_preview['is_pdf'] = true;
            $chada_travel_preview['preview_url'] = self::get_pdf_icon_url();
            return $chada_travel_preview;
        }
        if (function_exists('wp_get_attachment_image_url')) {
            $chada_travel_thumbnail_url = wp_get_attachment_image_url($chada_travel_attachment_id, 'thumbnail');
            if (is_string($chada_travel_thumbnail_url)) {
                $chada_travel_preview['preview_url'] = $chada_travel_thumbnail_url;
            }
        }
        return $chada_travel_preview;
    }

    private static function get_pdf_icon_url(): string {
        return defined('CHADA_TRAVEL_PLUGIN_URL')
            ? CHADA_TRAVEL_PLUGIN_URL . 'assets/images/chada-travel-pdf-icon.svg' : '';
    }

    /**
     * @param array<string, mixed> $chada_travel_country
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, mixed>
     */
    private static function add_media_preview_data(array $chada_travel_country, array $chada_travel_settings): array {
        $chada_travel_guide_preview = self::get_media_preview_data((int) ($chada_travel_country['guide_attachment_id'] ?? 0));
        $chada_travel_checklist_preview = self::get_media_preview_data(
            (int) ($chada_travel_country['checklist_attachment_id'] ?? 0)
        );
        $chada_travel_country['guide_thumbnail_url'] = !$chada_travel_guide_preview['is_pdf']
            ? $chada_travel_guide_preview['preview_url'] : '';
        $chada_travel_country['guide_is_pdf'] = $chada_travel_guide_preview['is_pdf'];
        $chada_travel_country['guide_title'] = self::get_attachment_title(
            (int) ($chada_travel_country['guide_attachment_id'] ?? 0)
        );
        $chada_travel_country['checklist_thumbnail_url'] = !$chada_travel_checklist_preview['is_pdf']
            ? $chada_travel_checklist_preview['preview_url'] : '';
        $chada_travel_country['checklist_pdf_icon_url'] = $chada_travel_checklist_preview['is_pdf']
            ? $chada_travel_checklist_preview['preview_url'] : '';
        $chada_travel_country['checklist_is_pdf'] = $chada_travel_checklist_preview['is_pdf'];
        $chada_travel_country['checklist_title'] = self::get_attachment_title(
            (int) ($chada_travel_country['checklist_attachment_id'] ?? 0)
        );
        $chada_travel_metadata = self::get_country_metadata((string) ($chada_travel_country['code'] ?? ''), $chada_travel_settings);
        $chada_travel_country['created_at'] = $chada_travel_metadata['created_at'];
        $chada_travel_country['updated_at'] = $chada_travel_metadata['updated_at'];
        return $chada_travel_country;
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array{created_at: string, updated_at: string}
     */
    private static function get_country_metadata(string $chada_travel_code, array $chada_travel_settings): array {
        $chada_travel_not_available = __('Not available', 'chada-travel');
        $chada_travel_metadata = ['created_at' => $chada_travel_not_available, 'updated_at' => $chada_travel_not_available];
        if ($chada_travel_code === '') {
            return $chada_travel_metadata;
        }

        global $wpdb;
        $chada_travel_row = CHADA_TRAVEL_Country_Repository::find_by_code($wpdb, $chada_travel_code);
        if (!is_array($chada_travel_row)) {
            return $chada_travel_metadata;
        }
        $chada_travel_created_at = CHADA_TRAVEL_Config::format_utc_datetime(
            (string) ($chada_travel_row['chada_travel_created_at'] ?? ''),
            $chada_travel_settings
        );
        $chada_travel_updated_at = CHADA_TRAVEL_Config::format_utc_datetime(
            (string) ($chada_travel_row['chada_travel_updated_at'] ?? ''),
            $chada_travel_settings
        );
        return [
            'created_at' => $chada_travel_created_at !== '' ? $chada_travel_created_at : $chada_travel_not_available,
            'updated_at' => $chada_travel_updated_at !== '' ? $chada_travel_updated_at : $chada_travel_not_available,
        ];
    }

    private static function get_attachment_title(int $chada_travel_attachment_id): string {
        if ($chada_travel_attachment_id > 0 && function_exists('get_the_title')) {
            return (string) get_the_title($chada_travel_attachment_id);
        }
        return '';
    }

    /** Limits the visible media name while keeping the attachment title unchanged. */
    private static function truncate_media_name(string $chada_travel_name): string {
        $chada_travel_name = trim($chada_travel_name);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($chada_travel_name) <= self::MEDIA_NAME_MAX_LENGTH) {
                return $chada_travel_name;
            }
            return mb_substr($chada_travel_name, 0, self::MEDIA_NAME_MAX_LENGTH - 1) . '…';
        }
        if (strlen($chada_travel_name) <= self::MEDIA_NAME_MAX_LENGTH) {
            return $chada_travel_name;
        }
        return substr($chada_travel_name, 0, self::MEDIA_NAME_MAX_LENGTH - 3) . '...';
    }

    private static function render_filters(): void {
        CHADA_TRAVEL_Template::output('admin/visa-countries/filters');
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_countries
     * @param array<string, mixed>       $chada_travel_settings
     */
    private static function render_table(array $chada_travel_countries, array $chada_travel_settings): void {
        $chada_travel_rows_html = '';
        foreach ($chada_travel_countries as $chada_travel_country) {
            $chada_travel_rows_html .= self::build_row_html($chada_travel_country, $chada_travel_settings);
        }
        $chada_travel_total = count($chada_travel_countries);
        /* translators: 1: visible record count, 2: total record count. */
        $chada_travel_count_text = sprintf(__('Showing %1$d of %2$d records', 'chada-travel'), $chada_travel_total, $chada_travel_total);
        CHADA_TRAVEL_Template::output('admin/visa-countries/table', [
            'chada_travel_rows_html'  => $chada_travel_rows_html,
            'chada_travel_count_text' => $chada_travel_count_text,
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_country
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function build_row_html(array $chada_travel_country, array $chada_travel_settings): string {
        $chada_travel_code      = (string) $chada_travel_country['code'];
        $chada_travel_name      = (string) $chada_travel_country['name'];
        $chada_travel_is_active = !empty($chada_travel_country['is_active']);
        $chada_travel_status    = $chada_travel_is_active ? 'active' : 'archived';
        $chada_travel_search    = strtolower($chada_travel_name . ' ' . $chada_travel_code);
        $chada_travel_order     = (string) $chada_travel_country['sort_order'];

        $chada_travel_fee_currency = (string) ($chada_travel_country['currency'] ?? CHADA_TRAVEL_Config::get_currency_code($chada_travel_settings));
        $chada_travel_fee_formatted = CHADA_TRAVEL_Config::format_money(
            (float) $chada_travel_country['processing_fee'],
            $chada_travel_fee_currency,
            $chada_travel_settings
        );

        global $wpdb;
        $chada_travel_country_id   = CHADA_TRAVEL_Country_Repository::find_id_by_code($wpdb, $chada_travel_code);
        $chada_travel_requirements = CHADA_TRAVEL_Requirement_Repository::get_active_for_country(
            $wpdb,
            $chada_travel_country_id,
            (string) $chada_travel_country['checklist_version']
        );

        $chada_travel_guide_ready = CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            (int) ($chada_travel_country['guide_attachment_id'] ?? 0),
            CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
        );
        $chada_travel_resource_ready = $chada_travel_guide_ready && CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            (int) ($chada_travel_country['checklist_attachment_id'] ?? 0),
            CHADA_TRAVEL_Media_Validator::CHECKLIST_MIME_TYPES
        );
        $chada_travel_frontend_ready = $chada_travel_resource_ready
            && trim((string) ($chada_travel_country['full_details'] ?? '')) !== '';
        $chada_travel_guide_preview = self::get_media_preview_data((int) ($chada_travel_country['guide_attachment_id'] ?? 0));

        $chada_travel_toggle_nonce_action = self::TOGGLE_ACTION . '_' . $chada_travel_code;

        return CHADA_TRAVEL_Template::render('admin/visa-countries/row', [
            'chada_travel_search'             => $chada_travel_search,
            'chada_travel_status'             => $chada_travel_status,
            // Not "chada_travel_name": CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter would EXTR_SKIP-shadow it.
            'chada_travel_country_name'       => $chada_travel_name,
            'chada_travel_fee'                => (string) $chada_travel_country['processing_fee'],
            'chada_travel_order'              => $chada_travel_order,
            'chada_travel_code'               => $chada_travel_code,
            'chada_travel_fee_formatted'      => $chada_travel_fee_formatted,
            'chada_travel_checklist_version'  => (string) $chada_travel_country['checklist_version'],
            'chada_travel_requirements_count' => count($chada_travel_requirements),
            'chada_travel_guide_ready'        => $chada_travel_guide_ready,
            'chada_travel_guide_thumbnail_url' => $chada_travel_guide_ready && !$chada_travel_guide_preview['is_pdf']
                ? $chada_travel_guide_preview['preview_url'] : '',
            'chada_travel_frontend_ready'     => $chada_travel_frontend_ready,
            'chada_travel_is_active'          => $chada_travel_is_active,
            'chada_travel_toggle_action'      => self::TOGGLE_ACTION,
            'chada_travel_toggle_url'         => admin_url('admin-post.php'),
            'chada_travel_toggle_nonce_html'  => wp_nonce_field($chada_travel_toggle_nonce_action, '_wpnonce', true, false),
        ]);
    }

    public static function handle_save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Countries.', 'chada-travel'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $chada_travel_input = [
            'name'              => CHADA_TRAVEL_Config::sanitize_request_post('name'),
            'code'              => CHADA_TRAVEL_Config::sanitize_request_post('code'),
            'processing_fee'    => CHADA_TRAVEL_Config::sanitize_request_post('processing_fee'),
            'checklist_version' => CHADA_TRAVEL_Config::sanitize_request_post('checklist_version'),
            'full_details'      => CHADA_TRAVEL_Config::sanitize_request_post_rich_text('full_details'),
            'guide_attachment_id' => CHADA_TRAVEL_Config::sanitize_request_int(
                CHADA_TRAVEL_Config::sanitize_request_post('guide_attachment_id', 0)
            ),
            'checklist_attachment_id' => CHADA_TRAVEL_Config::sanitize_request_int(
                CHADA_TRAVEL_Config::sanitize_request_post('checklist_attachment_id', 0)
            ),
        ];
        $chada_travel_original_code = CHADA_TRAVEL_Config::sanitize_request_post('original_code');

        $chada_travel_settings  = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_countries = (array) $chada_travel_settings['chada_travel_country_fees'];
        $chada_travel_current = null;
        foreach ($chada_travel_countries as $chada_travel_country) {
            if ((string) ($chada_travel_country['code'] ?? '') === $chada_travel_original_code) {
                $chada_travel_current = $chada_travel_country;
                break;
            }
        }
        $chada_travel_is_active = $chada_travel_current !== null && !empty($chada_travel_current['is_active']);
        $chada_travel_result = CHADA_TRAVEL_Country_Validator::validate(
            $chada_travel_input,
            $chada_travel_countries,
            $chada_travel_original_code,
            $chada_travel_is_active
        );
        if ((int) $chada_travel_input['guide_attachment_id'] > 0 && !CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            (int) $chada_travel_input['guide_attachment_id'],
            CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
        )) {
            $chada_travel_result['errors']['guide_attachment_id'] = 'Select a valid JPEG/JPG or PNG Media Library image.';
        }
        if ((int) $chada_travel_input['checklist_attachment_id'] > 0 && !CHADA_TRAVEL_Media_Validator::is_valid_attachment(
            (int) $chada_travel_input['checklist_attachment_id'],
            CHADA_TRAVEL_Media_Validator::CHECKLIST_MIME_TYPES
        )) {
            $chada_travel_result['errors']['checklist_attachment_id'] = 'Select a valid PDF, JPEG/JPG, or PNG Media file.';
        }

        if ($chada_travel_result['errors']) {
            self::stash_form_state([
                'errors' => $chada_travel_result['errors'],
                'input'  => array_merge($chada_travel_input, ['original_code' => $chada_travel_original_code]),
            ]);
            wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_country_notice=error'));
            exit;
        }

        $chada_travel_guide_state = null;
        $chada_travel_selected_guide_id = (int) $chada_travel_result['clean']['guide_attachment_id'];
        $chada_travel_current_guide_id = (int) ($chada_travel_current['guide_attachment_id'] ?? 0);
        try {
            $chada_travel_guide_state = CHADA_TRAVEL_Visa_Guide_Image_Service::prepare_for_country(
                $chada_travel_selected_guide_id,
                $chada_travel_current_guide_id,
                (string) $chada_travel_result['clean']['name']
            );
            if (is_array($chada_travel_guide_state)) {
                $chada_travel_result['clean']['guide_attachment_id'] = (int) $chada_travel_guide_state['attachment_id'];
            }
        } catch (\Throwable $chada_travel_exception) {
            self::stash_form_state([
                'errors' => ['guide_attachment_id' => $chada_travel_exception->getMessage()],
                'input'  => array_merge($chada_travel_input, ['original_code' => $chada_travel_original_code]),
            ]);
            wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_country_notice=error'));
            exit;
        }

        $chada_travel_updated = CHADA_TRAVEL_Country_Validator::upsert(
            $chada_travel_countries,
            $chada_travel_result['clean'],
            $chada_travel_original_code,
            (string) $chada_travel_settings['chada_travel_currency']
        );
        foreach ($chada_travel_updated as &$chada_travel_country) {
            $chada_travel_country['currency'] = CHADA_TRAVEL_Config::get_currency_code($chada_travel_settings);
        }
        unset($chada_travel_country);
        global $wpdb;
        try {
            update_option('chada_travel_country_fees', $chada_travel_updated);
            if ((array) get_option('chada_travel_country_fees', []) != $chada_travel_updated) {
                throw new \RuntimeException('The Visa Country option could not be saved.');
            }
            CHADA_TRAVEL_Country_Repository::sync_all($wpdb, $chada_travel_updated);
            $chada_travel_saved_code = (string) $chada_travel_result['clean']['code'];
            CHADA_TRAVEL_Country_Repository::log_event(
                $wpdb,
                CHADA_TRAVEL_Country_Repository::find_id_by_code($wpdb, $chada_travel_saved_code),
                $chada_travel_original_code === '' ? 'chada_travel_country_created' : 'chada_travel_country_updated',
                ['code' => $chada_travel_saved_code, 'active' => $chada_travel_is_active]
            );
            if (is_array($chada_travel_guide_state)) {
                CHADA_TRAVEL_Visa_Guide_Image_Service::finalize($chada_travel_guide_state);
            }
        } catch (\Throwable $chada_travel_exception) {
            update_option('chada_travel_country_fees', $chada_travel_countries);
            try {
                CHADA_TRAVEL_Country_Repository::sync_all($wpdb, $chada_travel_countries);
            } catch (\Throwable $chada_travel_rollback_exception) {
                // The original option remains restored even if the relational mirror cannot be restored here.
            }
            if (is_array($chada_travel_guide_state)) {
                CHADA_TRAVEL_Visa_Guide_Image_Service::rollback($chada_travel_guide_state);
            }
            wp_die(esc_html__('The Visa Country could not be saved. Please try again.', 'chada-travel'));
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_country_notice=saved'));
        exit;
    }

    public static function handle_toggle(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Countries.', 'chada-travel'));
        }
        $chada_travel_code = CHADA_TRAVEL_Config::sanitize_request_post('code');
        check_admin_referer(self::TOGGLE_ACTION . '_' . $chada_travel_code);

        $chada_travel_countries = (array) CHADA_TRAVEL_Config::get_settings()['chada_travel_country_fees'];
        $chada_travel_current   = null;
        foreach ($chada_travel_countries as $chada_travel_country) {
            if ((string) ($chada_travel_country['code'] ?? '') === $chada_travel_code) {
                $chada_travel_current = $chada_travel_country;
                break;
            }
        }
        if ($chada_travel_current !== null) {
            $chada_travel_activating = empty($chada_travel_current['is_active']);
            if ($chada_travel_activating && (!CHADA_TRAVEL_Country_Validator::is_resource_ready($chada_travel_current)
                || !CHADA_TRAVEL_Media_Validator::is_valid_attachment(
                    (int) ($chada_travel_current['guide_attachment_id'] ?? 0),
                    CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
                ) || !CHADA_TRAVEL_Media_Validator::is_valid_attachment(
                    (int) ($chada_travel_current['checklist_attachment_id'] ?? 0),
                    CHADA_TRAVEL_Media_Validator::CHECKLIST_MIME_TYPES
                ))) {
                self::stash_form_state([
                    'errors' => [
                        'full_details' => 'Complete Full Details, Guide, and Documents Checklist before activation.',
                    ],
                    'input'  => array_merge($chada_travel_current, ['original_code' => $chada_travel_code]),
                ]);
                wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_country_notice=error'));
                exit;
            }
            $chada_travel_updated = CHADA_TRAVEL_Country_Validator::set_active(
                $chada_travel_countries,
                $chada_travel_code,
                empty($chada_travel_current['is_active'])
            );
            update_option('chada_travel_country_fees', $chada_travel_updated);
            global $wpdb;
            CHADA_TRAVEL_Country_Repository::sync_all($wpdb, $chada_travel_updated);
            CHADA_TRAVEL_Country_Repository::log_event(
                $wpdb,
                CHADA_TRAVEL_Country_Repository::find_id_by_code($wpdb, $chada_travel_code),
                $chada_travel_activating ? 'chada_travel_country_activated' : 'chada_travel_country_archived',
                ['code' => $chada_travel_code]
            );
        }

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '&chada_travel_country_notice=status'));
        exit;
    }

    /** @param array{errors: array<string, string>, input: array<string, mixed>} $chada_travel_state */
    private static function stash_form_state(array $chada_travel_state): void {
        set_transient('chada_travel_country_form_state_' . get_current_user_id(), $chada_travel_state, 60);
    }

    /** @return array{errors?: array<string, string>, input?: array<string, mixed>} */
    private static function consume_form_state(): array {
        $chada_travel_key   = 'chada_travel_country_form_state_' . get_current_user_id();
        $chada_travel_state = get_transient($chada_travel_key);
        delete_transient($chada_travel_key);
        return is_array($chada_travel_state) ? $chada_travel_state : [];
    }
}
