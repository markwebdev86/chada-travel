<?php
/**
 * Payment-provider registry owned by the Free plugin.
 *
 * Bank Payment and Digital Wallet are registered by default. Add-ons may register genuinely independent payment
 * providers through the public provider-registration action; the Free package never hides a provider behind a paid
 * upgrade.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Provider_Registry {
    /** @var array<string, array{label: string, package: string, readiness?: callable|null, checkout_renderer?: callable|null, checkout_handler?: callable|null, admin_payment_renderer?: callable|null, settings_renderer?: callable|null, settings_saver?: callable|null, route_registrar?: callable|null}> */
    private static array $chada_travel_providers = [
        'chada_travel_bank' => ['label' => 'Bank Payment', 'package' => 'free'],
        'chada_travel_digital_wallet' => ['label' => 'Digital Wallet', 'package' => 'free'],
    ];

    /**
     * @param array<string, mixed> $chada_travel_definition
     */
    public static function register(
        string $chada_travel_id,
        string $chada_travel_label,
        string $chada_travel_package = 'pro',
        array $chada_travel_definition = []
    ): void {
        $chada_travel_id = function_exists('sanitize_key') ? sanitize_key($chada_travel_id) : strtolower($chada_travel_id);
        if ($chada_travel_id === '' || $chada_travel_label === '') {
            return;
        }
        self::$chada_travel_providers[$chada_travel_id] = [
            'label' => $chada_travel_label,
            'package' => $chada_travel_package === 'free' ? 'free' : 'pro',
        ] + self::normalize_contract($chada_travel_definition);
    }

    /** @return array<string, string> Provider id to display label. */
    public static function get_payment_methods(): array {
        $chada_travel_methods = [];
        foreach (self::$chada_travel_providers as $chada_travel_id => $chada_travel_provider) {
            $chada_travel_methods[$chada_travel_id] = $chada_travel_provider['label'];
        }
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_payment_methods', $chada_travel_methods)
            : $chada_travel_methods;
    }

    /** @return array<string, array{label: string, package: string, readiness?: callable|null, checkout_renderer?: callable|null, checkout_handler?: callable|null, admin_payment_renderer?: callable|null, settings_renderer?: callable|null, settings_saver?: callable|null, route_registrar?: callable|null}> */
    public static function get_definitions(): array {
        return self::$chada_travel_providers;
    }

    /**
     * @return array{label: string, package: string, readiness?: callable|null, checkout_renderer?: callable|null,
     *     checkout_handler?: callable|null, admin_payment_renderer?: callable|null, settings_renderer?: callable|null,
     *     settings_saver?: callable|null, route_registrar?: callable|null}|null
     */
    public static function get_definition(string $chada_travel_id): ?array {
        return self::$chada_travel_providers[$chada_travel_id] ?? null;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function is_ready(string $chada_travel_id, array $chada_travel_settings): bool {
        $chada_travel_definition = self::get_definition($chada_travel_id);
        if ($chada_travel_definition === null || empty($chada_travel_definition['readiness'])) {
            return false;
        }
        return (bool) call_user_func($chada_travel_definition['readiness'], $chada_travel_settings);
    }

    /**
     * @param array<string, mixed> $chada_travel_definition
     * @return array<string, callable|null>
     */
    private static function normalize_contract(array $chada_travel_definition): array {
        $chada_travel_contract = [];
        $chada_travel_contract_keys = [
            'readiness', 'checkout_renderer', 'checkout_handler', 'admin_payment_renderer',
            'settings_renderer', 'settings_saver', 'route_registrar',
        ];
        foreach ($chada_travel_contract_keys as $chada_travel_key) {
            if (array_key_exists($chada_travel_key, $chada_travel_definition) && is_callable($chada_travel_definition[$chada_travel_key])) {
                $chada_travel_contract[$chada_travel_key] = $chada_travel_definition[$chada_travel_key];
            }
        }
        return $chada_travel_contract;
    }
}
