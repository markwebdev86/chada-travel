<?php
/** Configures WordPress's bundled PHPMailer for the optional built-in SMTP transport. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_SMTP_Transport {
    public static function register(): void {
        add_action('phpmailer_init', [self::class, 'configure']);
    }

    /** Configures SMTP only when all required settings are valid; otherwise WordPress's normal mail path remains. */
    public static function configure(object $chada_travel_phpmailer): void {
        $chada_travel_settings = CHADA_TRAVEL_SMTP_Settings::get_runtime_settings();
        if (!$chada_travel_settings['enabled'] || !self::is_complete($chada_travel_settings)) {
            return;
        }
        if (method_exists($chada_travel_phpmailer, 'isSMTP')) {
            $chada_travel_phpmailer->isSMTP();
        }
        $chada_travel_phpmailer->Host = $chada_travel_settings['host'];
        $chada_travel_phpmailer->Port = $chada_travel_settings['port'];
        $chada_travel_phpmailer->SMTPAuth = $chada_travel_settings['authentication'];
        if ($chada_travel_settings['authentication']) {
            $chada_travel_phpmailer->Username = $chada_travel_settings['username'];
            $chada_travel_phpmailer->Password = $chada_travel_settings['password'];
        }
        if ($chada_travel_settings['encryption'] === 'ssl') {
            $chada_travel_phpmailer->SMTPSecure = 'ssl';
        } elseif ($chada_travel_settings['encryption'] === 'tls') {
            $chada_travel_phpmailer->SMTPSecure = 'tls';
        } else {
            $chada_travel_phpmailer->SMTPSecure = '';
            $chada_travel_phpmailer->SMTPAutoTLS = false;
        }
        $chada_travel_phpmailer->Timeout = 15;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function is_complete(array $chada_travel_settings): bool {
        if (empty($chada_travel_settings['enabled'])) {
            return true;
        }
        if (trim((string) ($chada_travel_settings['host'] ?? '')) === '') {
            return false;
        }
        if (!empty($chada_travel_settings['authentication'])) {
            return trim((string) ($chada_travel_settings['username'] ?? '')) !== ''
                && (string) ($chada_travel_settings['password'] ?? '') !== '';
        }
        return true;
    }
}
