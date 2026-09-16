<?php
/**
 * Pure, dependency-free Dashboard period allowlist and UTC boundary resolver. Contains no WordPress function
 * calls other than translation-free plain values, so it is directly unit-testable (see
 * tests/unit/DashboardPeriodTest.php) without a WordPress runtime, matching the existing
 * CHADA_TRAVEL_Order_Repository::is_draft_expired() precedent for pure date-boundary logic in this codebase.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Dashboard_Period {
    public const PERIOD_7D   = '7d';
    public const PERIOD_30D  = '30d';
    public const PERIOD_YEAR = 'year';
    public const PERIOD_ALL  = 'all';
    public const DEFAULT_PERIOD = self::PERIOD_30D;

    /** @return list<string> */
    public static function get_allowed_periods(): array {
        return [self::PERIOD_7D, self::PERIOD_30D, self::PERIOD_YEAR, self::PERIOD_ALL];
    }

    /** Sanitizes a raw request value through the fixed allowlist; missing/invalid values default to 30d. */
    public static function sanitize(string $chada_travel_raw_period): string {
        return in_array($chada_travel_raw_period, self::get_allowed_periods(), true)
            ? $chada_travel_raw_period : self::DEFAULT_PERIOD;
    }

    /**
     * Resolves the inclusive UTC start boundary ('Y-m-d H:i:s') for an already-sanitized period, or null for
     * `all` (no lower boundary). `year` is the only period that uses the configured company timezone - its
     * January 1 midnight is computed in that local timezone, then converted once to UTC for querying; `7d`/`30d`
     * are always a fixed number of UTC calendar days back from the current UTC instant, independent of timezone.
     * A record whose stored UTC timestamp equals this boundary exactly is included by the caller's `>=` comparison.
     *
     * @param \DateTimeImmutable|null $chada_travel_now Injectable for tests; defaults to the real current UTC instant.
     */
    public static function resolve_utc_start(
        string $chada_travel_period,
        string $chada_travel_company_timezone,
        ?\DateTimeImmutable $chada_travel_now = null
    ): ?string {
        $chada_travel_now_utc = ($chada_travel_now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));

        switch ($chada_travel_period) {
            case self::PERIOD_7D:
                return $chada_travel_now_utc->modify('-7 days')->format('Y-m-d H:i:s');
            case self::PERIOD_30D:
                return $chada_travel_now_utc->modify('-30 days')->format('Y-m-d H:i:s');
            case self::PERIOD_YEAR:
                $chada_travel_timezone   = new \DateTimeZone($chada_travel_company_timezone);
                $chada_travel_local_now  = $chada_travel_now_utc->setTimezone($chada_travel_timezone);
                $chada_travel_local_year_start = new \DateTimeImmutable(
                    $chada_travel_local_now->format('Y') . '-01-01 00:00:00',
                    $chada_travel_timezone
                );
                return $chada_travel_local_year_start->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            case self::PERIOD_ALL:
            default:
                return null;
        }
    }
}
