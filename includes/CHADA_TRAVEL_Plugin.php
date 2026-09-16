<?php
/**
 * Plugin hook coordinator.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Plugin {
    private static ?self $chada_travel_instance = null;
    private bool $chada_travel_running = false;

    public static function instance(): self {
        if (!self::$chada_travel_instance) {
            self::$chada_travel_instance = new self();
        }
        return self::$chada_travel_instance;
    }

    /** Registers Phase 0 foundation hooks plus the Phase 1 Stage 1-3 checkout shortcode and REST routes. */
    public function run(): void {
        if ($this->chada_travel_running) {
            return;
        }
        $this->chada_travel_running = true;
        // Deferred to `init` (priority 20, after core's own priority-0 create_initial_post_types()) rather than
        // called synchronously here at `plugins_loaded`: the Pages & Links settings-data migration
        // (CHADA_TRAVEL_Settings_Repository::migrate_page_settings()) queries Pages and calls url_to_postid(), neither
        // of which is reliable before the 'page' post type is registered and $wp_rewrite exists - both true only
        // from `init` onward. Every other hook this method registers below already fires after `init` on a real
        // request, so this ordering change is otherwise unobservable.
        add_action('init', [CHADA_TRAVEL_Installer::class, 'maybe_upgrade'], 20);
        add_action('init', [CHADA_TRAVEL_Extension_Manager::class, 'register_extensions'], 5);
        // Mint the page-bound guest token before template output so the Set-Cookie header is not lost when the
        // checkout shortcode later localizes the same token for REST request binding.
        add_action('template_redirect', static function (): void {
            CHADA_TRAVEL_Config::get_guest_request_token();
        }, 1);
        add_action('admin_init', [CHADA_TRAVEL_Config::class, 'register_settings']);
        add_action('admin_notices', [CHADA_TRAVEL_Readiness::class, 'render_admin_notices']);
        add_action('wp_enqueue_scripts', [$this, 'register_public_assets']);
        add_action('rest_api_init', [CHADA_TRAVEL_Rest_Controller::class, 'register_routes']);
        CHADA_TRAVEL_Checkout::register();
        CHADA_TRAVEL_Featured_Tours::register();
        CHADA_TRAVEL_Tour_Search::register();
        CHADA_TRAVEL_Tour_Public::register();
        CHADA_TRAVEL_Tour_Term_Public::register();
        CHADA_TRAVEL_Payment_Proof::register();
        CHADA_TRAVEL_Document_Upload::register();
        CHADA_TRAVEL_Admin_Menu::register();
        CHADA_TRAVEL_Admin_Dashboard::register();
        CHADA_TRAVEL_Admin_Tours::register();
        CHADA_TRAVEL_Admin_Tour_Terms::register();
        CHADA_TRAVEL_Admin_Payment_Review::register();
        CHADA_TRAVEL_Admin_Visa_Countries::register();
        CHADA_TRAVEL_Admin_Bookings::register();
        CHADA_TRAVEL_Admin_Settings::register();
        CHADA_TRAVEL_Admin_Instructions::register();
        add_action('admin_enqueue_scripts', [CHADA_TRAVEL_Admin_Visa_Countries::class, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [CHADA_TRAVEL_Admin_Tours::class, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [CHADA_TRAVEL_Admin_Tour_Terms::class, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [CHADA_TRAVEL_Admin_Settings::class, 'enqueue_assets']);
        CHADA_TRAVEL_Email_Service::register();
        CHADA_TRAVEL_SMTP_Transport::register();
    }

    /** Registers centralized public assets and enqueues them only on pages using a project shortcode. */
    public function register_public_assets(): void {
        $chada_travel_style_version = self::public_asset_version('assets/css/chada-travel-components.css');
        wp_register_style(
            'chada-travel-checkout',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-components.css',
            [],
            $chada_travel_style_version
        );
        wp_register_style(
            'chada-travel-featured-tours',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-featured-tours.css',
            [],
            self::public_asset_version('assets/css/chada-travel-featured-tours.css')
        );
        wp_register_style(
            'chada-travel-featured-tours-sidebar',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-featured-tours-sidebar.css',
            [],
            self::public_asset_version('assets/css/chada-travel-featured-tours-sidebar.css')
        );
        wp_register_style(
            'chada-travel-tour-details',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-tour-details.css',
            [],
            self::public_asset_version('assets/css/chada-travel-tour-details.css')
        );
        wp_register_style(
            'chada-travel-tour-search',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/css/chada-travel-tour-search.css',
            ['chada-travel-featured-tours'],
            self::public_asset_version('assets/css/chada-travel-tour-search.css')
        );
        wp_register_style(
            'chada-travel-glightbox',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/vendor/glightbox/glightbox.min.css',
            [],
            '3.3.1'
        );
        wp_register_script(
            'chada-travel-glightbox',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/vendor/glightbox/glightbox.min.js',
            [],
            '3.3.1',
            true
        );
        wp_register_script(
            'chada-travel-checkout',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-checkout.js',
            ['chada-travel-glightbox'],
            self::public_asset_version('assets/js/chada-travel-checkout.js'),
            true
        );
        wp_register_script(
            'chada-travel-payment-proof',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-payment-proof.js',
            [],
            self::public_asset_version('assets/js/chada-travel-payment-proof.js'),
            true
        );
        wp_register_script(
            'chada-travel-upload-portal',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-document-upload.js',
            [],
            self::public_asset_version('assets/js/chada-travel-document-upload.js'),
            true
        );
        wp_register_script(
            'chada-travel-tour-details',
            CHADA_TRAVEL_PLUGIN_URL . 'assets/js/chada-travel-tour-details.js',
            [],
            self::public_asset_version('assets/js/chada-travel-tour-details.js'),
            true
        );

        $chada_travel_post    = is_singular() ? get_post() : null;
        $chada_travel_content = $chada_travel_post ? (string) $chada_travel_post->post_content : '';
        if ($chada_travel_post && has_shortcode($chada_travel_content, CHADA_TRAVEL_Checkout::SHORTCODE_TAG)) {
            self::enqueue_customer_assets(1);
        }
        $chada_travel_has_search_shortcode = $chada_travel_post && (
            has_shortcode($chada_travel_content, CHADA_TRAVEL_Tour_Search::FORM_SHORTCODE_TAG)
            || has_shortcode($chada_travel_content, CHADA_TRAVEL_Tour_Search::RESULTS_SHORTCODE_TAG)
        );
        if ($chada_travel_post && has_shortcode($chada_travel_content, CHADA_TRAVEL_Featured_Tours::SHORTCODE_TAG)) {
            wp_enqueue_style('chada-travel-featured-tours');
        }
        if ($chada_travel_post && has_shortcode($chada_travel_content, CHADA_TRAVEL_Featured_Tours::SIDEBAR_SHORTCODE_TAG)) {
            wp_enqueue_style('chada-travel-featured-tours-sidebar');
        }
        if ($chada_travel_has_search_shortcode) {
            wp_enqueue_style('chada-travel-tour-search');
            if (has_shortcode($chada_travel_content, CHADA_TRAVEL_Tour_Search::RESULTS_SHORTCODE_TAG)) {
                wp_enqueue_style('chada-travel-featured-tours');
            }
        }
        if (is_front_page() || is_home()) {
            wp_enqueue_style('chada-travel-featured-tours');
            wp_enqueue_style('chada-travel-featured-tours-sidebar');
            // Dynamic Query Loop/page-builder content can render the search shortcode outside singular post content.
            wp_enqueue_style('chada-travel-tour-search');
        }
        if (CHADA_TRAVEL_Tour_Public::is_detail_request()) {
            wp_enqueue_style('chada-travel-tour-details');
            wp_enqueue_script('chada-travel-tour-details');
        }
        if ($chada_travel_post && has_shortcode($chada_travel_content, CHADA_TRAVEL_Payment_Proof::SHORTCODE_TAG)) {
            self::enqueue_payment_proof_assets();
        }
        if ($chada_travel_post && has_shortcode($chada_travel_content, CHADA_TRAVEL_Document_Upload::SHORTCODE_TAG)) {
            self::enqueue_document_upload_assets();
        }
    }

    /**
     * Returns a cache-safe version that changes when a plugin asset file changes, so a browser that already
     * cached an old copy under CHADA_TRAVEL_VERSION's own unchanged query string (this repo's convention keeps that
     * constant fixed across most feature releases) refetches it instead of silently running stale JS/CSS
     * indefinitely. Also called by CHADA_TRAVEL_Admin_Settings for its own script registration.
     */
    public static function public_asset_version(string $chada_travel_relative_path): string {
        $chada_travel_modified = filemtime(CHADA_TRAVEL_PLUGIN_DIR . ltrim($chada_travel_relative_path, '/\\'));
        return false === $chada_travel_modified ? CHADA_TRAVEL_VERSION : CHADA_TRAVEL_VERSION . '.' . $chada_travel_modified;
    }

    /** Enqueues the shared shell with safe settings for an authorized current stage. */
    public static function enqueue_customer_assets(int $chada_travel_current_stage): void {
        wp_enqueue_style('chada-travel-checkout');
        wp_enqueue_style('chada-travel-glightbox');
        wp_add_inline_style('chada-travel-checkout', CHADA_TRAVEL_Style_Config::get_custom_properties());
        wp_enqueue_script('chada-travel-checkout');
        wp_localize_script(
            'chada-travel-checkout',
            'chadaTravelCheckoutSettings',
            CHADA_TRAVEL_Config::get_javascript_settings($chada_travel_current_stage)
        );
    }

    /** Enqueues the standalone Bank proof page assets, separate from the Stage 1-4 checkout bundle. */
    public static function enqueue_payment_proof_assets(): void {
        wp_enqueue_style('chada-travel-checkout');
        wp_add_inline_style('chada-travel-checkout', CHADA_TRAVEL_Style_Config::get_custom_properties());
        wp_enqueue_script('chada-travel-payment-proof');
        wp_localize_script(
            'chada-travel-payment-proof',
            'chadaTravelPaymentProofSettings',
            CHADA_TRAVEL_Config::get_payment_proof_javascript_settings()
        );
    }

    /** Enqueues the standalone visa-document upload page assets, separate from the Stage 1-4 checkout bundle. */
    public static function enqueue_document_upload_assets(): void {
        wp_enqueue_style('chada-travel-checkout');
        wp_add_inline_style('chada-travel-checkout', CHADA_TRAVEL_Style_Config::get_custom_properties());
        wp_enqueue_script('chada-travel-upload-portal');
        wp_localize_script(
            'chada-travel-upload-portal',
            'chadaTravelDocumentUploadSettings',
            CHADA_TRAVEL_Config::get_document_upload_javascript_settings()
        );
    }

    private function __construct() {
    }
}
