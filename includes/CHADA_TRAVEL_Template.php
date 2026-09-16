<?php
/**
 * Loads a file under templates/ with the given arguments in local scope and captures its output as a string.
 *
 * Escaping convention (binding for every HTML template under templates/): an argument that is already safe,
 * pre-escaped HTML - typically built by CHADA_TRAVEL_View_Components or another template's render() call - is named
 * with an `_html` suffix (e.g. $chada_travel_brand_header_html) and echoed raw in the template, with a
 * `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by ..., see the _html
 * naming convention.` comment on that line. Every other argument is a raw value and must be escaped at its
 * point of output inside the template (esc_html()/esc_attr()/esc_url(), or
 * CHADA_TRAVEL_View_Components::escape_html()/escape_attr() where the template must also run without WordPress
 * loaded) - never pre-escape a raw value here and pass it through unsuffixed. Plain-text (non-HTML) templates,
 * such as email bodies, do not use HTML-escaping functions at all; that escaping is HTML-specific.
 *
 * Because PHPStan cannot see which variables extract() defines inside an included file, every template must
 * open with one type-documenting doc-comment line per consumed variable (the standard "var" tag, type, then
 * variable name and description), immediately after the opening PHP tag and before any markup. That tag alone
 * only supplies the type - PHPStan still reports each variable as possibly undefined unless the same variable
 * is also named in one assert(isset($chada_travel_name)); line (confirmed empirically against this project's
 * phpstan.neon.dist; the type tag on its own does not satisfy the check). zend.assertions is off by default in
 * production, so these calls compile away to nothing at runtime and exist purely for static analysis (and, in
 * an environment with assertions enabled, as a cheap sanity check that the caller passed every required arg).
 *
 * $chada_travel_name is always a hardcoded literal at every call site, never built from request data, so this loader
 * never becomes a path-traversal surface.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Template {
    /**
     * Renders templates/{$chada_travel_name}.php with $chada_travel_args extracted into local scope and returns its output.
     * Returns '' for a missing/unreadable template instead of a warning or fatal.
     *
     * @param array<string, mixed> $chada_travel_args
     */
    public static function render(string $chada_travel_name, array $chada_travel_args = []): string {
        $chada_travel_file = CHADA_TRAVEL_PLUGIN_DIR . 'templates/' . $chada_travel_name . '.php';
        if (function_exists('apply_filters')) {
            $chada_travel_file = (string) apply_filters('chada_travel_template_path', $chada_travel_file, $chada_travel_name);
        }
        if (!is_readable($chada_travel_file)) {
            return '';
        }
        // EXTR_SKIP: an arg key colliding with $chada_travel_file/$chada_travel_name/$chada_travel_args can never overwrite them.
        extract($chada_travel_args, EXTR_SKIP);
        ob_start();
        include $chada_travel_file;
        return (string) ob_get_clean();
    }

    /**
     * Echoes render()'s result, for the echo/void-style admin-page render methods.
     *
     * @param array<string, mixed> $chada_travel_args
     */
    public static function output(string $chada_travel_name, array $chada_travel_args = []): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() output is template-escaped.
        echo self::render($chada_travel_name, $chada_travel_args);
    }
}
