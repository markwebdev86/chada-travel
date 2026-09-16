<?php
/**
 * Single authoritative definition of every WordPress page the Settings "Pages & Links" tab lets an
 * administrator assign: the option name that stores the chosen page id, its label/description, the shortcode
 * tag the page must contain to be Ready, and the stable hardcoded path used as a fallback until a page is
 * configured (or becomes invalid again). CHADA_TRAVEL_Page_Settings, the Settings tab, the settings-data migration,
 * and CHADA_TRAVEL_Config's public URL helpers all read this one registry instead of duplicating these rules.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Page_Registry {
    public const CHECKOUT = 'checkout';
    public const PAYMENT_PROOF = 'payment_proof';
    public const DOCUMENT_UPLOAD = 'document_upload';
    public const TOUR_SEARCH_RESULTS = 'tour_search_results';

    /**
     * Structural definition only (option name, required shortcode, stable fallback path) - deliberately free of
     * any translation call, so this is safe to read from pure-PHP contexts with no WordPress i18n runtime
     * loaded (e.g. CHADA_TRAVEL_Config::get_default_settings(), exercised directly by PHPUnit). Administrator-facing
     * text lives in get_labels()/get_descriptions() instead, called only from real Settings-page rendering.
     *
     * @return array<string, array{option: string, shortcode: string, fallback_path: string, slug: string}>
     */
    public static function get_definitions(): array {
        return [
            self::CHECKOUT => [
                'option'        => 'chada_travel_checkout_page_id',
                'shortcode'     => CHADA_TRAVEL_Checkout::SHORTCODE_TAG,
                'fallback_path' => '/visa-application-checkout/',
                'slug'          => 'visa-application-checkout',
            ],
            self::PAYMENT_PROOF => [
                'option'        => 'chada_travel_payment_proof_page_id',
                'shortcode'     => CHADA_TRAVEL_Payment_Proof::SHORTCODE_TAG,
                'fallback_path' => '/bank-payment-proof/',
                'slug'          => 'bank-payment-proof',
            ],
            self::DOCUMENT_UPLOAD => [
                'option'        => 'chada_travel_document_upload_page_id',
                'shortcode'     => CHADA_TRAVEL_Document_Upload::SHORTCODE_TAG,
                'fallback_path' => '/visa-document-upload/',
                'slug'          => 'visa-document-upload',
            ],
            self::TOUR_SEARCH_RESULTS => [
                'option'        => 'chada_travel_tour_search_results_page_id',
                'shortcode'     => 'chada_travel_tour_search_results',
                'fallback_path' => '/tours/',
                'slug'          => 'tours',
            ],
        ];
    }

    /** @return array<string, int> Page-definition key => safe default (0, meaning "not selected"). */
    public static function get_option_defaults(): array {
        $chada_travel_defaults = [];
        foreach (self::get_definitions() as $chada_travel_definition) {
            $chada_travel_defaults[$chada_travel_definition['option']] = 0;
        }
        return $chada_travel_defaults;
    }

    /** @return array<string, string> Page-definition key => translated administrator-facing label. */
    public static function get_labels(): array {
        return [
            self::CHECKOUT        => __('Visa Application Checkout Page', 'chada-travel'),
            self::PAYMENT_PROOF   => __('Bank Payment Proof Page', 'chada-travel'),
            self::DOCUMENT_UPLOAD => __('Visa Document Upload Page', 'chada-travel'),
            self::TOUR_SEARCH_RESULTS => __('Tours Page', 'chada-travel'),
        ];
    }

    /** @return array<string, string> Page-definition key => translated administrator-facing description. */
    public static function get_descriptions(): array {
        return [
            self::CHECKOUT        => __(
                'Where Bookers start and continue the visa application checkout workflow.',
                'chada-travel'
            ),
            self::PAYMENT_PROOF   => __(
                'Where Bookers upload their Bank Deposit Slip proof of payment.',
                'chada-travel'
            ),
            self::DOCUMENT_UPLOAD => __(
                'Where paid Bookers view their Documents Checklist and upload visa documents.',
                'chada-travel'
            ),
            self::TOUR_SEARCH_RESULTS => __(
                'Where visitors browse and search active Tours. The plugin can create this Page automatically.',
                'chada-travel'
            ),
        ];
    }
}
