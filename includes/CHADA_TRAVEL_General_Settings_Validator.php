<?php
/**
 * Whole-payload validation for the Settings page's General tab: Company Profile, Company Contact Information,
 * Regional and Formatting Settings, Reference prefixes, and Operational Availability. Validates the complete
 * submitted payload before any option is written; on any error the caller must retain every previously stored
 * value unchanged, exactly like CHADA_TRAVEL_Payment_Settings_Validator for the Payment Method tab.
 *
 * Format is still coerced to a safe value rather than rejected for enumerable/select-driven fields (currency,
 * currency display, date/time format, prefixes, company status) and, for Company Country/Address Country, an
 * unrecognizable-but-present code - only genuine absence produces an error for those two, matching this class's
 * existing coerce-don't-reject style. Every other required field below rejects a blank submission outright: an
 * invalid submission indicates a real administrator mistake for Company Name, Website URL, Support Email,
 * Company Country, Address Line 1, City/Municipality, Province/Region, Address Country, Company Logo, Company
 * Timezone, and the conditionally-required Maintenance Message.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_General_Settings_Validator {
    private const LOGO_MIME_ALLOWLIST = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const NAME_MAX_LENGTH = 190;
    private const TAGLINE_MAX_LENGTH = 300;
    private const ADDRESS_MAX_LENGTH = 190;
    private const MAINTENANCE_MESSAGE_MAX_LENGTH = 1000;

    /**
     * Returns the required General Settings option names that are empty or unusable in effective runtime settings.
     * This read-only helper mirrors the save validator's required fields so administrator readiness messages do not
     * maintain a second, conflicting list of General Settings requirements.
     *
     * @param array<string, mixed> $chada_travel_settings Effective CHADA_TRAVEL_Config::get_settings() values.
     * @return list<string>
     */
    public static function missing_required_fields(array $chada_travel_settings): array {
        $chada_travel_missing = [];
        $chada_travel_company_name = self::setting_string($chada_travel_settings, 'chada_travel_company_name');
        if ($chada_travel_company_name === '') {
            $chada_travel_missing[] = 'chada_travel_company_name';
        }

        $chada_travel_website_url = CHADA_TRAVEL_Config::sanitize_url(
            $chada_travel_settings['chada_travel_company_website_url'] ?? ''
        );
        if ($chada_travel_website_url === '' || !self::is_http_url($chada_travel_website_url)) {
            $chada_travel_missing[] = 'chada_travel_company_website_url';
        }

        if (CHADA_TRAVEL_Config::sanitize_email($chada_travel_settings['chada_travel_company_support_email'] ?? '') === '') {
            $chada_travel_missing[] = 'chada_travel_company_support_email';
        }
        if (self::setting_string($chada_travel_settings, 'chada_travel_company_country') === '') {
            $chada_travel_missing[] = 'chada_travel_company_country';
        }
        foreach ([
            'chada_travel_company_address_line_1',
            'chada_travel_company_city',
            'chada_travel_company_province',
        ] as $chada_travel_option) {
            if (self::setting_string($chada_travel_settings, $chada_travel_option) === '') {
                $chada_travel_missing[] = $chada_travel_option;
            }
        }
        if (CHADA_TRAVEL_Config::sanitize_address_country($chada_travel_settings['chada_travel_company_address_country'] ?? '') === '') {
            $chada_travel_missing[] = 'chada_travel_company_address_country';
        }
        foreach (['chada_travel_booking_id_prefix', 'chada_travel_transaction_id_prefix'] as $chada_travel_option) {
            if (CHADA_TRAVEL_Config::sanitize_prefix($chada_travel_settings[$chada_travel_option] ?? '') === '') {
                $chada_travel_missing[] = $chada_travel_option;
            }
        }
        if (CHADA_TRAVEL_Config::sanitize_company_status($chada_travel_settings['chada_travel_company_status'] ?? '') === 'maintenance'
            && self::setting_string($chada_travel_settings, 'chada_travel_company_maintenance_message') === ''
        ) {
            $chada_travel_missing[] = 'chada_travel_company_maintenance_message';
        }
        return $chada_travel_missing;
    }

    /**
     * @param array<string, mixed> $chada_travel_post             Sanitized request payload.
     * @param array<string, mixed> $chada_travel_current_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array{
     *     errors: array<string, string>,
     *     clean?: array<string, mixed>,
     *     audit?: array<string, mixed>
     * }
     */
    public static function validate(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_errors = [];

        $chada_travel_company_name = CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_name'] ?? '');
        if ($chada_travel_company_name === '') {
            $chada_travel_errors['chada_travel_company_name'] = 'Company Name is required.';
        }
        $chada_travel_company_name = self::capped($chada_travel_company_name, self::NAME_MAX_LENGTH);

        [$chada_travel_logo_id, $chada_travel_logo_error] = self::validate_logo($chada_travel_post);
        if ($chada_travel_logo_error !== '') {
            $chada_travel_errors['chada_travel_company_logo_attachment_id'] = $chada_travel_logo_error;
        }

        $chada_travel_website_url = CHADA_TRAVEL_Config::sanitize_url($chada_travel_post['chada_travel_company_website_url'] ?? '');
        if ($chada_travel_website_url === '') {
            $chada_travel_errors['chada_travel_company_website_url'] = 'Company Website URL is required.';
        } elseif (!self::is_http_url($chada_travel_website_url)) {
            $chada_travel_errors['chada_travel_company_website_url'] =
                'Enter a valid Company Website URL starting with http:// or https://.';
        }

        $chada_travel_support_email = CHADA_TRAVEL_Config::sanitize_email($chada_travel_post['chada_travel_company_support_email'] ?? '');
        if ($chada_travel_support_email === '') {
            $chada_travel_errors['chada_travel_company_support_email'] = 'Enter a valid Support Email address.';
        }

        // Country fields are still coerced to a safe 2-letter code rather than rejected for an unrecognizable
        // value (see the class docblock) - only genuine absence is a validation error here.
        $chada_travel_country_raw = trim((string) ($chada_travel_post['chada_travel_company_country'] ?? ''));
        if ($chada_travel_country_raw === '') {
            $chada_travel_errors['chada_travel_company_country'] = 'Company Country is required.';
        }
        $chada_travel_company_country = CHADA_TRAVEL_Config::sanitize_company_country($chada_travel_country_raw);

        $chada_travel_address_line_1 = self::capped(
            CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_address_line_1'] ?? ''),
            self::ADDRESS_MAX_LENGTH
        );
        if ($chada_travel_address_line_1 === '') {
            $chada_travel_errors['chada_travel_company_address_line_1'] = 'Address Line 1 is required.';
        }

        $chada_travel_city = self::capped(
            CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_city'] ?? ''),
            self::ADDRESS_MAX_LENGTH
        );
        if ($chada_travel_city === '') {
            $chada_travel_errors['chada_travel_company_city'] = 'City/Municipality is required.';
        }

        $chada_travel_province = self::capped(
            CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_province'] ?? ''),
            self::ADDRESS_MAX_LENGTH
        );
        if ($chada_travel_province === '') {
            $chada_travel_errors['chada_travel_company_province'] = 'Province/Region is required.';
        }

        // Unlike Company Country, sanitize_address_country() has no non-empty fallback for an unrecognizable
        // value - it returns '' - so the sanitized result, not just raw presence, must be checked here or a
        // garbled-but-non-blank submission would silently save as empty.
        $chada_travel_address_country = CHADA_TRAVEL_Config::sanitize_address_country(
            $chada_travel_post['chada_travel_company_address_country'] ?? ''
        );
        if ($chada_travel_address_country === '') {
            $chada_travel_errors['chada_travel_company_address_country'] = 'Enter a valid two-letter Address Country code.';
        }

        $chada_travel_timezone_input = trim((string) ($chada_travel_post['chada_travel_company_timezone'] ?? ''));
        $chada_travel_timezone = CHADA_TRAVEL_Config::sanitize_timezone($chada_travel_timezone_input);
        if ($chada_travel_timezone_input !== '' && $chada_travel_timezone === '') {
            $chada_travel_errors['chada_travel_company_timezone'] = 'Select a valid Company Timezone.';
        }

        $chada_travel_booking_prefix = CHADA_TRAVEL_Config::sanitize_prefix($chada_travel_post['chada_travel_booking_id_prefix'] ?? '');
        if ($chada_travel_booking_prefix === '') {
            $chada_travel_errors['chada_travel_booking_id_prefix'] =
                'Enter a valid Booking ID Prefix (uppercase letters, numbers, and hyphens).';
        }
        $chada_travel_transaction_prefix = CHADA_TRAVEL_Config::sanitize_prefix($chada_travel_post['chada_travel_transaction_id_prefix'] ?? '');
        if ($chada_travel_transaction_prefix === '') {
            $chada_travel_errors['chada_travel_transaction_id_prefix'] =
                'Enter a valid Transaction ID Prefix (uppercase letters, numbers, and hyphens).';
        }

        $chada_travel_status = CHADA_TRAVEL_Config::sanitize_company_status($chada_travel_post['chada_travel_company_status'] ?? '');
        $chada_travel_maintenance_message = self::capped(
            CHADA_TRAVEL_Config::sanitize_textarea($chada_travel_post['chada_travel_company_maintenance_message'] ?? ''),
            self::MAINTENANCE_MESSAGE_MAX_LENGTH
        );
        if ($chada_travel_status === 'active') {
            // Preserves a previously entered/default message so a later switch back to maintenance is not blank.
            $chada_travel_maintenance_message = $chada_travel_maintenance_message !== '' ? $chada_travel_maintenance_message
                : (string) ($chada_travel_current_settings['chada_travel_company_maintenance_message'] ?? '');
        } elseif (trim($chada_travel_maintenance_message) === '') {
            $chada_travel_errors['chada_travel_company_maintenance_message'] =
                'Enter a Maintenance Message; it is required while Company Status is Under Maintenance.';
        }

        $chada_travel_previous_currency = strtoupper(trim((string) (
            $chada_travel_current_settings['chada_travel_currency'] ?? CHADA_TRAVEL_Config::DEFAULT_CURRENCY
        )));
        $chada_travel_currency_input = array_key_exists('chada_travel_currency', $chada_travel_post)
            ? $chada_travel_post['chada_travel_currency'] : $chada_travel_previous_currency;
        $chada_travel_currency = CHADA_TRAVEL_Config::sanitize_currency($chada_travel_currency_input);
        if (!CHADA_TRAVEL_Config::is_curated_currency($chada_travel_currency)
            && $chada_travel_currency !== $chada_travel_previous_currency
        ) {
            $chada_travel_currency = CHADA_TRAVEL_Config::DEFAULT_CURRENCY;
        }

        $chada_travel_clean = [
            'chada_travel_company_name'               => $chada_travel_company_name,
            'chada_travel_company_legal_name'         => self::capped(
                CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_legal_name'] ?? ''),
                self::NAME_MAX_LENGTH
            ),
            'chada_travel_company_logo_attachment_id' => $chada_travel_logo_id,
            'chada_travel_company_website_url'        => $chada_travel_website_url,
            'chada_travel_company_country'            => $chada_travel_company_country,
            'chada_travel_company_tagline'            => self::capped(
                CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_tagline'] ?? ''),
                self::TAGLINE_MAX_LENGTH
            ),
            'chada_travel_company_support_email'      => $chada_travel_support_email,
            'chada_travel_company_phone'              =>
                CHADA_TRAVEL_Config::sanitize_phone($chada_travel_post['chada_travel_company_phone'] ?? ''),
            'chada_travel_company_mobile'             =>
                CHADA_TRAVEL_Config::sanitize_phone($chada_travel_post['chada_travel_company_mobile'] ?? ''),
            'chada_travel_company_address_line_1'     => $chada_travel_address_line_1,
            'chada_travel_company_address_line_2'     => self::capped(
                CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_address_line_2'] ?? ''),
                self::ADDRESS_MAX_LENGTH
            ),
            'chada_travel_company_city'               => $chada_travel_city,
            'chada_travel_company_province'           => $chada_travel_province,
            'chada_travel_company_postal_code'        => self::capped(
                CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_company_postal_code'] ?? ''),
                40
            ),
            'chada_travel_company_address_country'    => $chada_travel_address_country,
            'chada_travel_company_timezone'           => $chada_travel_timezone,
            'chada_travel_currency'                   => $chada_travel_currency,
            'chada_travel_currency_display'           => CHADA_TRAVEL_Config::sanitize_currency_display(
                $chada_travel_post['chada_travel_currency_display'] ?? ''
            ),
            'chada_travel_date_format'                =>
                CHADA_TRAVEL_Config::sanitize_date_format($chada_travel_post['chada_travel_date_format'] ?? ''),
            'chada_travel_time_format'                =>
                CHADA_TRAVEL_Config::sanitize_time_format($chada_travel_post['chada_travel_time_format'] ?? ''),
            'chada_travel_booking_id_prefix'          => $chada_travel_booking_prefix,
            'chada_travel_transaction_id_prefix'      => $chada_travel_transaction_prefix,
            'chada_travel_company_status'             => $chada_travel_status,
            'chada_travel_company_maintenance_message'=> $chada_travel_maintenance_message,
        ];

        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        return [
            'errors' => [],
            'clean'  => $chada_travel_clean,
            'audit'  => self::build_audit($chada_travel_clean, $chada_travel_current_settings),
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_post
     * @return array{0: int, 1: string}
     */
    private static function validate_logo(array $chada_travel_post): array {
        $chada_travel_remove = !empty($chada_travel_post['chada_travel_company_logo_remove']);
        $chada_travel_attachment_id = $chada_travel_remove ? 0 : max(0, (int) ($chada_travel_post['chada_travel_company_logo_attachment_id'] ?? 0));
        if ($chada_travel_attachment_id > 0 && !self::is_valid_logo_attachment($chada_travel_attachment_id)) {
            return [0, 'Select a valid image (JPEG, PNG, GIF, or WebP) from the Media Library for the Company Logo.'];
        }
        return [$chada_travel_attachment_id, ''];
    }

    /** Verifies the attachment exists, is a real WordPress image attachment, and matches the logo MIME allowlist. */
    private static function is_valid_logo_attachment(int $chada_travel_attachment_id): bool {
        if (!function_exists('get_post')) {
            return $chada_travel_attachment_id > 0;
        }
        $chada_travel_attachment = get_post($chada_travel_attachment_id);
        if (!$chada_travel_attachment || $chada_travel_attachment->post_type !== 'attachment') {
            return false;
        }
        $chada_travel_mime = (string) $chada_travel_attachment->post_mime_type;
        if (in_array($chada_travel_mime, self::LOGO_MIME_ALLOWLIST, true)) {
            return true;
        }
        // SVG is accepted only when this WordPress install already allows it site-wide (its own upload_mimes
        // filter), never unconditionally - SVG can carry embedded script content unsafe to trust by default.
        if ($chada_travel_mime === 'image/svg+xml' && function_exists('get_allowed_mime_types')) {
            return in_array('image/svg+xml', get_allowed_mime_types(), true);
        }
        return false;
    }

    private static function is_http_url(string $chada_travel_url): bool {
        $chada_travel_scheme = strtolower((string) parse_url($chada_travel_url, PHP_URL_SCHEME));
        return in_array($chada_travel_scheme, ['http', 'https'], true);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    private static function setting_string(array $chada_travel_settings, string $chada_travel_option): string {
        $chada_travel_value = $chada_travel_settings[$chada_travel_option] ?? '';
        return is_scalar($chada_travel_value) ? trim((string) $chada_travel_value) : '';
    }

    private static function capped(string $chada_travel_value, int $chada_travel_max_length): string {
        return mb_substr($chada_travel_value, 0, $chada_travel_max_length);
    }

    /**
     * Builds safe administrator audit metadata: changed option *names* only (never phone/address/email values),
     * plus non-sensitive Company Status/Country/Address Country/Currency/Timezone values and the Company Logo action.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array<string, mixed>
     */
    private static function build_audit(array $chada_travel_clean, array $chada_travel_current_settings): array {
        $chada_travel_changed_fields = [];
        foreach ($chada_travel_clean as $chada_travel_option => $chada_travel_value) {
            // String-compared rather than strict: get_option() returns int-typed defaults (e.g. the logo
            // attachment id) back as strings once stored in the real options table, which would otherwise
            // falsely flag every untouched field of that type as "changed" on every unrelated save.
            if ((string) $chada_travel_value !== (string) ($chada_travel_current_settings[$chada_travel_option] ?? '')) {
                $chada_travel_changed_fields[] = $chada_travel_option;
            }
        }

        $chada_travel_previous_logo =
            (int) ($chada_travel_current_settings['chada_travel_company_logo_attachment_id'] ?? 0);
        $chada_travel_new_logo      = (int) $chada_travel_clean['chada_travel_company_logo_attachment_id'];
        $chada_travel_logo_action   = null;
        if ($chada_travel_previous_logo !== $chada_travel_new_logo) {
            $chada_travel_logo_action = $chada_travel_new_logo === 0
                ? 'removed' : ($chada_travel_previous_logo === 0 ? 'added' : 'replaced');
        }

        $chada_travel_previous_status = (string) ($chada_travel_current_settings['chada_travel_company_status'] ?? 'active');
        $chada_travel_previous_country = (string) ($chada_travel_current_settings['chada_travel_company_country'] ?? 'PH');
        $chada_travel_previous_address_country =
            (string) ($chada_travel_current_settings['chada_travel_company_address_country'] ?? '');
        $chada_travel_previous_currency = (string) (
            $chada_travel_current_settings['chada_travel_currency'] ?? CHADA_TRAVEL_Config::DEFAULT_CURRENCY
        );
        $chada_travel_previous_timezone = (string) ($chada_travel_current_settings['chada_travel_company_timezone'] ?? '');

        return [
            'changed_fields'         => $chada_travel_changed_fields,
            'company_status_changed' => $chada_travel_clean['chada_travel_company_status'] !== $chada_travel_previous_status,
            'company_status_to'      => $chada_travel_clean['chada_travel_company_status'],
            'company_country_changed' => $chada_travel_clean['chada_travel_company_country'] !== $chada_travel_previous_country,
            'company_country_to'      => $chada_travel_clean['chada_travel_company_country'],
            'address_country_changed' =>
                $chada_travel_clean['chada_travel_company_address_country'] !== $chada_travel_previous_address_country,
            'address_country_to'      => $chada_travel_clean['chada_travel_company_address_country'],
            'logo_action'            => $chada_travel_logo_action,
            'currency_changed'       => $chada_travel_clean['chada_travel_currency'] !== $chada_travel_previous_currency,
            'currency_to'            => $chada_travel_clean['chada_travel_currency'],
            'timezone_changed'       => $chada_travel_clean['chada_travel_company_timezone'] !== $chada_travel_previous_timezone,
            'timezone_to'            => $chada_travel_clean['chada_travel_company_timezone'],
        ];
    }
}
