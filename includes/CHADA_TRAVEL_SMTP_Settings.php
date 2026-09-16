<?php
/** Built-in SMTP settings with write-only encrypted password storage. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_SMTP_Settings {
    public const PASSWORD_OPTION = 'chada_travel_smtp_password';

    /** @return array<string, mixed> */
    public static function get_option_defaults(): array {
        return [
            'chada_travel_smtp_enabled' => 0,
            'chada_travel_smtp_host' => '',
            'chada_travel_smtp_port' => 587,
            'chada_travel_smtp_encryption' => 'tls',
            'chada_travel_smtp_authentication' => 1,
            'chada_travel_smtp_username' => '',
            self::PASSWORD_OPTION => '',
        ];
    }

    /** @return array<string, mixed> */
    public static function get_public_settings(): array {
        $chada_travel_defaults = self::get_option_defaults();
        $chada_travel_settings = [];
        foreach ($chada_travel_defaults as $chada_travel_option => $chada_travel_default) {
            $chada_travel_settings[$chada_travel_option] = function_exists('get_option')
                ? get_option($chada_travel_option, $chada_travel_default) : $chada_travel_default;
        }
        $chada_travel_settings[self::PASSWORD_OPTION] = self::has_password($chada_travel_settings[self::PASSWORD_OPTION])
            || (defined('CHADA_TRAVEL_SMTP_PASSWORD') && CHADA_TRAVEL_SMTP_PASSWORD !== '');
        $chada_travel_settings['chada_travel_smtp_port'] = max(1, min(65535, (int) $chada_travel_settings['chada_travel_smtp_port']));
        return $chada_travel_settings;
    }

    /** @return array{enabled: bool, host: string, port: int, encryption: string, authentication: bool, username: string, password: string} */
    public static function get_runtime_settings(): array {
        $chada_travel_settings = self::get_public_settings();
        $chada_travel_envelope = function_exists('get_option') ? get_option(self::PASSWORD_OPTION, '') : '';
        $chada_travel_password = CHADA_TRAVEL_Credential_Cipher::decrypt_for_purpose((string) $chada_travel_envelope, 'smtp_password');
        if (($chada_travel_password === '' || $chada_travel_password === null) && defined('CHADA_TRAVEL_SMTP_PASSWORD')) {
            $chada_travel_password = (string) CHADA_TRAVEL_SMTP_PASSWORD;
        }
        return [
            'enabled' => !empty($chada_travel_settings['chada_travel_smtp_enabled']),
            'host' => function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $chada_travel_settings['chada_travel_smtp_host'])
                : (string) $chada_travel_settings['chada_travel_smtp_host'],
            'port' => (int) $chada_travel_settings['chada_travel_smtp_port'],
            'encryption' => (string) $chada_travel_settings['chada_travel_smtp_encryption'],
            'authentication' => !empty($chada_travel_settings['chada_travel_smtp_authentication']),
            'username' => function_exists('sanitize_text_field')
                ? sanitize_text_field((string) $chada_travel_settings['chada_travel_smtp_username'])
                : (string) $chada_travel_settings['chada_travel_smtp_username'],
            'password' => $chada_travel_password === null ? '' : $chada_travel_password,
        ];
    }

    /** @param mixed $chada_travel_value */
    public static function has_password($chada_travel_value): bool {
        return is_string($chada_travel_value) && $chada_travel_value !== '';
    }
}
