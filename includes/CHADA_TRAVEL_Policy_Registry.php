<?php
/**
 * Single authoritative definition of every policy the Settings "Policies & Consent" tab lets an administrator
 * configure: the option that stores an assigned WordPress Page id, the option that stores an external URL
 * fallback, the checkout consent stage the policy belongs to, whether it is required for the bundle to be
 * Ready, and the key it is stored under inside an order's immutable policy snapshot. CHADA_TRAVEL_Policy_Settings, the
 * Settings tab, the settings-data migration, checkout rendering, and the order snapshot builder all read this
 * one registry instead of duplicating these rules - mirrors CHADA_TRAVEL_Page_Registry's role for Pages & Links.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Policy_Registry {
    public const PRIVACY = 'privacy';
    public const TERMS = 'terms';
    public const CANCELLATION_REFUND = 'cancellation_refund';

    public const STAGE_ONE = 'stage_1';
    public const STAGE_THREE = 'stage_3';

    /**
     * Structural definition only (option names, consent stage, required flag, snapshot key) - deliberately free
     * of any translation call, so this is safe to read from pure-PHP contexts with no WordPress i18n runtime
     * loaded (e.g. CHADA_TRAVEL_Config::get_default_settings(), exercised directly by PHPUnit). Administrator-facing
     * text lives in get_labels() instead, called only from real Settings-page/checkout rendering.
     *
     * @return array<string, array{
     *     page_option: string, url_option: string, stage: string, required: bool, snapshot_key: string, slug: string
     * }>
     */
    public static function get_definitions(): array {
        return [
            self::PRIVACY => [
                'page_option'  => 'chada_travel_privacy_policy_page_id',
                'url_option'   => 'chada_travel_privacy_policy_url',
                'stage'        => self::STAGE_ONE,
                'required'     => true,
                'snapshot_key' => 'privacy',
                'slug'         => 'privacy-policy',
            ],
            self::TERMS => [
                'page_option'  => 'chada_travel_terms_conditions_page_id',
                'url_option'   => 'chada_travel_terms_conditions_url',
                'stage'        => self::STAGE_ONE,
                'required'     => true,
                'snapshot_key' => 'terms',
                'slug'         => 'terms-and-conditions',
            ],
            self::CANCELLATION_REFUND => [
                'page_option'  => 'chada_travel_cancellation_refund_page_id',
                'url_option'   => 'chada_travel_cancellation_refund_url',
                'stage'        => self::STAGE_THREE,
                'required'     => true,
                'snapshot_key' => 'cancellation_refund',
                'slug'         => 'cancellation-and-refund-policy',
            ],
        ];
    }

    /** @return array<string, int> Page-id option name => safe default (0, meaning "not selected"). */
    public static function get_option_defaults(): array {
        $chada_travel_defaults = [];
        foreach (self::get_definitions() as $chada_travel_definition) {
            $chada_travel_defaults[$chada_travel_definition['page_option']] = 0;
        }
        return $chada_travel_defaults;
    }

    /** @return array<string, string> Policy key => translated administrator-facing label. */
    public static function get_labels(): array {
        return [
            self::PRIVACY             => __('Privacy Policy', 'chada-travel'),
            self::TERMS                => __('Terms and Conditions', 'chada-travel'),
            self::CANCELLATION_REFUND  => __('Cancellation and Refund Policy', 'chada-travel'),
        ];
    }

    /** @return array<string, string> Policy key => translated administrator-facing description. */
    public static function get_descriptions(): array {
        return [
            self::PRIVACY             => __(
                'How customer data is collected and used. Shown together with Terms and Conditions at Stage 1.',
                'chada-travel'
            ),
            self::TERMS                => __(
                // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
                'The rules Bookers agree to when starting a visa application. Shown together with the Privacy Policy at Stage 1.',
                'chada-travel'
            ),
            self::CANCELLATION_REFUND  => __(
                'What happens if a Booker cancels or requests a refund. Acknowledged separately at Stage 3.',
                'chada-travel'
            ),
        ];
    }
}
