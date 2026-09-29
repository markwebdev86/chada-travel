<?php
/** Shared presentation rules for Tour Travel Date durations. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Tour_Duration {
    /** @param list<array<string, mixed>> $chada_travel_dates */
    public static function format(array $chada_travel_dates): string {
        $chada_travel_range = self::first_range($chada_travel_dates);
        if ($chada_travel_range === null) {
            return '';
        }
        $chada_travel_start_date = $chada_travel_range[0];
        $chada_travel_end_date = $chada_travel_range[1];
        $chada_travel_nights = (int) $chada_travel_start_date->diff($chada_travel_end_date, true)->days;
        $chada_travel_days = $chada_travel_nights + 1;
        return sprintf(
            '%d %s %d %s',
            $chada_travel_days,
            self::day_label($chada_travel_days),
            $chada_travel_nights,
            self::night_label($chada_travel_nights)
        );
    }

    /** @param list<array<string, mixed>> $chada_travel_dates */
    public static function days_only(array $chada_travel_dates): string {
        $chada_travel_range = self::first_range($chada_travel_dates);
        if ($chada_travel_range === null) {
            return '';
        }
        $chada_travel_nights = (int) $chada_travel_range[0]->diff($chada_travel_range[1], true)->days;
        $chada_travel_days = $chada_travel_nights + 1;
        return sprintf('%d %s', $chada_travel_days, self::day_label($chada_travel_days));
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_dates
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}|null
     */
    private static function first_range(array $chada_travel_dates): ?array {
        $chada_travel_first_date = $chada_travel_dates[0] ?? [];
        $chada_travel_start = (string) ($chada_travel_first_date['chada_travel_start_date'] ?? $chada_travel_first_date['start_date'] ?? '');
        $chada_travel_end = (string) ($chada_travel_first_date['chada_travel_end_date'] ?? $chada_travel_first_date['end_date'] ?? '');
        $chada_travel_timezone = new \DateTimeZone('UTC');
        $chada_travel_start_date = \DateTimeImmutable::createFromFormat('!Y-m-d', $chada_travel_start, $chada_travel_timezone);
        $chada_travel_end_date = \DateTimeImmutable::createFromFormat('!Y-m-d', $chada_travel_end, $chada_travel_timezone);
        if (!$chada_travel_start_date || !$chada_travel_end_date || $chada_travel_start_date->format('Y-m-d') !== $chada_travel_start
            || $chada_travel_end_date->format('Y-m-d') !== $chada_travel_end) {
            return null;
        }
        return [$chada_travel_start_date, $chada_travel_end_date];
    }

    private static function day_label(int $chada_travel_days): string {
        if (!function_exists('__')) {
            return $chada_travel_days === 1 ? 'Day' : 'Days';
        }
        return $chada_travel_days === 1 ? __('Day', 'chada-travel') : __('Days', 'chada-travel');
    }

    private static function night_label(int $chada_travel_nights): string {
        if (!function_exists('__')) {
            return $chada_travel_nights === 1 ? 'Night' : 'Nights';
        }
        return $chada_travel_nights === 1 ? __('Night', 'chada-travel') : __('Nights', 'chada-travel');
    }
}
