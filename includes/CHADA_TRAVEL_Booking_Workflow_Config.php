<?php
/**
 * Fixed core checkout safety values retained for backward-compatible service calls.
 *
 * Booking automation settings and cron processors belong to the Pro add-on. The Free plugin keeps only the
 * bounded values required to protect a checkout request; they are not administrator-editable or payment-gated.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Booking_Workflow_Config {
    public const OPTION_DRAFT_EXPIRY_HOURS = 'chada_travel_booking_draft_expiry_hours';
    public const OPTION_MAX_APPLICANTS = 'chada_travel_max_applicants_per_booking';
    public const OPTION_ARCHIVE_DAYS = 'chada_travel_completed_archive_days';
    public const OPTION_DRAFT_CLEANUP_MONTHS = 'chada_travel_expired_draft_cleanup_months';
    public const MIN_DRAFT_EXPIRY_HOURS = 1;
    public const MAX_DRAFT_EXPIRY_HOURS = 720;
    public const MIN_MAX_APPLICANTS = 1;
    public const MAX_MAX_APPLICANTS = 100;
    public const MIN_ARCHIVE_DAYS = 0;
    public const MAX_ARCHIVE_DAYS = 3650;
    public const MIN_DRAFT_CLEANUP_MONTHS = 0;
    public const MAX_DRAFT_CLEANUP_MONTHS = 24;
    public const DEFAULT_ARCHIVE_DAYS = 0;
    public const DEFAULT_DRAFT_CLEANUP_MONTHS = 0;
    public const CRON_HOOK = 'chada_travel_booking_workflow_archive';
    public const DRAFT_CLEANUP_CRON_HOOK = 'chada_travel_expired_draft_cleanup';
    public const CRON_RECURRENCE = 'daily';
    public const ARCHIVE_BATCH_SIZE = 100;
    public const DRAFT_CLEANUP_BATCH_SIZE = 100;
    public const EVENT_BOOKING_AUTO_ARCHIVED = 'chada_travel_booking_auto_archived';
    public const EVENT_ARCHIVE_RUN = 'chada_travel_booking_archive_run';
    public const EVENT_DRAFT_CLEANUP_RUN = 'chada_travel_expired_draft_cleanup_run';
    public const RUN_RESULT_EMPTY = 'empty';
    public const RUN_RESULT_UNAVAILABLE = 'unavailable';
    public const RUN_RESULT_PARTIAL_FAILURE = 'partial_failure';
    public const DEFAULT_DRAFT_EXPIRY_HOURS = 72;
    public const DEFAULT_MAX_APPLICANTS = 10;

    /** @param array<string, mixed> $chada_travel_settings */
    public static function draft_expiry_hours(array $chada_travel_settings): int {
        $chada_travel_value = (int) ($chada_travel_settings[self::OPTION_DRAFT_EXPIRY_HOURS] ?? self::DEFAULT_DRAFT_EXPIRY_HOURS);
        return $chada_travel_value >= self::MIN_DRAFT_EXPIRY_HOURS && $chada_travel_value <= self::MAX_DRAFT_EXPIRY_HOURS
            ? $chada_travel_value : self::DEFAULT_DRAFT_EXPIRY_HOURS;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function max_applicants(array $chada_travel_settings): int {
        $chada_travel_value = (int) ($chada_travel_settings[self::OPTION_MAX_APPLICANTS] ?? self::DEFAULT_MAX_APPLICANTS);
        return $chada_travel_value >= self::MIN_MAX_APPLICANTS && $chada_travel_value <= self::MAX_MAX_APPLICANTS
            ? $chada_travel_value : self::DEFAULT_MAX_APPLICANTS;
    }

    /** @param array<string, mixed> $chada_travel_settings */
    public static function archive_days(array $chada_travel_settings): int {
        $chada_travel_value = (int) ($chada_travel_settings[self::OPTION_ARCHIVE_DAYS] ?? self::DEFAULT_ARCHIVE_DAYS);
        return $chada_travel_value >= self::MIN_ARCHIVE_DAYS && $chada_travel_value <= self::MAX_ARCHIVE_DAYS
            ? $chada_travel_value : self::DEFAULT_ARCHIVE_DAYS;
    }
    /** @param array<string, mixed> $chada_travel_settings */
    public static function draft_cleanup_months(array $chada_travel_settings): int {
        $chada_travel_value = (int) ($chada_travel_settings[self::OPTION_DRAFT_CLEANUP_MONTHS] ?? self::DEFAULT_DRAFT_CLEANUP_MONTHS);
        return $chada_travel_value >= self::MIN_DRAFT_CLEANUP_MONTHS && $chada_travel_value <= self::MAX_DRAFT_CLEANUP_MONTHS
            ? $chada_travel_value : self::DEFAULT_DRAFT_CLEANUP_MONTHS;
    }
    public static function is_valid_whole_number_in_range(string $chada_travel_value, int $chada_travel_min, int $chada_travel_max): bool {
        return is_numeric($chada_travel_value) && (string) (int) $chada_travel_value === (string) $chada_travel_value
            && (int) $chada_travel_value >= $chada_travel_min && (int) $chada_travel_value <= $chada_travel_max;
    }
    /**
     * @param mixed $chada_travel_result
     * @return array<string, mixed>
     */
    public static function normalize_archive_run_result($chada_travel_result): array {
        if ($chada_travel_result === null) {
            return ['state' => self::RUN_RESULT_EMPTY];
        }
        if (!is_array($chada_travel_result)) {
            return ['state' => self::RUN_RESULT_UNAVAILABLE];
        }
        $chada_travel_created_at = (string) ($chada_travel_result['chada_travel_created_at'] ?? $chada_travel_result['created_at'] ?? '');
        $chada_travel_meta = json_decode((string) ($chada_travel_result['chada_travel_event_meta'] ?? ''), true);
        $chada_travel_count_keys = ['processed_count', 'skipped_count', 'remaining_count'];
        if ($chada_travel_created_at === '' || !is_array($chada_travel_meta)) {
            return ['state' => self::RUN_RESULT_UNAVAILABLE];
        }
        foreach ($chada_travel_count_keys as $chada_travel_count_key) {
            if (!array_key_exists($chada_travel_count_key, $chada_travel_meta) || !is_int($chada_travel_meta[$chada_travel_count_key])) {
                return ['state' => self::RUN_RESULT_UNAVAILABLE];
            }
        }
        return [
            'state' => $chada_travel_meta['skipped_count'] > 0 ? self::RUN_RESULT_PARTIAL_FAILURE : 'success',
            'created_at' => $chada_travel_created_at,
            'processed_count' => $chada_travel_meta['processed_count'],
            'skipped_count' => $chada_travel_meta['skipped_count'],
            'remaining_count' => $chada_travel_meta['remaining_count'],
        ];
    }
}
