<?php
/**
 * Chada Travel - Agency Manager Settings admin screen (`admin.php?page=chada-travel-settings`): tab routing, navigation,
 * rendering, permission checks, nonce-protected saving, redirect notices, and page-specific asset loading.
 *
 * Tabs are driven by an internal registry (see get_tabs()) so additional settings tabs can be added later
 * without rewriting this controller, navigation, or asset-loading structure - only a new registry entry and a
 * render/save callback pair are needed. General is the first/default tab; Payment Method is the second.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Settings {
    public const MENU_SLUG = 'chada-travel-settings';
    private const CAPABILITY = 'manage_chada_travel_visa_settings';
    private const GENERAL_TAB = 'general';
    private const PAYMENT_TAB = 'payment-method';
    private const EMAIL_TAB = 'email';
    private const PAGES_LINKS_TAB = 'pages-links';
    private const POLICIES_CONSENT_TAB = 'policies-consent';
    private const DOCUMENTS_UPLOADS_TAB = 'documents-uploads';
    private const TOURS_TAB = 'tours';
    private const VISA_COUNTRIES_TAB = 'visa-countries';
    private const DEFAULT_TAB = self::GENERAL_TAB;
    private const GENERAL_SAVE_ACTION = 'chada_travel_save_general_settings';
    private const SAVE_ACTION = 'chada_travel_save_payment_settings';
    private const EMAIL_SAVE_ACTION = 'chada_travel_save_email_settings';
    private const TEST_EMAIL_ACTION = 'chada_travel_send_test_email';
    private const PAGES_LINKS_SAVE_ACTION = 'chada_travel_save_pages_links_settings';
    private const POLICIES_CONSENT_SAVE_ACTION = 'chada_travel_save_policies_consent_settings';
    private const DOCUMENTS_UPLOADS_SAVE_ACTION = 'chada_travel_save_documents_uploads_settings';
    private const TOURS_SAVE_ACTION = 'chada_travel_save_tour_settings';
    private const VISA_COUNTRIES_SAVE_ACTION = 'chada_travel_save_visa_countries_settings';

    public static function register(): void {
        add_action('admin_post_' . self::GENERAL_SAVE_ACTION, [self::class, 'handle_save_general']);
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handle_save']);
        add_action('admin_post_' . self::EMAIL_SAVE_ACTION, [self::class, 'handle_save_email']);
        add_action('admin_post_' . self::TEST_EMAIL_ACTION, [self::class, 'handle_send_test_email']);
        add_action('admin_post_' . self::PAGES_LINKS_SAVE_ACTION, [self::class, 'handle_save_pages_links']);
        add_action(
            'admin_post_' . self::POLICIES_CONSENT_SAVE_ACTION,
            [self::class, 'handle_save_policies_consent']
        );
        add_action(
            'admin_post_' . self::DOCUMENTS_UPLOADS_SAVE_ACTION,
            [self::class, 'handle_save_documents_uploads']
        );
        add_action('admin_post_' . self::TOURS_SAVE_ACTION, [self::class, 'handle_save_tour_settings']);
        add_action(
            'admin_post_' . self::VISA_COUNTRIES_SAVE_ACTION,
            [self::class, 'handle_save_visa_countries_settings']
        );
    }

    /** Loads wp.media and this page's own progressive-enhancement script only on the Settings page itself. */
    public static function enqueue_assets(string $chada_travel_hook): void {
        if (!str_contains($chada_travel_hook, self::MENU_SLUG)) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'chada-travel-admin-settings',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-admin-settings.js',
            [],
            CHADA_TRAVEL_Plugin::public_asset_version('assets/js/chada-travel-admin-settings.js'),
            true
        );
        wp_localize_script('chada-travel-admin-settings', 'chadaTravelAdminSettings', [
            'mediaTitle'        => __('Select Digital Wallet QR Code', 'chada-travel'),
            'mediaButton'       => __('Use this image', 'chada-travel'),
            'digitalWalletPlaceholderUrl' => CHADA_TRAVEL_Config::get_digital_wallet_qr_url([]),
            'logoMediaTitle'    => __('Select Company Logo', 'chada-travel'),
            'logoMediaButton'   => __('Use this image', 'chada-travel'),
            'logoSelectLabel'   => __('Select Logo', 'chada-travel'),
            'logoReplaceLabel'  => __('Replace Logo', 'chada-travel'),
            'copiedMessage'     => __('Webhook URL copied.', 'chada-travel'),
        ]);
    }

    /** @return array<string, array{label: string, capability: string, render: callable}> */
    private static function get_tabs(): array {
        $chada_travel_tabs = [
            self::GENERAL_TAB => [
                'label'      => __('General', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_general_tab'],
            ],
            self::PAYMENT_TAB => [
                'label'      => __('Payment Method', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_payment_method_tab'],
            ],
            self::EMAIL_TAB => [
                'label'      => __('Email', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_email_tab'],
            ],
            self::PAGES_LINKS_TAB => [
                'label'      => __('Pages & Links', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_pages_links_tab'],
            ],
            self::POLICIES_CONSENT_TAB => [
                'label'      => __('Policies & Consent', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_policies_consent_tab'],
            ],
            self::DOCUMENTS_UPLOADS_TAB => [
                'label'      => __('Documents & Uploads', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_documents_uploads_tab'],
            ],
            self::TOURS_TAB => [
                'label'      => __('Tours', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_tours_tab'],
            ],
            self::VISA_COUNTRIES_TAB => [
                'label'      => __('Visa Countries', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_visa_countries_tab'],
            ],
        ];
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_settings_tabs', $chada_travel_tabs)
            : $chada_travel_tabs;
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }

        $chada_travel_tabs = self::get_tabs();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.
        $chada_travel_requested_tab = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('tab', self::DEFAULT_TAB));
        $chada_travel_tab = array_key_exists($chada_travel_requested_tab, $chada_travel_tabs) ? $chada_travel_requested_tab : self::DEFAULT_TAB;

        CHADA_TRAVEL_Template::output('admin/settings/shared/page-header');
        self::render_notices($chada_travel_tab);
        self::render_tablist($chada_travel_tabs, $chada_travel_tab);
        echo '<div class="chada-travel-tabpanel">';
        ($chada_travel_tabs[$chada_travel_tab]['render'])();
        echo '</div>';
        echo '</div>';
    }

    /** @param array<string, array{label: string, capability: string, render: callable}> $chada_travel_tabs */
    private static function render_tablist(array $chada_travel_tabs, string $chada_travel_current_tab): void {
        $chada_travel_tab_list = [];
        foreach ($chada_travel_tabs as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_tab_list[] = [
                'key'      => $chada_travel_key,
                'label'    => $chada_travel_definition['label'],
                'href'     => admin_url('admin.php?page=' . self::MENU_SLUG . '&tab=' . $chada_travel_key),
                'selected' => $chada_travel_key === $chada_travel_current_tab,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/settings/shared/tablist', ['chada_travel_tabs' => $chada_travel_tab_list]);
    }

    private static function render_notices(string $chada_travel_tab): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, Generic.Files.LineLength.TooLong -- read-only notice flag.
        $chada_travel_notice = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('chada_travel_settings_notice'));
        $chada_travel_messages = [
            'saved'   => ['success', __('Payment settings saved.', 'chada-travel')],
            'invalid' => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'general_saved'   => ['success', __('General Settings saved.', 'chada-travel')],
            'general_invalid' => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'booking_workflow_saved' => ['success', __('Booking workflow settings saved.', 'chada-travel')],
            'email_saved'     => ['success', __('Email settings saved.', 'chada-travel')],
            'email_invalid'   => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'test_email_sent'    => ['success', __('Test email sent.', 'chada-travel')],
            'test_email_failed'  => ['error', __(
                'Test email could not be sent. Check your email delivery configuration.',
                'chada-travel'
            )],
            'test_email_invalid' => ['error', __('Enter a valid Test Email Recipient address.', 'chada-travel')],
            'pages_links_saved'   => ['success', __('Pages & Links settings saved.', 'chada-travel')],
            'pages_links_invalid' => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'policies_consent_saved'   => ['success', __('Policies & Consent settings saved.', 'chada-travel')],
            'policies_consent_invalid' => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'documents_uploads_saved'   => ['success', __('Documents & Uploads settings saved.', 'chada-travel')],
            'documents_uploads_invalid' => ['error', __('Correct the highlighted fields and save again.', 'chada-travel')],
            'tours_saved'   => ['success', __('Tour Settings saved.', 'chada-travel')],
            'tours_invalid' => ['error', __('Correct the highlighted Tour Settings fields and save again.', 'chada-travel')],
            'visa_countries_saved'   => ['success', __('Visa Countries settings saved.', 'chada-travel')],
            'visa_countries_invalid' => ['error', __(
                'Correct the highlighted Visa Countries fields and save again.',
                'chada-travel'
            )],
        ];
        if (isset($chada_travel_messages[$chada_travel_notice])) {
            [$chada_travel_type, $chada_travel_message] = $chada_travel_messages[$chada_travel_notice];
            CHADA_TRAVEL_Template::output('admin/settings/shared/notice', [
                'chada_travel_type'    => $chada_travel_type,
                'chada_travel_message' => $chada_travel_message,
            ]);
        }

        $chada_travel_errors = self::consume_form_errors($chada_travel_tab);
        if ($chada_travel_errors) {
            CHADA_TRAVEL_Template::output('admin/settings/shared/form-errors', [
                'chada_travel_intro'  => self::form_errors_intro($chada_travel_tab),
                'chada_travel_errors' => $chada_travel_errors,
            ]);
        }
    }

    // ============================================================ GENERAL TAB

    public static function render_general_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        CHADA_TRAVEL_Template::output('admin/settings/general/tab', [
            'chada_travel_action_url'           => admin_url('admin-post.php'),
            'chada_travel_save_action'          => self::GENERAL_SAVE_ACTION,
            'chada_travel_nonce_html'           => wp_nonce_field(self::GENERAL_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_company_profile_html' => self::build_company_profile_section_html($chada_travel_settings),
            'chada_travel_contact_html'         => self::build_company_contact_section_html($chada_travel_settings),
            'chada_travel_regional_html'        => self::build_regional_section_html($chada_travel_settings),
            'chada_travel_reference_html'       => self::build_reference_section_html($chada_travel_settings),
            'chada_travel_availability_html'    => self::build_availability_section_html($chada_travel_settings),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_company_profile_section_html(array $chada_travel_settings): string {
        $chada_travel_fields_html = self::build_text_field_html(
            'chada-travel-company-name',
            'chada_travel_company_name',
            __('Company Name', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_name'],
            true,
            'text',
            __('Public business/display name shown to customers.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-legal-name',
            'chada_travel_company_legal_name',
            __('Legal Company Name', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_legal_name'],
            false,
            'text',
            __('Optional; used only when the registered legal entity name differs from Company Name.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_logo_field_html($chada_travel_settings);
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-website-url',
            'chada_travel_company_website_url',
            __('Company Website URL', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_website_url'],
            true,
            'url',
            __('Prefilled from this site\'s address; edit if the corporate website is elsewhere.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_country_field_html(
            'chada-travel-company-country',
            'chada_travel_company_country',
            __('Company Country', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_country'],
            __('Select the company country used for regional defaults and customer-facing context.', 'chada-travel'),
            true
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-tagline',
            'chada_travel_company_tagline',
            __('Company Tagline', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_tagline'],
            false,
            'text',
            __('Optional short customer-facing description.', 'chada-travel')
        );

        return CHADA_TRAVEL_Template::render('admin/settings/general/company-profile', [
            'chada_travel_fields_html' => $chada_travel_fields_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_logo_field_html(array $chada_travel_settings): string {
        $chada_travel_attachment_id = (int) $chada_travel_settings['chada_travel_company_logo_attachment_id'];
        $chada_travel_logo_url      = self::get_company_logo_preview_url($chada_travel_attachment_id);

        return CHADA_TRAVEL_Template::render('admin/settings/general/logo-field', [
            'chada_travel_logo_url'      => $chada_travel_logo_url,
            'chada_travel_has_logo'      => $chada_travel_attachment_id > 0,
            'chada_travel_attachment_id' => $chada_travel_attachment_id,
        ]);
    }

    private static function get_company_logo_preview_url(int $chada_travel_attachment_id): string {
        if ($chada_travel_attachment_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $chada_travel_url = wp_get_attachment_image_url($chada_travel_attachment_id, 'medium');
            if ($chada_travel_url) {
                return $chada_travel_url;
            }
        }
        return '';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_company_contact_section_html(array $chada_travel_settings): string {
        $chada_travel_fields_html = self::build_text_field_html(
            'chada-travel-company-support-email',
            'chada_travel_company_support_email',
            __('Support Email', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_support_email'],
            true,
            'email',
            __('Customer-visible support address; separate from the transactional sender address.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-phone',
            'chada_travel_company_phone',
            __('Company Phone', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_phone']
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-mobile',
            'chada_travel_company_mobile',
            __('Company Mobile', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_mobile']
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-address-line-1',
            'chada_travel_company_address_line_1',
            __('Address Line 1', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_address_line_1'],
            true
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-address-line-2',
            'chada_travel_company_address_line_2',
            __('Address Line 2', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_address_line_2'],
            false,
            'text',
            __('Optional.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-city',
            'chada_travel_company_city',
            __('City/Municipality', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_city'],
            true
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-province',
            'chada_travel_company_province',
            __('Province/Region', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_province'],
            true
        );
        $chada_travel_fields_html .= self::build_text_field_html(
            'chada-travel-company-postal-code',
            'chada_travel_company_postal_code',
            __('Postal Code', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_postal_code']
        );
        $chada_travel_fields_html .= self::build_country_field_html(
            'chada-travel-company-address-country',
            'chada_travel_company_address_country',
            __('Address Country', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_company_address_country'],
            __('Select the country for the company address.', 'chada-travel'),
            true
        );

        return CHADA_TRAVEL_Template::render('admin/settings/general/contact', [
            'chada_travel_fields_html' => $chada_travel_fields_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_regional_section_html(array $chada_travel_settings): string {
        $chada_travel_fields_html = self::build_timezone_field_html($chada_travel_settings);
        $chada_travel_fields_html .= self::build_currency_field_html($chada_travel_settings);
        $chada_travel_fields_html .= self::build_currency_display_field_html($chada_travel_settings);
        $chada_travel_fields_html .= self::build_date_format_field_html($chada_travel_settings);
        $chada_travel_fields_html .= self::build_time_format_field_html($chada_travel_settings);

        return CHADA_TRAVEL_Template::render('admin/settings/general/regional', [
            'chada_travel_fields_html' => $chada_travel_fields_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_currency_field_html(array $chada_travel_settings): string {
        $chada_travel_current = strtoupper(trim((string) (
            $chada_travel_settings['chada_travel_currency'] ?? CHADA_TRAVEL_Config::DEFAULT_CURRENCY
        )));
        $chada_travel_options_html = '';
        foreach (CHADA_TRAVEL_Config::get_currency_options_for_select($chada_travel_current) as $chada_travel_code => $chada_travel_label) {
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_code) . '" '
                . selected($chada_travel_code, $chada_travel_current, false) . '>'
                . esc_html($chada_travel_code . ' — ' . $chada_travel_label) . '</option>';
        }
        return CHADA_TRAVEL_Template::render('admin/settings/general/currency-field', [
            'chada_travel_current'      => $chada_travel_current,
            'chada_travel_options_html' => $chada_travel_options_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_timezone_field_html(array $chada_travel_settings): string {
        $chada_travel_current     = (string) $chada_travel_settings['chada_travel_company_timezone'];
        $chada_travel_has_choices = function_exists('wp_timezone_choice');

        return CHADA_TRAVEL_Template::render('admin/settings/general/timezone-field', [
            'chada_travel_current'      => $chada_travel_current,
            'chada_travel_has_choices'  => $chada_travel_has_choices,
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_timezone_choice() builds and escapes its own <option> markup.
            'chada_travel_options_html' => $chada_travel_has_choices ? wp_timezone_choice($chada_travel_current) : '',
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_currency_display_field_html(array $chada_travel_settings): string {
        $chada_travel_current = (string) $chada_travel_settings['chada_travel_currency_display'];
        $chada_travel_options = [
            'symbol'      => __('Symbol only', 'chada-travel') . ' (₱1,000.00)',
            'code'        => __('Code only', 'chada-travel') . ' (PHP 1,000.00)',
            'symbol_code' => __('Symbol + Code', 'chada-travel') . ' (₱1,000.00 PHP)',
        ];
        $chada_travel_options_html = '';
        foreach ($chada_travel_options as $chada_travel_value => $chada_travel_label) {
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_value) . '" '
                . selected($chada_travel_value, $chada_travel_current, false) . '>' . esc_html($chada_travel_label) . '</option>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/general/currency-display-field', [
            'chada_travel_options_html' => $chada_travel_options_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_date_format_field_html(array $chada_travel_settings): string {
        $chada_travel_current   = (string) $chada_travel_settings['chada_travel_date_format'];
        $chada_travel_wp_format = function_exists('get_option') ? (string) get_option('date_format', 'F j, Y') : 'F j, Y';
        $chada_travel_options_html = '';
        foreach (CHADA_TRAVEL_Config::get_date_format_options() as $chada_travel_format) {
            $chada_travel_label = $chada_travel_format === ''
                ? sprintf(
                    /* translators: %s: example date in the Company Timezone using the inherited WordPress format. */
                    __('Inherit WordPress (%s)', 'chada-travel'),
                    CHADA_TRAVEL_Config::preview_datetime_format($chada_travel_wp_format, $chada_travel_settings)
                )
                : $chada_travel_format . ' — ' . CHADA_TRAVEL_Config::preview_datetime_format($chada_travel_format, $chada_travel_settings);
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_format) . '" '
                . selected($chada_travel_format, $chada_travel_current, false) . '>' . esc_html($chada_travel_label) . '</option>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/general/date-format-field', [
            'chada_travel_options_html' => $chada_travel_options_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_time_format_field_html(array $chada_travel_settings): string {
        $chada_travel_current   = (string) $chada_travel_settings['chada_travel_time_format'];
        $chada_travel_wp_format = function_exists('get_option') ? (string) get_option('time_format', 'g:i a') : 'g:i a';
        $chada_travel_options_html = '';
        foreach (CHADA_TRAVEL_Config::get_time_format_options() as $chada_travel_format) {
            $chada_travel_label = $chada_travel_format === ''
                ? sprintf(
                    /* translators: %s: example time in the Company Timezone using the inherited WordPress format. */
                    __('Inherit WordPress (%s)', 'chada-travel'),
                    CHADA_TRAVEL_Config::preview_datetime_format($chada_travel_wp_format, $chada_travel_settings)
                )
                : $chada_travel_format . ' — ' . CHADA_TRAVEL_Config::preview_datetime_format($chada_travel_format, $chada_travel_settings);
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_format) . '" '
                . selected($chada_travel_format, $chada_travel_current, false) . '>' . esc_html($chada_travel_label) . '</option>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/general/time-format-field', [
            'chada_travel_options_html' => $chada_travel_options_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_reference_section_html(array $chada_travel_settings): string {
        $chada_travel_fields_html = self::build_prefix_field_html(
            'chada-travel-booking-id-prefix',
            'chada_travel_booking_id_prefix',
            __('Booking ID Prefix', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_booking_id_prefix'],
            __('Applies only to future Booking IDs; existing Booking IDs never change.', 'chada-travel')
        );
        $chada_travel_fields_html .= self::build_prefix_field_html(
            'chada-travel-transaction-id-prefix',
            'chada_travel_transaction_id_prefix',
            __('Transaction ID Prefix', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_transaction_id_prefix'],
            __('Applies only to future Transaction IDs; existing Transaction IDs never change.', 'chada-travel')
        );

        return CHADA_TRAVEL_Template::render('admin/settings/general/reference', [
            'chada_travel_fields_html' => $chada_travel_fields_html,
        ]);
    }

    private static function build_prefix_field_html(
        string $chada_travel_id,
        string $chada_travel_name,
        string $chada_travel_label,
        string $chada_travel_value,
        string $chada_travel_note
    ): string {
        $chada_travel_preview = CHADA_TRAVEL_Order_Repository::generate_public_id($chada_travel_value !== '' ? $chada_travel_value : 'CHADA_TRAVEL');

        return CHADA_TRAVEL_Template::render('admin/settings/general/prefix-field', [
            'chada_travel_id'          => $chada_travel_id,
            'chada_travel_field_name'  => $chada_travel_name,
            'chada_travel_label'       => $chada_travel_label,
            'chada_travel_value'       => $chada_travel_value,
            'chada_travel_note'        => $chada_travel_note,
            'chada_travel_example_label' => sprintf(
                /* translators: %s: example generated ID using the current prefix. */
                __('Example: %s', 'chada-travel'),
                $chada_travel_preview
            ),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_availability_section_html(array $chada_travel_settings): string {
        $chada_travel_status = (string) $chada_travel_settings['chada_travel_company_status'];
        $chada_travel_status_options = [
            'active'      => __('Active', 'chada-travel'),
            'maintenance' => __('Under Maintenance', 'chada-travel'),
        ];
        $chada_travel_status_options_html = '';
        foreach ($chada_travel_status_options as $chada_travel_value => $chada_travel_label) {
            $chada_travel_status_options_html .= '<label style="display:block;"><input type="radio" '
                . 'name="chada_travel_company_status" value="' . esc_attr($chada_travel_value)
                . '" data-chada-travel-company-status-option '
                . checked($chada_travel_status, $chada_travel_value, false) . '> ' . esc_html($chada_travel_label) . '</label>';
        }

        // Always rendered (never server-side hidden) so saving still works with JavaScript disabled; JavaScript
        // only progressively hides the Maintenance Message row while Company Status is Active (see
        // chada-travel-admin-settings.js) - the template must not make that row conditional either.
        return CHADA_TRAVEL_Template::render('admin/settings/general/availability', [
            'chada_travel_status_options_html' => $chada_travel_status_options_html,
            'chada_travel_maintenance_message' => (string) $chada_travel_settings['chada_travel_company_maintenance_message'],
        ]);
    }

    private static function build_country_field_html(
        string $chada_travel_id,
        string $chada_travel_name,
        string $chada_travel_label,
        string $chada_travel_value,
        string $chada_travel_description = '',
        bool $chada_travel_required = false
    ): string {
        return CHADA_TRAVEL_Template::render('admin/settings/general/country-field', [
            'chada_travel_id'          => $chada_travel_id,
            'chada_travel_field_name'  => $chada_travel_name,
            'chada_travel_label'       => $chada_travel_label,
            'chada_travel_value'       => $chada_travel_value,
            'chada_travel_description' => $chada_travel_description,
            'chada_travel_required'    => $chada_travel_required,
        ]);
    }

    private static function build_text_field_html(
        string $chada_travel_id,
        string $chada_travel_name,
        string $chada_travel_label,
        string $chada_travel_value,
        bool $chada_travel_required = false,
        string $chada_travel_type = 'text',
        string $chada_travel_description = '',
        string $chada_travel_extra_attrs = ''
    ): string {
        return CHADA_TRAVEL_Template::render('admin/settings/shared/text-field', [
            'chada_travel_id'               => $chada_travel_id,
            'chada_travel_field_name'       => $chada_travel_name,
            'chada_travel_label'            => $chada_travel_label,
            'chada_travel_value'            => $chada_travel_value,
            'chada_travel_required'         => $chada_travel_required,
            'chada_travel_type'             => $chada_travel_type,
            'chada_travel_description'      => $chada_travel_description,
            'chada_travel_extra_attrs_html' => $chada_travel_extra_attrs,
        ]);
    }

    public static function handle_save_general(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(
                esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'),
                esc_html__('Forbidden', 'chada-travel'),
                ['response' => 403]
            );
        }
        check_admin_referer(self::GENERAL_SAVE_ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload();
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_general($chada_travel_post, CHADA_TRAVEL_Config::get_settings());

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::GENERAL_TAB);
            wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'general_invalid'], self::GENERAL_TAB));
            exit;
        }

        wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'general_saved'], self::GENERAL_TAB));
        exit;
    }

    // ============================================================ PAYMENT METHOD TAB

    public static function render_payment_method_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_bank_accounts = CHADA_TRAVEL_Settings_Repository::get_bank_accounts();

        CHADA_TRAVEL_Template::output('admin/settings/payment-method/tab', [
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_save_action' => self::SAVE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_status_summary_html' => self::build_status_summary_html($chada_travel_settings),
            'chada_travel_general_html' => self::build_general_section_html($chada_travel_settings),
            'chada_travel_bank_html' => self::build_bank_section_html($chada_travel_bank_accounts),
            'chada_travel_digital_wallet_html' => self::build_digital_wallet_section_html($chada_travel_settings),
            'chada_travel_extensions_html' => function_exists('apply_filters')
                ? (string) apply_filters('chada_travel_payment_settings_html', '', $chada_travel_settings) : '',
        ]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function status_labels(): array {
        return [
            CHADA_TRAVEL_Payment_Readiness::STATUS_DISABLED           => [__('Disabled', 'chada-travel'), ''],
            CHADA_TRAVEL_Payment_Readiness::STATUS_INCOMPLETE         => [__('Incomplete', 'chada-travel'), 'is-warning'],
            CHADA_TRAVEL_Payment_Readiness::STATUS_READY              => [__('Ready', 'chada-travel'), 'is-success'],
            CHADA_TRAVEL_Payment_Readiness::STATUS_MANAGED_EXTERNALLY => [__('Managed Externally', 'chada-travel'), 'is-info'],
            CHADA_TRAVEL_Payment_Readiness::STATUS_CONNECTION_ERROR   => [__('Connection Error', 'chada-travel'), 'is-warning'],
        ];
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_status_summary_html(array $chada_travel_settings): string {
        $chada_travel_labels = self::status_labels();
        $chada_travel_rows_html = '';
        foreach (CHADA_TRAVEL_Config::get_admin_method_labels($chada_travel_settings) as $chada_travel_method => $chada_travel_label) {
            $chada_travel_status = CHADA_TRAVEL_Payment_Readiness::status($chada_travel_method, $chada_travel_settings);
            [$chada_travel_text, $chada_travel_class] = $chada_travel_labels[$chada_travel_status] ?? [$chada_travel_status, ''];
            $chada_travel_rows_html .= '<dt>' . esc_html($chada_travel_label) . '</dt><dd><span class="chada-travel-badge '
                . esc_attr($chada_travel_class) . '">' . esc_html($chada_travel_text) . '</span></dd>';
        }
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_none_ready_notice = __('No payment method is both enabled and fully configured yet. At least one payment method must be enabled and fully configured (Ready) before this tab can be saved; until then, checkout shows customers a safe "payment temporarily unavailable" notice.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/status-summary', [
            'chada_travel_rows_html'         => $chada_travel_rows_html,
            'chada_travel_has_any_ready'     => CHADA_TRAVEL_Payment_Readiness::has_any_ready_method($chada_travel_settings),
            'chada_travel_none_ready_notice' => $chada_travel_none_ready_notice,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_general_section_html(array $chada_travel_settings): string {
        $chada_travel_enabled = (array) $chada_travel_settings['chada_travel_payment_methods'];
        $chada_travel_method_labels = CHADA_TRAVEL_Config::get_admin_method_labels($chada_travel_settings);
        $chada_travel_checkboxes_html = '';
        foreach ($chada_travel_method_labels as $chada_travel_method => $chada_travel_label) {
            $chada_travel_checkboxes_html .= '<label style="display:block;"><input type="checkbox" '
                . 'name="chada_travel_payment_methods[]" value="' . esc_attr($chada_travel_method) . '" '
                . checked(in_array($chada_travel_method, $chada_travel_enabled, true), true, false)
                . '> ' . esc_html($chada_travel_label) . '</label>';
        }
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_enabled_notice = __('A method may be enabled while still Incomplete; only enabled and fully configured (Ready) methods appear at customer checkout.', 'chada-travel');

        $chada_travel_options_html = '';
        foreach (CHADA_TRAVEL_Config::get_admin_method_labels($chada_travel_settings) as $chada_travel_method => $chada_travel_label) {
            $chada_travel_options_html .= '<option value="' . esc_attr($chada_travel_method) . '" '
                . selected($chada_travel_method, $chada_travel_settings['chada_travel_default_payment_method'], false)
                . '>' . esc_html($chada_travel_label) . '</option>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/general', [
            'chada_travel_checkboxes_html' => $chada_travel_checkboxes_html,
            'chada_travel_enabled_notice'  => $chada_travel_enabled_notice,
            'chada_travel_options_html'    => $chada_travel_options_html,
        ]);
    }

    /** @param list<array<string, mixed>> $chada_travel_bank_accounts */
    private static function build_bank_section_html(array $chada_travel_bank_accounts): string {
        $chada_travel_account_count = count($chada_travel_bank_accounts);
        $chada_travel_slot_count = max(1, $chada_travel_account_count + 1);

        $chada_travel_rows_html = '';
        for ($chada_travel_index = 0; $chada_travel_index < $chada_travel_slot_count; $chada_travel_index++) {
            $chada_travel_account = $chada_travel_bank_accounts[$chada_travel_index] ?? null;
            // The first slot is always visible on a fresh install; every persisted account remains visible.
            $chada_travel_visible = $chada_travel_account !== null || ($chada_travel_index === 0 && $chada_travel_account_count === 0);
            $chada_travel_rows_html .= self::build_bank_account_row_html($chada_travel_index, $chada_travel_account, $chada_travel_visible);
        }

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/bank', [
            'chada_travel_rows_html'              => $chada_travel_rows_html,
            'chada_travel_account_count'          => $chada_travel_account_count,
        ]);
    }


    /** @param array<string, mixed>|null $chada_travel_account */
    private static function build_bank_account_row_html(
        int $chada_travel_index,
        ?array $chada_travel_account,
        bool $chada_travel_visible
    ): string {
        $chada_travel_prefix = "bank_accounts[{$chada_travel_index}]";
        $chada_travel_label          = (string) ($chada_travel_account['label'] ?? '');
        $chada_travel_id             = (string) ($chada_travel_account['id'] ?? '');
        $chada_travel_bank_name      = (string) ($chada_travel_account['bank_name'] ?? '');
        $chada_travel_account_name   = (string) ($chada_travel_account['account_name'] ?? '');
        $chada_travel_account_number = (string) ($chada_travel_account['account_number'] ?? '');
        $chada_travel_branch         = (string) ($chada_travel_account['branch'] ?? '');
        $chada_travel_account_type   = (string) ($chada_travel_account['account_type'] ?? '');
        $chada_travel_instructions   = (string) ($chada_travel_account['instructions'] ?? '');
        $chada_travel_is_active      = $chada_travel_account === null || !empty($chada_travel_account['is_active']);
        $chada_travel_sort_order     = (int) ($chada_travel_account['sort_order'] ?? ($chada_travel_index + 1));

        $chada_travel_fields_html = implode('', [
            self::build_bank_field_html($chada_travel_prefix, 'label', __('Label', 'chada-travel'), $chada_travel_label),
            self::build_bank_field_html($chada_travel_prefix, 'bank_name', __('Bank name', 'chada-travel'), $chada_travel_bank_name, true),
            self::build_bank_field_html(
                $chada_travel_prefix,
                'account_name',
                __('Account name', 'chada-travel'),
                $chada_travel_account_name,
                true
            ),
            self::build_bank_field_html(
                $chada_travel_prefix,
                'account_number',
                __('Account number', 'chada-travel'),
                $chada_travel_account_number,
                true
            ),
            self::build_bank_field_html($chada_travel_prefix, 'branch', __('Branch (optional)', 'chada-travel'), $chada_travel_branch),
            self::build_bank_field_html(
                $chada_travel_prefix,
                'account_type',
                __('Account type (optional)', 'chada-travel'),
                $chada_travel_account_type
            ),
        ]);

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/bank-account-row', [
            'chada_travel_index'        => $chada_travel_index,
            'chada_travel_visible'      => $chada_travel_visible,
            'chada_travel_prefix'       => $chada_travel_prefix,
            'chada_travel_id'           => $chada_travel_id,
            'chada_travel_sort_order'   => $chada_travel_sort_order,
            'chada_travel_fields_html'  => $chada_travel_fields_html,
            'chada_travel_instructions' => $chada_travel_instructions,
            'chada_travel_is_active'    => $chada_travel_is_active,
        ]);
    }

    private static function build_bank_field_html(
        string $chada_travel_prefix,
        string $chada_travel_field,
        string $chada_travel_label,
        string $chada_travel_value,
        bool $chada_travel_required = false
    ): string {
        $chada_travel_id = 'chada-travel-bank-' . $chada_travel_field . '-' . md5($chada_travel_prefix);
        $chada_travel_required_indicator_html = $chada_travel_required
            ? ' <span aria-hidden="true">*</span><span class="screen-reader-text">'
                . esc_html__('(required)', 'chada-travel') . '</span>'
            : '';

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/bank-field', [
            'chada_travel_id'                       => $chada_travel_id,
            'chada_travel_field_name'               => $chada_travel_prefix . '[' . $chada_travel_field . ']',
            'chada_travel_label'                    => $chada_travel_label,
            'chada_travel_value'                    => $chada_travel_value,
            'chada_travel_required'                 => $chada_travel_required,
            'chada_travel_required_indicator_html'  => $chada_travel_required_indicator_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_digital_wallet_section_html(array $chada_travel_settings): string {
        $chada_travel_attachment_id = (int) $chada_travel_settings['chada_travel_digital_wallet_qr_attachment_id'];

        return CHADA_TRAVEL_Template::render('admin/settings/payment-method/digital-wallet', [
            'chada_travel_provider_name'  => (string) $chada_travel_settings['chada_travel_digital_wallet_name'],
            'chada_travel_account_name'   => (string) $chada_travel_settings['chada_travel_digital_wallet_account_name'],
            'chada_travel_account_number' => (string) $chada_travel_settings['chada_travel_digital_wallet_account_number'],
            'chada_travel_qr_url'         => CHADA_TRAVEL_Config::get_digital_wallet_qr_url($chada_travel_settings),
            'chada_travel_attachment_id'  => $chada_travel_attachment_id,
            'chada_travel_has_real_qr'    => $chada_travel_attachment_id > 0,
        ]);
    }

    // ============================================================ EMAIL TAB

    public static function render_email_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        CHADA_TRAVEL_Template::output('admin/settings/email/tab', [
            'chada_travel_action_url'               => admin_url('admin-post.php'),
            'chada_travel_save_action'              => self::EMAIL_SAVE_ACTION,
            'chada_travel_nonce_html'                => wp_nonce_field(self::EMAIL_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_sender_identity_html'      => self::build_sender_identity_section_html($chada_travel_settings),
            'chada_travel_admin_notifications_html'  => self::build_admin_notifications_section_html($chada_travel_settings),
            'chada_travel_smtp_html'                 => self::build_smtp_section_html(),
            'chada_travel_delivery_status_html'      => self::build_delivery_status_section_html($chada_travel_settings),
            'chada_travel_send_test_email_html'      => self::build_send_test_email_section_html(),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_sender_identity_section_html(array $chada_travel_settings): string {
        $chada_travel_company_name = (string) $chada_travel_settings['chada_travel_company_name'];
        $chada_travel_from_name_field_html = self::build_text_field_html(
            'chada-travel-email-from-name',
            'chada_travel_email_from_name',
            __('From Name', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_email_from_name'],
            false,
            'text',
            sprintf(
                /* translators: %s: effective Company Name from General Settings. */
                __('Leave blank to use the Company Name from General Settings: %s.', 'chada-travel'),
                $chada_travel_company_name
            )
        );
        $chada_travel_from_address_field_html = self::build_text_field_html(
            'chada-travel-email-from-address',
            'chada_travel_email_from_address',
            __('From Email Address', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_email_from_address'],
            false,
            'email',
            // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
            __('Technical transactional sender address. Leave blank to use the WordPress mail system default.', 'chada-travel')
        );
        $chada_travel_reply_to_field_html = self::build_text_field_html(
            'chada-travel-email-reply-to-address',
            'chada_travel_email_reply_to_address',
            __('Reply-To Email Address', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_email_reply_to_address'],
            false,
            'email',
            sprintf(
                /* translators: %s: effective Company Support Email from General Settings. */
                __('Leave blank to use the Company Support Email from General Settings: %s.', 'chada-travel'),
                (string) $chada_travel_settings['chada_travel_company_support_email']
            )
        );

        return CHADA_TRAVEL_Template::render('admin/settings/email/sender-identity', [
            'chada_travel_from_name_field_html'    => $chada_travel_from_name_field_html,
            'chada_travel_from_address_field_html' => $chada_travel_from_address_field_html,
            'chada_travel_reply_to_field_html'     => $chada_travel_reply_to_field_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_admin_notifications_section_html(array $chada_travel_settings): string {
        $chada_travel_wp_admin_email = function_exists('get_option') ? (string) get_option('admin_email', '') : '';
        $chada_travel_enabled = !empty($chada_travel_settings['chada_travel_email_admin_notifications_enabled']);

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_admin_notifications_notice = __('Controls only new administrator payment-review notifications; Booker/customer emails are never affected. Disabling this keeps the Admin Notification Email below stored for later reuse.', 'chada-travel');

        $chada_travel_admin_notification_field_html = self::build_text_field_html(
            'chada-travel-email-admin-notification-address',
            'chada_travel_email_admin_notification_address',
            __('Admin Notification Email', 'chada-travel'),
            (string) $chada_travel_settings['chada_travel_email_admin_notification_address'],
            false,
            'email',
            sprintf(
                /* translators: %s: WordPress administrator email. */
                __('Leave blank to use the WordPress administrator email: %s.', 'chada-travel'),
                $chada_travel_wp_admin_email
            )
        );

        return CHADA_TRAVEL_Template::render('admin/settings/email/admin-notifications', [
            'chada_travel_enabled'                       => $chada_travel_enabled,
            'chada_travel_admin_notifications_notice'    => $chada_travel_admin_notifications_notice,
            'chada_travel_admin_notification_field_html' => $chada_travel_admin_notification_field_html,
        ]);
    }

    /** Builds the optional, plugin-owned SMTP configuration section without exposing the stored password. */
    private static function build_smtp_section_html(): string {
        return CHADA_TRAVEL_Template::render('admin/settings/email/smtp', [
            'chada_travel_settings' => CHADA_TRAVEL_SMTP_Settings::get_public_settings(),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_delivery_status_section_html(array $chada_travel_settings): string {
        global $wpdb;
        // CHADA_TRAVEL_Readiness::get_email_status() is the one authoritative email-readiness resolver, shared with the
        // site-wide readiness notice (CHADA_TRAVEL_Readiness::render_admin_notices()) - this section only formats it.
        $chada_travel_email_status = CHADA_TRAVEL_Readiness::get_email_status();

        return CHADA_TRAVEL_Template::render('admin/settings/email/delivery-status', [
            'chada_travel_delivery_provider_label' => self::delivery_provider_label($chada_travel_email_status),
            'chada_travel_queue_provider_label'    => $chada_travel_email_status['queue_active']
                ? __('Plugin queue active; WP-Cron worker scheduled.', 'chada-travel')
                : __('Plugin queue ready; no pending email work.', 'chada-travel'),
            'chada_travel_sender_identity_label'   => self::sender_state_label($chada_travel_email_status['sender_state']),
            'chada_travel_last_attempt_html'       => self::build_last_email_attempt_html($wpdb, $chada_travel_settings),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_email_status CHADA_TRAVEL_Readiness::get_email_status() result. */
    private static function delivery_provider_label(array $chada_travel_email_status): string {
        if ($chada_travel_email_status['smtp_enabled'] && $chada_travel_email_status['smtp_state'] === 'configured') {
            return __('Built-in SMTP configured; using WordPress wp_mail() transport.', 'chada-travel');
        }
        if ($chada_travel_email_status['smtp_enabled']) {
            return __('Built-in SMTP incomplete; using WordPress mail fallback.', 'chada-travel');
        }
        return __('WordPress wp_mail() transport.', 'chada-travel');
    }

    /** @param string $chada_travel_sender_state One of CHADA_TRAVEL_Readiness::get_email_status()['sender_state']'s values. */
    private static function sender_state_label(string $chada_travel_sender_state): string {
        switch ($chada_travel_sender_state) {
            case 'explicit':
                return __('Explicit sender configured.', 'chada-travel');
            case 'inherited_company_name':
                return __('Inherited Company Name.', 'chada-travel');
            case 'managed_externally':
                return __('Sender managed by WordPress defaults.', 'chada-travel');
            default:
                return __('Incomplete or using WordPress default.', 'chada-travel');
        }
    }

    /** @return array<string, string> Email-dispatch event slug => Delivery Status display label. */
    private static function email_event_labels(): array {
        return [
            'chada_travel_email_bank_proof_requested'       => __('Bank Payment Instructions Sent', 'chada-travel'),
            'chada_travel_email_bank_proof_received'        => __('Bank Proof Received Acknowledgement', 'chada-travel'),
            'chada_travel_email_payment_pending'             => __('Digital Wallet Payment Details Received', 'chada-travel'),
            'chada_travel_email_payment_confirmed'           => __('Payment Confirmed', 'chada-travel'),
            'chada_travel_email_payment_rejected'            => __('Payment Rejected', 'chada-travel'),
            'chada_travel_email_upload_link_reissued'        => __('Upload Link Reissued', 'chada-travel'),
            'chada_travel_email_admin_bank_proof_submitted'  => __('Bank Deposit Slip Submitted', 'chada-travel'),
            'chada_travel_email_admin_digital_wallet_submitted' => __('Digital Wallet Payment Submitted', 'chada-travel'),
        ];
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_last_email_attempt_html(object $chada_travel_wpdb, array $chada_travel_settings): string {
        $chada_travel_event = CHADA_TRAVEL_Payment_Repository::find_latest_event_by_type($chada_travel_wpdb, 'chada_travel_email_dispatched');
        if (!$chada_travel_event) {
            return CHADA_TRAVEL_Template::render('admin/settings/email/last-attempt', [
                'chada_travel_has_attempt'    => false,
                'chada_travel_label'          => '',
                'chada_travel_audience_label' => '',
                'chada_travel_status_class'   => '',
                'chada_travel_status_label'   => '',
                'chada_travel_attempted_at'   => '',
            ]);
        }

        $chada_travel_meta           = json_decode((string) ($chada_travel_event['chada_travel_event_meta'] ?? ''), true) ?: [];
        $chada_travel_event_slug     = (string) ($chada_travel_meta['event'] ?? '');
        $chada_travel_audience       = (string) ($chada_travel_meta['audience'] ?? 'customer');
        $chada_travel_sent           = !empty($chada_travel_meta['sent']);
        $chada_travel_label          = self::email_event_labels()[$chada_travel_event_slug] ?? $chada_travel_event_slug;
        $chada_travel_audience_label = $chada_travel_audience === 'admin'
            ? __('Administrator', 'chada-travel')
            : __('Customer', 'chada-travel');
        $chada_travel_status_class   = $chada_travel_sent ? 'is-success' : 'is-warning';
        $chada_travel_status_label   = $chada_travel_sent ? __('Sent', 'chada-travel') : __('Failed', 'chada-travel');
        $chada_travel_attempted_at   = CHADA_TRAVEL_Config::format_utc_datetime(
            (string) ($chada_travel_event['chada_travel_created_at'] ?? ''),
            $chada_travel_settings
        );

        return CHADA_TRAVEL_Template::render('admin/settings/email/last-attempt', [
            'chada_travel_has_attempt'    => true,
            'chada_travel_label'          => $chada_travel_label,
            'chada_travel_audience_label' => $chada_travel_audience_label,
            'chada_travel_status_class'   => $chada_travel_status_class,
            'chada_travel_status_label'   => $chada_travel_status_label,
            'chada_travel_attempted_at'   => $chada_travel_attempted_at,
        ]);
    }

    private static function build_send_test_email_section_html(): string {
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_test_email_notice = __('Sends immediately through the real wp_mail() path using the currently saved From/Reply-To above; contains no booking, payment, document, or customer data.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/email/send-test-email', [
            'chada_travel_action_url'        => admin_url('admin-post.php'),
            'chada_travel_test_email_action' => self::TEST_EMAIL_ACTION,
            'chada_travel_nonce_html'        => wp_nonce_field(self::TEST_EMAIL_ACTION, '_wpnonce', true, false),
            'chada_travel_default_recipient' => self::default_test_email_recipient(),
            'chada_travel_test_email_notice' => $chada_travel_test_email_notice,
        ]);
    }

    private static function default_test_email_recipient(): string {
        // wp_get_current_user() always returns a WP_User instance (an empty, ID-0 one when not logged in),
        // never null/false - so only the resulting email needs to be checked for blank.
        if (function_exists('wp_get_current_user')) {
            $chada_travel_user_email = (string) wp_get_current_user()->user_email;
            if ($chada_travel_user_email !== '') {
                return $chada_travel_user_email;
            }
        }
        return function_exists('get_option') ? (string) get_option('admin_email', '') : '';
    }

    // ============================================================ PAGES & LINKS TAB

    public static function render_pages_links_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_pages_links_intro = __('These WordPress Pages host the plugin\'s public customer workflows and the Tours browse/search page. Create or edit an ordinary Page, add the required shortcode, publish it, then select it below. The Tours Page is automatically adopted or created at /tours/ when needed.', 'chada-travel');

        $chada_travel_labels       = CHADA_TRAVEL_Page_Registry::get_labels();
        $chada_travel_descriptions = CHADA_TRAVEL_Page_Registry::get_descriptions();
        $chada_travel_sections_html = '';
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_sections_html .= self::build_page_link_section_html(
                $chada_travel_key,
                $chada_travel_definition,
                $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key,
                $chada_travel_descriptions[$chada_travel_key] ?? '',
                $chada_travel_settings
            );
        }

        CHADA_TRAVEL_Template::output('admin/settings/pages-links/tab', [
            'chada_travel_intro'               => $chada_travel_pages_links_intro,
            'chada_travel_status_summary_html' => self::build_pages_links_status_summary_html($chada_travel_settings),
            'chada_travel_action_url'          => admin_url('admin-post.php'),
            'chada_travel_save_action'         => self::PAGES_LINKS_SAVE_ACTION,
            'chada_travel_nonce_html'          => wp_nonce_field(self::PAGES_LINKS_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_sections_html'       => $chada_travel_sections_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_pages_links_status_summary_html(array $chada_travel_settings): string {
        $chada_travel_status_labels = CHADA_TRAVEL_Page_Settings::get_status_labels();
        $chada_travel_labels        = CHADA_TRAVEL_Page_Registry::get_labels();
        $chada_travel_rows_html = '';
        foreach (array_keys(CHADA_TRAVEL_Page_Registry::get_definitions()) as $chada_travel_key) {
            $chada_travel_status = CHADA_TRAVEL_Page_Settings::resolve_status($chada_travel_key, $chada_travel_settings)['status'];
            $chada_travel_class  = $chada_travel_status === CHADA_TRAVEL_Page_Settings::STATUS_READY ? 'is-success'
                : ($chada_travel_status === CHADA_TRAVEL_Page_Settings::STATUS_NOT_SELECTED ? '' : 'is-warning');
            $chada_travel_rows_html .= '<dt>' . esc_html($chada_travel_labels[$chada_travel_key] ?? $chada_travel_key)
                . '</dt><dd><span class="chada-travel-badge ' . esc_attr($chada_travel_class) . '">'
                . esc_html($chada_travel_status_labels[$chada_travel_status] ?? $chada_travel_status) . '</span></dd>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/pages-links/status-summary', [
            'chada_travel_rows_html' => $chada_travel_rows_html,
        ]);
    }

    /**
     * @param array{option:string,shortcode:string,fallback_path:string} $chada_travel_definition
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function build_page_link_section_html(
        string $chada_travel_key,
        array $chada_travel_definition,
        string $chada_travel_label,
        string $chada_travel_description,
        array $chada_travel_settings
    ): string {
        $chada_travel_resolved      = CHADA_TRAVEL_Page_Settings::resolve_status($chada_travel_key, $chada_travel_settings);
        $chada_travel_status        = $chada_travel_resolved['status'];
        $chada_travel_page_id       = $chada_travel_resolved['page_id'];
        $chada_travel_status_label  = CHADA_TRAVEL_Page_Settings::get_status_labels()[$chada_travel_status] ?? $chada_travel_status;
        $chada_travel_field_id      = 'chada-travel-page-' . str_replace('_', '-', $chada_travel_key);

        return CHADA_TRAVEL_Template::render('admin/settings/pages-links/page-link-section', [
            'chada_travel_label'           => $chada_travel_label,
            'chada_travel_field_id'        => $chada_travel_field_id,
            'chada_travel_dropdown_html'   => self::build_page_dropdown_html(
                $chada_travel_field_id,
                $chada_travel_definition['option'],
                $chada_travel_page_id
            ),
            'chada_travel_description'     => $chada_travel_description,
            'chada_travel_shortcode'       => $chada_travel_definition['shortcode'],
            'chada_travel_status_label'    => $chada_travel_status_label,
            'chada_travel_current_url'     => CHADA_TRAVEL_Page_Settings::resolve_url($chada_travel_key, $chada_travel_settings),
            'chada_travel_page_links_html' => self::build_page_links_html($chada_travel_page_id, $chada_travel_status),
        ]);
    }

    /**
     * Shared by both Pages & Links (build_page_link_section_html() above) and Policies & Consent
     * (build_policy_section_html() below) - converted exactly once, both callers reuse this same method/template
     * rather than each having their own copy.
     */
    private static function build_page_dropdown_html(
        string $chada_travel_field_id,
        string $chada_travel_option,
        int $chada_travel_selected
    ): string {
        $chada_travel_pages = function_exists('get_pages') ? get_pages([
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'sort_column' => 'post_title',
            'sort_order'  => 'ASC',
        ]) : [];
        $chada_travel_options_html = '<option value="0"' . selected($chada_travel_selected, 0, false) . '>'
            . esc_html__('— Select a Page —', 'chada-travel') . '</option>';
        foreach ((array) $chada_travel_pages as $chada_travel_page) {
            $chada_travel_page_id     = (int) $chada_travel_page->ID;
            $chada_travel_page_status = (string) $chada_travel_page->post_status;
            $chada_travel_page_title  = $chada_travel_page->post_title !== '' ? $chada_travel_page->post_title : sprintf(
                /* translators: %d: numeric WordPress Page id. */
                __('(no title) #%d', 'chada-travel'),
                $chada_travel_page_id
            );
            $chada_travel_status_suffix = $chada_travel_page_status === 'publish' ? '' : ' (' . ucfirst($chada_travel_page_status) . ')';
            $chada_travel_options_html .= '<option value="' . esc_attr((string) $chada_travel_page_id) . '" '
                . selected($chada_travel_selected, $chada_travel_page_id, false) . '>'
                . esc_html($chada_travel_page_title . $chada_travel_status_suffix) . '</option>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/shared/page-dropdown', [
            'chada_travel_field_id'     => $chada_travel_field_id,
            'chada_travel_field_name'   => $chada_travel_option,
            'chada_travel_options_html' => $chada_travel_options_html,
        ]);
    }

    /** Status-gated View/Edit Page links, specific to Pages & Links. */
    private static function build_page_links_html(int $chada_travel_page_id, string $chada_travel_status): string {
        if ($chada_travel_page_id <= 0) {
            return '';
        }
        $chada_travel_links = [];
        if (CHADA_TRAVEL_Page_Settings::can_offer_view_link($chada_travel_status) && function_exists('get_permalink')) {
            $chada_travel_permalink = get_permalink($chada_travel_page_id);
            if ($chada_travel_permalink) {
                $chada_travel_links[] = '<a href="' . esc_url($chada_travel_permalink) . '">' . esc_html__('View Page', 'chada-travel')
                    . '</a>';
            }
        }
        if ($chada_travel_status !== CHADA_TRAVEL_Page_Settings::STATUS_MISSING
            && function_exists('current_user_can') && current_user_can('edit_post', $chada_travel_page_id)
            && function_exists('get_edit_post_link')
        ) {
            $chada_travel_edit_link = get_edit_post_link($chada_travel_page_id, 'raw');
            if ($chada_travel_edit_link) {
                $chada_travel_links[] = '<a href="' . esc_url($chada_travel_edit_link) . '">' . esc_html__('Edit Page', 'chada-travel')
                    . '</a>';
            }
        }
        if (!$chada_travel_links) {
            return '';
        }
        return CHADA_TRAVEL_Template::render('admin/settings/pages-links/page-links', [
            'chada_travel_links_html' => implode(' | ', $chada_travel_links),
        ]);
    }

    public static function handle_save_pages_links(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::PAGES_LINKS_SAVE_ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload();
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_pages_links($chada_travel_post, CHADA_TRAVEL_Config::get_settings());

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::PAGES_LINKS_TAB);
            wp_safe_redirect(
                self::redirect_url(['chada_travel_settings_notice' => 'pages_links_invalid'], self::PAGES_LINKS_TAB)
            );
            exit;
        }

        wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'pages_links_saved'], self::PAGES_LINKS_TAB));
        exit;
    }

    // ============================================================ POLICIES & CONSENT TAB

    public static function render_policies_consent_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_intro = __('Configure the Policy Version/Effective Date and each policy\'s WordPress Page or External URL. This is a technical consent record only, not legal advice or a compliance guarantee.', 'chada-travel');

        $chada_travel_sections_html = '';
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_sections_html .= self::build_policy_section_html($chada_travel_key, $chada_travel_definition, $chada_travel_settings);
        }

        CHADA_TRAVEL_Template::output('admin/settings/policies-consent/tab', [
            'chada_travel_intro'                        => $chada_travel_intro,
            'chada_travel_status_summary_html'          => self::build_policies_status_summary_html($chada_travel_settings),
            'chada_travel_action_url'                   => admin_url('admin-post.php'),
            'chada_travel_save_action'                  => self::POLICIES_CONSENT_SAVE_ACTION,
            'chada_travel_nonce_html'                    =>
                wp_nonce_field(self::POLICIES_CONSENT_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_policy_bundle_fields_html'     => self::build_policy_bundle_fields_html($chada_travel_settings),
            'chada_travel_transaction_confirmation_html' => self::build_transaction_confirmation_fields_html($chada_travel_settings),
            'chada_travel_sections_html'                 => $chada_travel_sections_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_policies_status_summary_html(array $chada_travel_settings): string {
        $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings);
        $chada_travel_status_labels = CHADA_TRAVEL_Policy_Settings::get_status_labels();
        $chada_travel_labels = CHADA_TRAVEL_Policy_Registry::get_labels();

        $chada_travel_rows_html = '<dt>' . esc_html__('Overall bundle', 'chada-travel') . '</dt><dd><span class="chada-travel-badge '
            . ($chada_travel_bundle['ready'] ? 'is-success' : 'is-warning') . '">' . esc_html(
                $chada_travel_bundle['ready'] ? __('Ready', 'chada-travel') : __('Not Ready', 'chada-travel')
            ) . '</span></dd>';
        $chada_travel_rows_html .= '<dt>' . esc_html__('Policy Version', 'chada-travel') . '</dt><dd>' . esc_html(
            $chada_travel_bundle['version_valid'] ? __('Valid', 'chada-travel') : __('Invalid or missing', 'chada-travel')
        ) . '</dd>';
        $chada_travel_rows_html .= '<dt>' . esc_html__('Policy Effective Date', 'chada-travel') . '</dt><dd>' . esc_html(
            $chada_travel_bundle['effective_date_valid']
                ? __('Valid', 'chada-travel') : __('Invalid, missing, or in the future', 'chada-travel')
        ) . '</dd>';
        foreach ($chada_travel_bundle['policies'] as $chada_travel_key => $chada_travel_status) {
            $chada_travel_class = CHADA_TRAVEL_Policy_Settings::is_ready_status($chada_travel_status['status'])
                ? 'is-success' : 'is-warning';
            $chada_travel_rows_html .= '<dt>' . esc_html($chada_travel_labels[$chada_travel_key] ?? $chada_travel_key)
                . '</dt><dd><span class="chada-travel-badge ' . esc_attr($chada_travel_class) . '">'
                . esc_html($chada_travel_status_labels[$chada_travel_status['status']] ?? $chada_travel_status['status'])
                . '</span></dd>';
        }

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_not_ready_notice = __('New visa application checkout drafts are unavailable to customers until every item above is Ready.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/policies-consent/status-summary', [
            'chada_travel_rows_html'        => $chada_travel_rows_html,
            'chada_travel_bundle_ready'     => $chada_travel_bundle['ready'],
            'chada_travel_not_ready_notice' => $chada_travel_not_ready_notice,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_policy_bundle_fields_html(array $chada_travel_settings): string {
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_version_notice = __('A compact identifier (letters, numbers, periods, underscores, hyphens), e.g. 2026-07, v1.0, or privacy-2026-01. Increment this whenever policy text actually changes, even if the page URL stays the same.', 'chada-travel');
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_date_notice = __('Interpreted using the Company Timezone from General Settings. Cannot be a future date; scheduled policy activation is not implemented in this version.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/policies-consent/policy-bundle-fields', [
            'chada_travel_policy_version' => (string) $chada_travel_settings['chada_travel_policy_version'],
            'chada_travel_version_notice' => $chada_travel_version_notice,
            'chada_travel_effective_date' => (string) $chada_travel_settings['chada_travel_policy_effective_date'],
            'chada_travel_date_notice'    => $chada_travel_date_notice,
        ]);
    }

    /**
     * Transaction Confirmation Disclaimer: a Stage 5 payment-confirmation notice, backed by the existing
     * chada_travel_transaction_disclaimer option - not one of the Stage 1/Stage 3 consent sources above, so it is
     * deliberately its own section and never touches Policy Version, policy sources, or the immutable per-order
     * policy snapshot.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function build_transaction_confirmation_fields_html(array $chada_travel_settings): string {
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_disclaimer_notice = __('Shown below the payment confirmation/transaction summary on the customer Stage 5 screen. Clarify that this page is not an official receipt unless your business has a separately approved legal/accounting process. Plain text only - HTML, scripts, and shortcodes are not supported and will not run. Changing this does not alter existing consent records or require a new Policy Version.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/policies-consent/transaction-confirmation-fields', [
            'chada_travel_disclaimer'        => (string) $chada_travel_settings['chada_travel_transaction_disclaimer'],
            'chada_travel_disclaimer_notice' => $chada_travel_disclaimer_notice,
        ]);
    }

    /**
     * @param array{
     *     page_option:string,url_option:string,stage:string,required:bool,snapshot_key:string
     * } $chada_travel_definition
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function build_policy_section_html(
        string $chada_travel_key,
        array $chada_travel_definition,
        array $chada_travel_settings
    ): string {
        $chada_travel_label  = CHADA_TRAVEL_Policy_Registry::get_labels()[$chada_travel_key] ?? $chada_travel_key;
        $chada_travel_status = CHADA_TRAVEL_Policy_Settings::resolve_policy_status($chada_travel_key, $chada_travel_settings);
        $chada_travel_status_label = CHADA_TRAVEL_Policy_Settings::get_status_labels()[$chada_travel_status['status']]
            ?? $chada_travel_status['status'];
        $chada_travel_field_id     = 'chada-travel-policy-page-' . str_replace('_', '-', $chada_travel_key);
        $chada_travel_section_id   = 'chada-travel-policy-section-' . str_replace('_', '-', $chada_travel_key);
        $chada_travel_url_field_id = 'chada-travel-policy-url-' . str_replace('_', '-', $chada_travel_key);
        $chada_travel_class = CHADA_TRAVEL_Policy_Settings::is_ready_status($chada_travel_status['status']) ? 'is-success' : 'is-warning';

        return CHADA_TRAVEL_Template::render('admin/settings/policies-consent/policy-section', [
            'chada_travel_section_id'    => $chada_travel_section_id,
            'chada_travel_label'         => $chada_travel_label,
            'chada_travel_description'   => CHADA_TRAVEL_Policy_Registry::get_descriptions()[$chada_travel_key] ?? '',
            'chada_travel_field_id'      => $chada_travel_field_id,
            'chada_travel_dropdown_html' => self::build_page_dropdown_html(
                $chada_travel_field_id,
                $chada_travel_definition['page_option'],
                self::current_page_id($chada_travel_definition['page_option'], $chada_travel_settings)
            ),
            'chada_travel_url_field_id'           => $chada_travel_url_field_id,
            'chada_travel_url_field_name'         => $chada_travel_definition['url_option'],
            'chada_travel_url_value'              => (string) $chada_travel_settings[$chada_travel_definition['url_option']],
            'chada_travel_status_class'           => $chada_travel_class,
            'chada_travel_status_value'           => $chada_travel_status['status'],
            'chada_travel_status_label'           => $chada_travel_status_label,
            'chada_travel_resolved_url'           => $chada_travel_status['url'],
            'chada_travel_policy_page_links_html' => self::build_policy_page_links_html((int) $chada_travel_status['page_id']),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function current_page_id(string $chada_travel_option, array $chada_travel_settings): int {
        return max(0, (int) ($chada_travel_settings[$chada_travel_option] ?? 0));
    }

    /**
     * Offers View/Edit Page links whenever a page id is assigned at all; a nonexistent/trashed page simply
     * yields no permalink/edit link below, so no separate status check is needed here. Unlike
     * build_page_links_html() (Pages & Links), there is no status gate at all here.
     */
    private static function build_policy_page_links_html(int $chada_travel_page_id): string {
        if ($chada_travel_page_id <= 0) {
            return '';
        }
        $chada_travel_links = [];
        if (function_exists('get_permalink')) {
            $chada_travel_permalink = get_permalink($chada_travel_page_id);
            if ($chada_travel_permalink) {
                $chada_travel_links[] = '<a href="' . esc_url($chada_travel_permalink) . '">' . esc_html__('View Page', 'chada-travel')
                    . '</a>';
            }
        }
        if (function_exists('current_user_can') && current_user_can('edit_post', $chada_travel_page_id)
            && function_exists('get_edit_post_link')
        ) {
            $chada_travel_edit_link = get_edit_post_link($chada_travel_page_id, 'raw');
            if ($chada_travel_edit_link) {
                $chada_travel_links[] = '<a href="' . esc_url($chada_travel_edit_link) . '">' . esc_html__('Edit Page', 'chada-travel')
                    . '</a>';
            }
        }
        if (!$chada_travel_links) {
            return '';
        }
        return CHADA_TRAVEL_Template::render('admin/settings/policies-consent/policy-page-links', [
            'chada_travel_links_html' => implode(' | ', $chada_travel_links),
        ]);
    }

    public static function handle_save_policies_consent(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::POLICIES_CONSENT_SAVE_ACTION);

        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload();
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_policies_consent(
            $chada_travel_post,
            CHADA_TRAVEL_Config::get_settings(),
            $wpdb
        );

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::POLICIES_CONSENT_TAB);
            wp_safe_redirect(
                self::redirect_url(['chada_travel_settings_notice' => 'policies_consent_invalid'], self::POLICIES_CONSENT_TAB)
            );
            exit;
        }

        wp_safe_redirect(
            self::redirect_url(['chada_travel_settings_notice' => 'policies_consent_saved'], self::POLICIES_CONSENT_TAB)
        );
        exit;
    }

    // ============================================================ DOCUMENTS & UPLOADS TAB

    public static function render_documents_uploads_tab(): void {
        $chada_travel_settings   = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_server_max = CHADA_TRAVEL_Upload_Settings::get_server_max_upload_bytes();

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_intro = __('Global upload safety ceilings and secure link lifetimes for Visa Documents and Bank Payment Proof. Country-specific document requirements are configured separately in Visa Countries.', 'chada-travel');

        CHADA_TRAVEL_Template::output('admin/settings/documents-uploads/tab', [
            'chada_travel_intro'               => $chada_travel_intro,
            'chada_travel_status_summary_html' => self::build_documents_uploads_status_summary_html(
                $chada_travel_settings,
                $chada_travel_server_max
            ),
            'chada_travel_action_url'         => admin_url('admin-post.php'),
            'chada_travel_save_action'        => self::DOCUMENTS_UPLOADS_SAVE_ACTION,
            'chada_travel_nonce_html'          =>
                wp_nonce_field(self::DOCUMENTS_UPLOADS_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_visa_documents_html' => self::build_visa_documents_fieldset_html($chada_travel_settings, $chada_travel_server_max),
            'chada_travel_bank_proof_html'     => self::build_bank_proof_fieldset_html($chada_travel_settings),
            'chada_travel_scope_note_html'     => self::build_documents_uploads_scope_note_html(),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_documents_uploads_status_summary_html(
        array $chada_travel_settings,
        int $chada_travel_server_max
    ): string {
        $chada_travel_status = CHADA_TRAVEL_Upload_Settings::get_status($chada_travel_settings, $chada_travel_server_max);

        $chada_travel_overall_class = 'is-success';
        $chada_travel_overall_label = __('OK', 'chada-travel');
        if ($chada_travel_status['server_limit_unavailable']) {
            $chada_travel_overall_class = 'is-warning';
            $chada_travel_overall_label = __('Server Limit Unavailable', 'chada-travel');
        } elseif ($chada_travel_status['over_server_limit']) {
            $chada_travel_overall_class = 'is-warning';
            $chada_travel_overall_label = __('Server Limit Exceeded', 'chada-travel');
        }

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_over_limit_notice = __('The configured Maximum File Size is currently above the effective WordPress upload limit. Uploads are already capped to the lower effective ceiling above; lower Maximum File Size on your next save to clear this warning.', 'chada-travel');
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_server_unavailable_notice = __('The server did not report a usable upload size limit. Uploads are unavailable and this tab cannot be saved until a valid server upload limit can be determined.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/documents-uploads/status-summary', [
            'chada_travel_overall_class'       => $chada_travel_overall_class,
            'chada_travel_overall_label'       => $chada_travel_overall_label,
            'chada_travel_configured_ceiling'  => self::format_mb_and_bytes($chada_travel_status['configured_max_bytes']),
            'chada_travel_effective_wp_limit'  => $chada_travel_status['server_max_bytes'] > 0
                ? self::format_mb_and_bytes($chada_travel_status['server_max_bytes'])
                : __('Unavailable', 'chada-travel'),
            'chada_travel_effective_ceiling_now' => $chada_travel_status['effective_max_bytes'] > 0
                ? self::format_mb_and_bytes($chada_travel_status['effective_max_bytes'])
                : __('None - uploads fail closed', 'chada-travel'),
            'chada_travel_php_upload_max_filesize' => $chada_travel_status['php_upload_max_filesize'] !== ''
                ? $chada_travel_status['php_upload_max_filesize'] : __('Unknown', 'chada-travel'),
            'chada_travel_php_post_max_size' => $chada_travel_status['php_post_max_size'] !== ''
                ? $chada_travel_status['php_post_max_size'] : __('Unknown', 'chada-travel'),
            'chada_travel_show_over_limit_notice' => $chada_travel_status['over_server_limit'],
            'chada_travel_over_limit_notice'      => $chada_travel_over_limit_notice,
            'chada_travel_show_server_unavailable_notice' => $chada_travel_status['server_limit_unavailable'],
            'chada_travel_server_unavailable_notice'      => $chada_travel_server_unavailable_notice,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_visa_documents_fieldset_html(array $chada_travel_settings, int $chada_travel_server_max): string {
        $chada_travel_mb_value = self::format_mb_input_value(
            CHADA_TRAVEL_Upload_Settings::bytes_to_mb((int) $chada_travel_settings['chada_travel_upload_max_bytes'])
        );
        $chada_travel_max_attr = $chada_travel_server_max > 0
            ? self::format_mb_input_value(CHADA_TRAVEL_Upload_Settings::bytes_to_mb($chada_travel_server_max)) : '';

        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_mb_notice = __('This is the plugin-wide per-file ceiling: it applies to both Visa Document and Bank Payment Proof uploads, and can never exceed the effective WordPress upload limit shown above.', 'chada-travel');
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_doc_types_notice = __('At least one type must remain selected. A country\'s own requirement may narrow this further; it never broadens it.', 'chada-travel');

        $chada_travel_doc_hours = (int) $chada_travel_settings['chada_travel_upload_token_hours'];

        return CHADA_TRAVEL_Template::render('admin/settings/documents-uploads/visa-documents-fieldset', [
            'chada_travel_mb_value'  => $chada_travel_mb_value,
            'chada_travel_max_attr'  => $chada_travel_max_attr,
            'chada_travel_mb_notice' => $chada_travel_mb_notice,
            'chada_travel_mime_checkboxes_html' => self::build_mime_checkboxes_html(
                'chada_travel_document_upload_mime_types',
                CHADA_TRAVEL_Upload_Settings::get_visa_document_mime_registry(),
                (array) $chada_travel_settings['chada_travel_document_upload_mime_types']
            ),
            'chada_travel_doc_types_notice' => $chada_travel_doc_types_notice,
            'chada_travel_doc_hours'        => $chada_travel_doc_hours,
            'chada_travel_hours_min'        => (string) CHADA_TRAVEL_Upload_Settings::MIN_EXPIRY_HOURS,
            'chada_travel_hours_max'        => (string) CHADA_TRAVEL_Upload_Settings::MAX_EXPIRY_HOURS,
            'chada_travel_hours_preview'    => self::hours_preview_text($chada_travel_doc_hours),
            'chada_travel_reissue_note'     => self::reissue_note_text(),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function build_bank_proof_fieldset_html(array $chada_travel_settings): string {
        $chada_travel_proof_hours = (int) $chada_travel_settings['chada_travel_payment_proof_token_hours'];

        return CHADA_TRAVEL_Template::render('admin/settings/documents-uploads/bank-proof-fieldset', [
            'chada_travel_mime_checkboxes_html' => self::build_mime_checkboxes_html(
                'chada_travel_payment_proof_mime_types',
                CHADA_TRAVEL_Upload_Settings::get_bank_proof_mime_registry(),
                (array) $chada_travel_settings['chada_travel_payment_proof_mime_types']
            ),
            'chada_travel_proof_hours'   => $chada_travel_proof_hours,
            'chada_travel_hours_min'     => (string) CHADA_TRAVEL_Upload_Settings::MIN_EXPIRY_HOURS,
            'chada_travel_hours_max'     => (string) CHADA_TRAVEL_Upload_Settings::MAX_EXPIRY_HOURS,
            'chada_travel_hours_preview' => self::hours_preview_text($chada_travel_proof_hours),
            'chada_travel_reissue_note'  => self::reissue_note_text(),
        ]);
    }

    private static function reissue_note_text(): string {
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        return __('A reissued link is a newly issued link and uses the value saved at the time of reissue; already-issued links keep their original expiry unchanged.', 'chada-travel');
    }

    private static function build_documents_uploads_scope_note_html(): string {
        $chada_travel_url = '';
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_scope_note = __('This tab sets global upload safety ceilings and link lifetimes only. Country-specific document requirements, per-requirement file types, sizes, and required/optional status stay in Visa Countries.', 'chada-travel');

        return CHADA_TRAVEL_Template::render('admin/settings/documents-uploads/scope-note', [
            'chada_travel_scope_note' => $chada_travel_scope_note,
            'chada_travel_show_link'  => false,
            'chada_travel_url'        => $chada_travel_url,
        ]);
    }

    /**
     * @param string                                                       $chada_travel_field_name
     * @param list<array{mime: string, extensions: list<string>, label: string}> $chada_travel_registry
     * @param list<string>                                                $chada_travel_selected
     */
    private static function build_mime_checkboxes_html(
        string $chada_travel_field_name,
        array $chada_travel_registry,
        array $chada_travel_selected
    ): string {
        $chada_travel_checkboxes_html = '';
        foreach ($chada_travel_registry as $chada_travel_choice) {
            $chada_travel_id = 'chada-travel-mime-' . str_replace(['/', '_'], '-', $chada_travel_field_name . '-' . $chada_travel_choice['mime']);
            $chada_travel_checked = in_array($chada_travel_choice['mime'], $chada_travel_selected, true);
            $chada_travel_checkboxes_html .= '<label style="display:block;" for="' . esc_attr($chada_travel_id)
                . '"><input type="checkbox" id="' . esc_attr($chada_travel_id) . '" name="' . esc_attr($chada_travel_field_name)
                . '[]" value="' . esc_attr($chada_travel_choice['mime']) . '" ' . ($chada_travel_checked ? 'checked' : '') . '> '
                . esc_html($chada_travel_choice['label']) . ' <span class="chada-travel-muted chada-travel-small">(.'
                . esc_html(implode(', .', $chada_travel_choice['extensions'])) . ')</span></label>';
        }

        return CHADA_TRAVEL_Template::render('admin/settings/documents-uploads/mime-checkboxes', [
            'chada_travel_checkboxes_html' => $chada_travel_checkboxes_html,
        ]);
    }

    private static function format_mb_input_value(float $chada_travel_mb): string {
        $chada_travel_value = rtrim(rtrim(number_format($chada_travel_mb, 4, '.', ''), '0'), '.');
        return $chada_travel_value === '' ? '0' : $chada_travel_value;
    }

    private static function format_mb_and_bytes(int $chada_travel_bytes): string {
        return self::format_mb_input_value(CHADA_TRAVEL_Upload_Settings::bytes_to_mb($chada_travel_bytes)) . ' MB ('
            . number_format($chada_travel_bytes) . ' ' . __('bytes', 'chada-travel') . ')';
    }

    private static function hours_preview_text(int $chada_travel_hours): string {
        if ($chada_travel_hours < 24) {
            return sprintf(
                /* translators: %d: number of hours. */
                __('%d hours.', 'chada-travel'),
                $chada_travel_hours
            );
        }
        $chada_travel_days = intdiv($chada_travel_hours, 24);
        return $chada_travel_days === 1
            ? sprintf(
                /* translators: %d: number of hours (always 24-47 here). */
                __('%d hours (1 day).', 'chada-travel'),
                $chada_travel_hours
            )
            : sprintf(
                /* translators: 1: number of hours, 2: the same duration rounded down to whole days (2+). */
                __('%1$d hours (%2$d days).', 'chada-travel'),
                $chada_travel_hours,
                $chada_travel_days
            );
    }

    public static function handle_save_documents_uploads(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::DOCUMENTS_UPLOADS_SAVE_ACTION);

        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload();
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_documents_uploads(
            $chada_travel_post,
            CHADA_TRAVEL_Config::get_settings(),
            $wpdb
        );

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::DOCUMENTS_UPLOADS_TAB);
            wp_safe_redirect(self::redirect_url(
                ['chada_travel_settings_notice' => 'documents_uploads_invalid'],
                self::DOCUMENTS_UPLOADS_TAB
            ));
            exit;
        }

        wp_safe_redirect(self::redirect_url(
            ['chada_travel_settings_notice' => 'documents_uploads_saved'],
            self::DOCUMENTS_UPLOADS_TAB
        ));
        exit;
    }

    // ============================================================ TOURS TAB

    /** Renders defaults and image-normalization limits used by the Tours manager. */
    public static function render_tours_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_default_currency = (string) (
            $chada_travel_settings['chada_travel_tour_default_currency'] ?? CHADA_TRAVEL_Config::TOUR_DEFAULT_CURRENCY
        );
        $chada_travel_currency_options_html = '';
        foreach (CHADA_TRAVEL_Config::get_tour_currency_options() as $chada_travel_currency) {
            $chada_travel_currency_options_html .= '<option value="' . esc_attr($chada_travel_currency) . '" '
                . selected($chada_travel_currency, $chada_travel_default_currency, false) . '>'
                . esc_html($chada_travel_currency) . '</option>';
        }
        CHADA_TRAVEL_Template::output('admin/settings/tours/tab', [
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_save_action' => self::TOURS_SAVE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::TOURS_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_currency_options_html' => $chada_travel_currency_options_html,
            'chada_travel_image_width' => (int) (
                $chada_travel_settings['chada_travel_tour_featured_image_width'] ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_WIDTH
            ),
            'chada_travel_image_height' => (int) (
                $chada_travel_settings['chada_travel_tour_featured_image_height'] ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_HEIGHT
            ),
            'chada_travel_image_max_mb' => (int) (
                $chada_travel_settings['chada_travel_tour_featured_image_max_mb'] ?? CHADA_TRAVEL_Config::TOUR_FEATURED_IMAGE_DEFAULT_MAX_MB
            ),
        ]);
    }

    /** Validates and persists the dedicated Tours Settings tab. */
    public static function handle_save_tour_settings(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::TOURS_SAVE_ACTION);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        // phpcs:ignore Generic.Files.LineLength.TooLong -- the shared save call keeps validation and persistence atomic.
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_tour_settings(
            CHADA_TRAVEL_Config::sanitize_request_post_payload(),
            CHADA_TRAVEL_Config::get_settings()
        );
        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::TOURS_TAB);
            wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'tours_invalid'], self::TOURS_TAB));
            exit;
        }
        wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'tours_saved'], self::TOURS_TAB));
        exit;
    }

    /** Renders image-normalization limits used by Visa Country Step-by-Step Guide images. */
    public static function render_visa_countries_tab(): void {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        CHADA_TRAVEL_Template::output('admin/settings/visa-countries/tab', [
            'chada_travel_action_url' => admin_url('admin-post.php'),
            'chada_travel_save_action' => self::VISA_COUNTRIES_SAVE_ACTION,
            'chada_travel_nonce_html' => wp_nonce_field(self::VISA_COUNTRIES_SAVE_ACTION, '_wpnonce', true, false),
            'chada_travel_visa_guide_image_width' => (int) (
                $chada_travel_settings['chada_travel_visa_guide_image_width'] ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_WIDTH
            ),
            'chada_travel_visa_guide_image_height' => (int) (
                $chada_travel_settings['chada_travel_visa_guide_image_height'] ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_HEIGHT
            ),
            'chada_travel_visa_guide_image_max_mb' => (int) min(
                CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_MAX_MB,
                $chada_travel_settings['chada_travel_visa_guide_image_max_mb'] ?? CHADA_TRAVEL_Config::VISA_GUIDE_IMAGE_DEFAULT_MAX_MB
            ),
        ]);
    }

    /** Validates and persists the dedicated Visa Countries Settings tab. */
    public static function handle_save_visa_countries_settings(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::VISA_COUNTRIES_SAVE_ACTION);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_visa_countries_settings(
            CHADA_TRAVEL_Config::sanitize_request_post_payload(),
            CHADA_TRAVEL_Config::get_settings()
        );
        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::VISA_COUNTRIES_TAB);
            wp_safe_redirect(self::redirect_url(
                ['chada_travel_settings_notice' => 'visa_countries_invalid'],
                self::VISA_COUNTRIES_TAB
            ));
            exit;
        }
        wp_safe_redirect(self::redirect_url(
            ['chada_travel_settings_notice' => 'visa_countries_saved'],
            self::VISA_COUNTRIES_TAB
        ));
        exit;
    }

    // ============================================================ SAVE HANDLERS

    public static function handle_save(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::SAVE_ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload();
        $chada_travel_result = CHADA_TRAVEL_Settings_Save_Service::save_payment($chada_travel_post, CHADA_TRAVEL_Config::get_settings());

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::PAYMENT_TAB);
            wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'invalid'], self::PAYMENT_TAB));
            exit;
        }

        $chada_travel_extension_post = CHADA_TRAVEL_Settings_Save_Service::sanitize_extension_payload($chada_travel_post);
        do_action('chada_travel_save_payment_settings', $chada_travel_extension_post, CHADA_TRAVEL_Config::get_settings());

        wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'saved'], self::PAYMENT_TAB));
        exit;
    }

    public static function handle_save_email(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::EMAIL_SAVE_ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; shared request helper sanitizes the payload.
        $chada_travel_post = CHADA_TRAVEL_Config::sanitize_request_post_payload(['chada_travel_smtp_password']);
        $chada_travel_current_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_result = CHADA_TRAVEL_Email_Settings_Validator::validate($chada_travel_post, $chada_travel_current_settings);

        if ($chada_travel_result['errors']) {
            self::stash_form_errors($chada_travel_result['errors'], self::EMAIL_TAB);
            wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'email_invalid'], self::EMAIL_TAB));
            exit;
        }

        $chada_travel_smtp_clean = $chada_travel_result['smtp_clean'] ?? null;
        $chada_travel_smtp_encrypted_password = '';
        $chada_travel_save_smtp_password = false;
        if (is_array($chada_travel_smtp_clean)) {
            $chada_travel_password = (string) ($chada_travel_smtp_clean['chada_travel_smtp_password'] ?? '');
            if (!empty($chada_travel_smtp_clean['chada_travel_smtp_clear_password'])) {
                $chada_travel_save_smtp_password = true;
            } elseif ($chada_travel_password !== '') {
                $chada_travel_smtp_encrypted_password = CHADA_TRAVEL_Credential_Cipher::encrypt_for_purpose(
                    $chada_travel_password,
                    'smtp_password'
                );
                if ($chada_travel_smtp_encrypted_password === null) {
                    self::stash_form_errors([
                        'chada_travel_smtp_password' => __(
                            'This server cannot encrypt SMTP credentials safely.',
                            'chada-travel'
                        ),
                    ], self::EMAIL_TAB);
                    wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'email_invalid'], self::EMAIL_TAB));
                    exit;
                }
                $chada_travel_save_smtp_password = true;
            }
        }

        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }
        if (is_array($chada_travel_smtp_clean)) {
            $chada_travel_smtp_option_names = [
                'chada_travel_smtp_enabled',
                'chada_travel_smtp_host',
                'chada_travel_smtp_port',
                'chada_travel_smtp_encryption',
                'chada_travel_smtp_authentication',
                'chada_travel_smtp_username',
            ];
            foreach ($chada_travel_smtp_option_names as $chada_travel_option_name) {
                update_option($chada_travel_option_name, $chada_travel_smtp_clean[$chada_travel_option_name]);
            }
            if ($chada_travel_save_smtp_password) {
                update_option(CHADA_TRAVEL_SMTP_Settings::PASSWORD_OPTION, $chada_travel_smtp_encrypted_password, false);
            }
        }

        CHADA_TRAVEL_Settings_Save_Service::log_settings_audit_event(
            'chada_travel_email_settings_saved',
            'An administrator saved the Email settings.',
            $chada_travel_result['audit']
        );

        wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'email_saved'], self::EMAIL_TAB));
        exit;
    }

    /**
     * Capability-checked, nonce-protected Send Test Email action. Uses the currently *saved* effective From/
     * Reply-To headers (never unsaved values from the main form), sends through the real wp_mail() path so
     * and never creates a booking/payment/customer notification record or queues a workflow email.
     */
    public static function handle_send_test_email(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Chada Travel - Agency Manager settings.', 'chada-travel'));
        }
        check_admin_referer(self::TEST_EMAIL_ACTION);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above; the shared request helper and email sanitizer run before validation.
        $chada_travel_recipient = CHADA_TRAVEL_Config::sanitize_email(
            CHADA_TRAVEL_Config::sanitize_request_post('chada_travel_test_email_recipient')
        );
        $chada_travel_valid = $chada_travel_recipient !== ''
            && (function_exists('is_email') ? (bool) is_email($chada_travel_recipient) : true);
        if (!$chada_travel_valid) {
            wp_safe_redirect(self::redirect_url(['chada_travel_settings_notice' => 'test_email_invalid'], self::EMAIL_TAB));
            exit;
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_company_name = (string) $chada_travel_settings['chada_travel_company_name'];
        $chada_travel_subject = sprintf(
            /* translators: %s: Company Name. */
            __('[%s] Chada Travel - Agency Manager Email Delivery Test', 'chada-travel'),
            $chada_travel_company_name
        );
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
        $chada_travel_test_body_notice = __('This is a Chada Travel - Agency Manager email delivery test. It contains no booking, payment, document, or customer data.', 'chada-travel');
        $chada_travel_body = sprintf(
            /* translators: %s: Company Name. */
            __('This message confirms the current WordPress email delivery path for %s.', 'chada-travel'),
            $chada_travel_company_name
        ) . "\n\n" . $chada_travel_test_body_notice;

        $chada_travel_sent = function_exists('wp_mail') ? wp_mail(
            $chada_travel_recipient,
            $chada_travel_subject,
            $chada_travel_body,
            CHADA_TRAVEL_Config::build_email_headers($chada_travel_settings)
        ) : false;

        CHADA_TRAVEL_Settings_Save_Service::log_settings_audit_event(
            'chada_travel_email_test_sent',
            'An administrator sent a ' . CHADA_TRAVEL_PROJECT_NAME . ' test email.',
            ['sent' => (bool) $chada_travel_sent]
        );

        wp_safe_redirect(self::redirect_url([
            'chada_travel_settings_notice' => $chada_travel_sent ? 'test_email_sent' : 'test_email_failed',
        ], self::EMAIL_TAB));
        exit;
    }

    /** @param array<string, mixed> $chada_travel_extra_query */
    private static function redirect_url(array $chada_travel_extra_query, string $chada_travel_tab): string {
        return add_query_arg(
            array_merge(['page' => self::MENU_SLUG, 'tab' => $chada_travel_tab], $chada_travel_extra_query),
            admin_url('admin.php')
        );
    }

    /** @param array<string, string> $chada_travel_errors */
    private static function stash_form_errors(array $chada_travel_errors, string $chada_travel_tab): void {
        set_transient(self::form_errors_key($chada_travel_tab), array_values($chada_travel_errors), 60);
    }

    /** @return list<string> */
    private static function consume_form_errors(string $chada_travel_tab): array {
        $chada_travel_key    = self::form_errors_key($chada_travel_tab);
        $chada_travel_errors = get_transient($chada_travel_key);
        delete_transient($chada_travel_key);
        return is_array($chada_travel_errors) ? $chada_travel_errors : [];
    }

    private static function form_errors_key(string $chada_travel_tab): string {
        return 'chada_travel_settings_form_errors_' . $chada_travel_tab . '_' . get_current_user_id();
    }

    private static function form_errors_intro(string $chada_travel_tab): string {
        switch ($chada_travel_tab) {
            case self::GENERAL_TAB:
                return __('General Settings were not saved:', 'chada-travel');
            case self::EMAIL_TAB:
                return __('Email settings were not saved:', 'chada-travel');
            case self::PAGES_LINKS_TAB:
                return __('Pages & Links settings were not saved:', 'chada-travel');
            case self::POLICIES_CONSENT_TAB:
                return __('Policies & Consent settings were not saved:', 'chada-travel');
            case self::DOCUMENTS_UPLOADS_TAB:
                return __('Documents & Uploads settings were not saved:', 'chada-travel');
            default:
                return __('Payment settings were not saved:', 'chada-travel');
        }
    }
}
