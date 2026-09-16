<?php
/**
 * Atomic validation for the Free Payment Method settings: Bank Payment and Digital Wallet.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Settings_Validator {
    private const DIGITAL_WALLET_QR_MIME_ALLOWLIST = ['image/jpeg', 'image/png'];
    private const DIGITAL_WALLET_NAME_MAX_LENGTH = 50;

    /**
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @param list<array<string, mixed>>|null $chada_travel_current_accounts Optional persisted rows for pure contract tests.
     * @return array{errors: array<string, string>, clean?: array<string, mixed>, audit?: array<string, mixed>}
     */
    public static function validate(
        array $chada_travel_post,
        array $chada_travel_current_settings,
        ?array $chada_travel_current_accounts = null
    ): array {
        $chada_travel_payment_methods = CHADA_TRAVEL_Config::sanitize_payment_methods($chada_travel_post['chada_travel_payment_methods'] ?? []);
        $chada_travel_default_method = CHADA_TRAVEL_Config::sanitize_default_payment_method(
            $chada_travel_post['chada_travel_default_payment_method'] ?? ''
        );
        $chada_travel_current_accounts = $chada_travel_current_accounts ?? CHADA_TRAVEL_Settings_Repository::get_bank_accounts();
        [$chada_travel_bank_accounts, $chada_travel_bank_errors] = self::validate_bank_accounts(
            $chada_travel_post,
            $chada_travel_current_accounts
        );
        [$chada_travel_digital_wallet, $chada_travel_wallet_errors] = self::validate_digital_wallet(
            $chada_travel_post,
            $chada_travel_current_settings
        );
        $chada_travel_clean = [
            'payment_methods' => $chada_travel_payment_methods,
            'default_payment_method' => $chada_travel_default_method,
            'bank_accounts' => $chada_travel_bank_accounts,
            'digital_wallet_name' => $chada_travel_digital_wallet['name'],
            'digital_wallet_account_name' => $chada_travel_digital_wallet['account_name'],
            'digital_wallet_account_number' => $chada_travel_digital_wallet['account_number'],
            'digital_wallet_qr_attachment_id' => $chada_travel_digital_wallet['qr_attachment_id'],
        ];
        $chada_travel_errors = array_merge($chada_travel_bank_errors, $chada_travel_wallet_errors);
        if (!$chada_travel_errors) {
            $chada_travel_errors = self::validate_cross_field($chada_travel_clean, $chada_travel_current_settings, $chada_travel_post);
        }
        if (count($chada_travel_errors) > 0) {
            return ['errors' => $chada_travel_errors];
        }
        return [
            'errors' => [],
            'clean' => $chada_travel_clean,
            'audit' => [
                'payment_methods_enabled' => $chada_travel_payment_methods,
                'default_method_changed' => $chada_travel_default_method
                    !== (string) ($chada_travel_current_settings['chada_travel_default_payment_method'] ?? ''),
                'bank_account_count' => count($chada_travel_bank_accounts),
                'digital_wallet_name_changed' => $chada_travel_digital_wallet['name']
                    !== (string) ($chada_travel_current_settings['chada_travel_digital_wallet_name'] ?? ''),
                'digital_wallet_qr_changed' => $chada_travel_digital_wallet['qr_attachment_id']
                    !== (int) ($chada_travel_current_settings['chada_travel_digital_wallet_qr_attachment_id'] ?? 0),
                'digital_wallet_qr_removed' => $chada_travel_digital_wallet['qr_attachment_id'] === 0
                    && (int) ($chada_travel_current_settings['chada_travel_digital_wallet_qr_attachment_id'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_post
     * @param list<array<string, mixed>> $chada_travel_current_accounts
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private static function validate_bank_accounts(array $chada_travel_post, array $chada_travel_current_accounts): array {
        $chada_travel_errors = [];
        $chada_travel_submitted = array_key_exists('bank_accounts', $chada_travel_post)
            ? (array) $chada_travel_post['bank_accounts'] : $chada_travel_current_accounts;
        $chada_travel_current_by_id = [];
        foreach ($chada_travel_current_accounts as $chada_travel_account) {
            $chada_travel_current_by_id[(string) ($chada_travel_account['id'] ?? '')] = $chada_travel_account;
        }
        $chada_travel_rows = [];
        foreach ($chada_travel_submitted as $chada_travel_index => $chada_travel_row) {
            if (!is_array($chada_travel_row) || self::bank_row_is_empty($chada_travel_row)) {
                continue;
            }
            $chada_travel_bank_name = CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['bank_name'] ?? '');
            $chada_travel_account_name = CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['account_name'] ?? '');
            $chada_travel_account_number = self::sanitize_account_number($chada_travel_row['account_number'] ?? '');
            if ($chada_travel_bank_name === '' || $chada_travel_account_name === '' || $chada_travel_account_number === '') {
                $chada_travel_errors['bank_accounts_' . $chada_travel_index] = 'Complete the Bank Name, Account Name, and '
                    . 'Account Number for this Bank Account, or leave the entire row blank.';
                continue;
            }
            $chada_travel_submitted_id = CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['id'] ?? '');
            $chada_travel_id = $chada_travel_submitted_id !== '' && isset($chada_travel_current_by_id[$chada_travel_submitted_id])
                ? $chada_travel_submitted_id : CHADA_TRAVEL_Settings_Repository::generate_bank_account_id();
            $chada_travel_label = CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['label'] ?? '');
            $chada_travel_rows[] = [
                'id' => $chada_travel_id,
                'label' => $chada_travel_label !== '' ? $chada_travel_label : ('Account ' . ((int) $chada_travel_index + 1)),
                'bank_name' => $chada_travel_bank_name,
                'account_name' => $chada_travel_account_name,
                'account_number' => $chada_travel_account_number,
                'branch' => CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['branch'] ?? ''),
                'account_type' => CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['account_type'] ?? ''),
                'instructions' => CHADA_TRAVEL_Config::sanitize_text($chada_travel_row['instructions'] ?? ''),
                'is_active' => !empty($chada_travel_row['is_active']) ? 1 : 0,
                'sort_order' => max(0, (int) ($chada_travel_row['sort_order'] ?? ((int) $chada_travel_index + 1))),
            ];
        }
        if ($chada_travel_errors) {
            return [[], $chada_travel_errors];
        }
        usort($chada_travel_rows, static fn(array $chada_travel_a, array $chada_travel_b): int =>
            $chada_travel_a['sort_order'] <=> $chada_travel_b['sort_order']);
        foreach ($chada_travel_rows as $chada_travel_position => &$chada_travel_row) {
            $chada_travel_row['sort_order'] = $chada_travel_position + 1;
        }
        unset($chada_travel_row);
        return [$chada_travel_rows, []];
    }

    /** @param array<string, mixed> $chada_travel_row */
    private static function bank_row_is_empty(array $chada_travel_row): bool {
        $chada_travel_fields = ['label', 'bank_name', 'account_name', 'account_number', 'branch', 'account_type',
            'instructions'];
        foreach ($chada_travel_fields as $chada_travel_field) {
            if (trim((string) ($chada_travel_row[$chada_travel_field] ?? '')) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param mixed $chada_travel_value */
    private static function sanitize_account_number($chada_travel_value): string {
        $chada_travel_value = CHADA_TRAVEL_Config::sanitize_text($chada_travel_value);
        return trim(preg_replace('/[^A-Za-z0-9 \-]/', '', $chada_travel_value) ?? '');
    }

    /**
     * @param array<string, mixed> $chada_travel_post
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function validate_digital_wallet(array $chada_travel_post, array $chada_travel_current_settings): array {
        $chada_travel_errors = [];
        $chada_travel_name = CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['digital_wallet_name'] ?? '');
        if (mb_strlen($chada_travel_name) > self::DIGITAL_WALLET_NAME_MAX_LENGTH) {
            $chada_travel_errors['digital_wallet_name'] = sprintf(
                'Digital Wallet Name must be %d characters or fewer.',
                self::DIGITAL_WALLET_NAME_MAX_LENGTH
            );
        }
        $chada_travel_attachment_id = !empty($chada_travel_post['digital_wallet_qr_remove'])
            ? 0 : max(0, (int) ($chada_travel_post['digital_wallet_qr_attachment_id'] ?? 0));
        if ($chada_travel_attachment_id > 0 && !self::is_valid_qr_attachment($chada_travel_attachment_id)) {
            $chada_travel_attachment_id = 0;
            $chada_travel_errors['digital_wallet_qr_attachment_id'] = 'Select a valid image (JPEG or PNG) from the Media '
                . 'Library for the Digital Wallet QR Code.';
        }
        return [[
            'name' => mb_strlen($chada_travel_name) <= self::DIGITAL_WALLET_NAME_MAX_LENGTH ? $chada_travel_name : '',
            'account_name' => CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['digital_wallet_account_name'] ?? ''),
            'account_number' => CHADA_TRAVEL_Config::sanitize_text($chada_travel_post['digital_wallet_account_number'] ?? ''),
            'qr_attachment_id' => $chada_travel_attachment_id,
        ], $chada_travel_errors];
    }

    private static function is_valid_qr_attachment(int $chada_travel_attachment_id): bool {
        if (!function_exists('get_post')) {
            return $chada_travel_attachment_id > 0;
        }
        $chada_travel_attachment = get_post($chada_travel_attachment_id);
        return $chada_travel_attachment && $chada_travel_attachment->post_type === 'attachment'
            && in_array((string) $chada_travel_attachment->post_mime_type, self::DIGITAL_WALLET_QR_MIME_ALLOWLIST, true);
    }

    /**
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array{0: list<string>, 1: array<string, string>}
     */
    /**
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @param array<string, mixed> $chada_travel_post
     * @return array<string, string>
     */
    private static function validate_cross_field(
        array $chada_travel_clean,
        array $chada_travel_current_settings,
        array $chada_travel_post
    ): array {
        $chada_travel_ready = [];
        foreach (array_keys(CHADA_TRAVEL_Config::get_payment_methods()) as $chada_travel_method) {
            if (self::tentative_ready($chada_travel_method, $chada_travel_clean, $chada_travel_current_settings, $chada_travel_post)) {
                $chada_travel_ready[] = $chada_travel_method;
            }
        }
        $chada_travel_errors = [];
        if ($chada_travel_ready && !in_array($chada_travel_clean['default_payment_method'], $chada_travel_ready, true)) {
            $chada_travel_errors['chada_travel_default_payment_method'] = 'The default payment method must be enabled and '
                . 'fully configured (Ready).';
        }
        if (!$chada_travel_ready) {
            $chada_travel_errors['chada_travel_payment_methods'] = 'At least one payment method must be enabled and fully '
                . 'configured (Ready).';
        }
        $chada_travel_currently_enabled = (array) ($chada_travel_current_settings['chada_travel_payment_methods'] ?? []);
        foreach ([CHADA_TRAVEL_Payment_Service::METHOD_BANK, CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET] as $chada_travel_method) {
            if (in_array($chada_travel_method, $chada_travel_clean['payment_methods'], true)
                && !in_array($chada_travel_method, $chada_travel_currently_enabled, true)
                && !self::tentative_ready($chada_travel_method, $chada_travel_clean, $chada_travel_current_settings, $chada_travel_post)
            ) {
                $chada_travel_errors[$chada_travel_method . '_enabled'] = $chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_BANK
                    ? 'Bank Payment cannot be enabled without at least one complete, active Bank Account.'
                    : 'Digital Wallet cannot be enabled until its name, account details, and QR Code are complete.';
            }
        }
        return $chada_travel_errors;
    }

    /**
     * Lets an independently registered provider validate its posted credentials before the shared settings save.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @param array<string, mixed> $chada_travel_post
     */
    private static function tentative_ready(
        string $chada_travel_method,
        array $chada_travel_clean,
        array $chada_travel_current_settings,
        array $chada_travel_post
    ): bool {
        if (function_exists('apply_filters')) {
            $chada_travel_extension_result = apply_filters(
                'chada_travel_payment_settings_tentative_ready',
                null,
                $chada_travel_method,
                $chada_travel_clean,
                $chada_travel_current_settings,
                $chada_travel_post
            );
            if (is_bool($chada_travel_extension_result)) {
                return $chada_travel_extension_result;
            }
        }
        if ($chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_BANK) {
            foreach ($chada_travel_clean['bank_accounts'] as $chada_travel_account) {
                if (!empty($chada_travel_account['is_active'])
                    && CHADA_TRAVEL_Payment_Readiness::bank_account_is_complete($chada_travel_account)
                ) {
                    return true;
                }
            }
            return false;
        }
        return $chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET
            && $chada_travel_clean['digital_wallet_name'] !== ''
            && $chada_travel_clean['digital_wallet_account_name'] !== ''
            && $chada_travel_clean['digital_wallet_account_number'] !== ''
            && (int) $chada_travel_clean['digital_wallet_qr_attachment_id'] > 0;
    }
}
