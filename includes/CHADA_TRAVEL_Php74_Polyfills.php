<?php
/**
 * PHP 8.0 string-function polyfills for the PHP 7.4 compatibility floor: `str_contains()`, `str_starts_with()`,
 * and `str_ends_with()` do not exist before PHP 8.0. Each is guarded with `function_exists()` so this is a safe
 * no-op on any PHP 8.0+ runtime (the native function always wins) and only ever defines the function on 7.4.
 *
 * Deliberately defined in the global namespace, not `CHADA_TRAVEL\`: `chada-travel.php`'s own autoloader-fallback closure
 * calls `str_starts_with()` from the global namespace before any `CHADA_TRAVEL\` code has loaded, and every `CHADA_TRAVEL\`
 * call site falls back to a global function of the same name once no `CHADA_TRAVEL\`-namespaced one exists - one
 * global definition here covers both. Must be `require`d directly (functions cannot be autoloaded) before
 * anything that might call these three functions - see the top of `chada-travel.php`.
 *
 * @package Chada_Travel
 */

defined('ABSPATH') || exit;

if (!function_exists('str_contains')) {
    function str_contains(string $chada_travel_haystack, string $chada_travel_needle): bool {
        return $chada_travel_needle === '' || strpos($chada_travel_haystack, $chada_travel_needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $chada_travel_haystack, string $chada_travel_needle): bool {
        return $chada_travel_needle === '' || strncmp($chada_travel_haystack, $chada_travel_needle, strlen($chada_travel_needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $chada_travel_haystack, string $chada_travel_needle): bool {
        return $chada_travel_needle === '' || (
            strlen($chada_travel_needle) <= strlen($chada_travel_haystack)
            && substr_compare($chada_travel_haystack, $chada_travel_needle, -strlen($chada_travel_needle)) === 0
        );
    }
}
