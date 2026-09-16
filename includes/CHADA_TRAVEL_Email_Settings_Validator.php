<?php
/**
 * Whole-payload validation for the Settings page's Email tab: Sender Identity (From Name, From Email Address,
 * Reply-To Email Address) and Administrator Notifications (enabled flag, Admin Notification Email). Validates
 * the complete submitted payload before any option is written; on any error the caller must retain every
 * previously stored value unchanged, matching CHADA_TRAVEL_General_Settings_Validator/CHADA_TRAVEL_Payment_Settings_Validator.
 *
 * Every address field here is optional - blank is a meaningful, valid "inherit"/"omit" state (see
 * CHADA_TRAVEL_Config::resolve_email_identity()/resolve_admin_notification_email()); only a non-blank value that fails
 * to sanitize to a single valid email address is rejected.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Email_Settings_Validator {
    private const FROM_NAME_MAX_LENGTH = 190;

    /**
     * @param array<string, mixed> $chada_travel_post             Sanitized request payload; SMTP password is unslashed only.
     * @param array<string, mixed> $chada_travel_current_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array{
     *     errors: array<string, string>,
     *     clean?: array<string, mixed>,
     *     audit?: array<string, mixed>,
     *     smtp_clean?: array<string, mixed>|null
     * }
     */
    public static function validate(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_errors = [];

        $chada_travel_from_name = mb_substr(
            CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_email_from_name'] ?? ''),
            0,
            self::FROM_NAME_MAX_LENGTH
        );

        [$chada_travel_from_address, $chada_travel_from_address_error] = self::validate_optional_email(
            $chada_travel_post['chada_travel_email_from_address'] ?? '',
            'From Email Address'
        );
        if ($chada_travel_from_address_error !== '') {
            $chada_travel_errors['chada_travel_email_from_address'] = $chada_travel_from_address_error;
        }

        [$chada_travel_reply_to, $chada_travel_reply_to_error] = self::validate_optional_email(
            $chada_travel_post['chada_travel_email_reply_to_address'] ?? '',
            'Reply-To Email Address'
        );
        if ($chada_travel_reply_to_error !== '') {
            $chada_travel_errors['chada_travel_email_reply_to_address'] = $chada_travel_reply_to_error;
        }

        $chada_travel_admin_notifications_enabled = CHADA_TRAVEL_Config::sanitize_bool_int(
            $chada_travel_post['chada_travel_email_admin_notifications_enabled'] ?? ''
        );

        [$chada_travel_admin_address, $chada_travel_admin_address_error] = self::validate_optional_email(
            $chada_travel_post['chada_travel_email_admin_notification_address'] ?? '',
            'Admin Notification Email'
        );
        if ($chada_travel_admin_address_error !== '') {
            $chada_travel_errors['chada_travel_email_admin_notification_address'] = $chada_travel_admin_address_error;
        }

        $chada_travel_clean = [
            'chada_travel_email_from_name'                   => $chada_travel_from_name,
            'chada_travel_email_from_address'                => $chada_travel_from_address,
            'chada_travel_email_reply_to_address'             => $chada_travel_reply_to,
            'chada_travel_email_admin_notifications_enabled'  => $chada_travel_admin_notifications_enabled,
            'chada_travel_email_admin_notification_address'   => $chada_travel_admin_address,
        ];

        $chada_travel_smtp_clean = self::validate_smtp($chada_travel_post, $chada_travel_errors);

        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        $chada_travel_result = [
            'errors' => [],
            'clean'  => $chada_travel_clean,
            'audit'  => self::build_audit($chada_travel_clean, $chada_travel_current_settings),
        ];
        if ($chada_travel_smtp_clean !== null) {
            $chada_travel_result['smtp_clean'] = $chada_travel_smtp_clean;
        }
        return $chada_travel_result;
    }

    /**
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, string> $chada_travel_errors
     * @return array<string, mixed>|null
     */
    private static function validate_smtp(array $chada_travel_post, array &$chada_travel_errors): ?array {
        if (!array_key_exists('chada_travel_smtp_enabled', $chada_travel_post)
            && !array_key_exists('chada_travel_smtp_host', $chada_travel_post)
            && !array_key_exists('chada_travel_smtp_password', $chada_travel_post)
        ) {
            return null;
        }
        $chada_travel_enabled = CHADA_TRAVEL_Config::sanitize_bool_int($chada_travel_post['chada_travel_smtp_enabled'] ?? 0);
        $chada_travel_host = substr(CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_smtp_host'] ?? ''), 0, 190);
        $chada_travel_port = filter_var($chada_travel_post['chada_travel_smtp_port'] ?? 587, FILTER_VALIDATE_INT);
        $chada_travel_encryption = (string) ($chada_travel_post['chada_travel_smtp_encryption'] ?? 'tls');
        $chada_travel_authentication = CHADA_TRAVEL_Config::sanitize_bool_int($chada_travel_post['chada_travel_smtp_authentication'] ?? 0);
        $chada_travel_username = substr(CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['chada_travel_smtp_username'] ?? ''), 0, 190);
        $chada_travel_password = trim((string) ($chada_travel_post['chada_travel_smtp_password'] ?? ''));
        $chada_travel_clear_password = !empty($chada_travel_post['chada_travel_smtp_clear_password']);
        if ($chada_travel_port === false || $chada_travel_port < 1 || $chada_travel_port > 65535) {
            $chada_travel_errors['chada_travel_smtp_port'] = __('Enter an SMTP port from 1 to 65535.', 'chada-travel');
        }
        if (!in_array($chada_travel_encryption, ['none', 'tls', 'ssl'], true)) {
            $chada_travel_errors['chada_travel_smtp_encryption'] = __('Choose a valid SMTP encryption mode.', 'chada-travel');
        }
        if ($chada_travel_enabled && $chada_travel_host === '') {
            $chada_travel_errors['chada_travel_smtp_host'] = __('Enter an SMTP host or disable built-in SMTP.', 'chada-travel');
        }
        if ($chada_travel_enabled && $chada_travel_authentication && $chada_travel_username === '' && $chada_travel_password === '') {
            $chada_travel_existing = CHADA_TRAVEL_SMTP_Settings::get_public_settings();
            if (empty($chada_travel_existing['chada_travel_smtp_password']) && !defined('CHADA_TRAVEL_SMTP_PASSWORD')) {
                $chada_travel_errors['chada_travel_smtp_password'] = __(
                    'Enter an SMTP password, or disable authentication.',
                    'chada-travel'
                );
            }
        }
        return [
            'chada_travel_smtp_enabled' => $chada_travel_enabled,
            'chada_travel_smtp_host' => $chada_travel_host,
            'chada_travel_smtp_port' => (int) ($chada_travel_port === false ? 587 : $chada_travel_port),
            'chada_travel_smtp_encryption' => $chada_travel_encryption,
            'chada_travel_smtp_authentication' => $chada_travel_authentication,
            'chada_travel_smtp_username' => $chada_travel_username,
            'chada_travel_smtp_password' => $chada_travel_password,
            'chada_travel_smtp_clear_password' => $chada_travel_clear_password,
        ];
    }

    /**
     * Validates an optional single-email field: blank is always valid (meaning "inherit"/"omit"); a non-blank
     * value must sanitize to a single valid address, rejecting header-injection/control characters and anything
     * that does not survive sanitize_email()/is_email().
     *
     * @param mixed $chada_travel_raw
     * @return array{0: string, 1: string} [clean value, error message ('' when valid)]
     */
    private static function validate_optional_email($chada_travel_raw, string $chada_travel_label): array {
        $chada_travel_raw = trim((string) $chada_travel_raw);
        if ($chada_travel_raw === '') {
            return ['', ''];
        }
        $chada_travel_clean = CHADA_TRAVEL_Config::sanitize_email($chada_travel_raw);
        $chada_travel_valid = $chada_travel_clean !== '' && (function_exists('is_email') ? (bool) is_email($chada_travel_clean) : true);
        if (!$chada_travel_valid) {
            return ['', sprintf('Enter a valid %s, or leave it blank.', $chada_travel_label)];
        }
        return [$chada_travel_clean, ''];
    }

    /**
     * Builds safe administrator audit metadata: changed option *names* only, the enable/disable state, and
     * before/after inheritance-state flags - never an actual From Name or email address value.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array<string, mixed>
     */
    private static function build_audit(array $chada_travel_clean, array $chada_travel_current_settings): array {
        $chada_travel_changed_fields = [];
        foreach ($chada_travel_clean as $chada_travel_option => $chada_travel_value) {
            // String-compared rather than strict: get_option() returns int-typed defaults (e.g. the enabled
            // flag) back as strings once stored in the real options table, which would otherwise falsely flag
            // every untouched field of that type as "changed" on every unrelated save.
            if ((string) $chada_travel_value !== (string) ($chada_travel_current_settings[$chada_travel_option] ?? '')) {
                $chada_travel_changed_fields[] = $chada_travel_option;
            }
        }

        $chada_travel_from_name_inherits = $chada_travel_clean['chada_travel_email_from_name'] === '';
        $chada_travel_previous_enabled = !empty($chada_travel_current_settings['chada_travel_email_admin_notifications_enabled']);
        $chada_travel_new_enabled      = (bool) $chada_travel_clean['chada_travel_email_admin_notifications_enabled'];

        return [
            'changed_fields'                              => $chada_travel_changed_fields,
            'from_name_inherits_company_name'              => $chada_travel_from_name_inherits,
            'reply_to_inherits_support_email'              => $chada_travel_clean['chada_travel_email_reply_to_address'] === '',
            'admin_notifications_enabled_to'               => $chada_travel_new_enabled,
            'admin_notifications_enabled_changed'          => $chada_travel_previous_enabled !== $chada_travel_new_enabled,
            'admin_notification_email_inherits_wp_admin'   =>
                $chada_travel_clean['chada_travel_email_admin_notification_address'] === '',
        ];
    }
}
