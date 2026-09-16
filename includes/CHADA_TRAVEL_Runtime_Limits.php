<?php
/** Best-effort request runtime limits for Chada Travel operations. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Runtime_Limits {
    public const DESIRED_SECONDS = 300;
    private const EXECUTION_DIRECTIVE = 'max_execution_time';
    private const SOCKET_DIRECTIVE = 'default_socket_timeout';

    /** Raises lower finite PHP limits for the current request without changing persistent host configuration. */
    public static function apply(): void {
        $chada_travel_execution_value = self::read_ini_value(self::EXECUTION_DIRECTIVE);
        $chada_travel_execution_limit = self::parse_limit($chada_travel_execution_value);
        self::raise_ini_limit(self::EXECUTION_DIRECTIVE, $chada_travel_execution_limit);
        self::raise_ini_limit(self::SOCKET_DIRECTIVE, self::parse_limit(self::read_ini_value(self::SOCKET_DIRECTIVE)));
        if (self::should_raise($chada_travel_execution_limit) && function_exists('set_time_limit')) {
            // phpcs:ignore WordPress.PHP.RestrictedFunctions.set_time_limit -- best-effort request-scoped limit.
            @set_time_limit(self::DESIRED_SECONDS);
        }
    }

    /**
     * Returns the requested and currently effective PHP values for an administrator-facing readiness diagnostic.
     *
     * @return array{desired_seconds: int, max_execution_time: string, default_socket_timeout: string,
     *     execution_ready: bool, socket_ready: bool}
     */
    public static function get_status(): array {
        $chada_travel_execution_value = self::read_ini_value(self::EXECUTION_DIRECTIVE);
        $chada_travel_socket_value = self::read_ini_value(self::SOCKET_DIRECTIVE);
        return [
            'desired_seconds' => self::DESIRED_SECONDS,
            'max_execution_time' => $chada_travel_execution_value,
            'default_socket_timeout' => $chada_travel_socket_value,
            'execution_ready' => self::is_satisfied($chada_travel_execution_value),
            'socket_ready' => self::is_satisfied($chada_travel_socket_value),
        ];
    }

    /** Raises one finite lower directive when PHP permits request-level changes. */
    private static function raise_ini_limit(string $chada_travel_directive, ?int $chada_travel_current_limit): void {
        if (!self::should_raise($chada_travel_current_limit) || !function_exists('ini_set')) {
            return;
        }
        // phpcs:ignore WordPress.PHP.IniSet -- best-effort request-scoped limit; persistent host configuration is untouched.
        @ini_set($chada_travel_directive, (string) self::DESIRED_SECONDS);
    }

    /** Reads one PHP directive without allowing unavailable configuration to cause a plugin failure. */
    private static function read_ini_value(string $chada_travel_directive): string {
        if (!function_exists('ini_get')) {
            return '';
        }
        $chada_travel_value = ini_get($chada_travel_directive);
        return is_string($chada_travel_value) ? trim($chada_travel_value) : '';
    }

    /** Parses integer PHP duration directives while treating an unavailable value as unknown. */
    private static function parse_limit(string $chada_travel_value): ?int {
        return preg_match('/^-?\d+$/', $chada_travel_value) === 1 ? (int) $chada_travel_value : null;
    }

    /** Returns whether a known finite value is below the requested duration. */
    private static function should_raise(?int $chada_travel_current_limit): bool {
        return $chada_travel_current_limit !== null
            && $chada_travel_current_limit > 0
            && $chada_travel_current_limit < self::DESIRED_SECONDS;
    }

    /** Returns whether a readable limit is already higher, equal, or unlimited. */
    private static function is_satisfied(string $chada_travel_value): bool {
        $chada_travel_limit = self::parse_limit($chada_travel_value);
        return $chada_travel_value !== '' && ($chada_travel_limit === null || $chada_travel_limit <= 0
            || $chada_travel_limit >= self::DESIRED_SECONDS);
    }
}
