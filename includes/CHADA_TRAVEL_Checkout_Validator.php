<?php
/**
 * Pure Stage 1-3 checkout validation, sanitization, and total calculation.
 *
 * Contains no WordPress database access so it can be exercised directly by PHPUnit. PHP remains the
 * authoritative source for every field, fee, and total; client-submitted amounts are never trusted.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Checkout_Validator {
    public const MAX_NAME_LENGTH = 100;
    public const MAX_EMAIL_LENGTH = 190;
    public const MAX_MOBILE_LENGTH = 40;
    public const MAX_ADDRESS_LENGTH = 2000;
    public const MIN_BIRTH_YEAR = 1900;

    /**
     * @param array<string, mixed> $chada_travel_input Raw Stage 1 request payload.
     * @return array{errors: array<string, string>, clean: array<string, mixed>}
     */
    public static function validate_booker(array $chada_travel_input): array {
        $chada_travel_errors = [];
        $chada_travel_first_name = self::clean_text($chada_travel_input['first_name'] ?? '', self::MAX_NAME_LENGTH);
        $chada_travel_last_name  = self::clean_text($chada_travel_input['last_name'] ?? '', self::MAX_NAME_LENGTH);
        $chada_travel_email      = self::clean_text($chada_travel_input['email'] ?? '', self::MAX_EMAIL_LENGTH);
        $chada_travel_mobile     = self::clean_text($chada_travel_input['mobile'] ?? '', self::MAX_MOBILE_LENGTH);
        $chada_travel_address    = self::clean_multiline_text($chada_travel_input['address'] ?? '', self::MAX_ADDRESS_LENGTH);
        $chada_travel_privacy    = self::to_bool($chada_travel_input['privacy_consent'] ?? false);
        $chada_travel_terms      = self::to_bool($chada_travel_input['terms_consent'] ?? false);

        if ($chada_travel_first_name === '') {
            $chada_travel_errors['first_name'] = 'First Name is required.';
        }
        if ($chada_travel_email === '' || !self::is_valid_email($chada_travel_email)) {
            $chada_travel_errors['email'] = 'A valid Email Address is required.';
        }
        if ($chada_travel_mobile === '' || !self::is_valid_mobile($chada_travel_mobile)) {
            $chada_travel_errors['mobile'] = 'A valid Mobile Number is required.';
        }
        if (!$chada_travel_privacy || !$chada_travel_terms) {
            $chada_travel_errors['consent'] = 'Privacy Policy and Terms and Conditions consent is required.';
        }

        return [
            'errors' => $chada_travel_errors,
            'clean'  => [
                'first_name'      => $chada_travel_first_name,
                'last_name'       => $chada_travel_last_name,
                'email'           => strtolower($chada_travel_email),
                'mobile'          => $chada_travel_mobile,
                'address'         => $chada_travel_address,
                'privacy_consent' => $chada_travel_privacy,
                'terms_consent'   => $chada_travel_terms,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_country Country row with at least code/name/fee/currency/checklist_version.
     * @return array<string, mixed>
     */
    public static function normalize_country(
        array $chada_travel_country,
        string $chada_travel_global_currency = 'USD'
    ): array {
        return [
            'code'              => strtoupper(self::clean_text((string) ($chada_travel_country['code'] ?? ''), 2)),
            'name'              => self::clean_text((string) ($chada_travel_country['name'] ?? ''), 120),
            'processing_fee'    => number_format(max(0, (float) ($chada_travel_country['processing_fee'] ?? 0)), 2, '.', ''),
            'currency'          => strtoupper(self::clean_text($chada_travel_global_currency, 3)),
            'checklist_version' => self::clean_text((string) ($chada_travel_country['checklist_version'] ?? 'mvp-1'), 40),
            'sort_order'        => max(0, (int) ($chada_travel_country['sort_order'] ?? 0)),
            'full_details'      => CHADA_TRAVEL_Country_Validator::sanitize_full_details(
                $chada_travel_country['full_details'] ?? ''
            ),
            'guide_attachment_id' => max(0, (int) ($chada_travel_country['guide_attachment_id'] ?? 0)),
            'checklist_attachment_id' => max(0, (int) ($chada_travel_country['checklist_attachment_id'] ?? 0)),
        ];
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_country_fees Raw `chada_travel_country_fees` setting rows.
     * @return array<string, array<string, mixed>> Active countries keyed by uppercase country code.
     */
    public static function get_active_countries(
        array $chada_travel_country_fees,
        string $chada_travel_global_currency = 'USD'
    ): array {
        $chada_travel_active = [];
        foreach ($chada_travel_country_fees as $chada_travel_country) {
            if (!is_array($chada_travel_country) || empty($chada_travel_country['is_active'])) {
                continue;
            }
            $chada_travel_normalized = self::normalize_country($chada_travel_country, $chada_travel_global_currency);
            if ($chada_travel_normalized['code'] !== '') {
                $chada_travel_active[$chada_travel_normalized['code']] = $chada_travel_normalized;
            }
        }
        return $chada_travel_active;
    }

    /**
     * @param list<mixed>                  $chada_travel_input           Raw, untrusted Stage 2 application rows.
     * @param array<string, array<string, mixed>> $chada_travel_active_countries Server-authoritative active countries.
     * @param int $chada_travel_max_applicants Server-authoritative cap (see CHADA_TRAVEL_Booking_Workflow_Config::max_applicants());
     *                                   checked before any per-row processing or mutation.
     * @param array<string, mixed> $chada_travel_booker Validated Booker details used for the optional copy check.
     * @param string $chada_travel_min_travel_date Y-m-d; the earliest acceptable Target Travel Date. Empty defaults to
     *     UTC "tomorrow" so this pure function stays testable/dependency-free - real requests always pass the
     *     Company-Timezone-computed value from CHADA_TRAVEL_Config::get_target_travel_date_bounds().
     * @return array{
     *     errors: list<array<string, string>>,
     *     clean: list<array<string, mixed>>,
     *     warnings: list<int>,
     *     has_error: bool
     * }
     */
    public static function validate_applications(
        array $chada_travel_input,
        array $chada_travel_active_countries,
        int $chada_travel_max_applicants = CHADA_TRAVEL_Booking_Workflow_Config::DEFAULT_MAX_APPLICANTS,
        array $chada_travel_booker = [],
        string $chada_travel_min_travel_date = ''
    ): array {
        if ($chada_travel_min_travel_date === '') {
            $chada_travel_min_travel_date = (new \DateTime('tomorrow', new \DateTimeZone('UTC')))->format('Y-m-d');
        }
        $chada_travel_errors    = [];
        $chada_travel_clean     = [];
        $chada_travel_group_map = [];
        $chada_travel_booker_indexes = [];

        if (!$chada_travel_input) {
            return ['errors' => [['_all' => 'At least one Visa Application is required.']], 'clean' => [],
                'warnings' => [], 'has_error' => true];
        }

        if (count($chada_travel_input) > $chada_travel_max_applicants) {
            return ['errors' => [['_all' => sprintf(
                'You can add up to %d applicants per booking.',
                $chada_travel_max_applicants
            )]], 'clean' => [], 'warnings' => [], 'has_error' => true];
        }

        foreach (array_values($chada_travel_input) as $chada_travel_index => $chada_travel_row) {
            $chada_travel_row          = is_array($chada_travel_row) ? $chada_travel_row : [];
            $chada_travel_row_errors   = [];
            $chada_travel_country_code = strtoupper(self::clean_text((string) ($chada_travel_row['country_code'] ?? ''), 2));
            $chada_travel_first_name   = self::clean_text((string) ($chada_travel_row['first_name'] ?? ''), self::MAX_NAME_LENGTH);
            $chada_travel_last_name    = self::clean_text((string) ($chada_travel_row['last_name'] ?? ''), self::MAX_NAME_LENGTH);
            // date_of_birth is intentionally never read here: it is administrator-only from this point forward,
            // so a customer-submitted value (forged or not) must never reach a stored applicant record.
            $chada_travel_travel_date  = self::clean_text((string) ($chada_travel_row['target_travel_date'] ?? ''), 10);
            $chada_travel_is_booker    = self::to_bool($chada_travel_row['is_booker'] ?? false);

            if (!isset($chada_travel_active_countries[$chada_travel_country_code])) {
                $chada_travel_row_errors['country_code'] = 'Select a valid Visa Country.';
            }
            if ($chada_travel_first_name === '') {
                $chada_travel_row_errors['first_name'] = 'Applicant First Name is required.';
            }
            if ($chada_travel_travel_date === '') {
                $chada_travel_row_errors['target_travel_date'] = 'Target Travel Date is required.';
            } elseif (!self::is_valid_travel_date($chada_travel_travel_date, $chada_travel_min_travel_date)) {
                $chada_travel_row_errors['target_travel_date'] = 'Enter a valid Target Travel Date.';
            }
            if (array_key_exists('currency', $chada_travel_row)
                && strtoupper((string) $chada_travel_row['currency'])
                    !== (string) ($chada_travel_active_countries[$chada_travel_country_code]['currency'] ?? '')
            ) {
                $chada_travel_row_errors['currency'] = 'The submitted currency does not match the booking currency.';
            }
            if (array_key_exists('processing_fee', $chada_travel_row)
                && number_format((float) $chada_travel_row['processing_fee'], 2, '.', '')
                    !== (string) ($chada_travel_active_countries[$chada_travel_country_code]['processing_fee'] ?? '')
            ) {
                $chada_travel_row_errors['processing_fee'] = 'The submitted fee does not match the current country fee.';
            }
            if ($chada_travel_is_booker) {
                $chada_travel_booker_indexes[] = $chada_travel_index;
                if ($chada_travel_booker && ($chada_travel_first_name !== (string) ($chada_travel_booker['first_name'] ?? '')
                    || $chada_travel_last_name !== (string) ($chada_travel_booker['last_name'] ?? ''))) {
                    $chada_travel_row_errors['is_booker'] = 'The Booker applicant name must match Booker Details.';
                }
            }

            $chada_travel_errors[] = $chada_travel_row_errors;
            $chada_travel_clean[]  = [
                'country_code'        => $chada_travel_country_code,
                'first_name'          => $chada_travel_first_name,
                'last_name'           => $chada_travel_last_name,
                'target_travel_date'  => $chada_travel_travel_date,
                'is_booker'           => $chada_travel_is_booker,
            ];

            if (!$chada_travel_row_errors) {
                $chada_travel_group_key = strtolower($chada_travel_first_name) . '|' . strtolower($chada_travel_last_name) . '|'
                    . $chada_travel_country_code;
                $chada_travel_group_map[$chada_travel_group_key][] = $chada_travel_index;
            }
        }

        if (count($chada_travel_booker_indexes) > 1) {
            foreach ($chada_travel_booker_indexes as $chada_travel_index) {
                $chada_travel_errors[$chada_travel_index]['is_booker'] = 'Only one applicant can be the Booker.';
            }
        }

        $chada_travel_warnings = [];
        foreach ($chada_travel_group_map as $chada_travel_indexes) {
            if (count($chada_travel_indexes) > 1) {
                array_push($chada_travel_warnings, ...$chada_travel_indexes);
            }
        }
        sort($chada_travel_warnings);

        $chada_travel_has_error = (bool) array_filter($chada_travel_errors);

        return [
            'errors'    => $chada_travel_errors,
            'clean'     => $chada_travel_clean,
            'warnings'  => $chada_travel_warnings,
            'has_error' => $chada_travel_has_error,
        ];
    }

    /**
     * @param list<array<string, mixed>>          $chada_travel_applications     Clean application rows.
     * @param array<string, array<string, mixed>> $chada_travel_active_countries Server-authoritative active countries.
     * @return array{
     *     subtotal: string, total: string, currency: string, lines: list<array<string, mixed>>,
     *     summary: list<array<string, mixed>>
     * }
     */
    public static function calculate_totals(array $chada_travel_applications, array $chada_travel_active_countries): array {
        $chada_travel_currency = 'USD';
        $chada_travel_subtotal = 0.0;
        $chada_travel_lines    = [];
        $chada_travel_summary  = [];

        foreach ($chada_travel_applications as $chada_travel_application) {
            $chada_travel_country = $chada_travel_active_countries[$chada_travel_application['country_code']] ?? null;
            $chada_travel_fee     = $chada_travel_country ? (float) $chada_travel_country['processing_fee'] : 0.0;
            if ($chada_travel_country) {
                $chada_travel_currency = $chada_travel_country['currency'];
            }
            $chada_travel_subtotal += $chada_travel_fee;
            $chada_travel_lines[]   = [
                'country_code'        => $chada_travel_application['country_code'],
                'country_name'        => $chada_travel_country['name'] ?? $chada_travel_application['country_code'],
                'first_name'          => $chada_travel_application['first_name'],
                'last_name'           => $chada_travel_application['last_name'],
                'target_travel_date'  => $chada_travel_application['target_travel_date'],
                'processing_fee'      => number_format($chada_travel_fee, 2, '.', ''),
                'currency'          => $chada_travel_country['currency'] ?? $chada_travel_currency,
                'checklist_version' => $chada_travel_country['checklist_version'] ?? '',
            ];
            if (!isset($chada_travel_summary[$chada_travel_application['country_code']])) {
                $chada_travel_summary[$chada_travel_application['country_code']] = [
                    'country_code' => $chada_travel_application['country_code'],
                    'country_name' => $chada_travel_country['name'] ?? $chada_travel_application['country_code'],
                    'quantity'     => 0,
                    'amount'       => '0.00',
                    'currency'     => $chada_travel_currency,
                ];
            }
            $chada_travel_summary[$chada_travel_application['country_code']]['quantity']++;
            $chada_travel_summary[$chada_travel_application['country_code']]['amount'] = number_format(
                (float) $chada_travel_summary[$chada_travel_application['country_code']]['amount'] + $chada_travel_fee,
                2,
                '.',
                ''
            );
        }

        return [
            'subtotal' => number_format($chada_travel_subtotal, 2, '.', ''),
            'total'    => number_format($chada_travel_subtotal, 2, '.', ''),
            'currency' => $chada_travel_currency,
            'lines'    => $chada_travel_lines,
            'summary'  => array_values($chada_travel_summary),
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_input Raw Stage 3 request payload.
     * @return array{errors: array<string, string>, clean: array{cancellation_refund_consent: bool}}
     */
    public static function validate_review(array $chada_travel_input): array {
        $chada_travel_consent = self::to_bool($chada_travel_input['cancellation_refund_consent'] ?? false);
        $chada_travel_errors  = $chada_travel_consent ? [] : [
            'cancellation_refund_consent' => 'Acknowledge the Cancellation and Refund Policy to continue.',
        ];
        return ['errors' => $chada_travel_errors, 'clean' => ['cancellation_refund_consent' => $chada_travel_consent]];
    }

    public static function is_valid_email(string $chada_travel_value): bool {
        return function_exists('is_email') ? (bool) is_email($chada_travel_value)
            : (bool) filter_var($chada_travel_value, FILTER_VALIDATE_EMAIL);
    }

    public static function is_valid_mobile(string $chada_travel_value): bool {
        return (bool) preg_match('/^[0-9+()\-\s]{7,40}$/', $chada_travel_value);
    }

    public static function is_valid_birth_date(string $chada_travel_value): bool {
        $chada_travel_date = \DateTime::createFromFormat('Y-m-d', $chada_travel_value);
        if (!$chada_travel_date || $chada_travel_date->format('Y-m-d') !== $chada_travel_value) {
            return false;
        }
        $chada_travel_year = (int) $chada_travel_date->format('Y');
        return $chada_travel_year >= self::MIN_BIRTH_YEAR && $chada_travel_date <= new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /** @param string $chada_travel_min_date Y-m-d; the earliest date accepted (inclusive). */
    public static function is_valid_travel_date(string $chada_travel_value, string $chada_travel_min_date): bool {
        $chada_travel_date = \DateTime::createFromFormat('Y-m-d', $chada_travel_value);
        if (!$chada_travel_date || $chada_travel_date->format('Y-m-d') !== $chada_travel_value) {
            return false;
        }
        $chada_travel_min = \DateTime::createFromFormat('Y-m-d', $chada_travel_min_date);
        return !$chada_travel_min || $chada_travel_date >= $chada_travel_min;
    }

    /** @param mixed $chada_travel_value */
    public static function to_bool($chada_travel_value): bool {
        if (is_string($chada_travel_value)) {
            return in_array(strtolower($chada_travel_value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $chada_travel_value;
    }

    /** @param mixed $chada_travel_value */
    private static function clean_text($chada_travel_value, int $chada_travel_max_length): string {
        if (function_exists('sanitize_text_field')) {
            return mb_substr(sanitize_text_field((string) $chada_travel_value), 0, $chada_travel_max_length);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return mb_substr(trim(strip_tags((string) $chada_travel_value)), 0, $chada_travel_max_length);
    }

    /** @param mixed $chada_travel_value */
    private static function clean_multiline_text($chada_travel_value, int $chada_travel_max_length): string {
        if (function_exists('sanitize_textarea_field')) {
            return mb_substr(sanitize_textarea_field((string) $chada_travel_value), 0, $chada_travel_max_length);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return mb_substr(trim(strip_tags((string) $chada_travel_value)), 0, $chada_travel_max_length);
    }
}
