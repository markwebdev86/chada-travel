<?php
/**
 * Resolves the readiness of the Free payment methods used by Settings and checkout.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Readiness {
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_READY = 'ready';
    public const STATUS_MANAGED_EXTERNALLY = 'managed_externally';
    public const STATUS_CONNECTION_ERROR = 'connection_error';

    /** @param array<string, mixed> $chada_travel_settings */
    public static function is_enabled(string $chada_travel_method, array $chada_travel_settings): bool {
        return in_array($chada_travel_method, (array) ($chada_travel_settings['chada_travel_payment_methods'] ?? []), true);
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function is_ready(string $chada_travel_method, array $chada_travel_settings): bool {
        if ($chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_BANK) {
            return self::bank_ready();
        }
        if ($chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET) {
            return self::digital_wallet_ready($chada_travel_settings);
        }
        return CHADA_TRAVEL_Payment_Provider_Registry::is_ready($chada_travel_method, $chada_travel_settings);
    }

    /** Bank is ready when one active account has every required field populated. */
    public static function bank_ready(): bool {
        foreach (CHADA_TRAVEL_Settings_Repository::get_bank_accounts() as $chada_travel_account) {
            if (self::bank_account_is_complete($chada_travel_account) && !empty($chada_travel_account['is_active'])) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $chada_travel_account */
    public static function bank_account_is_complete(array $chada_travel_account): bool {
        return (string) ($chada_travel_account['bank_name'] ?? '') !== ''
            && (string) ($chada_travel_account['account_name'] ?? '') !== ''
            && (string) ($chada_travel_account['account_number'] ?? '') !== '';
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function digital_wallet_ready(array $chada_travel_settings): bool {
        return (string) ($chada_travel_settings['chada_travel_digital_wallet_name'] ?? '') !== ''
            && (string) ($chada_travel_settings['chada_travel_digital_wallet_account_name'] ?? '') !== ''
            && (string) ($chada_travel_settings['chada_travel_digital_wallet_account_number'] ?? '') !== ''
            && (int) ($chada_travel_settings['chada_travel_digital_wallet_qr_attachment_id'] ?? 0) > 0;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function status(string $chada_travel_method, array $chada_travel_settings): string {
        if (!self::is_enabled($chada_travel_method, $chada_travel_settings)) {
            return self::STATUS_DISABLED;
        }
        return self::is_ready($chada_travel_method, $chada_travel_settings) ? self::STATUS_READY : self::STATUS_INCOMPLETE;
    }

    /**
     * @param array<string, mixed> $chada_travel_settings
     * @return list<string>
     */
    public static function get_enabled_ready_methods(array $chada_travel_settings): array {
        $chada_travel_ordered = array_keys(CHADA_TRAVEL_Config::get_payment_methods());
        return array_values(array_filter(
            $chada_travel_ordered,
            static fn(string $chada_travel_method): bool =>
                self::is_enabled($chada_travel_method, $chada_travel_settings) && self::is_ready($chada_travel_method, $chada_travel_settings)
        ));
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function has_any_ready_method(array $chada_travel_settings): bool {
        return self::get_enabled_ready_methods($chada_travel_settings) !== [];
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function resolve_default_method(array $chada_travel_settings): string {
        $chada_travel_ready = self::get_enabled_ready_methods($chada_travel_settings);
        $chada_travel_configured = (string) ($chada_travel_settings['chada_travel_default_payment_method'] ?? '');
        return in_array($chada_travel_configured, $chada_travel_ready, true) ? $chada_travel_configured : ($chada_travel_ready[0] ?? '');
    }
}
