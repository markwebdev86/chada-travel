<?php
/**
 * Shared whole-payload validate + atomic persist + safe audit-log logic for administrator Settings tabs (General,
 * Payment Method, Pages & Links, Policies & Consent, Documents & Uploads, and Booking Workflow), extracted from
 * CHADA_TRAVEL_Admin_Settings so every Settings page calls exactly the same validator and persistence/side-effect code.
 *
 * Every method here reproduces the pre-existing CHADA_TRAVEL_Admin_Settings handler behavior verbatim (same validator,
 * same update_option() calls, same cascade/cron side effects, same audit event type/message), including any
 * pre-existing quirk of that behavior - this class changes ownership, not behavior. Each method returns the
 * validator's own `array{errors, clean?, audit?}` result unchanged so a caller can inspect `errors` to decide
 * whether the atomic save happened.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Settings_Save_Service {
    /**
     * Sanitizes a payload before it crosses the Free-to-extension action boundary.
     *
     * Free validators receive a field-sanitized request from the administrator handler; rich-text fields in the
     * dedicated Tour and Visa handlers retain their existing rich-text sanitizer. The extension copy is normalized
     * again at the action boundary without changing field names or nesting.
     *
     * @param array<string, mixed> $chada_travel_payload
     * @return array<string, mixed>
     */
    public static function sanitize_extension_payload(array $chada_travel_payload): array {
        return self::sanitize_extension_value($chada_travel_payload);
    }

    /**
     * Recursively sanitizes scalar extension values while preserving the established field names and nesting.
     *
     * @param mixed $chada_travel_value
     * @return mixed
     */
    private static function sanitize_extension_value($chada_travel_value) {
        if (is_array($chada_travel_value)) {
            $chada_travel_clean = [];
            foreach ($chada_travel_value as $chada_travel_key => $chada_travel_nested_value) {
                $chada_travel_clean[$chada_travel_key] = self::sanitize_extension_value($chada_travel_nested_value);
            }
            return $chada_travel_clean;
        }
        if (is_string($chada_travel_value)) {
            return CHADA_TRAVEL_Config::sanitize_text($chada_travel_value);
        }
        if (is_int($chada_travel_value) || is_float($chada_travel_value) || is_bool($chada_travel_value)) {
            return $chada_travel_value;
        }
        return '';
    }

    /**
     * Validates and, only when there are zero errors, atomically persists General Settings - including the
     * existing Currency -> Company Bank Fees/Countries cascade - then logs the standard audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_general(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_result = CHADA_TRAVEL_General_Settings_Validator::validate($chada_travel_post, $chada_travel_current_settings);
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }

        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }

        if (in_array('chada_travel_currency', (array) ($chada_travel_result['audit']['changed_options'] ?? []), true)) {
            self::sync_country_fee_currency((string) $chada_travel_result['clean']['chada_travel_currency']);
        }

        self::log_settings_audit_event(
            'chada_travel_general_settings_saved',
            'An administrator saved the General Settings.',
            $chada_travel_result['audit']
        );

        return $chada_travel_result;
    }

    /** Synchronizes current country-fee currencies without changing historical checkout snapshots. */
    private static function sync_country_fee_currency(string $chada_travel_currency): void {
        $chada_travel_countries = (array) get_option('chada_travel_country_fees', []);
        foreach ($chada_travel_countries as &$chada_travel_country) {
            $chada_travel_country['currency'] = $chada_travel_currency;
        }
        unset($chada_travel_country);
        update_option('chada_travel_country_fees', $chada_travel_countries, false);
        global $wpdb;
        CHADA_TRAVEL_Country_Repository::sync_all($wpdb, $chada_travel_countries);
    }

    /**
     * Validates and, only when there are zero errors, atomically persists Bank Payment and Digital Wallet fields.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_payment(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_result = CHADA_TRAVEL_Payment_Settings_Validator::validate($chada_travel_post, $chada_travel_current_settings);
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }

        $chada_travel_clean = $chada_travel_result['clean'];
        update_option('chada_travel_payment_methods', $chada_travel_clean['payment_methods']);
        update_option('chada_travel_default_payment_method', $chada_travel_clean['default_payment_method']);
        CHADA_TRAVEL_Settings_Repository::save_bank_accounts($chada_travel_clean['bank_accounts']);
        update_option('chada_travel_digital_wallet_name', $chada_travel_clean['digital_wallet_name']);
        update_option('chada_travel_digital_wallet_account_name', $chada_travel_clean['digital_wallet_account_name']);
        update_option('chada_travel_digital_wallet_account_number', $chada_travel_clean['digital_wallet_account_number']);
        update_option('chada_travel_digital_wallet_qr_attachment_id', $chada_travel_clean['digital_wallet_qr_attachment_id']);

        self::log_settings_audit_event(
            'chada_travel_payment_settings_saved',
            'An administrator saved the Payment Method settings.',
            $chada_travel_result['audit']
        );

        return $chada_travel_result;
    }

    /**
     * Validates (no dedicated validator class exists for this tab - the same per-slot sanitize/validate loop as
     * CHADA_TRAVEL_Admin_Settings::handle_save_pages_links() runs here) and, only when there are zero errors, atomically
     * persists the three Pages & Links page-id assignments, then logs the standard audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, int>, audit?: array<string, mixed>}
     */
    public static function save_pages_links(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_errors = [];
        $chada_travel_clean  = [];
        $chada_travel_labels = CHADA_TRAVEL_Page_Registry::get_labels();

        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_option  = $chada_travel_definition['option'];
            $chada_travel_page_id = CHADA_TRAVEL_Page_Settings::sanitize_page_id($chada_travel_post[$chada_travel_option] ?? 0);
            $chada_travel_validation = CHADA_TRAVEL_Page_Settings::validate_page_id($chada_travel_page_id);
            if (!$chada_travel_validation['valid']) {
                $chada_travel_errors[$chada_travel_option] = sprintf(
                    /* translators: 1: field label, 2: validation error message. */
                    __('%1$s: %2$s', 'chada-travel'),
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key,
                    $chada_travel_validation['error']
                );
                continue;
            }
            if ($chada_travel_page_id === 0 && $chada_travel_key !== CHADA_TRAVEL_Page_Registry::TOUR_SEARCH_RESULTS) {
                $chada_travel_errors[$chada_travel_option] = sprintf(
                    /* translators: %s: field label. */
                    __('%s is required.', 'chada-travel'),
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key
                );
                continue;
            }
            if ($chada_travel_page_id === 0) {
                continue;
            }
            $chada_travel_clean[$chada_travel_option] = $chada_travel_page_id;
        }

        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        $chada_travel_audit = self::build_pages_links_audit($chada_travel_clean, $chada_travel_current_settings);
        foreach ($chada_travel_clean as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }

        self::log_settings_audit_event(
            'chada_travel_pages_links_settings_saved',
            'An administrator saved the Pages & Links settings.',
            $chada_travel_audit
        );

        return ['errors' => [], 'clean' => $chada_travel_clean, 'audit' => $chada_travel_audit];
    }

    /**
     * Validates and, only when there are zero errors, atomically persists Policies & Consent settings, then logs
     * the standard audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_policies_consent(
        array $chada_travel_post,
        array $chada_travel_current_settings,
        object $chada_travel_wpdb
    ): array {
        $chada_travel_result = CHADA_TRAVEL_Policy_Settings_Validator::validate(
            $chada_travel_post, $chada_travel_current_settings, $chada_travel_wpdb
        );
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }

        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }

        self::log_settings_audit_event(
            'chada_travel_policies_consent_settings_saved',
            'An administrator saved the Policies & Consent settings.',
            $chada_travel_result['audit']
        );

        return $chada_travel_result;
    }

    /**
     * Validates and, only when there are zero errors, atomically persists Documents & Uploads settings, then logs
     * the standard audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_documents_uploads(
        array $chada_travel_post,
        array $chada_travel_current_settings,
        object $chada_travel_wpdb
    ): array {
        $chada_travel_result = CHADA_TRAVEL_Documents_Uploads_Settings_Validator::validate(
            $chada_travel_post,
            $chada_travel_current_settings,
            $chada_travel_wpdb
        );
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }

        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }

        self::log_settings_audit_event(
            'chada_travel_documents_uploads_settings_saved',
            'An administrator saved the Documents & Uploads settings.',
            $chada_travel_result['audit']
        );

        return $chada_travel_result;
    }

    /**
     * Validates and persists the dedicated Tours Settings options, then records a safe audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_tour_settings(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_result = CHADA_TRAVEL_Tour_Settings_Validator::validate($chada_travel_post, $chada_travel_current_settings);
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }
        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }
        self::log_settings_audit_event(
            'chada_travel_tour_settings_saved',
            'An administrator saved the Tours Settings.',
            $chada_travel_result['audit']
        );
        return $chada_travel_result;
    }

    /**
     * Validates and persists the dedicated Visa Countries Settings options, then records a safe audit event.
     *
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function save_visa_countries_settings(
        array $chada_travel_post,
        array $chada_travel_current_settings
    ): array {
        $chada_travel_result = CHADA_TRAVEL_Visa_Countries_Settings_Validator::validate($chada_travel_post, $chada_travel_current_settings);
        if ($chada_travel_result['errors']) {
            return $chada_travel_result;
        }
        foreach ($chada_travel_result['clean'] as $chada_travel_option_name => $chada_travel_value) {
            update_option($chada_travel_option_name, $chada_travel_value);
        }
        self::log_settings_audit_event(
            'chada_travel_visa_countries_settings_saved',
            'An administrator saved the Visa Countries Settings.',
            $chada_travel_result['audit']
        );
        return $chada_travel_result;
    }

    /**
     * Writes one safe local audit event row. Shared by every Settings tab handler (CHADA_TRAVEL_Admin_Settings) and
     * every administrator Settings save that uses this class, so the entry points log identically.
     *
     * @param array<string, mixed> $chada_travel_audit Safe metadata only - never a secret, token, full phone number,
     *                                           full address, or email value.
     */
    public static function log_settings_audit_event(
        string $chada_travel_event_type,
        string $chada_travel_message,
        array $chada_travel_audit
    ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom audit table write; no equivalent WordPress API exists.
        $wpdb->insert($wpdb->prefix . 'chada_travel_events', [
            'chada_travel_record_type'   => 'system',
            'chada_travel_event_type'    => $chada_travel_event_type,
            'chada_travel_event_message' => $chada_travel_message,
            'chada_travel_event_meta'    => function_exists('wp_json_encode') ? wp_json_encode($chada_travel_audit) : '{}',
            'chada_travel_actor_user_id' => get_current_user_id(),
            'chada_travel_actor_type'    => 'admin',
            'chada_travel_created_at'    => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s']);
    }

    /**
     * Builds safe administrator audit metadata: changed option names, old/new page ids (never page content,
     * customer data, or a token-bearing URL), and each page's readiness-state transition.
     *
     * @param array<string, int>   $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array<string, mixed>
     */
    private static function build_pages_links_audit(array $chada_travel_clean, array $chada_travel_current_settings): array {
        $chada_travel_changed = [];
        $chada_travel_old_ids = [];
        $chada_travel_new_ids = [];
        $chada_travel_readiness_changes = [];
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_option = $chada_travel_definition['option'];
            $chada_travel_old_id = (int) ($chada_travel_current_settings[$chada_travel_option] ?? 0);
            $chada_travel_new_id = (int) ($chada_travel_clean[$chada_travel_option] ?? $chada_travel_old_id);
            $chada_travel_old_ids[$chada_travel_key] = $chada_travel_old_id;
            $chada_travel_new_ids[$chada_travel_key] = $chada_travel_new_id;
            if ($chada_travel_old_id !== $chada_travel_new_id) {
                $chada_travel_changed[] = $chada_travel_option;
            }
            $chada_travel_old_status = CHADA_TRAVEL_Page_Settings::resolve_status(
                $chada_travel_key,
                $chada_travel_current_settings
            )['status'];
            $chada_travel_new_status = CHADA_TRAVEL_Page_Settings::resolve_status(
                $chada_travel_key,
                array_merge($chada_travel_current_settings, $chada_travel_clean)
            )['status'];
            if ($chada_travel_old_status !== $chada_travel_new_status) {
                $chada_travel_readiness_changes[$chada_travel_key] = ['from' => $chada_travel_old_status, 'to' => $chada_travel_new_status];
            }
        }
        return [
            'changed_options'   => $chada_travel_changed,
            'old_page_ids'      => $chada_travel_old_ids,
            'new_page_ids'      => $chada_travel_new_ids,
            'readiness_changes' => $chada_travel_readiness_changes,
        ];
    }
}
