<?php
/**
 * Capability-protected administrator Instructions screen (`admin.php?page=chada-travel-instructions`): static,
 * read-only reference content covering first-time setup order, shortcode usage, the customer checkout journey,
 * payment setup guidance, troubleshooting, and a glossary.
 *
 * Tabs are driven by an internal registry (see get_tabs()). Unlike CHADA_TRAVEL_Admin_Settings - whose tabs are each a
 * separate full-page load, appropriate for its heavy per-tab forms - every tab here is cheap, static content, so
 * all 6 are rendered into the page in a single request and switched between purely client-side: each tab's
 * `href`/`aria-controls` is a "#<slug>" URL hash matching its own always-present `<div id="<slug>"
 * role="tabpanel">`, and assets/js/chada-travel-admin.js's shared activateFromHash()/activateTab() functions show only
 * the matching panel, on load and on every click/hashchange, via `hidden` - no admin-post, no page reload. No
 * admin-post/admin-ajax handlers, no options, no save state.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Instructions {
    public const MENU_SLUG = 'chada-travel-instructions';
    private const CAPABILITY = 'manage_chada_travel_visa_applications';
    private const SETTINGS_CAPABILITY = 'manage_chada_travel_visa_settings';
    private const PAYMENT_CAPABILITY = 'verify_chada_travel_payments';
    private const GETTING_STARTED_TAB = 'getting-started';
    private const SHORTCODES_TAB = 'shortcodes';
    private const CHECKOUT_JOURNEY_TAB = 'checkout-journey';
    private const PAYMENT_SETUP_TAB = 'payment-setup';
    private const TROUBLESHOOTING_TAB = 'troubleshooting';
    private const GLOSSARY_TAB = 'glossary';
    private const DEFAULT_TAB = self::GETTING_STARTED_TAB;

    /**
     * No admin-post/admin-ajax handlers: this screen has no mutations of its own. Kept for structural
     * consistency with the other CHADA_TRAVEL_Admin_* screens, mirroring CHADA_TRAVEL_Admin_Dashboard::register().
     */
    public static function register(): void {
    }

    /** @return array<string, array{label: string, capability: string, render: callable}> */
    private static function get_tabs(): array {
        $chada_travel_tabs = [
            self::GETTING_STARTED_TAB => [
                'label'      => __('Getting Started', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_getting_started_tab'],
            ],
            self::SHORTCODES_TAB => [
                'label'      => __('Shortcodes', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_shortcodes_tab'],
            ],
            self::CHECKOUT_JOURNEY_TAB => [
                'label'      => __('Checkout Journey', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_checkout_journey_tab'],
            ],
            self::PAYMENT_SETUP_TAB => [
                'label'      => __('Payment Setup', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_payment_setup_tab'],
            ],
            self::TROUBLESHOOTING_TAB => [
                'label'      => __('Troubleshooting', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_troubleshooting_tab'],
            ],
            self::GLOSSARY_TAB => [
                'label'      => __('Glossary', 'chada-travel'),
                'capability' => self::CAPABILITY,
                'render'     => [self::class, 'render_glossary_tab'],
            ],
        ];
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_instructions_tabs', $chada_travel_tabs)
            : $chada_travel_tabs;
    }

    public static function render_page(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view this page.', 'chada-travel'));
        }

        $chada_travel_tabs = self::get_tabs();

        CHADA_TRAVEL_Template::output('admin/shared/page-header', [
            'chada_travel_title'      => __('Instructions', 'chada-travel'),
            'chada_travel_badge_html' => '',
        ]);
        self::render_tablist($chada_travel_tabs);
        foreach ($chada_travel_tabs as $chada_travel_key => $chada_travel_definition) {
            echo '<div class="chada-travel-tabpanel" id="' . esc_attr($chada_travel_key) . '" role="tabpanel"'
                . ' aria-labelledby="chada-travel-instructions-tab-' . esc_attr($chada_travel_key) . '">';
            ($chada_travel_definition['render'])();
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Every tab is included un-hidden here - the "only one panel visible" behavior is applied by
     * assets/js/chada-travel-admin.js on top of this, never relied on to hide the other 6 from a visitor with
     * JavaScript disabled. `selected` marks DEFAULT_TAB only; that JS corrects the selected/visible tab from
     * the URL hash once it runs.
     *
     * @param array<string, array{label: string, capability: string, render: callable}> $chada_travel_tabs
     */
    private static function render_tablist(array $chada_travel_tabs): void {
        $chada_travel_tab_list = [];
        foreach ($chada_travel_tabs as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_tab_list[] = [
                'key'      => $chada_travel_key,
                'label'    => $chada_travel_definition['label'],
                'selected' => $chada_travel_key === self::DEFAULT_TAB,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/instructions/shared/tablist', ['chada_travel_tabs' => $chada_travel_tab_list]);
    }

    // ============================================================ GETTING STARTED TAB

    public static function render_getting_started_tab(): void {
        $chada_travel_links = self::build_links();
        CHADA_TRAVEL_Template::output('admin/instructions/getting-started', [
            'chada_travel_general_url'   => $chada_travel_links['general'],
            'chada_travel_policies_url'  => $chada_travel_links['policies_consent'],
            'chada_travel_payment_url'   => $chada_travel_links['payment_method'],
            'chada_travel_pages_url'     => $chada_travel_links['pages_links'],
            'chada_travel_email_url'     => $chada_travel_links['email'],
            'chada_travel_documents_url' => $chada_travel_links['documents_uploads'],
            'chada_travel_workflow_url'  => $chada_travel_links['booking_workflow'],
            'chada_travel_privacy_url'   => $chada_travel_links['data_privacy'],
            'chada_travel_tours_url'     => $chada_travel_links['tours'],
            'chada_travel_visa_countries_settings_url' => $chada_travel_links['visa_countries_settings'],
            'chada_travel_tour_destinations_url' => $chada_travel_links['tour_destinations'],
            'chada_travel_tour_types_url' => $chada_travel_links['tour_types'],
            'chada_travel_wizard_url'    => $chada_travel_links['wizard'],
            'chada_travel_tour_url'      => $chada_travel_links['tour_launch'],
        ]);
    }

    // ============================================================ SHORTCODES TAB

    public static function render_shortcodes_tab(): void {
        $chada_travel_links = self::build_links();
        CHADA_TRAVEL_Template::output('admin/instructions/shortcodes', [
            'chada_travel_pages_url' => $chada_travel_links['pages_links'],
        ]);
    }

    // ============================================================ CHECKOUT JOURNEY TAB

    public static function render_checkout_journey_tab(): void {
        CHADA_TRAVEL_Template::output('admin/instructions/checkout-journey');
    }

    // ============================================================ PAYMENT SETUP TAB

    public static function render_payment_setup_tab(): void {
        $chada_travel_links = self::build_links();
        CHADA_TRAVEL_Template::output('admin/instructions/payment-setup', [
            'chada_travel_payment_url'        => $chada_travel_links['payment_method'],
            'chada_travel_payment_review_url' => $chada_travel_links['payment_review'],
        ]);
    }

    // ============================================================ TROUBLESHOOTING TAB

    public static function render_troubleshooting_tab(): void {
        $chada_travel_links = self::build_links();
        CHADA_TRAVEL_Template::output('admin/instructions/troubleshooting', [
            'chada_travel_general_url'        => $chada_travel_links['general'],
            'chada_travel_policies_url'       => $chada_travel_links['policies_consent'],
            'chada_travel_payment_review_url' => $chada_travel_links['payment_review'],
        ]);
    }

    // ============================================================ GLOSSARY TAB

    public static function render_glossary_tab(): void {
        CHADA_TRAVEL_Template::output('admin/instructions/glossary');
    }

    /**
     * Every Settings-tab, Visa Countries, and Payment Review URL is included only when the current user
     * actually holds that destination's own capability - mirroring
     * CHADA_TRAVEL_Admin_Dashboard::render_setup_status_section() - so this page never renders a link to a screen the
     * viewer would immediately be denied. Dashboard and Bookings share this screen's own base capability, so they
     * are always included.
     *
     * @return array<string, string> Destination key => admin URL, or '' when not permitted.
     */
    private static function build_links(): array {
        $chada_travel_can_manage_settings = current_user_can(self::SETTINGS_CAPABILITY);
        $chada_travel_can_verify_payments = current_user_can(self::PAYMENT_CAPABILITY);

        return [
            'general'           => $chada_travel_can_manage_settings ? self::settings_tab_url('general') : '',
            'payment_method'    => $chada_travel_can_manage_settings ? self::settings_tab_url('payment-method') : '',
            'email'             => $chada_travel_can_manage_settings ? self::settings_tab_url('email') : '',
            'pages_links'       => $chada_travel_can_manage_settings ? self::settings_tab_url('pages-links') : '',
            'policies_consent'  => $chada_travel_can_manage_settings ? self::settings_tab_url('policies-consent') : '',
            'documents_uploads' => $chada_travel_can_manage_settings ? self::settings_tab_url('documents-uploads') : '',
            'booking_workflow'  => '',
            'data_privacy'      => '',
            'tours_settings'   => $chada_travel_can_manage_settings ? self::settings_tab_url('tours') : '',
            'visa_countries_settings' => $chada_travel_can_manage_settings
                ? self::settings_tab_url('visa-countries')
                : '',
            'visa_countries'    => $chada_travel_can_manage_settings
                ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Visa_Countries::MENU_SLUG)
                : '',
            'tours'             => current_user_can('manage_chada_travel_tours')
                ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Tours::MENU_SLUG)
                : '',
            'tour_destinations' => current_user_can('manage_chada_travel_tours')
                ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Tour_Terms::DESTINATIONS_MENU_SLUG)
                : '',
            'tour_types'        => current_user_can('manage_chada_travel_tours') && CHADA_TRAVEL_Extension_Manager::is_pro_active()
                ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Tour_Terms::TYPES_MENU_SLUG)
                : '',
            'payment_review'    => $chada_travel_can_verify_payments
                ? admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Payment_Review::MENU_SLUG)
                : '',
            'dashboard'         => admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Dashboard::MENU_SLUG),
            'bookings'          => admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG),
            'record_grid'       => '',
            'wizard'            => '',
            'tour_launch'       => '',
        ];
    }

    private static function settings_tab_url(string $chada_travel_tab): string {
        return admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Settings::MENU_SLUG . '&tab=' . $chada_travel_tab);
    }
}
