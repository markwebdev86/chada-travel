<?php
/**
 * Whole-payload validation for the Settings page's Policies & Consent tab. Validates the complete submitted
 * payload before any option is written; on any error the caller must retain every previously stored value
 * unchanged, exactly like CHADA_TRAVEL_General_Settings_Validator/CHADA_TRAVEL_Payment_Settings_Validator.
 *
 * Also enforces the consent-audit-integrity rule: if an already-effective policy source changes while Policy
 * Version stays the same, and the stored bundle is already usable or an order has recorded consent for that
 * stored version, the save is rejected so an administrator must enter a new Policy Version instead of silently
 * redefining what an existing Policy Version means.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Policy_Settings_Validator {
    /** Rejected as too long past this many characters (after sanitization) - see get_transaction_disclaimer_errors(). */
    private const TRANSACTION_DISCLAIMER_MAX_LENGTH = 500;

    /**
     * Plain, untranslated policy labels for error-message text only, matching every other validator's existing
     * convention (e.g. CHADA_TRAVEL_General_Settings_Validator) of never calling __() so validators stay directly
     * unit-testable without a WordPress i18n runtime loaded. Administrator-facing rendering elsewhere uses the
     * real translated CHADA_TRAVEL_Policy_Registry::get_labels() instead.
     *
     * @return array<string, string>
     */
    private static function plain_labels(): array {
        return [
            CHADA_TRAVEL_Policy_Registry::PRIVACY             => 'Privacy Policy',
            CHADA_TRAVEL_Policy_Registry::TERMS                => 'Terms and Conditions',
            CHADA_TRAVEL_Policy_Registry::CANCELLATION_REFUND  => 'Cancellation and Refund Policy',
        ];
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
    public static function validate(array $chada_travel_post, array $chada_travel_current_settings, object $chada_travel_wpdb): array {
        $chada_travel_errors = [];

        $chada_travel_version = trim((string) ($chada_travel_post['chada_travel_policy_version'] ?? ''));
        if ($chada_travel_version === '') {
            $chada_travel_errors['chada_travel_policy_version'] = 'Policy Version is required.';
        } elseif (!CHADA_TRAVEL_Policy_Settings::is_valid_policy_version($chada_travel_version)) {
            $chada_travel_errors['chada_travel_policy_version'] = 'Policy Version may only use letters, numbers, periods, '
                . 'underscores, and hyphens, up to 40 characters.';
        }

        $chada_travel_effective_date = trim((string) ($chada_travel_post['chada_travel_policy_effective_date'] ?? ''));
        if ($chada_travel_effective_date === '') {
            $chada_travel_errors['chada_travel_policy_effective_date'] = 'Policy Effective Date is required.';
        } elseif (!CHADA_TRAVEL_Policy_Settings::is_valid_effective_date($chada_travel_effective_date, $chada_travel_current_settings)) {
            $chada_travel_errors['chada_travel_policy_effective_date'] =
                'Enter a valid Policy Effective Date that is not in the future.';
        }

        $chada_travel_disclaimer_raw = $chada_travel_post['chada_travel_transaction_disclaimer'] ?? '';
        // A forged array-shaped submission (e.g. chada_travel_transaction_disclaimer[]=x) must never reach a (string)
        // cast, which would emit a PHP "Array to string conversion" warning; treated as empty/invalid instead.
        $chada_travel_disclaimer_clean = is_scalar($chada_travel_disclaimer_raw)
            ? trim(CHADA_TRAVEL_Config::sanitize_textarea((string) $chada_travel_disclaimer_raw))
            : '';
        if ($chada_travel_disclaimer_clean === '') {
            $chada_travel_errors['chada_travel_transaction_disclaimer'] = 'Transaction Confirmation Disclaimer is required.';
        } elseif (mb_strlen($chada_travel_disclaimer_clean) > self::TRANSACTION_DISCLAIMER_MAX_LENGTH) {
            $chada_travel_errors['chada_travel_transaction_disclaimer'] = sprintf(
                'Transaction Confirmation Disclaimer must be %d characters or fewer.',
                self::TRANSACTION_DISCLAIMER_MAX_LENGTH
            );
        }

        $chada_travel_clean = [
            'chada_travel_policy_version'         => $chada_travel_version,
            'chada_travel_policy_effective_date'  => $chada_travel_effective_date,
            'chada_travel_transaction_disclaimer' => $chada_travel_disclaimer_clean,
        ];
        $chada_travel_labels = self::plain_labels();
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_page_id = CHADA_TRAVEL_Policy_Settings::sanitize_page_id(
                $chada_travel_post[$chada_travel_definition['page_option']] ?? 0
            );
            $chada_travel_page_validation = CHADA_TRAVEL_Policy_Settings::validate_page_id($chada_travel_page_id);
            if (!$chada_travel_page_validation['valid']) {
                $chada_travel_errors[$chada_travel_definition['page_option']] = sprintf(
                    /* translators: 1: policy label, 2: validation error message. */
                    '%1$s: %2$s',
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key,
                    $chada_travel_page_validation['error']
                );
            }

            $chada_travel_external_raw = trim((string) ($chada_travel_post[$chada_travel_definition['url_option']] ?? ''));
            $chada_travel_external_clean = $chada_travel_external_raw !== '' ? CHADA_TRAVEL_Config::sanitize_url($chada_travel_external_raw) : '';
            if ($chada_travel_external_raw !== '' && !CHADA_TRAVEL_Policy_Settings::is_safe_external_url($chada_travel_external_clean)) {
                $chada_travel_errors[$chada_travel_definition['url_option']] = sprintf(
                    /* translators: %s: policy label. */
                    'Enter a valid %s External URL starting with http:// or https://.',
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key
                );
                $chada_travel_external_clean = '';
            }

            $chada_travel_clean[$chada_travel_definition['page_option']] = $chada_travel_page_id;
            $chada_travel_clean[$chada_travel_definition['url_option']]  = $chada_travel_external_clean;

            $chada_travel_page_error_already_set = isset($chada_travel_errors[$chada_travel_definition['page_option']]);
            $chada_travel_url_error_already_set  = isset($chada_travel_errors[$chada_travel_definition['url_option']]);
            if (!$chada_travel_page_error_already_set && !$chada_travel_url_error_already_set
                && $chada_travel_page_id === 0 && $chada_travel_external_clean === ''
            ) {
                $chada_travel_errors[$chada_travel_definition['page_option']] = sprintf(
                    /* translators: %s: policy label. */
                    '%s requires a WordPress Page or an External URL.',
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key
                );
            }
        }

        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        $chada_travel_source_change_error = self::detect_unversioned_source_change(
            $chada_travel_clean,
            $chada_travel_current_settings,
            $chada_travel_wpdb
        );
        if ($chada_travel_source_change_error !== '') {
            return ['errors' => ['chada_travel_policy_version' => $chada_travel_source_change_error]];
        }

        return [
            'errors' => [],
            'clean'  => $chada_travel_clean,
            'audit'  => self::build_audit($chada_travel_clean, $chada_travel_current_settings),
        ];
    }

    /**
     * Compares each policy's OLD vs NEW *resolved effective URL* (never the raw page-id/external-URL fields
     * directly) whenever the submitted Policy Version is unchanged from the currently stored one. A changed
     * effective source is only rejected when the stored bundle was already usable, or when at least one order
     * has already recorded consent for the currently stored Policy Version - so a first-time configuration of a
     * previously missing source, or picking a Page whose resolved URL is identical to the current fallback URL,
     * is never incorrectly blocked.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     */
    private static function detect_unversioned_source_change(
        array $chada_travel_clean,
        array $chada_travel_current_settings,
        object $chada_travel_wpdb
    ): string {
        $chada_travel_old_version = (string) ($chada_travel_current_settings['chada_travel_policy_version'] ?? '');
        if ($chada_travel_clean['chada_travel_policy_version'] !== $chada_travel_old_version) {
            // Administrator is already entering a new Policy Version - any source change is explicitly covered.
            return '';
        }

        $chada_travel_proposed_settings = array_merge($chada_travel_current_settings, $chada_travel_clean);
        $chada_travel_labels = self::plain_labels();
        $chada_travel_consent_exists = CHADA_TRAVEL_Order_Repository::any_order_with_policy_version($chada_travel_wpdb, $chada_travel_old_version);

        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_old_status = CHADA_TRAVEL_Policy_Settings::resolve_policy_status($chada_travel_key, $chada_travel_current_settings);
            $chada_travel_new_status = CHADA_TRAVEL_Policy_Settings::resolve_policy_status($chada_travel_key, $chada_travel_proposed_settings);
            if ($chada_travel_old_status['url'] === $chada_travel_new_status['url']) {
                continue;
            }
            $chada_travel_old_bundle_ready = CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_current_settings)['ready'];
            if ($chada_travel_old_bundle_ready || $chada_travel_consent_exists) {
                return sprintf(
                    /* translators: %s: policy label whose effective source changed. */
                    'The %s source changed while Policy Version stayed the same. Enter a new Policy Version to '
                        . 'save this change.',
                    $chada_travel_labels[$chada_travel_key] ?? $chada_travel_key
                );
            }
        }
        return '';
    }

    /**
     * Builds safe administrator audit metadata: changed option names, Policy Version/Effective Date before-
     * after values, and each policy's readiness-state transition - never a raw external URL or page content.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array<string, mixed>
     */
    private static function build_audit(array $chada_travel_clean, array $chada_travel_current_settings): array {
        $chada_travel_changed = [];
        foreach ($chada_travel_clean as $chada_travel_option => $chada_travel_value) {
            if ((string) $chada_travel_value !== (string) ($chada_travel_current_settings[$chada_travel_option] ?? '')) {
                $chada_travel_changed[] = $chada_travel_option;
            }
        }

        $chada_travel_proposed_settings = array_merge($chada_travel_current_settings, $chada_travel_clean);
        $chada_travel_readiness_changes = [];
        foreach (array_keys(CHADA_TRAVEL_Policy_Registry::get_definitions()) as $chada_travel_key) {
            $chada_travel_old_status = CHADA_TRAVEL_Policy_Settings::resolve_policy_status(
                $chada_travel_key,
                $chada_travel_current_settings
            )['status'];
            $chada_travel_new_status = CHADA_TRAVEL_Policy_Settings::resolve_policy_status(
                $chada_travel_key,
                $chada_travel_proposed_settings
            )['status'];
            if ($chada_travel_old_status !== $chada_travel_new_status) {
                $chada_travel_readiness_changes[$chada_travel_key] = ['from' => $chada_travel_old_status, 'to' => $chada_travel_new_status];
            }
        }

        return [
            'changed_options'        => $chada_travel_changed,
            'policy_version_from'    => (string) ($chada_travel_current_settings['chada_travel_policy_version'] ?? ''),
            'policy_version_to'      => $chada_travel_clean['chada_travel_policy_version'],
            'effective_date_from'    => (string) ($chada_travel_current_settings['chada_travel_policy_effective_date'] ?? ''),
            'effective_date_to'      => $chada_travel_clean['chada_travel_policy_effective_date'],
            'readiness_changes'      => $chada_travel_readiness_changes,
            'bundle_ready_after_save'=> CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_proposed_settings)['ready'],
        ];
    }
}
