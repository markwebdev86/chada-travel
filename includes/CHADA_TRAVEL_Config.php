<?php
/**
 * Central business configuration and safe administrator settings.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

require_once __DIR__ . '/CHADA_TRAVEL_Extension_Manager.php';
require_once __DIR__ . '/CHADA_TRAVEL_Payment_Provider_Registry.php';

final class CHADA_TRAVEL_Config {
    public const SETTINGS_GROUP = 'chada_travel_settings';
    public const DEFAULT_CURRENCY = 'USD';
    public const TOUR_DEFAULT_CURRENCY = 'USD';
    public const TOUR_FEATURED_IMAGE_DEFAULT_WIDTH = 1366;
    public const TOUR_FEATURED_IMAGE_DEFAULT_HEIGHT = 1024;
    public const TOUR_FEATURED_IMAGE_DEFAULT_MAX_MB = 3;
    public const VISA_GUIDE_IMAGE_DEFAULT_WIDTH = 1000;
    public const VISA_GUIDE_IMAGE_DEFAULT_HEIGHT = 1600;
    public const VISA_GUIDE_IMAGE_DEFAULT_MAX_MB = 3;
    public const VISA_GUIDE_IMAGE_MAX_MB = 3;
    public const DEFAULT_UPLOAD_MAX_BYTES = 5242880;
    public const DEFAULT_UPLOAD_TOKEN_HOURS = 72;
    public const DEFAULT_PROOF_TOKEN_HOURS = 48;
    private const GUEST_REQUEST_COOKIE = 'chada_travel_guest_request';
    private const GUEST_REQUEST_TTL = 7200;

    /** Returns the page-bound guest request token used alongside WordPress's guest nonce. */
    public static function get_guest_request_token(): string {
        $chada_travel_existing = self::guest_cookie_value();
        if ($chada_travel_existing !== '') {
            return $chada_travel_existing;
        }

        $chada_travel_token = bin2hex(random_bytes(32));
        if (!headers_sent()) {
            setcookie(self::GUEST_REQUEST_COOKIE, $chada_travel_token, [
                'expires'  => time() + self::GUEST_REQUEST_TTL,
                'path'     => defined('COOKIEPATH') && COOKIEPATH !== '' ? COOKIEPATH : '/',
                'secure'   => function_exists('is_ssl') && is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[self::GUEST_REQUEST_COOKIE] = $chada_travel_token;
        return $chada_travel_token;
    }

    /** Checks the page-bound guest token; the nonce remains a separate CSRF control, never authentication. */
    public static function has_valid_guest_request_token(string $chada_travel_provided): bool {
        $chada_travel_expected = self::guest_cookie_value();
        return $chada_travel_expected !== '' && $chada_travel_provided !== ''
            && hash_equals($chada_travel_expected, $chada_travel_provided);
    }

    private static function guest_cookie_value(): string {
        $chada_travel_cookie = self::sanitize_request_cookie(self::GUEST_REQUEST_COOKIE);
        return preg_match('/\A[a-f0-9]{64}\z/', $chada_travel_cookie) ? $chada_travel_cookie : '';
    }

    /**
     * Returns supported payment methods in their production display order.
     *
     * @return array<string, string>
     */
    public static function get_payment_methods(): array {
        return CHADA_TRAVEL_Payment_Provider_Registry::get_payment_methods();
    }

    /**
     * Returns Stage 4 method-card labels in the registry's approved order. The Digital Wallet entry resolves to
     * the administrator-configured provider name
     * (e.g. "Maya") so the customer sees that name directly rather than a hard-coded generic provider, falling
     * back to the generic "Digital Wallet" label only while no provider name is configured yet (Incomplete).
     *
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array<string, string>
     */
    public static function get_payment_method_labels(array $chada_travel_settings): array {
        $chada_travel_wallet_name = trim((string) ($chada_travel_settings['chada_travel_digital_wallet_name'] ?? ''));
        $chada_travel_labels = [];
        foreach (array_keys(self::get_payment_methods()) as $chada_travel_method) {
            $chada_travel_labels[$chada_travel_method] = $chada_travel_method === 'chada_travel_digital_wallet'
                ? ($chada_travel_wallet_name !== '' ? $chada_travel_wallet_name : 'Digital Wallet')
                : ((string) (self::get_admin_method_labels([])[$chada_travel_method] ?? $chada_travel_method));
        }
        return $chada_travel_labels;
    }

    /**
     * Returns administrator-summary method labels for the currently registered providers (Settings status summary,
     * General tab enable/default controls, and the Payment Review/Dashboard/Bookings method filters). Each provider
     * keeps its fixed administrator label regardless of the configured provider name - distinct from
     * get_payment_method_labels(), which resolves the provider-name-aware customer-facing Stage 4 label instead.
     *
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array<string, string>
     */
    public static function get_admin_method_labels(array $chada_travel_settings): array {
        $chada_travel_labels = [
            'chada_travel_bank' => 'Bank Payment',
            'chada_travel_digital_wallet' => 'Digital Wallet',
        ];
        foreach (CHADA_TRAVEL_Payment_Provider_Registry::get_definitions() as $chada_travel_id => $chada_travel_definition) {
            $chada_travel_labels[$chada_travel_id] = $chada_travel_definition['label'];
        }
        return array_intersect_key($chada_travel_labels, self::get_payment_methods());
    }

    /**
     * Resolves the configured Digital Wallet QR image, falling back to the bundled MVP placeholder until a real
     * QR is set.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_digital_wallet_qr_url(array $chada_travel_settings): string {
        $chada_travel_attachment_id = (int) ($chada_travel_settings['chada_travel_digital_wallet_qr_attachment_id'] ?? 0);
        if ($chada_travel_attachment_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $chada_travel_url = wp_get_attachment_image_url($chada_travel_attachment_id, 'medium');
            if ($chada_travel_url) {
                return $chada_travel_url;
            }
        }
        $chada_travel_configured_url = (string) ($chada_travel_settings['chada_travel_digital_wallet_qr_url'] ?? '');
        if ($chada_travel_configured_url !== '') {
            return $chada_travel_configured_url;
        }
        return defined('CHADA_TRAVEL_PLUGIN_URL')
            ? CHADA_TRAVEL_PLUGIN_URL . 'assets/images/chada-travel-digital-wallet-qr-placeholder.jpg' : '';
    }

    /**
     * Returns active Company Bank Accounts in display order, browser-safe (no internal-only fields beyond the
     * stable id used to render each account card).
     *
     * @return list<array<string, mixed>>
     */
    public static function get_active_bank_accounts(): array {
        $chada_travel_accounts = array_values(array_filter(
            CHADA_TRAVEL_Settings_Repository::get_bank_accounts(),
            static fn(array $chada_travel_account): bool => !empty($chada_travel_account['is_active'])
        ));
        usort(
            $chada_travel_accounts,
            static fn(array $chada_travel_a, array $chada_travel_b): int =>
                (int) ($chada_travel_a['sort_order'] ?? 0) <=> (int) ($chada_travel_b['sort_order'] ?? 0)
        );
        return $chada_travel_accounts;
    }

    /**
     * Builds the public visa application checkout/entry page URL: the administrator-assigned Checkout Page
     * (see CHADA_TRAVEL_Page_Registry/CHADA_TRAVEL_Page_Settings) once it is genuinely Ready, otherwise the bundled
     * `/visa-application-checkout/` fallback page. Never carries a draft token or Booking ID - an in-progress session/browser draft token
     * remains the only authorized way to resume an existing checkout (see CHADA_TRAVEL_Page_Settings::resolve_url()).
     */
    public static function get_checkout_url(): string {
        return CHADA_TRAVEL_Page_Settings::resolve_url(CHADA_TRAVEL_Page_Registry::CHECKOUT, self::get_settings());
    }

    /** Builds the public Bank proof page URL, optionally with a one-time proof-link token query argument. */
    public static function get_payment_proof_url(string $chada_travel_token = ''): string {
        return CHADA_TRAVEL_Page_Settings::resolve_url(CHADA_TRAVEL_Page_Registry::PAYMENT_PROOF, self::get_settings(), $chada_travel_token);
    }

    /** Builds the public visa-document upload page URL, optionally with a one-time upload-token query argument. */
    public static function get_document_upload_url(string $chada_travel_token = ''): string {
        return CHADA_TRAVEL_Page_Settings::resolve_url(
            CHADA_TRAVEL_Page_Registry::DOCUMENT_UPLOAD,
            self::get_settings(),
            $chada_travel_token
        );
    }

    /**
     * Returns the controlled option defaults without sample business or payment data.
     *
     * @return array<string, mixed>
     */
    public static function get_default_settings(): array {
        return [
            'chada_travel_currency'                    => self::DEFAULT_CURRENCY,
            'chada_travel_tour_default_currency'       => self::TOUR_DEFAULT_CURRENCY,
            'chada_travel_tour_featured_image_width'   => self::TOUR_FEATURED_IMAGE_DEFAULT_WIDTH,
            'chada_travel_tour_featured_image_height'  => self::TOUR_FEATURED_IMAGE_DEFAULT_HEIGHT,
            'chada_travel_tour_featured_image_max_mb'  => self::TOUR_FEATURED_IMAGE_DEFAULT_MAX_MB,
            'chada_travel_visa_guide_image_width'      => self::VISA_GUIDE_IMAGE_DEFAULT_WIDTH,
            'chada_travel_visa_guide_image_height'     => self::VISA_GUIDE_IMAGE_DEFAULT_HEIGHT,
            'chada_travel_visa_guide_image_max_mb'     => self::VISA_GUIDE_IMAGE_DEFAULT_MAX_MB,
            'chada_travel_payment_methods'             => array_keys(self::get_payment_methods()),
            'chada_travel_default_payment_method'      => 'chada_travel_bank',
            'chada_travel_bank_account_details'         => [],
            'chada_travel_bank_accounts'                => [],
            'chada_travel_bank_payment_email'           => '',
            'chada_travel_digital_wallet_name'            => '',
            'chada_travel_digital_wallet_account_name'    => '',
            'chada_travel_digital_wallet_account_number'  => '',
            'chada_travel_digital_wallet_qr_attachment_id' => 0,
            'chada_travel_digital_wallet_qr_url'          => '',
            'chada_travel_country_fees'                 => [],
            'chada_travel_policy_version'               => '',
            'chada_travel_policy_effective_date'         => '',
            'chada_travel_privacy_policy_url'           => '',
            'chada_travel_privacy_policy_page_id'        => 0,
            'chada_travel_terms_conditions_url'         => '',
            'chada_travel_terms_conditions_page_id'      => 0,
            'chada_travel_cancellation_refund_url'      => '',
            'chada_travel_cancellation_refund_page_id'   => 0,
            'chada_travel_document_upload_mime_types'   => ['application/pdf', 'image/jpeg', 'image/png'],
            'chada_travel_payment_proof_mime_types'     => ['image/jpeg', 'image/png'],
            'chada_travel_upload_max_bytes'             => self::DEFAULT_UPLOAD_MAX_BYTES,
            'chada_travel_upload_token_hours'           => self::DEFAULT_UPLOAD_TOKEN_HOURS,
            'chada_travel_payment_proof_token_hours'    => self::DEFAULT_PROOF_TOKEN_HOURS,
            'chada_travel_booking_id_prefix'            => 'CHADA_TRAVEL',
            'chada_travel_transaction_id_prefix'        => 'CHADA_TRAVEL-TXN',
            'chada_travel_transaction_disclaimer'       => 'This page is a payment confirmation and transaction summary only. '
                . 'It is not an official receipt.',
            'chada_travel_email_from_name'              => '',
            'chada_travel_email_from_address'           => '',
            'chada_travel_email_reply_to_address'        => '',
            'chada_travel_email_admin_notifications_enabled' => 1,
            'chada_travel_email_admin_notification_address'  => '',
        ] + self::get_general_default_settings() + CHADA_TRAVEL_Page_Registry::get_option_defaults();
    }

    /** @return array<string, mixed> Options seeded by the standalone Free package. */
    public static function get_free_option_defaults(): array {
        return self::get_default_settings();
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, mixed>
     */
    public static function get_provider_secrets(string $chada_travel_provider, array $chada_travel_settings): array {
        if (!function_exists('apply_filters')) {
            return [];
        }
        return (array) apply_filters('chada_travel_provider_secrets', [], $chada_travel_provider, $chada_travel_settings);
    }

    /**
     * Returns the General Settings tab's option defaults. Values that depend on a WordPress runtime function
     * (home_url(), the site timezone, admin_email, get_bloginfo()) are deliberately stored empty/zero here and
     * resolved at read time in get_settings() instead, so this method - and clean plugin activation, which
     * writes these defaults verbatim via CHADA_TRAVEL_Seeder::seed_options() - never depends unconditionally on
     * WordPress being loaded.
     *
     * @return array<string, mixed>
     */
    private static function get_general_default_settings(): array {
        return [
            // Company Profile
            'chada_travel_company_name'                 => '',
            'chada_travel_company_legal_name'            => '',
            'chada_travel_company_logo_attachment_id'    => 0,
            'chada_travel_company_website_url'           => '',
            'chada_travel_company_country'               => 'PH',
            'chada_travel_company_tagline'               => '',
            // Company Contact Information
            'chada_travel_company_support_email'         => '',
            'chada_travel_company_phone'                 => '',
            'chada_travel_company_mobile'                => '',
            'chada_travel_company_address_line_1'        => '',
            'chada_travel_company_address_line_2'        => '',
            'chada_travel_company_city'                  => '',
            'chada_travel_company_province'              => '',
            'chada_travel_company_postal_code'           => '',
            'chada_travel_company_address_country'       => '',
            // Regional and Formatting Settings (chada_travel_currency above is reused, not duplicated)
            'chada_travel_company_timezone'              => '',
            'chada_travel_currency_display'              => 'code',
            'chada_travel_date_format'                   => '',
            'chada_travel_time_format'                   => '',
            // Operational Availability
            'chada_travel_company_status'                => 'maintenance',
            'chada_travel_company_maintenance_message'   => 'New online visa applications are temporarily unavailable. '
                . 'Please contact support for assistance.',
        ];
    }

    /**
     * Returns current settings while preserving immutable code-owned project identity.
     *
     * @return array<string, mixed>
     */
    public static function get_settings(): array {
        $chada_travel_settings = [];
        foreach (self::get_default_settings() as $chada_travel_option_name => $chada_travel_default_value) {
            $chada_travel_settings[$chada_travel_option_name] = function_exists('get_option')
                ? get_option($chada_travel_option_name, $chada_travel_default_value) : $chada_travel_default_value;
        }
        $chada_travel_settings['chada_travel_payment_methods'] = self::sanitize_payment_methods(
            $chada_travel_settings['chada_travel_payment_methods']
        );
        $chada_travel_settings['chada_travel_default_payment_method'] = self::sanitize_default_payment_method(
            $chada_travel_settings['chada_travel_default_payment_method']
        );
        $chada_travel_settings = self::apply_dynamic_general_defaults($chada_travel_settings);
        $chada_travel_settings = self::apply_dynamic_policy_defaults($chada_travel_settings);
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_runtime_settings', $chada_travel_settings)
            : $chada_travel_settings;
    }

    /**
     * Backfills the General Settings values whose default depends on a WordPress runtime function - never
     * overwrites an administrator-stored value, only an empty one (see get_general_default_settings()).
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, mixed>
     */
    private static function apply_dynamic_general_defaults(array $chada_travel_settings): array {
        if ((string) $chada_travel_settings['chada_travel_company_name'] === '') {
            $chada_travel_bloginfo_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
            $chada_travel_settings['chada_travel_company_name'] =
                $chada_travel_bloginfo_name !== '' ? $chada_travel_bloginfo_name : CHADA_TRAVEL_PROJECT_NAME;
        }
        if ((string) $chada_travel_settings['chada_travel_company_tagline'] === '') {
            $chada_travel_settings['chada_travel_company_tagline'] = function_exists('get_bloginfo')
                ? (string) get_bloginfo('description') : '';
        }
        if ((string) $chada_travel_settings['chada_travel_company_website_url'] === '') {
            $chada_travel_settings['chada_travel_company_website_url'] = function_exists('home_url') ? home_url('/') : '';
        }
        if ((string) $chada_travel_settings['chada_travel_company_support_email'] === '') {
            $chada_travel_settings['chada_travel_company_support_email'] = function_exists('get_option')
                ? (string) get_option('admin_email', '') : '';
        }
        if ((string) $chada_travel_settings['chada_travel_company_timezone'] === '') {
            $chada_travel_settings['chada_travel_company_timezone'] = CHADA_TRAVEL_Settings_Repository::default_timezone();
        }
        if ((string) $chada_travel_settings['chada_travel_company_address_country'] === '') {
            $chada_travel_settings['chada_travel_company_address_country'] = (string) $chada_travel_settings['chada_travel_company_country'] !== ''
                ? (string) $chada_travel_settings['chada_travel_company_country'] : 'PH';
        }
        return $chada_travel_settings;
    }

    /**
     * Backfills Policy Version/Policy Effective Date to the install date the first time they are read - never
     * overwrites an administrator-stored value, only an empty one. Computed from CHADA_TRAVEL_Policy_Settings::
     * company_today(), the same Company-Timezone-aware "today" its own is_valid_effective_date() future-date
     * check uses, so a freshly seeded default can never itself read as "in the future". Must run after
     * apply_dynamic_general_defaults() has already resolved chada_travel_company_timezone.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, mixed>
     */
    private static function apply_dynamic_policy_defaults(array $chada_travel_settings): array {
        if ((string) $chada_travel_settings['chada_travel_policy_version'] === ''
            || (string) $chada_travel_settings['chada_travel_policy_effective_date'] === ''
        ) {
            $chada_travel_today = CHADA_TRAVEL_Policy_Settings::company_today($chada_travel_settings);
            if ((string) $chada_travel_settings['chada_travel_policy_version'] === '') {
                $chada_travel_settings['chada_travel_policy_version'] = substr($chada_travel_today, 0, 7);
            }
            if ((string) $chada_travel_settings['chada_travel_policy_effective_date'] === '') {
                $chada_travel_settings['chada_travel_policy_effective_date'] = $chada_travel_today;
            }
        }
        return $chada_travel_settings;
    }

    /**
     * Returns the normalized Company Profile: name, legal name, logo URL, website URL, country, tagline.
     * The single authoritative read for customer-facing branding; consumers must not read the underlying
     * chada_travel_company_* options directly.
     *
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array{
     *     name: string, legal_name: string, logo_url: string, website_url: string, country: string, tagline: string
     * }
     */
    public static function get_company_profile(array $chada_travel_settings): array {
        return [
            'name'        => (string) ($chada_travel_settings['chada_travel_company_name'] ?? '') !== ''
                ? (string) $chada_travel_settings['chada_travel_company_name'] : CHADA_TRAVEL_PROJECT_NAME,
            'legal_name'  => (string) ($chada_travel_settings['chada_travel_company_legal_name'] ?? ''),
            'logo_url'    => self::get_company_logo_url($chada_travel_settings),
            'website_url' => self::get_company_website_url($chada_travel_settings),
            'country'     => (string) ($chada_travel_settings['chada_travel_company_country'] ?? 'PH'),
            'tagline'     => (string) ($chada_travel_settings['chada_travel_company_tagline'] ?? ''),
        ];
    }

    /**
     * Resolves the configured Company Logo, safely falling back to '' (never a broken image) when unset or the
     * attachment no longer exists.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_company_logo_url(array $chada_travel_settings): string {
        $chada_travel_attachment_id = (int) ($chada_travel_settings['chada_travel_company_logo_attachment_id'] ?? 0);
        if ($chada_travel_attachment_id > 0 && function_exists('wp_get_attachment_image_url')) {
            $chada_travel_url = wp_get_attachment_image_url($chada_travel_attachment_id, 'medium');
            if ($chada_travel_url) {
                return $chada_travel_url;
            }
        }
        return '';
    }

    /**
     * Resolves the Company Website URL, falling back to the internal WordPress home URL (get_settings() already
     * backfills this dynamic default, so this accessor is defensive for callers that build their own settings
     * array, e.g. tests).
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_company_website_url(array $chada_travel_settings): string {
        $chada_travel_url = (string) ($chada_travel_settings['chada_travel_company_website_url'] ?? '');
        return $chada_travel_url !== '' ? $chada_travel_url : (function_exists('home_url') ? home_url('/') : '/');
    }

    /**
     * Resolves the effective Company Timezone identifier, falling back to CHADA_TRAVEL_Settings_Repository's default
     * when the stored value is somehow not a valid named identifier (get_settings() already backfills this).
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_company_timezone(array $chada_travel_settings): string {
        $chada_travel_timezone = (string) ($chada_travel_settings['chada_travel_company_timezone'] ?? '');
        return CHADA_TRAVEL_Settings_Repository::is_valid_timezone($chada_travel_timezone)
            ? $chada_travel_timezone : CHADA_TRAVEL_Settings_Repository::default_timezone();
    }

    /**
     * Target Travel Date boundaries for Stage 2, computed in the Company Timezone rather than the browser's own
     * clock so a customer near a day boundary sees the same "today" the business does.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{min: string, default: string} Y-m-d.
     */
    public static function get_target_travel_date_bounds(array $chada_travel_settings): array {
        $chada_travel_now = new \DateTimeImmutable('now', new \DateTimeZone(self::get_company_timezone($chada_travel_settings)));
        return [
            'min'     => $chada_travel_now->modify('+1 day')->format('Y-m-d'),
            'default' => $chada_travel_now->modify('+14 days')->format('Y-m-d'),
        ];
    }

    /**
     * Returns only the configured Company Contact fields, keyed for display; a field is omitted entirely when
     * empty so consumers never render an empty row, separator, or punctuation for a missing value.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array<string, string>
     */
    public static function get_company_contact(array $chada_travel_settings): array {
        $chada_travel_fields = [
            'support_email'  => (string) ($chada_travel_settings['chada_travel_company_support_email'] ?? ''),
            'phone'          => (string) ($chada_travel_settings['chada_travel_company_phone'] ?? ''),
            'mobile'         => (string) ($chada_travel_settings['chada_travel_company_mobile'] ?? ''),
            'address_line_1' => (string) ($chada_travel_settings['chada_travel_company_address_line_1'] ?? ''),
            'address_line_2' => (string) ($chada_travel_settings['chada_travel_company_address_line_2'] ?? ''),
            'city'           => (string) ($chada_travel_settings['chada_travel_company_city'] ?? ''),
            'province'       => (string) ($chada_travel_settings['chada_travel_company_province'] ?? ''),
            'postal_code'    => (string) ($chada_travel_settings['chada_travel_company_postal_code'] ?? ''),
            'country'        => (string) ($chada_travel_settings['chada_travel_company_address_country'] ?? ''),
        ];
        return array_filter($chada_travel_fields, static fn(string $chada_travel_value): bool => $chada_travel_value !== '');
    }

    /**
     * Formats the configured Company Address as a single comma-separated line, omitting empty parts and never
     * producing a stray/leading/trailing comma when some parts are missing.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_company_address(array $chada_travel_settings): string {
        $chada_travel_contact = self::get_company_contact($chada_travel_settings);
        $chada_travel_parts   = array_intersect_key(
            $chada_travel_contact,
            array_flip(['address_line_1', 'address_line_2', 'city', 'province', 'postal_code', 'country'])
        );
        return implode(', ', $chada_travel_parts);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function get_currency_code(array $chada_travel_settings): string {
        return (string) ($chada_travel_settings['chada_travel_currency'] ?? self::DEFAULT_CURRENCY);
    }

    /**
     * Returns the curated General Settings currency catalog in administrator display order.
     *
     * @return array<string, string> Currency code => translated display name.
     */
    public static function get_currency_options(): array {
        $chada_travel_currency_names = [
            'USD' => function_exists('__') ? __('US Dollar', 'chada-travel') : 'US Dollar',
            'PHP' => function_exists('__') ? __('Philippine Peso', 'chada-travel') : 'Philippine Peso',
            'EUR' => function_exists('__') ? __('Euro', 'chada-travel') : 'Euro',
            'GBP' => function_exists('__') ? __('Pound Sterling', 'chada-travel') : 'Pound Sterling',
            'JPY' => function_exists('__') ? __('Japanese Yen', 'chada-travel') : 'Japanese Yen',
            'AUD' => function_exists('__') ? __('Australian Dollar', 'chada-travel') : 'Australian Dollar',
            'CAD' => function_exists('__') ? __('Canadian Dollar', 'chada-travel') : 'Canadian Dollar',
            'SGD' => function_exists('__') ? __('Singapore Dollar', 'chada-travel') : 'Singapore Dollar',
            'HKD' => function_exists('__') ? __('Hong Kong Dollar', 'chada-travel') : 'Hong Kong Dollar',
            'CNY' => function_exists('__') ? __('Chinese Yuan', 'chada-travel') : 'Chinese Yuan',
            'CHF' => function_exists('__') ? __('Swiss Franc', 'chada-travel') : 'Swiss Franc',
            'NZD' => function_exists('__') ? __('New Zealand Dollar', 'chada-travel') : 'New Zealand Dollar',
            'KRW' => function_exists('__') ? __('South Korean Won', 'chada-travel') : 'South Korean Won',
            'INR' => function_exists('__') ? __('Indian Rupee', 'chada-travel') : 'Indian Rupee',
            'THB' => function_exists('__') ? __('Thai Baht', 'chada-travel') : 'Thai Baht',
            'MYR' => function_exists('__') ? __('Malaysian Ringgit', 'chada-travel') : 'Malaysian Ringgit',
            'IDR' => function_exists('__') ? __('Indonesian Rupiah', 'chada-travel') : 'Indonesian Rupiah',
            'VND' => function_exists('__') ? __('Vietnamese Dong', 'chada-travel') : 'Vietnamese Dong',
            'ZAR' => function_exists('__') ? __('South African Rand', 'chada-travel') : 'South African Rand',
            'BRL' => function_exists('__') ? __('Brazilian Real', 'chada-travel') : 'Brazilian Real',
            'MXN' => function_exists('__') ? __('Mexican Peso', 'chada-travel') : 'Mexican Peso',
            'SEK' => function_exists('__') ? __('Swedish Krona', 'chada-travel') : 'Swedish Krona',
            'NOK' => function_exists('__') ? __('Norwegian Krone', 'chada-travel') : 'Norwegian Krone',
            'DKK' => function_exists('__') ? __('Danish Krone', 'chada-travel') : 'Danish Krone',
            'PLN' => function_exists('__') ? __('Polish Zloty', 'chada-travel') : 'Polish Zloty',
            'TRY' => function_exists('__') ? __('Turkish Lira', 'chada-travel') : 'Turkish Lira',
            'AED' => function_exists('__') ? __('UAE Dirham', 'chada-travel') : 'UAE Dirham',
            'SAR' => function_exists('__') ? __('Saudi Riyal', 'chada-travel') : 'Saudi Riyal',
            'ILS' => function_exists('__') ? __('Israeli New Shekel', 'chada-travel') : 'Israeli New Shekel',
            'RUB' => function_exists('__') ? __('Russian Ruble', 'chada-travel') : 'Russian Ruble',
            'TWD' => function_exists('__') ? __('Taiwan New Dollar', 'chada-travel') : 'Taiwan New Dollar',
            'CZK' => function_exists('__') ? __('Czech Koruna', 'chada-travel') : 'Czech Koruna',
            'HUF' => function_exists('__') ? __('Hungarian Forint', 'chada-travel') : 'Hungarian Forint',
            'RON' => function_exists('__') ? __('Romanian Leu', 'chada-travel') : 'Romanian Leu',
            'ISK' => function_exists('__') ? __('Icelandic Krona', 'chada-travel') : 'Icelandic Krona',
            'QAR' => function_exists('__') ? __('Qatari Riyal', 'chada-travel') : 'Qatari Riyal',
            'KWD' => function_exists('__') ? __('Kuwaiti Dinar', 'chada-travel') : 'Kuwaiti Dinar',
            'BHD' => function_exists('__') ? __('Bahraini Dinar', 'chada-travel') : 'Bahraini Dinar',
            'OMR' => function_exists('__') ? __('Omani Rial', 'chada-travel') : 'Omani Rial',
            'JOD' => function_exists('__') ? __('Jordanian Dinar', 'chada-travel') : 'Jordanian Dinar',
            'EGP' => function_exists('__') ? __('Egyptian Pound', 'chada-travel') : 'Egyptian Pound',
            'MAD' => function_exists('__') ? __('Moroccan Dirham', 'chada-travel') : 'Moroccan Dirham',
            'NGN' => function_exists('__') ? __('Nigerian Naira', 'chada-travel') : 'Nigerian Naira',
            'KES' => function_exists('__') ? __('Kenyan Shilling', 'chada-travel') : 'Kenyan Shilling',
            'PKR' => function_exists('__') ? __('Pakistani Rupee', 'chada-travel') : 'Pakistani Rupee',
            'BDT' => function_exists('__') ? __('Bangladeshi Taka', 'chada-travel') : 'Bangladeshi Taka',
            'CLP' => function_exists('__') ? __('Chilean Peso', 'chada-travel') : 'Chilean Peso',
            'COP' => function_exists('__') ? __('Colombian Peso', 'chada-travel') : 'Colombian Peso',
            'PEN' => function_exists('__') ? __('Peruvian Sol', 'chada-travel') : 'Peruvian Sol',
            'ARS' => function_exists('__') ? __('Argentine Peso', 'chada-travel') : 'Argentine Peso',
        ];
        return $chada_travel_currency_names;
    }

    /** @param string $chada_travel_currency */
    public static function is_curated_currency(string $chada_travel_currency): bool {
        return array_key_exists(strtoupper(trim($chada_travel_currency)), self::get_currency_options());
    }

    /**
     * Returns select options and preserves an existing valid code not present in the curated catalog.
     *
     * @return array<string, string> Currency code => translated display name.
     */
    public static function get_currency_options_for_select(string $chada_travel_current_currency = ''): array {
        $chada_travel_options = self::get_currency_options();
        $chada_travel_current_currency = strtoupper(trim($chada_travel_current_currency));
        if ($chada_travel_current_currency !== '' && preg_match('/^[A-Z]{3}$/', $chada_travel_current_currency)
            && !array_key_exists($chada_travel_current_currency, $chada_travel_options)
        ) {
            $chada_travel_options = [$chada_travel_current_currency => sprintf(
                /* translators: %s: an existing three-letter currency code. */
                function_exists('__') ? __('Current currency (%s)', 'chada-travel') : 'Current currency (%s)',
                $chada_travel_current_currency
            )] + $chada_travel_options;
        }
        return $chada_travel_options;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function get_booking_id_prefix(array $chada_travel_settings): string {
        return (string) ($chada_travel_settings['chada_travel_booking_id_prefix'] ?? 'CHADA_TRAVEL');
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function get_transaction_id_prefix(array $chada_travel_settings): string {
        return (string) ($chada_travel_settings['chada_travel_transaction_id_prefix'] ?? 'CHADA_TRAVEL-TXN');
    }

    /**
     * The one authoritative maintenance/readiness check; the customer checkout shortcode and the REST handler
     * that would otherwise create a new checkout draft must both call this instead of reading
     * chada_travel_company_status directly.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function is_accepting_new_applications(array $chada_travel_settings): bool {
        return (string) ($chada_travel_settings['chada_travel_company_status'] ?? 'active') !== 'maintenance';
    }

    /** @return array<string, string> Currency code => symbol, for currencies with a known safe display glyph. */
    private static function get_currency_symbols(): array {
        return [
            'PHP' => "\u{20B1}", 'USD' => '$', 'EUR' => "\u{20AC}", 'GBP' => "\u{00A3}", 'JPY' => "\u{00A5}",
            'AUD' => '$', 'CAD' => '$', 'SGD' => '$', 'HKD' => '$', 'CNY' => "\u{00A5}",
            'CHF' => 'CHF', 'NZD' => 'NZ$', 'KRW' => "\u{20A9}", 'INR' => "\u{20B9}",
            'THB' => "\u{0E3F}", 'MYR' => 'RM', 'IDR' => 'Rp', 'VND' => "\u{20AB}",
            'ZAR' => 'R', 'BRL' => 'R$', 'MXN' => 'MX$', 'SEK' => 'kr', 'NOK' => 'kr',
            'DKK' => 'kr', 'PLN' => "\u{007A}\u{0142}", 'TRY' => "\u{20BA}",
            'AED' => "\u{062F}.\u{0625}", 'SAR' => 'SAR', 'ILS' => "\u{20AA}", 'RUB' => "\u{20BD}",
            'TWD' => 'NT$', 'CZK' => "K\u{010D}", 'HUF' => 'Ft', 'RON' => 'lei', 'ISK' => 'kr',
            'NGN' => "\u{20A6}", 'KES' => 'KSh', 'PKR' => "\u{20A8}", 'BDT' => "\u{09F3}", 'PEN' => 'S/',
        ];
    }

    /**
     * Formats a monetary amount for display using the configured chada_travel_currency_display mode
     * (symbol/code/symbol_code). $chada_travel_currency_code is a caller-supplied explicit currency (e.g. a historical
     * order's stored chada_travel_currency), never re-derived from current settings, so a display change never alters
     * how an existing order/payment amount renders. Calculation/storage values must remain the untouched numeric
     * source of $chada_travel_amount; this method's output must never be parsed back into a calculation.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function format_money(
        float $chada_travel_amount,
        string $chada_travel_currency_code,
        array $chada_travel_settings
    ): string {
        $chada_travel_currency_code = strtoupper($chada_travel_currency_code) !== ''
            ? strtoupper($chada_travel_currency_code) : self::DEFAULT_CURRENCY;
        $chada_travel_display       = (string) ($chada_travel_settings['chada_travel_currency_display'] ?? 'code');
        $chada_travel_formatted     = number_format($chada_travel_amount, 2);
        $chada_travel_symbol        = self::get_currency_symbols()[$chada_travel_currency_code] ?? null;

        if ($chada_travel_symbol === null) {
            // Safe fallback for an unrecognized currency: the code is always a valid, unambiguous display.
            return $chada_travel_currency_code . ' ' . $chada_travel_formatted;
        }
        switch ($chada_travel_display) {
            case 'code':
                return $chada_travel_currency_code . ' ' . $chada_travel_formatted;
            case 'symbol_code':
                return $chada_travel_symbol . $chada_travel_formatted . ' ' . $chada_travel_currency_code;
            default:
                return $chada_travel_symbol . $chada_travel_formatted;
        }
    }

    /**
     * Converts a UTC-stored `Y-m-d H:i:s` database timestamp to the configured Company Timezone for display,
     * using the General Settings date/time format when set or the WordPress site format when "inherit". Never
     * rewrites the stored value - presentation only. An empty, zero, or unparseable prior timestamp fails safely
     * by returning '' (empty) or the original raw string rather than throwing.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @param 'date'|'time'|'datetime' $chada_travel_part
     */
    public static function format_utc_datetime(
        ?string $chada_travel_utc_datetime,
        array $chada_travel_settings,
        string $chada_travel_part = 'datetime'
    ): string {
        $chada_travel_value = trim((string) $chada_travel_utc_datetime);
        if ($chada_travel_value === '' || $chada_travel_value === '0000-00-00 00:00:00') {
            return '';
        }

        $chada_travel_utc = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $chada_travel_value, new \DateTimeZone('UTC'));
        if ($chada_travel_utc === false) {
            $chada_travel_timestamp = strtotime($chada_travel_value . ' UTC');
            if ($chada_travel_timestamp === false) {
                return $chada_travel_value;
            }
            $chada_travel_utc = new \DateTimeImmutable('@' . $chada_travel_timestamp);
        }

        $chada_travel_timezone = new \DateTimeZone(self::get_company_timezone($chada_travel_settings));
        $chada_travel_format   = self::resolve_datetime_format($chada_travel_settings, $chada_travel_part);
        $chada_travel_timestamp = $chada_travel_utc->getTimestamp();

        return function_exists('wp_date')
            ? (string) wp_date($chada_travel_format, $chada_travel_timestamp, $chada_travel_timezone)
            : $chada_travel_utc->setTimezone($chada_travel_timezone)->format($chada_travel_format);
    }

    /**
     * Formats the current moment using an arbitrary candidate date/time format string, converted to the
     * configured Company Timezone. This is the Settings page's live-preview helper only (illustrating what each
     * selectable Date/Time Format *option* would look like before it is saved); real stored business timestamps
     * must go through format_utc_datetime() instead.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function preview_datetime_format(string $chada_travel_candidate_format, array $chada_travel_settings): string {
        $chada_travel_timezone = new \DateTimeZone(self::get_company_timezone($chada_travel_settings));
        $chada_travel_now      = time();
        return function_exists('wp_date')
            ? (string) wp_date($chada_travel_candidate_format, $chada_travel_now, $chada_travel_timezone)
            : (new \DateTimeImmutable('@' . $chada_travel_now))->setTimezone($chada_travel_timezone)->format($chada_travel_candidate_format);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function resolve_datetime_format(array $chada_travel_settings, string $chada_travel_part): string {
        $chada_travel_date_format = (string) ($chada_travel_settings['chada_travel_date_format'] ?? '');
        if ($chada_travel_date_format === '') {
            $chada_travel_date_format = function_exists('get_option')
                ? (string) get_option('date_format', 'F j, Y') : 'F j, Y';
        }
        $chada_travel_time_format = (string) ($chada_travel_settings['chada_travel_time_format'] ?? '');
        if ($chada_travel_time_format === '') {
            $chada_travel_time_format = function_exists('get_option') ? (string) get_option('time_format', 'g:i a') : 'g:i a';
        }
        switch ($chada_travel_part) {
            case 'date':
                return $chada_travel_date_format;
            case 'time':
                return $chada_travel_time_format;
            default:
                return $chada_travel_date_format . ' ' . $chada_travel_time_format;
        }
    }

    /**
     * Resolves the effective email From-name: a meaningful explicit/custom chada_travel_email_from_name is always
     * preserved and used as-is; an empty value inherits the configured Company Name instead. Never writes back
     * to the option - resolution only, so a later Email tab can still tell the two cases apart. Company Support
     * Email must never be substituted here; this only resolves the display name, never chada_travel_email_from_address.
     * When even Company Name is blank (should not happen in practice - Company Name is a required General
     * Settings field - but this resolver must still fail safely), the ultimate fallback is the current product
     * name (CHADA_TRAVEL_PROJECT_NAME).
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function resolve_email_from_name(array $chada_travel_settings): string {
        $chada_travel_from_name = trim((string) ($chada_travel_settings['chada_travel_email_from_name'] ?? ''));
        if ($chada_travel_from_name !== '') {
            return $chada_travel_from_name;
        }
        $chada_travel_company_name = trim((string) ($chada_travel_settings['chada_travel_company_name'] ?? ''));
        return $chada_travel_company_name !== '' ? $chada_travel_company_name : CHADA_TRAVEL_PROJECT_NAME;
    }

    /**
     * Resolves the effective outgoing-email identity: From Name (see resolve_email_from_name()), From Address
     * (empty means omit the From header and let WordPress apply its own configured sender), and
     * Reply-To Address (empty means the Company Support Email from General Settings; still empty after that
     * means omit the Reply-To header entirely). This is the one authoritative resolver - no email template may
     * duplicate these inheritance rules; use build_email_headers() to turn this into wp_mail() headers.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{from_name: string, from_address: string, reply_to_address: string}
     */
    public static function resolve_email_identity(array $chada_travel_settings): array {
        $chada_travel_reply_to = trim((string) ($chada_travel_settings['chada_travel_email_reply_to_address'] ?? ''));
        if ($chada_travel_reply_to === '') {
            $chada_travel_reply_to = trim((string) ($chada_travel_settings['chada_travel_company_support_email'] ?? ''));
        }
        return [
            'from_name'        => self::resolve_email_from_name($chada_travel_settings),
            'from_address'     => trim((string) ($chada_travel_settings['chada_travel_email_from_address'] ?? '')),
            'reply_to_address' => $chada_travel_reply_to,
        ];
    }

    /**
     * Builds the wp_mail() header list from resolve_email_identity(): a From header only when an explicit From
     * Address is configured (otherwise WordPress applies its own default sender), and a Reply-To
     * header whenever a Reply-To Address resolves, independently of whether a From header is present. Shared by
     * every customer and administrator notification email so header-building rules are never duplicated per
     * template (see CHADA_TRAVEL_Email_Service).
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return list<string>
     */
    public static function build_email_headers(array $chada_travel_settings): array {
        $chada_travel_identity = self::resolve_email_identity($chada_travel_settings);
        $chada_travel_headers  = [];
        if ($chada_travel_identity['from_address'] !== '') {
            $chada_travel_headers[] = 'From: ' . $chada_travel_identity['from_name'] . ' <' . $chada_travel_identity['from_address'] . '>';
        }
        if ($chada_travel_identity['reply_to_address'] !== '') {
            $chada_travel_headers[] = 'Reply-To: ' . $chada_travel_identity['reply_to_address'];
        }
        return $chada_travel_headers;
    }

    /**
     * Resolves the effective administrator payment-review notification recipient: the configured Admin
     * Notification Email, falling back to the WordPress administrator email when blank. Single primary
     * recipient only - never a comma-separated list in this initial version.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function resolve_admin_notification_email(array $chada_travel_settings): string {
        $chada_travel_address = trim((string) ($chada_travel_settings['chada_travel_email_admin_notification_address'] ?? ''));
        if ($chada_travel_address !== '') {
            return $chada_travel_address;
        }
        return function_exists('get_option') ? (string) get_option('admin_email', '') : '';
    }

    /**
     * Builds a short plain-text company signature block for the plain-text notification emails: Company Name,
     * Website URL, and any configured Support Email/Phone/Mobile, one per line, omitting anything not configured.
     * Website URL always resolves to a safe fallback (see get_company_website_url()), so this never returns an
     * empty signature block.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function get_email_footer_text(array $chada_travel_settings): string {
        $chada_travel_lines = [];
        $chada_travel_name  = (string) ($chada_travel_settings['chada_travel_company_name'] ?? '');
        if ($chada_travel_name !== '') {
            $chada_travel_lines[] = $chada_travel_name;
        }
        $chada_travel_lines[] = self::get_company_website_url($chada_travel_settings);
        $chada_travel_contact = self::get_company_contact($chada_travel_settings);
        foreach (['support_email', 'phone', 'mobile'] as $chada_travel_key) {
            if (isset($chada_travel_contact[$chada_travel_key])) {
                $chada_travel_lines[] = $chada_travel_contact[$chada_travel_key];
            }
        }
        return "\n\n--\n" . implode("\n", $chada_travel_lines);
    }

    /**
     * Returns PHP-authoritative upload rules for each protected upload context. `max_bytes` is always the
     * effective, server-capped ceiling (see CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes()) - never
     * the raw configured `chada_travel_upload_max_bytes` value alone - so a hosting reduction below an already-saved
     * plugin limit is enforced immediately without rewriting the stored option. A per-requirement Visa document
     * upload additionally intersects/minimizes against its own record via
     * CHADA_TRAVEL_Upload_Settings::get_effective_requirement_rules() in CHADA_TRAVEL_Document_Service - this method's
     * `documents` entry is the plugin-wide ceiling only, not a specific requirement's effective rule.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_upload_rules(): array {
        $chada_travel_settings   = self::get_settings();
        $chada_travel_server_max = CHADA_TRAVEL_Upload_Settings::get_server_max_upload_bytes();
        return [
            'documents' => [
                'mime_types' => $chada_travel_settings['chada_travel_document_upload_mime_types'],
                'max_bytes'  => CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes(
                    $chada_travel_settings,
                    $chada_travel_server_max
                ),
                'max_files'  => 1,
            ],
            'payment_proof' => CHADA_TRAVEL_Upload_Settings::get_effective_proof_rules($chada_travel_settings, $chada_travel_server_max),
        ];
    }

    /**
     * Returns browser-safe, page-specific defaults. A caller supplies the authorized current stage.
     *
     * @return array<string, mixed>
     */
    public static function get_javascript_settings(int $chada_travel_current_stage = 1): array {
        $chada_travel_settings = self::get_settings();
        $chada_travel_travel_date_bounds = self::get_target_travel_date_bounds($chada_travel_settings);
        $chada_travel_browser_settings = [
            'restUrl'        => function_exists('rest_url') ? rest_url(CHADA_TRAVEL_REST_NAMESPACE . '/') : '',
            'checkoutNonce'  => function_exists('wp_create_nonce') ? wp_create_nonce('chada_travel_checkout_nonce') : '',
            'guestToken'     => self::get_guest_request_token(),
            'currentStage'   => max(1, min(5, $chada_travel_current_stage)),
            'currency'        => (string) $chada_travel_settings['chada_travel_currency'],
            'currencyDisplay' => (string) $chada_travel_settings['chada_travel_currency_display'],
            'uploadMaxBytes' => CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes($chada_travel_settings),
            'maxApplicants'  => CHADA_TRAVEL_Booking_Workflow_Config::max_applicants($chada_travel_settings),
            'draftExpiryHours' => CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours($chada_travel_settings),
            'minTravelDate'     => $chada_travel_travel_date_bounds['min'],
            'defaultTravelDate' => $chada_travel_travel_date_bounds['default'],
            'breakpoints'    => CHADA_TRAVEL_Style_Config::get_breakpoints(),
            'stages'         => array_map(
                static fn(int $chada_travel_stage, array $chada_travel_definition): array => [
                    'stage' => $chada_travel_stage, 'label' => $chada_travel_definition['label'],
                ],
                array_keys(array_slice(CHADA_TRAVEL_Workflow::get_checkout_stages(), 0, 4, true)),
                array_slice(CHADA_TRAVEL_Workflow::get_checkout_stages(), 0, 4, true)
            ),
            'countries'      => self::get_browser_countries($chada_travel_settings),
            'policyUrls'     => self::get_resolved_policy_urls($chada_travel_settings),
            'payment'        => [
                'methods'       => CHADA_TRAVEL_Payment_Readiness::get_enabled_ready_methods($chada_travel_settings),
                'methodLabels'  => self::get_payment_method_labels($chada_travel_settings),
                'defaultMethod' => CHADA_TRAVEL_Payment_Readiness::resolve_default_method($chada_travel_settings),
                'bankAccounts'  => self::get_active_bank_accounts(),
                'digitalWallet' => [
                    'name'          => (string) $chada_travel_settings['chada_travel_digital_wallet_name'],
                    'accountName'   => (string) $chada_travel_settings['chada_travel_digital_wallet_account_name'],
                    'accountNumber' => (string) $chada_travel_settings['chada_travel_digital_wallet_account_number'],
                    'qrUrl'         => self::get_digital_wallet_qr_url($chada_travel_settings),
                ],
                'proofMimeTypes'   => CHADA_TRAVEL_Upload_Settings::get_effective_proof_rules($chada_travel_settings)['mime_types'],
                'proofMaxBytes'    => CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes($chada_travel_settings),
                'proofNonce'       => function_exists('wp_create_nonce')
                    ? wp_create_nonce('chada_travel_payment_proof_nonce') : '',
            ],
            'disclaimer'      => (string) $chada_travel_settings['chada_travel_transaction_disclaimer'],
            'homeUrl'         => self::get_company_website_url($chada_travel_settings),
            'paymentProofUrl' => self::get_payment_proof_url(),
            'strings'         => self::get_checkout_strings(),
        ];
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_payment_browser_settings', $chada_travel_browser_settings, $chada_travel_settings)
            : $chada_travel_browser_settings;
    }

    /**
     * Resolves each policy's current effective public URL through CHADA_TRAVEL_Policy_Settings's source precedence
     * (a Ready WordPress Page, otherwise a valid external fallback, otherwise ''), for browser-safe localization
     * only. Never exposes a page id or which source is in use - just the resolved link.
     *
     * @param array<string, mixed> $chada_travel_settings
     * @return array{privacy: string, terms: string, cancellationRefund: string}
     */
    public static function get_resolved_policy_urls(array $chada_travel_settings): array {
        $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::resolve_effective_bundle($chada_travel_settings);
        return [
            'privacy'            => (string) ($chada_travel_bundle['privacy']['url'] ?? ''),
            'terms'              => (string) ($chada_travel_bundle['terms']['url'] ?? ''),
            'cancellationRefund' => (string) ($chada_travel_bundle['cancellation_refund']['url'] ?? ''),
        ];
    }

    /**
     * Browser-safe settings for the standalone Bank proof page, localized separately from the checkout SPA.
     *
     * @return array<string, mixed>
     */
    public static function get_payment_proof_javascript_settings(): array {
        $chada_travel_settings = self::get_settings();
        return [
            'restUrl'   => function_exists('rest_url') ? rest_url(CHADA_TRAVEL_REST_NAMESPACE . '/') : '',
            'proofNonce'=> function_exists('wp_create_nonce') ? wp_create_nonce('chada_travel_payment_proof_nonce') : '',
            'guestToken' => self::get_guest_request_token(),
            'maxBytes'  => CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes($chada_travel_settings),
            'mimeTypes' => CHADA_TRAVEL_Upload_Settings::get_effective_proof_rules($chada_travel_settings)['mime_types'],
            'homeUrl'   => self::get_company_website_url($chada_travel_settings),
            'strings'   => self::get_checkout_strings(),
        ];
    }

    /**
     * Browser-safe settings for the standalone visa-document upload page, localized separately from the
     * checkout SPA and authenticated by its own chada_travel_upload_nonce rather than the checkout nonce.
     *
     * @return array<string, mixed>
     */
    public static function get_document_upload_javascript_settings(): array {
        $chada_travel_settings = self::get_settings();
        return [
            'restUrl'    => function_exists('rest_url') ? rest_url(CHADA_TRAVEL_REST_NAMESPACE . '/') : '',
            'uploadNonce'=> function_exists('wp_create_nonce') ? wp_create_nonce('chada_travel_upload_nonce') : '',
            'guestToken' => self::get_guest_request_token(),
            'maxBytes'   => CHADA_TRAVEL_Upload_Settings::get_effective_global_max_bytes($chada_travel_settings),
            'mimeTypes'  => (array) $chada_travel_settings['chada_travel_document_upload_mime_types'],
            'homeUrl'    => self::get_company_website_url($chada_travel_settings),
            'strings'    => self::get_checkout_strings(),
        ];
    }

    /** @return array<string, string> Centralized customer-facing checkout copy. */
    public static function get_checkout_strings(): array {
        return [
            'ready'                 => 'Visa application controls are ready.',
            'stageBlocked'          => 'Complete the current stage before continuing.',
            'savingBooker'          => 'Saving Booker Details...',
            'savingApplications'    => 'Saving Visa Applications...',
            'savingReview'          => 'Saving your acknowledgement...',
            'genericError'          => 'Something went wrong. Please try again.',
            'duplicateWarning'      => 'This applicant and country combination has already been added.',
            'atLeastOneApplication' => 'Add at least one Visa Application before continuing.',
            'helpPanelTitle'        => 'Why we need this',
            'helpPanelBody'         => 'Booking contact, payment updates, and application messages.',
            'consentLabel'          => 'I agree to the Privacy Policy and Terms and Conditions.',
            'policyAckLabel'        => 'I acknowledge the Cancellation Policy and Refund Policy.',
            'addApplicant'          => '+ Add Another Applicant',
            'removeApplicant'       => 'Remove',
            'maxApplicantsReachedNotice' => 'You have reached the maximum of %d applicants for this booking.',
            'draftExpiredNotice'    => 'This booking session has expired. Start again from Visa Countries.',
            'browserDraftExpiredNotice' => 'Your saved browser session expired. '
                . 'Please start again from Visa Countries.',
            'emptyCartNotice'       => 'Apply at least one Visa Country before continuing.',
            'noCountriesNotice'     => 'No Visa Countries are currently available. Please check back later.',
            'missingGuideNotice'    => 'A step-by-step guide is not available for this country yet.',
            'missingDetailsNotice'  => 'Full Visa Country details are not available yet.',
            'cancelApplication'     => 'Cancel Application',
            'isBookerLabel'         => 'I am the visa applicant',
            'selectCountry'         => 'Select country',
            'editBookerDetails'     => 'Edit Booker Details',
            'editApplication'       => 'Edit',
            'estimatedTotal'        => 'Estimated Total',
            'orderTotal'            => 'Total',
            'notProvided'           => 'Not provided',
            'defaultBadge'          => 'Default',
            'guideNotice'           => 'Guides are emailed only after payment is confirmed.',
            'copyBankNotice'        => 'Copy these details before continuing.',
            'selectBankPayment'     => 'Select Bank Payment',
            'submitDigitalWalletPayment' => 'Submit Digital Wallet Payment Details',
            'digitalWalletReferenceLabel'   => 'Reference No.',
            'digitalWalletNameLabel'        => 'Digital Wallet Name',
            'digitalWalletAccountNameLabel' => 'Account Name',
            'digitalWalletAccountNumberLabel' => 'Account/Mobile Number',
            'digitalWalletFallbackTitle'    => 'Digital Wallet',
            'bankBookingNotice'     => 'Creates a Booking ID and sets Status: Awaiting Proof.',
            'digitalWalletBookingNotice' => 'Creates a Booking ID and sets Status: Awaiting Verification.',
            'awaitingProofBadge'    => 'Awaiting Proof',
            'awaitingVerificationBadge' => 'Awaiting Verification',
            'digitalWalletReferenceRequired' => 'Enter the Reference No. before submitting.',
            'savingPayment'         => 'Saving your payment selection...',
            'bookingIdLabel'        => 'Booking ID',
            'proofInstructionsTitle'    => 'Payment Instructions',
            'proofInstructionOne'       => 'Deposit to the Company Bank Account',
            'proofInstructionOneBody'   => 'Make the deposit for your visa application to our company bank account.',
            'proofInstructionTwo'       => 'Take a clear Deposit Slip picture',
            'proofInstructionTwoBody'   => 'Take a clear photo of the Deposit Slip showing the transaction details.',
            'proofInstructionThree'     => 'Enter Booking ID and upload proof',
            'proofInstructionThreeBody' => 'Enter your Booking ID and upload the Deposit Slip picture in the form.',
            'proofFormTitle'        => 'Submit Proof of Payment',
            'proofBookingIdLabel'   => 'Booking ID',
            'proofFileLabel'        => 'Payment Proof / Deposit Slip',
            'proofSubmit'           => 'Submit Proof of Payment',
            'proofSubmittedNotice'  => 'Status changed to Awaiting Verification and email notifications are sent.',
            'proofTransactionNotice'    => 'Unique Transaction ID is generated only after administrator confirmation.',
            'backToConfirmation'    => 'Back to Confirmation',
            'proofUseBookingId'     => 'Use the Booking ID from your confirmation email.',
            'paymentUnavailableNotice'  => 'Payment is temporarily unavailable. Please try again later or '
                . 'contact support.',
            'bankTitle'             => 'Bank Payment',
            'confirmationHeading'   => 'Stage 5: Confirmation',
            'paymentConfirmationHeading' => 'Payment Confirmation',
            'savedIdNotice'         => 'Save this ID for payment and application support.',
            'paidBadge'             => 'PAID',
            'paymentMethodLabel'    => 'Payment Method',
            'transactionIdLabel'    => 'Unique Transaction ID',
            'paymentDateLabel'      => 'Payment Date',
            'servicesPaidHeading'   => 'Services Paid',
            'serviceColumnLabel'    => 'Service',
            'amountColumnLabel'     => 'Amount',
            'uploadInitialFiles'    => 'Upload Initial Files',
            'viewGuideChecklists'   => 'View Guide & Checklists',
            'emailDeliveryNotice'   => 'Transaction ID, Step-by-Step Guide, and Documents Checklist sent by email.',
            'returnToHome'          => 'Return to Home',
            'returnToPayment'       => '← Return to Payment',
            'awaitingProofInstruction'        => 'Upload your Deposit Slip to move to Awaiting Verification.',
            'awaitingVerificationInstruction' => 'An administrator is verifying your payment.',
            'waitingForAdminVerification'     => 'Waiting for Admin Verification',
            'uploadBankPaymentProofAction'    => 'Upload Bank Payment Proof',
            'rejectedBadge'         => 'Rejected',
            'rejectedInstruction'   => 'We could not confirm this payment. Review the reason and try again.',
            'bookerLabel'           => 'Booker',
            'dateTimeLabel'         => 'Date/Time',
            'noPaidResourcesNotice' => 'No Unique Transaction ID or guides until administrator confirmation.',
            'requirementListTitle'      => 'Documents Checklist',
            'uploadDocumentAction'      => 'Upload File',
            'replaceDocumentAction'     => 'Replace File',
            'requirementRequiredLabel'  => 'Required',
            'requirementOptionalLabel'  => 'Optional',
            'noFileYetLabel'            => 'No file uploaded yet',
            'documentUploadedNotice'    => 'File uploaded.',
            'documentUploadFailedNotice'=> 'The file could not be uploaded.',
            'uploadLinkInvalidNotice'   => 'This upload link is invalid, expired, or has been revoked.',
        ];
    }

    /** Registers business-editable settings and centralized sanitizers. */
    public static function register_settings(): void {
        if (!function_exists('register_setting')) {
            return;
        }
        // phpcs:disable Generic.Files.LineLength.TooLong -- each explicit registration keeps WordPress's callback visible and statically callable.
        $chada_travel_defaults = self::get_default_settings();
        register_setting(self::SETTINGS_GROUP, 'chada_travel_currency', [
            'default' => $chada_travel_defaults['chada_travel_currency'], 'sanitize_callback' => [self::class, 'sanitize_currency'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_tour_default_currency', [
            'default' => $chada_travel_defaults['chada_travel_tour_default_currency'], 'sanitize_callback' => [self::class, 'sanitize_tour_currency'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_tour_featured_image_width', [
            'default' => $chada_travel_defaults['chada_travel_tour_featured_image_width'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_tour_featured_image_height', [
            'default' => $chada_travel_defaults['chada_travel_tour_featured_image_height'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_tour_featured_image_max_mb', [
            'default' => $chada_travel_defaults['chada_travel_tour_featured_image_max_mb'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_visa_guide_image_width', [
            'default' => $chada_travel_defaults['chada_travel_visa_guide_image_width'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_visa_guide_image_height', [
            'default' => $chada_travel_defaults['chada_travel_visa_guide_image_height'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_visa_guide_image_max_mb', [
            'default' => $chada_travel_defaults['chada_travel_visa_guide_image_max_mb'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_payment_methods', [
            'default' => $chada_travel_defaults['chada_travel_payment_methods'], 'sanitize_callback' => [self::class, 'sanitize_payment_methods'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_default_payment_method', [
            'default' => $chada_travel_defaults['chada_travel_default_payment_method'], 'sanitize_callback' => [self::class, 'sanitize_default_payment_method'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_bank_account_details', [
            'default' => $chada_travel_defaults['chada_travel_bank_account_details'], 'sanitize_callback' => [self::class, 'sanitize_bank_details'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_bank_accounts', [
            'default' => $chada_travel_defaults['chada_travel_bank_accounts'], 'sanitize_callback' => [self::class, 'sanitize_array_passthrough'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_bank_payment_email', [
            'default' => $chada_travel_defaults['chada_travel_bank_payment_email'], 'sanitize_callback' => [self::class, 'sanitize_email'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_digital_wallet_name', [
            'default' => $chada_travel_defaults['chada_travel_digital_wallet_name'], 'sanitize_callback' => [self::class, 'sanitize_digital_wallet_name'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_digital_wallet_account_name', [
            'default' => $chada_travel_defaults['chada_travel_digital_wallet_account_name'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_digital_wallet_account_number', [
            'default' => $chada_travel_defaults['chada_travel_digital_wallet_account_number'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_digital_wallet_qr_attachment_id', [
            'default' => $chada_travel_defaults['chada_travel_digital_wallet_qr_attachment_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_digital_wallet_qr_url', [
            'default' => $chada_travel_defaults['chada_travel_digital_wallet_qr_url'], 'sanitize_callback' => [self::class, 'sanitize_url'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_country_fees', [
            'default' => $chada_travel_defaults['chada_travel_country_fees'], 'sanitize_callback' => [self::class, 'sanitize_country_fees'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_policy_version', [
            'default' => $chada_travel_defaults['chada_travel_policy_version'], 'sanitize_callback' => [self::class, 'sanitize_policy_version'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_policy_effective_date', [
            'default' => $chada_travel_defaults['chada_travel_policy_effective_date'], 'sanitize_callback' => [self::class, 'sanitize_iso_date'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_privacy_policy_url', [
            'default' => $chada_travel_defaults['chada_travel_privacy_policy_url'], 'sanitize_callback' => [self::class, 'sanitize_url'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_privacy_policy_page_id', [
            'default' => $chada_travel_defaults['chada_travel_privacy_policy_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_terms_conditions_url', [
            'default' => $chada_travel_defaults['chada_travel_terms_conditions_url'], 'sanitize_callback' => [self::class, 'sanitize_url'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_terms_conditions_page_id', [
            'default' => $chada_travel_defaults['chada_travel_terms_conditions_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_cancellation_refund_url', [
            'default' => $chada_travel_defaults['chada_travel_cancellation_refund_url'], 'sanitize_callback' => [self::class, 'sanitize_url'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_cancellation_refund_page_id', [
            'default' => $chada_travel_defaults['chada_travel_cancellation_refund_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_document_upload_mime_types', [
            'default' => $chada_travel_defaults['chada_travel_document_upload_mime_types'], 'sanitize_callback' => [self::class, 'sanitize_document_mime_types'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_payment_proof_mime_types', [
            'default' => $chada_travel_defaults['chada_travel_payment_proof_mime_types'], 'sanitize_callback' => [self::class, 'sanitize_proof_mime_types'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_upload_max_bytes', [
            'default' => $chada_travel_defaults['chada_travel_upload_max_bytes'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_upload_token_hours', [
            'default' => $chada_travel_defaults['chada_travel_upload_token_hours'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_payment_proof_token_hours', [
            'default' => $chada_travel_defaults['chada_travel_payment_proof_token_hours'], 'sanitize_callback' => [self::class, 'sanitize_positive_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_booking_id_prefix', [
            'default' => $chada_travel_defaults['chada_travel_booking_id_prefix'], 'sanitize_callback' => [self::class, 'sanitize_prefix'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_transaction_id_prefix', [
            'default' => $chada_travel_defaults['chada_travel_transaction_id_prefix'], 'sanitize_callback' => [self::class, 'sanitize_prefix'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_transaction_disclaimer', [
            'default' => $chada_travel_defaults['chada_travel_transaction_disclaimer'], 'sanitize_callback' => [self::class, 'sanitize_textarea'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_email_from_name', [
            'default' => $chada_travel_defaults['chada_travel_email_from_name'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_email_from_address', [
            'default' => $chada_travel_defaults['chada_travel_email_from_address'], 'sanitize_callback' => [self::class, 'sanitize_email'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_email_reply_to_address', [
            'default' => $chada_travel_defaults['chada_travel_email_reply_to_address'], 'sanitize_callback' => [self::class, 'sanitize_email'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_email_admin_notifications_enabled', [
            'default' => $chada_travel_defaults['chada_travel_email_admin_notifications_enabled'], 'sanitize_callback' => [self::class, 'sanitize_bool_int'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_email_admin_notification_address', [
            'default' => $chada_travel_defaults['chada_travel_email_admin_notification_address'], 'sanitize_callback' => [self::class, 'sanitize_email'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_name', [
            'default' => $chada_travel_defaults['chada_travel_company_name'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_legal_name', [
            'default' => $chada_travel_defaults['chada_travel_company_legal_name'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_logo_attachment_id', [
            'default' => $chada_travel_defaults['chada_travel_company_logo_attachment_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_website_url', [
            'default' => $chada_travel_defaults['chada_travel_company_website_url'], 'sanitize_callback' => [self::class, 'sanitize_url'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_country', [
            'default' => $chada_travel_defaults['chada_travel_company_country'], 'sanitize_callback' => [self::class, 'sanitize_company_country'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_tagline', [
            'default' => $chada_travel_defaults['chada_travel_company_tagline'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_address_country', [
            'default' => $chada_travel_defaults['chada_travel_company_address_country'], 'sanitize_callback' => [self::class, 'sanitize_address_country'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_address_line_1', [
            'default' => $chada_travel_defaults['chada_travel_company_address_line_1'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_address_line_2', [
            'default' => $chada_travel_defaults['chada_travel_company_address_line_2'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_city', [
            'default' => $chada_travel_defaults['chada_travel_company_city'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_province', [
            'default' => $chada_travel_defaults['chada_travel_company_province'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_postal_code', [
            'default' => $chada_travel_defaults['chada_travel_company_postal_code'], 'sanitize_callback' => [self::class, 'sanitize_text'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_support_email', [
            'default' => $chada_travel_defaults['chada_travel_company_support_email'], 'sanitize_callback' => [self::class, 'sanitize_email'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_phone', [
            'default' => $chada_travel_defaults['chada_travel_company_phone'], 'sanitize_callback' => [self::class, 'sanitize_phone'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_mobile', [
            'default' => $chada_travel_defaults['chada_travel_company_mobile'], 'sanitize_callback' => [self::class, 'sanitize_phone'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_timezone', [
            'default' => $chada_travel_defaults['chada_travel_company_timezone'], 'sanitize_callback' => [self::class, 'sanitize_timezone'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_currency_display', [
            'default' => $chada_travel_defaults['chada_travel_currency_display'], 'sanitize_callback' => [self::class, 'sanitize_currency_display'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_date_format', [
            'default' => $chada_travel_defaults['chada_travel_date_format'], 'sanitize_callback' => [self::class, 'sanitize_date_format'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_time_format', [
            'default' => $chada_travel_defaults['chada_travel_time_format'], 'sanitize_callback' => [self::class, 'sanitize_time_format'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_status', [
            'default' => $chada_travel_defaults['chada_travel_company_status'], 'sanitize_callback' => [self::class, 'sanitize_company_status'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_company_maintenance_message', [
            'default' => $chada_travel_defaults['chada_travel_company_maintenance_message'], 'sanitize_callback' => [self::class, 'sanitize_textarea'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_checkout_page_id', [
            'default' => $chada_travel_defaults['chada_travel_checkout_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_payment_proof_page_id', [
            'default' => $chada_travel_defaults['chada_travel_payment_proof_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_document_upload_page_id', [
            'default' => $chada_travel_defaults['chada_travel_document_upload_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        register_setting(self::SETTINGS_GROUP, 'chada_travel_tour_search_results_page_id', [
            'default' => $chada_travel_defaults['chada_travel_tour_search_results_page_id'], 'sanitize_callback' => [self::class, 'sanitize_nonnegative_integer'], 'show_in_rest' => false,
        ]);
        // phpcs:enable Generic.Files.LineLength.TooLong
    }

    /**
     * Compact identifier: letters, numbers, periods, underscores, and hyphens only, capped at 40 characters.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_policy_version($chada_travel_value): string {
        $chada_travel_value = trim((string) $chada_travel_value);
        $chada_travel_value = preg_replace('/[^A-Za-z0-9._-]+/', '', $chada_travel_value) ?? '';
        return substr($chada_travel_value, 0, 40);
    }

    /**
     * A plain `Y-m-d`-shaped string, or '' when not recognizable; real calendar/future-date checks happen in
     * CHADA_TRAVEL_Policy_Settings::is_valid_effective_date(), not here.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_iso_date($chada_travel_value): string {
        $chada_travel_value = trim((string) $chada_travel_value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $chada_travel_value) ? $chada_travel_value : '';
    }

    /**
     * @param mixed $chada_travel_value
     * @return array<array-key, mixed>
     */
    public static function sanitize_array_passthrough($chada_travel_value): array {
        return is_array($chada_travel_value) ? $chada_travel_value : [];
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_text($chada_travel_value): string {
        if (is_array($chada_travel_value) || is_object($chada_travel_value)) {
            // A forged array/object submission must never reach an implicit string cast below (PHP emits an
            // "Array to string conversion" warning and would otherwise store the literal word "Array").
            $chada_travel_value = '';
        }
        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field((string) $chada_travel_value);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return trim(strip_tags((string) $chada_travel_value));
    }

    /**
     * Recursively unslashes and sanitizes a settings request while preserving selected secret scalar fields.
     *
     * @param mixed $chada_travel_value
     * @param list<string> $chada_travel_preserve_keys
     * @return array<string|int, mixed>
     */
    public static function sanitize_request_payload($chada_travel_value, array $chada_travel_preserve_keys = []): array {
        if (!is_array($chada_travel_value)) {
            return [];
        }
        $chada_travel_clean = [];
        foreach ($chada_travel_value as $chada_travel_key => $chada_travel_nested_value) {
            if (in_array((string) $chada_travel_key, $chada_travel_preserve_keys, true)) {
                $chada_travel_clean[$chada_travel_key] = self::sanitize_request_secret($chada_travel_nested_value);
                continue;
            }
            $chada_travel_clean[$chada_travel_key] = is_array($chada_travel_nested_value)
                ? self::sanitize_request_payload($chada_travel_nested_value, $chada_travel_preserve_keys)
                : self::sanitize_request_text($chada_travel_nested_value);
        }
        return $chada_travel_clean;
    }

    /**
     * Reads and immediately sanitizes one scalar request value as plain text.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_request_text($chada_travel_value): string {
        if (!is_scalar($chada_travel_value)) {
            return '';
        }
        if (function_exists('wp_unslash')) {
            $chada_travel_value = wp_unslash($chada_travel_value);
        }
        return self::sanitize_text($chada_travel_value);
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- callers verify write nonces; read-only routing values are handled by this centralized boundary.

    /**
     * Reads and sanitizes one scalar GET value as plain text.
     *
     * @param mixed $chada_travel_default
     */
    public static function sanitize_request_get(string $chada_travel_key, $chada_travel_default = ''): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- type-safe helper sanitizes immediately.
        return self::sanitize_request_text($_GET[$chada_travel_key] ?? $chada_travel_default);
    }

    /**
     * Reads and sanitizes one scalar POST value as plain text.
     *
     * @param mixed $chada_travel_default
     */
    public static function sanitize_request_post(string $chada_travel_key, $chada_travel_default = ''): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- type-safe helper sanitizes immediately.
        return self::sanitize_request_text($_POST[$chada_travel_key] ?? $chada_travel_default);
    }

    /**
     * Reads and sanitizes one scalar cookie value as plain text.
     *
     * @param mixed $chada_travel_default
     */
    public static function sanitize_request_cookie(string $chada_travel_key, $chada_travel_default = ''): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- type-safe helper sanitizes immediately.
        return self::sanitize_request_text($_COOKIE[$chada_travel_key] ?? $chada_travel_default);
    }

    /**
     * Reads and sanitizes one scalar POST value as allowed post content.
     *
     * @param mixed $chada_travel_default
     */
    public static function sanitize_request_post_rich_text(string $chada_travel_key, $chada_travel_default = ''): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- type-safe helper sanitizes immediately.
        return self::sanitize_request_rich_text($_POST[$chada_travel_key] ?? $chada_travel_default);
    }

    /**
     * Returns one posted array; callers must sanitize each field by its expected type.
     *
     * @return array<string|int, mixed>
     */
    public static function get_request_post_array(string $chada_travel_key): array {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each returned field is sanitized by its caller.
        $chada_travel_value = $_POST[$chada_travel_key] ?? [];
        if (!is_array($chada_travel_value)) {
            return [];
        }
        return $chada_travel_value;
    }

    /**
     * Reads and sanitizes the complete POST payload before a settings validator or extension sees it.
     *
     * @param list<string> $chada_travel_preserve_keys
     * @return array<string|int, mixed>
     */
    public static function sanitize_request_post_payload(array $chada_travel_preserve_keys = []): array {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- recursive helper sanitizes every field by scalar type.
        return self::sanitize_request_payload($_POST, $chada_travel_preserve_keys);
    }

    /**
     * Reads and immediately sanitizes one scalar request value as allowed post content.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_request_rich_text($chada_travel_value): string {
        if (!is_scalar($chada_travel_value)) {
            return '';
        }
        if (function_exists('wp_unslash')) {
            $chada_travel_value = wp_unslash($chada_travel_value);
        }
        return function_exists('wp_kses_post') ? wp_kses_post((string) $chada_travel_value) : self::sanitize_text($chada_travel_value);
    }

    /**
     * Reads and immediately sanitizes one scalar request value as a WordPress key.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_request_key($chada_travel_value): string {
        $chada_travel_value = self::sanitize_request_text($chada_travel_value);
        return function_exists('sanitize_key') ? sanitize_key($chada_travel_value) : strtolower($chada_travel_value);
    }

    /**
     * Reads and immediately sanitizes one scalar request value as a non-negative integer.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_request_int($chada_travel_value): int {
        return max(0, (int) self::sanitize_request_text($chada_travel_value));
    }

    /**
     * Unslashes a scalar secret before the owning validator encrypts or stores it.
     *
     * @param mixed $chada_travel_value
     */
    private static function sanitize_request_secret($chada_travel_value): string {
        if (!is_scalar($chada_travel_value)) {
            return '';
        }
        return function_exists('wp_unslash') ? (string) wp_unslash($chada_travel_value) : (string) $chada_travel_value;
    }

    /** Reads and immediately sanitizes one scalar server value for a narrowly scoped request decision. */
    public static function sanitize_server_value(string $chada_travel_key): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- scalar is type-checked and sanitized immediately below.
        if (!isset($_SERVER[$chada_travel_key]) || !is_scalar($_SERVER[$chada_travel_key])) {
            return '';
        }
        return self::sanitize_request_text($_SERVER[$chada_travel_key]);
    }

    // phpcs:enable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification

    /**
     * Plain-text, multibyte-safe, capped at 50 characters - the Digital Wallet Name field's defensive backstop
     * sanitizer for any update_option() call outside the primary CHADA_TRAVEL_Payment_Settings_Validator rejection path
     * (which never silently truncates; see validate_digital_wallet()). Forged array/object input is neutralized
     * to '' by sanitize_text() above rather than triggering a PHP warning.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_digital_wallet_name($chada_travel_value): string {
        return mb_substr(self::sanitize_text($chada_travel_value), 0, 50);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_email($chada_travel_value): string {
        return function_exists('sanitize_email') ? sanitize_email((string) $chada_travel_value)
            : (string) filter_var((string) $chada_travel_value, FILTER_SANITIZE_EMAIL);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_url($chada_travel_value): string {
        return function_exists('esc_url_raw') ? esc_url_raw((string) $chada_travel_value)
            : (string) filter_var((string) $chada_travel_value, FILTER_SANITIZE_URL);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_currency($chada_travel_value): string {
        $chada_travel_currency = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $chada_travel_value) ?? '');
        return strlen($chada_travel_currency) === 3 ? $chada_travel_currency : self::DEFAULT_CURRENCY;
    }

    /** @return list<string> Currency codes available for individual Tour records. */
    public static function get_tour_currency_options(): array {
        return array_keys(self::get_currency_options());
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_tour_currency($chada_travel_value): string {
        $chada_travel_currency = strtoupper(trim((string) $chada_travel_value));
        return in_array($chada_travel_currency, self::get_tour_currency_options(), true)
            ? $chada_travel_currency : self::TOUR_DEFAULT_CURRENCY;
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<string>
     */
    public static function sanitize_payment_methods($chada_travel_value): array {
        $chada_travel_methods = is_array($chada_travel_value) ? $chada_travel_value : [];
        return array_values(array_intersect(array_keys(self::get_payment_methods()), $chada_travel_methods));
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_default_payment_method($chada_travel_value): string {
        $chada_travel_method = (string) $chada_travel_value;
        return array_key_exists($chada_travel_method, self::get_payment_methods()) ? $chada_travel_method : 'chada_travel_bank';
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_positive_integer($chada_travel_value): int {
        return max(1, (int) $chada_travel_value);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_nonnegative_integer($chada_travel_value): int {
        return max(0, (int) $chada_travel_value);
    }

    /**
     * Normalizes any truthy/falsy submitted value (checkbox presence, "1"/"0", bool) to a stored 1 or 0.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_bool_int($chada_travel_value): int {
        return !empty($chada_travel_value) ? 1 : 0;
    }

    /**
     * @param mixed $chada_travel_value
     * @return array<string, string>
     */
    public static function sanitize_bank_details($chada_travel_value): array {
        $chada_travel_value = is_array($chada_travel_value) ? $chada_travel_value : [];
        $chada_travel_keys  = ['bank_name', 'account_name', 'account_number', 'instructions'];
        $chada_travel_clean = [];
        foreach ($chada_travel_keys as $chada_travel_key) {
            $chada_travel_clean[$chada_travel_key] = self::sanitize_text($chada_travel_value[$chada_travel_key] ?? '');
        }
        return $chada_travel_clean;
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<array<string, mixed>>
     */
    public static function sanitize_country_fees($chada_travel_value): array {
        if (!is_array($chada_travel_value)) {
            return [];
        }
        $chada_travel_countries = [];
        foreach ($chada_travel_value as $chada_travel_country) {
            if (!is_array($chada_travel_country)) {
                continue;
            }
            $chada_travel_code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) ($chada_travel_country['code'] ?? '')) ?? '');
            if (strlen($chada_travel_code) !== 2) {
                continue;
            }
            $chada_travel_countries[] = [
                'code'              => $chada_travel_code,
                'name'              => self::sanitize_text($chada_travel_country['name'] ?? ''),
                'sort_order'        => max(0, (int) ($chada_travel_country['sort_order'] ?? 0)),
                'processing_fee'    => number_format(
                    max(0, (float) ($chada_travel_country['processing_fee'] ?? 0)),
                    2,
                    '.',
                    ''
                ),
                'currency'          => self::sanitize_currency($chada_travel_country['currency'] ?? self::DEFAULT_CURRENCY),
                'checklist_version' => self::sanitize_text($chada_travel_country['checklist_version'] ?? 'mvp-1'),
                'full_details'      => CHADA_TRAVEL_Country_Validator::sanitize_full_details(
                    $chada_travel_country['full_details'] ?? ''
                ),
                'guide_attachment_id' => max(0, (int) ($chada_travel_country['guide_attachment_id'] ?? 0)),
                'checklist_attachment_id' => max(0, (int) ($chada_travel_country['checklist_attachment_id'] ?? 0)),
                'is_active'         => empty($chada_travel_country['is_active']) ? 0 : 1,
            ];
        }
        return $chada_travel_countries;
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return list<array<string, mixed>>
     */
    private static function get_browser_countries(array $chada_travel_settings): array {
        $chada_travel_active = CHADA_TRAVEL_Checkout_Validator::get_active_countries(
            (array) $chada_travel_settings['chada_travel_country_fees'],
            self::get_currency_code($chada_travel_settings)
        );
        return array_values(array_map(static function (array $chada_travel_country): array {
            $chada_travel_country['guide_url'] = CHADA_TRAVEL_Media_Validator::public_url(
                (int) ($chada_travel_country['guide_attachment_id'] ?? 0),
                CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
            );
            unset($chada_travel_country['guide_attachment_id'], $chada_travel_country['checklist_attachment_id']);
            return $chada_travel_country;
        }, $chada_travel_active));
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<string>
     */
    public static function sanitize_document_mime_types($chada_travel_value): array {
        return self::sanitize_mime_types($chada_travel_value, ['application/pdf', 'image/jpeg', 'image/png']);
    }

    /**
     * @param mixed $chada_travel_value
     * @return list<string>
     */
    public static function sanitize_proof_mime_types($chada_travel_value): array {
        return self::sanitize_mime_types($chada_travel_value, ['image/jpeg', 'image/png']);
    }

    /**
     * @param mixed $chada_travel_value
     * @param list<string> $chada_travel_allowlist Allowed MIME types.
     * @return list<string>
     */
    private static function sanitize_mime_types($chada_travel_value, array $chada_travel_allowlist): array {
        return array_values(array_intersect($chada_travel_allowlist, is_array($chada_travel_value) ? $chada_travel_value : []));
    }

    /**
     * Uppercase two-letter code, or 'PH' when the submitted value is not a recognizable country code.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_company_country($chada_travel_value): string {
        return self::sanitize_country_code($chada_travel_value) ?? 'PH';
    }

    /**
     * Uppercase two-letter code, or '' when not recognizable - required going forward
     * (CHADA_TRAVEL_General_Settings_Validator rejects a blank submission); '' can still surface from prior/pre-save
     * stored data, which self::apply_dynamic_general_defaults() backfills from Company Country when read, never
     * from this sanitizer itself.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_address_country($chada_travel_value): string {
        return self::sanitize_country_code($chada_travel_value) ?? '';
    }

    /** @param mixed $chada_travel_value */
    private static function sanitize_country_code($chada_travel_value): ?string {
        $chada_travel_code = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $chada_travel_value) ?? '');
        return strlen($chada_travel_code) === 2 ? $chada_travel_code : null;
    }

    /**
     * Preserves digits, spaces, and the safe phone-formatting characters +()-; strips everything else (markup).
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_phone($chada_travel_value): string {
        $chada_travel_value = self::sanitize_text($chada_travel_value);
        return trim(preg_replace('/[^0-9+\-() ]/', '', $chada_travel_value) ?? '');
    }

    /**
     * A named PHP/IANA timezone identifier, or '' (meaning "use the resolved default") when not recognizable.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_timezone($chada_travel_value): string {
        $chada_travel_value = (string) $chada_travel_value;
        return CHADA_TRAVEL_Settings_Repository::is_valid_timezone($chada_travel_value) ? $chada_travel_value : '';
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_currency_display($chada_travel_value): string {
        return in_array($chada_travel_value, self::get_currency_display_options(), true)
            ? (string) $chada_travel_value : 'code';
    }

    /** @return list<string> Allowed chada_travel_currency_display values. */
    public static function get_currency_display_options(): array {
        return ['symbol', 'code', 'symbol_code'];
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_date_format($chada_travel_value): string {
        return in_array($chada_travel_value, self::get_date_format_options(), true) ? (string) $chada_travel_value : '';
    }

    /** @return list<string> Allowed chada_travel_date_format values; '' means "inherit the WordPress date format". */
    public static function get_date_format_options(): array {
        return ['', 'F j, Y', 'M j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'];
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_time_format($chada_travel_value): string {
        return in_array($chada_travel_value, self::get_time_format_options(), true) ? (string) $chada_travel_value : '';
    }

    /** @return list<string> Allowed chada_travel_time_format values; '' means "inherit the WordPress time format". */
    public static function get_time_format_options(): array {
        return ['', 'g:i a', 'H:i'];
    }

    /**
     * Compact uppercase identifier: letters, digits, underscores, and internal hyphens only, capped at 20 characters.
     *
     * @param mixed $chada_travel_value
     */
    public static function sanitize_prefix($chada_travel_value): string {
        $chada_travel_value = strtoupper(self::sanitize_text($chada_travel_value));
        $chada_travel_value = trim(preg_replace('/[^A-Z0-9_\-]+/', '', $chada_travel_value) ?? '', '-_');
        return substr($chada_travel_value, 0, 20);
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_company_status($chada_travel_value): string {
        return in_array($chada_travel_value, self::get_company_status_options(), true) ? (string) $chada_travel_value : 'active';
    }

    /** @return list<string> Allowed chada_travel_company_status values. */
    public static function get_company_status_options(): array {
        return ['active', 'maintenance'];
    }

    /** @param mixed $chada_travel_value */
    public static function sanitize_textarea($chada_travel_value): string {
        if (function_exists('sanitize_textarea_field')) {
            return sanitize_textarea_field((string) $chada_travel_value);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return trim(strip_tags((string) $chada_travel_value));
    }
}
