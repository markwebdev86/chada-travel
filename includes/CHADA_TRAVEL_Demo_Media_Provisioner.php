<?php
/**
 * Attaches the plugin's bundled demo Step-by-Step Guide image and Documents Checklist PDF, plus a short
 * clearly-labeled placeholder Full Details paragraph, to the six original "wireframe test" Visa Countries
 * (Australia, Canada, Japan, New Zealand, South Korea, United States) and activates each one - so a fresh
 * install has working, professional-looking checkout examples instead of an empty/incomplete-looking catalog.
 *
 * Idempotency mirrors CHADA_TRAVEL_Page_Provisioner exactly: a field is only ever filled while it is still at its
 * genuinely-never-configured value (0 for an attachment id, '' for full_details) - a nonzero attachment id or
 * non-empty full_details, whether administrator-entered or left over from an earlier run of this same class, is
 * left completely untouched, never re-validated, never overwritten, never duplicated. A country code entirely
 * absent from `chada_travel_country_fees` (most commonly because it is the site's own configured Company Country,
 * deliberately excluded by CHADA_TRAVEL_Seeder - see its class docblock) is skipped outright; this class never inserts
 * a new country record.
 *
 * `is_active` is only ever forced to `1` for a country in the same pass this class also filled in at least one
 * of guide/checklist/full_details for it. If those three are already all populated and only `is_active` is `0`,
 * that state can only mean an administrator (or an earlier run of this same class, which always sets is_active
 * alongside content) already fully configured the country and then deliberately archived it - that choice is
 * left untouched rather than silently reversed by a later, unrelated version bump.
 *
 * Called once from CHADA_TRAVEL_Installer::activate() (right after CHADA_TRAVEL_Seeder::seed_country_catalog()), itself safe
 * to re-run on every activation and upgrade, so this is too: re-running finds nothing left to provision once
 * every field resolves to real content.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Demo_Media_Provisioner {
    /**
     * Country code => [guide demo filename, checklist demo filename]. Full Details text is deliberately not
     * stored here - see full_details_text(), which composes it from the country's own current `name` field so
     * it always matches whichever display name that code currently uses (an upgrading site's previous "United
     * States" versus a fresh install's "United States of America"), never a name hardcoded in this map.
     *
     * @var array<string, array{guide: string, checklist: string}>
     */
    private const DEMO_FILES = [
        'AU' => ['guide' => 'AU-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'AU-documents-checklist-demo.pdf'],
        'CA' => ['guide' => 'CA-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'CA-documents-checklist-demo.pdf'],
        'JP' => ['guide' => 'JP-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'JP-documents-checklist-demo.pdf'],
        'NZ' => ['guide' => 'NZ-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'NZ-documents-checklist-demo.pdf'],
        'KR' => ['guide' => 'KR-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'KR-documents-checklist-demo.pdf'],
        'US' => ['guide' => 'US-visa-app-step-by-step-guide-demo.jpg',
            'checklist' => 'US-documents-checklist-demo.pdf'],
    ];

    public const GUIDE_DIR = 'assets/images/visa-countries-step-by-step-guide-demos/';
    public const CHECKLIST_DIR = 'assets/visa-countries-step-by-step-guide-pdf-demos/';

    /** @return list<string> The six demo-covered country codes. */
    public static function get_country_codes(): array {
        return array_keys(self::DEMO_FILES);
    }

    /** @return array<string, array{guide: string, checklist: string}> */
    public static function get_demo_files(): array {
        return self::DEMO_FILES;
    }

    /**
     * Pure, no WordPress dependency: true while $chada_travel_country still needs its guide attachment filled in.
     * @param array<string, mixed> $chada_travel_country
     */
    public static function country_needs_guide(array $chada_travel_country): bool {
        return (int) ($chada_travel_country['guide_attachment_id'] ?? 0) <= 0;
    }

    /**
     * Pure: true while $chada_travel_country still needs its checklist attachment filled in.
     * @param array<string, mixed> $chada_travel_country
     */
    public static function country_needs_checklist(array $chada_travel_country): bool {
        return (int) ($chada_travel_country['checklist_attachment_id'] ?? 0) <= 0;
    }

    /**
     * Pure: true while $chada_travel_country still needs Full Details text filled in.
     * @param array<string, mixed> $chada_travel_country
     */
    public static function country_needs_full_details(array $chada_travel_country): bool {
        return CHADA_TRAVEL_Country_Validator::is_full_details_empty((string) ($chada_travel_country['full_details'] ?? ''));
    }

    /**
     * Pure: true when this demo-covered country still needs any of guide/checklist/full_details filled in.
     * @param array<string, mixed> $chada_travel_country
     */
    public static function country_needs_content(array $chada_travel_country): bool {
        return self::country_needs_guide($chada_travel_country)
            || self::country_needs_checklist($chada_travel_country)
            || self::country_needs_full_details($chada_travel_country);
    }

    /**
     * Pure: true when this demo-covered country needs any field filled in, or is not yet Active.
     * @param array<string, mixed> $chada_travel_country
     */
    public static function country_needs_provisioning(array $chada_travel_country): bool {
        return self::country_needs_content($chada_travel_country) || empty($chada_travel_country['is_active']);
    }

    /** Pure: the shared, clearly-labeled demo Full Details paragraph for one country's current display name. */
    public static function full_details_text(string $chada_travel_country_name): string {
        return sprintf(
            '[Demo content - replace before launch] This example %1$s Visa Country record shows how the '
                . 'Step-by-Step Guide and Documents Checklist appear to a customer at checkout. Replace this '
                . 'Full Details text, the Guide image, and the Documents Checklist file with your own %1$s visa '
                . 'requirements and instructions before making this country available to real customers.',
            $chada_travel_country_name
        );
    }

    /**
     * Attaches the bundled demo Guide/Checklist and Full Details text to the six demo countries and activates
     * each one, filling in only whichever of the four fields is still unconfigured for that specific country -
     * see class docblock. No-op outside a real WordPress admin-capable runtime (mirrors
     * CHADA_TRAVEL_Page_Provisioner::provision_missing_pages()'s own guard) so a lightweight test harness without
     * wp_upload_bits()/wp_insert_attachment() never fatals.
     */
    public static function provision(object $chada_travel_wpdb): void {
        if (!function_exists('wp_upload_bits') || !function_exists('wp_insert_attachment')) {
            return;
        }
        $chada_travel_countries = (array) get_option('chada_travel_country_fees', []);
        $chada_travel_changed   = false;

        foreach ($chada_travel_countries as &$chada_travel_country) {
            if (!is_array($chada_travel_country)) {
                continue;
            }
            $chada_travel_code = strtoupper((string) ($chada_travel_country['code'] ?? ''));
            if (!isset(self::DEMO_FILES[$chada_travel_code]) || !self::country_needs_provisioning($chada_travel_country)) {
                continue;
            }
            $chada_travel_files             = self::DEMO_FILES[$chada_travel_code];
            $chada_travel_name              = (string) ($chada_travel_country['name'] ?? $chada_travel_code);
            $chada_travel_content_filled_now = false;

            if (self::country_needs_guide($chada_travel_country)) {
                $chada_travel_guide_id = self::sideload_demo_file(
                    self::GUIDE_DIR . $chada_travel_files['guide'],
                    'image/jpeg',
                    $chada_travel_name . ' Visa Step-by-Step Guide (Demo)'
                );
                if ($chada_travel_guide_id > 0) {
                    $chada_travel_country['guide_attachment_id'] = $chada_travel_guide_id;
                    $chada_travel_content_filled_now = true;
                }
            }
            if (self::country_needs_checklist($chada_travel_country)) {
                $chada_travel_checklist_id = self::sideload_demo_file(
                    self::CHECKLIST_DIR . $chada_travel_files['checklist'],
                    'application/pdf',
                    $chada_travel_name . ' Documents Checklist (Demo)'
                );
                if ($chada_travel_checklist_id > 0) {
                    $chada_travel_country['checklist_attachment_id'] = $chada_travel_checklist_id;
                    $chada_travel_content_filled_now = true;
                }
            }
            if (self::country_needs_full_details($chada_travel_country)) {
                $chada_travel_country['full_details'] = self::full_details_text($chada_travel_name);
                $chada_travel_content_filled_now = true;
            }
            // Only force activation in the same pass that also filled in real content - see class docblock for
            // why a country whose content was already complete before this run must keep whatever is_active
            // value an administrator (or an earlier run of this class) already deliberately left it at.
            if ($chada_travel_content_filled_now) {
                if (empty($chada_travel_country['is_active'])) {
                    $chada_travel_country['is_active'] = 1;
                }
                $chada_travel_changed = true;
            }
        }
        unset($chada_travel_country);

        if (!$chada_travel_changed) {
            return;
        }
        update_option('chada_travel_country_fees', $chada_travel_countries, false);
        CHADA_TRAVEL_Country_Repository::sync_all($chada_travel_wpdb, $chada_travel_countries);
    }

    /**
     * Sideloads one bundled plugin file into the Media Library and returns its new attachment id, or 0 on any
     * failure (missing/unreadable source file, upload/insert failure) - a return of 0 leaves the caller's
     * existing country_needs_*() check true so a later activation retries automatically, never a partial write.
     * Mirrors the exact wp_upload_bits() -> wp_insert_attachment() -> wp_generate_attachment_metadata() ->
     * wp_update_attachment_metadata() sequence already proven in tests/e2e/admin-visa-countries.spec.js.
     */
    private static function sideload_demo_file(
        string $chada_travel_relative_path,
        string $chada_travel_mime,
        string $chada_travel_title
    ): int {
        $chada_travel_source_path = CHADA_TRAVEL_PLUGIN_DIR . $chada_travel_relative_path;
        if (!is_readable($chada_travel_source_path)) {
            return 0;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin-bundled demo asset, not user input.
        $chada_travel_contents = file_get_contents($chada_travel_source_path);
        if ($chada_travel_contents === false) {
            return 0;
        }
        $chada_travel_upload = wp_upload_bits(basename($chada_travel_source_path), null, $chada_travel_contents);
        if (!empty($chada_travel_upload['error'])) {
            return 0;
        }
        $chada_travel_attachment_id = wp_insert_attachment([
            'post_mime_type' => $chada_travel_mime,
            'post_title'     => $chada_travel_title,
            'post_status'    => 'inherit',
            'post_content'   => '',
        ], $chada_travel_upload['file'], 0, true);
        if (is_wp_error($chada_travel_attachment_id)) {
            return 0;
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $chada_travel_metadata = wp_generate_attachment_metadata((int) $chada_travel_attachment_id, $chada_travel_upload['file']);
        wp_update_attachment_metadata((int) $chada_travel_attachment_id, $chada_travel_metadata);
        return (int) $chada_travel_attachment_id;
    }
}
