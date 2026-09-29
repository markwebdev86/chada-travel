<?php
/**
 * Whole-payload validation for the Settings page's Documents & Uploads tab. Validates the complete submitted
 * payload - Maximum File Size, Allowed Visa Document Types, Document Upload Link Expiry, Allowed Proof Image
 * Types, Payment Proof Link Expiry - before any of the five canonical options is written; on any error the
 * caller must retain every previously stored value unchanged, exactly like every other Settings tab validator
 * (CHADA_TRAVEL_General_Settings_Validator, CHADA_TRAVEL_Policy_Settings_Validator, ...).
 *
 * Also enforces the requirement-integrity rule: a proposed Allowed Visa Document Types selection may never
 * leave an active Visa Country requirement with zero effective MIME types (see
 * CHADA_TRAVEL_Upload_Settings::get_effective_requirement_rules()). This never mutates a requirement row - it only
 * blocks the Settings save until the administrator either picks a compatible selection or updates the affected
 * requirement(s) in Visa Countries.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Documents_Uploads_Settings_Validator {
    /** Maximum number of stranded-requirement labels named in one error message before summarizing the rest. */
    private const MAX_STRANDED_LABELS_SHOWN = 10;

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
        $chada_travel_server_max = CHADA_TRAVEL_Upload_Settings::get_server_max_upload_bytes();
        if ($chada_travel_server_max <= 0) {
            // Fail closed: never overwrite the five existing options when the environment reports no usable
            // positive upload limit at all.
            return ['errors' => [
                'chada_travel_upload_max_mb' => 'The server did not report a usable upload size limit. '
                    . 'Settings were not saved.',
            ]];
        }

        $chada_travel_errors = [];

        $chada_travel_max_bytes = CHADA_TRAVEL_Upload_Settings::parse_mb_input($chada_travel_post['chada_travel_upload_max_mb'] ?? '');
        if ($chada_travel_max_bytes === null) {
            $chada_travel_errors['chada_travel_upload_max_mb'] = 'Enter a valid Maximum File Size greater than zero, in MB.';
        } elseif ($chada_travel_max_bytes > $chada_travel_server_max) {
            $chada_travel_errors['chada_travel_upload_max_mb'] = sprintf(
                'Maximum File Size cannot exceed the current effective WordPress upload limit of %s.',
                self::format_bytes_for_message($chada_travel_server_max)
            );
        }

        $chada_travel_document_mime = CHADA_TRAVEL_Upload_Settings::validate_mime_checkbox_selection(
            $chada_travel_post['chada_travel_document_upload_mime_types'] ?? [],
            CHADA_TRAVEL_Upload_Settings::get_visa_document_mime_values()
        );
        if (!$chada_travel_document_mime['valid']) {
            $chada_travel_errors['chada_travel_document_upload_mime_types'] = $chada_travel_document_mime['reason'] === 'empty'
                ? 'Choose at least one Allowed Visa Document Type.'
                : 'Allowed Visa Document Types contains an unsupported or malformed value.';
        }

        $chada_travel_proof_mime = CHADA_TRAVEL_Upload_Settings::validate_mime_checkbox_selection(
            $chada_travel_post['chada_travel_payment_proof_mime_types'] ?? [],
            CHADA_TRAVEL_Upload_Settings::get_bank_proof_mime_values()
        );
        if (!$chada_travel_proof_mime['valid']) {
            $chada_travel_errors['chada_travel_payment_proof_mime_types'] = $chada_travel_proof_mime['reason'] === 'empty'
                ? 'Choose at least one Allowed Proof Image Type.'
                : 'Allowed Proof Image Types contains an unsupported or malformed value.';
        }

        $chada_travel_upload_hours_raw = $chada_travel_post['chada_travel_upload_token_hours'] ?? '';
        if (!CHADA_TRAVEL_Upload_Settings::is_valid_expiry_hours($chada_travel_upload_hours_raw)) {
            $chada_travel_errors['chada_travel_upload_token_hours'] = sprintf(
                'Enter a whole number of hours between %d and %d for Document Upload Link Expiry.',
                CHADA_TRAVEL_Upload_Settings::MIN_EXPIRY_HOURS,
                CHADA_TRAVEL_Upload_Settings::MAX_EXPIRY_HOURS
            );
        }

        $chada_travel_proof_hours_raw = $chada_travel_post['chada_travel_payment_proof_token_hours'] ?? '';
        if (!CHADA_TRAVEL_Upload_Settings::is_valid_expiry_hours($chada_travel_proof_hours_raw)) {
            $chada_travel_errors['chada_travel_payment_proof_token_hours'] = sprintf(
                'Enter a whole number of hours between %d and %d for Payment Proof Link Expiry.',
                CHADA_TRAVEL_Upload_Settings::MIN_EXPIRY_HOURS,
                CHADA_TRAVEL_Upload_Settings::MAX_EXPIRY_HOURS
            );
        }

        if ($chada_travel_errors) {
            return ['errors' => $chada_travel_errors];
        }

        $chada_travel_clean = [
            'chada_travel_upload_max_bytes'           => $chada_travel_max_bytes,
            'chada_travel_document_upload_mime_types' => $chada_travel_document_mime['clean'],
            'chada_travel_upload_token_hours'         => (int) $chada_travel_upload_hours_raw,
            'chada_travel_payment_proof_mime_types'   => $chada_travel_proof_mime['clean'],
            'chada_travel_payment_proof_token_hours'  => (int) $chada_travel_proof_hours_raw,
        ];

        $chada_travel_stranded = self::find_stranded_requirements(
            $chada_travel_wpdb,
            $chada_travel_clean['chada_travel_document_upload_mime_types']
        );
        if ($chada_travel_stranded) {
            return ['errors' => [
                'chada_travel_document_upload_mime_types' => self::build_stranded_message($chada_travel_stranded),
            ]];
        }

        return [
            'errors' => [],
            'clean'  => $chada_travel_clean,
            'audit'  => self::build_audit($chada_travel_clean, $chada_travel_current_settings, $chada_travel_server_max),
        ];
    }

    /**
     * Finds every active Visa Country requirement that would be left with zero effective MIME types under the
     * proposed global Allowed Visa Document Types selection - i.e. its own allowed MIME types no longer
     * intersect the proposed selection at all.
     *
     * @param list<string> $chada_travel_proposed_document_types
     * @return list<string> Safe "label (country)" identifiers only - never customer/application data.
     */
    private static function find_stranded_requirements(
        object $chada_travel_wpdb,
        array $chada_travel_proposed_document_types
    ): array {
        $chada_travel_stranded = [];
        foreach (CHADA_TRAVEL_Requirement_Repository::get_active_requirements($chada_travel_wpdb) as $chada_travel_requirement) {
            if (!array_intersect($chada_travel_requirement['mime_types'], $chada_travel_proposed_document_types)) {
                $chada_travel_stranded[] = $chada_travel_requirement['label'] . ' (' . $chada_travel_requirement['country_name'] . ')';
            }
        }
        return $chada_travel_stranded;
    }

    /** @param list<string> $chada_travel_stranded */
    private static function build_stranded_message(array $chada_travel_stranded): string {
        $chada_travel_shown = array_slice($chada_travel_stranded, 0, self::MAX_STRANDED_LABELS_SHOWN);
        $chada_travel_extra = count($chada_travel_stranded) - count($chada_travel_shown);
        $chada_travel_list  = implode(', ', $chada_travel_shown) . ($chada_travel_extra > 0 ? sprintf(', and %d more', $chada_travel_extra) : '');
        return 'This selection would leave the following active requirement(s) with no allowed file type: '
            . $chada_travel_list . '. Update these in Visa Countries, or choose a different '
            . 'Allowed Visa Document Types selection.';
    }

    /**
     * Builds safe administrator audit metadata: changed canonical option names and old/new values for each of
     * the five options, plus the server byte limit in effect at save time - never a physical path, token, hash,
     * filename, or customer/application data.
     *
     * @param array<string, mixed> $chada_travel_clean
     * @param array<string, mixed> $chada_travel_current_settings
     * @return array<string, mixed>
     */
    private static function build_audit(
        array $chada_travel_clean,
        array $chada_travel_current_settings,
        int $chada_travel_server_max
    ): array {
        $chada_travel_changed = [];
        foreach ($chada_travel_clean as $chada_travel_option => $chada_travel_value) {
            $chada_travel_old  = $chada_travel_current_settings[$chada_travel_option] ?? null;
            $chada_travel_same = is_array($chada_travel_value)
                ? $chada_travel_old === $chada_travel_value
                : (string) $chada_travel_old === (string) $chada_travel_value;
            if (!$chada_travel_same) {
                $chada_travel_changed[] = $chada_travel_option;
            }
        }

        return [
            'changed_options'          => $chada_travel_changed,
            'old_max_bytes'            => (int) ($chada_travel_current_settings['chada_travel_upload_max_bytes'] ?? 0),
            'new_max_bytes'            => $chada_travel_clean['chada_travel_upload_max_bytes'],
            'old_document_mime_types'  => (array) ($chada_travel_current_settings['chada_travel_document_upload_mime_types'] ?? []),
            'new_document_mime_types'  => $chada_travel_clean['chada_travel_document_upload_mime_types'],
            'old_proof_mime_types'     => (array) ($chada_travel_current_settings['chada_travel_payment_proof_mime_types'] ?? []),
            'new_proof_mime_types'     => $chada_travel_clean['chada_travel_payment_proof_mime_types'],
            'old_upload_token_hours'   => (int) ($chada_travel_current_settings['chada_travel_upload_token_hours'] ?? 0),
            'new_upload_token_hours'   => $chada_travel_clean['chada_travel_upload_token_hours'],
            'old_proof_token_hours'    => (int) ($chada_travel_current_settings['chada_travel_payment_proof_token_hours'] ?? 0),
            'new_proof_token_hours'    => $chada_travel_clean['chada_travel_payment_proof_token_hours'],
            'server_max_bytes_at_save' => $chada_travel_server_max,
        ];
    }

    private static function format_bytes_for_message(int $chada_travel_bytes): string {
        if (function_exists('size_format')) {
            $chada_travel_formatted = size_format($chada_travel_bytes);
            if ($chada_travel_formatted) {
                return $chada_travel_formatted . ' (' . number_format($chada_travel_bytes) . ' bytes)';
            }
        }
        return number_format($chada_travel_bytes) . ' bytes';
    }
}
