<?php
/**
 * Normalized reads/writes, defaults, backward compatibility, and storage for the Free Payment Method Settings
 * options (`chada_travel_bank_accounts`), plus General Settings helpers (e.g. default_timezone()). Also owns maybe_migrate(),
 * the one idempotent settings-data migration gated by `chada_travel_settings_version` - the overall plugin
 * settings-data migration version, tracked independently of `CHADA_TRAVEL_DB_VERSION` so an option-format-only release
 * (a new settings group, a new normalized option shape) never needs a custom-table schema bump. Today it
 * performs the one-time Payment Method option migration (`chada_travel_bank_account_details`) and, from version 2
 * onward, ensures every General Settings option added in that release exists through the non-destructive
 * CHADA_TRAVEL_Seeder::seed_options() path a
 * version bump forces to re-run - see CHADA_TRAVEL_Installer::maybe_upgrade()). From version 4 onward it also attempts
 * idempotent Pages & Links page-id discovery (see migrate_page_settings()). Version 9 (Booking Workflow) retains
 * the Free checkout defaults in CHADA_TRAVEL_Booking_Workflow_Config. Version 11
 * (Digital Wallet) migrates the previous `chada_travel_gcash_*` Settings options to their canonical
 * `chada_travel_digital_wallet_*` equivalents and converts any `chada_travel_gcash` value inside `chada_travel_payment_methods`/
 * `chada_travel_default_payment_method` to `chada_travel_digital_wallet` - see the Digital Wallet option migration. The
 * `chada_travel_gcash_*` options themselves are never written or deleted by this migration; they remain
 * untouched, rollback-safe, unused prior data on an upgraded installation. Version 12 (Page/Policy
 * auto-provisioning) adds no migration step of its own here - the version bump alone is what re-arms
 * CHADA_TRAVEL_Installer::maybe_upgrade() to call activate() one more time on an already-running site, so
 * CHADA_TRAVEL_Page_Provisioner::provision_missing_pages() (called unconditionally from activate(), like the
 * cron-scheduling calls beside it, so it also keeps self-healing on every later manual reactivation) runs
 * against it too. Version 13 (default Visa Countries catalog) likewise adds no migration step of its own - the
 * version bump alone re-arms CHADA_TRAVEL_Installer::maybe_upgrade() so the expanded default catalog (see
 * CHADA_TRAVEL_Seeder::COUNTRY_CATALOG) additively merges into an already-running site's `chada_travel_country_fees` via the
 * same non-destructive CHADA_TRAVEL_Seeder::seed_options()/merge_country_records() path, without touching any
 * administrator-edited or already-seeded country record. Version 14 (add Italy) is the same version-bump-only
 * pattern again, for the same reason, after Italy was added to CHADA_TRAVEL_Seeder::COUNTRY_CATALOG. Version 15
 * (add Hungary) repeats it once more for the same reason. Version 16 (demo media for the six original
 * countries) again adds no migration step of its own here - the version bump alone re-arms
 * CHADA_TRAVEL_Installer::maybe_upgrade() so CHADA_TRAVEL_Demo_Media_Provisioner::provision() (called unconditionally from
 * activate(), right after CHADA_TRAVEL_Seeder::seed_country_catalog(), the same way CHADA_TRAVEL_Page_Provisioner's own
 * provisioning is called unconditionally on a populated upgrade) also runs against an already-activated site: it
 * attaches the plugin's bundled demo Step-by-Step Guide image and Documents Checklist PDF, plus a short
 * clearly-labeled placeholder Full Details paragraph, to whichever of Australia, Canada, Japan, New Zealand,
 * South Korea, and United States (the six original wireframe-test countries) still needs any of those three
 * fields, and activates each - filling in only what is not already there, never overwriting or duplicating
 * anything, and never reversing an administrator's later decision to archive one of these six after it was
 * already fully configured.
 *
 * This class does not decide payment-method readiness (see CHADA_TRAVEL_Payment_Readiness) or perform request-time save
 * validation (see CHADA_TRAVEL_Payment_Settings_Validator / CHADA_TRAVEL_General_Settings_Validator).
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Settings_Repository {
    public const CURRENT_VERSION = 23;
    private const FALLBACK_TIMEZONE = 'Asia/Manila';

    /** @return list<array<string, mixed>> */
    public static function get_bank_accounts(): array {
        $chada_travel_stored = function_exists('get_option') ? get_option('chada_travel_bank_accounts', []) : [];
        return is_array($chada_travel_stored) ? array_values($chada_travel_stored) : [];
    }

    /** @param list<array<string, mixed>> $chada_travel_accounts */
    public static function save_bank_accounts(array $chada_travel_accounts): void {
        if (function_exists('update_option')) {
            update_option('chada_travel_bank_accounts', array_values($chada_travel_accounts), false);
        }
    }

    public static function generate_bank_account_id(): string {
        return 'bnk_' . bin2hex(random_bytes(6));
    }

    /**
     * Resolves the General Settings default Company Timezone: the site's own configured WordPress timezone when
     * it is a valid named IANA identifier, otherwise the fixed fallback. WordPress reports a raw UTC-offset
     * string (e.g. "+08:00") instead of a named identifier for installs that only ever set a numeric offset, so
     * that case intentionally also falls back rather than being stored as a fabricated identifier.
     */
    public static function default_timezone(): string {
        $chada_travel_configured = function_exists('wp_timezone_string')
            ? wp_timezone_string()
            : (function_exists('get_option') ? (string) get_option('timezone_string', '') : '');
        return self::is_valid_timezone($chada_travel_configured) ? $chada_travel_configured : self::FALLBACK_TIMEZONE;
    }

    /** True only for a real, named PHP/IANA timezone identifier (never a raw UTC-offset string). */
    public static function is_valid_timezone(string $chada_travel_timezone): bool {
        return $chada_travel_timezone !== '' && in_array($chada_travel_timezone, \DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Runs the settings-data migration steps due for this release and advances `chada_travel_settings_version` to
     * self::CURRENT_VERSION. Currently performs the one-time Payment Method option migration: normalizes
     * a previous singular `chada_travel_bank_account_details` value into the first `chada_travel_bank_accounts` record.
     * The version-2 General Settings options have no predecessor to migrate from - they are added purely
     * by CHADA_TRAVEL_Installer::activate() re-running CHADA_TRAVEL_Seeder::seed_options() (add_option(), never overwrites) on
     * any install whose stored version is behind, which the version-2 bump alone is what forces to happen; no
     * extra migration step is needed here for them. Version 3 (the Email tab) migrates a previous
     * `chada_travel_bank_payment_email` fallback address into the new `chada_travel_email_admin_notification_address` option -
     * see the administrator-notification migration helper. Version 4 (Pages & Links) attempts a conservative,
     * idempotent discovery of the three workflow page ids (see migrate_page_settings()) - never creates a
     * page, never publishes a Draft, and never guesses when zero or more than one candidate page matches.
     * Version 18 adds the Tour Search Results Page registry option; activation provisions or adopts that page
     * independently without changing existing administrator-selected page assignments.
     * Version 19 adds non-destructive Visa Country Guide image-processing defaults through seed_options().
     * Optional provider and automation options from older installations are intentionally left untouched; they are
     * owned and migrated by the separate Pro add-on when it is installed.
     * Safe to call on every activation - it is a no-op once `chada_travel_settings_version` already meets
     * self::CURRENT_VERSION, and a no-op for a clean install with no prior values to migrate.
     */
    public static function maybe_migrate(object $chada_travel_wpdb): void {
        if (!function_exists('get_option')) {
            return;
        }
        $chada_travel_installed_version = (int) get_option('chada_travel_settings_version', 0);
        if ($chada_travel_installed_version >= self::CURRENT_VERSION) {
            return;
        }

        $chada_travel_bank_migrated            = self::migrate_previous_bank_account();
        $chada_travel_admin_email_migrated     = self::migrate_previous_admin_notification_email();
        $chada_travel_pages_migrated           = self::migrate_page_settings();
        $chada_travel_policy_migrated          = self::migrate_policy_settings();
        $chada_travel_country_resources_migrated = self::migrate_country_resources($chada_travel_wpdb);
        $chada_travel_digital_wallet_migrated  = self::migrate_previous_digital_wallet_options();
        $chada_travel_region_options_removed  = self::migrate_removed_region_autofill_options();

        update_option('chada_travel_settings_version', self::CURRENT_VERSION, false);

        if ($chada_travel_bank_migrated
            || $chada_travel_admin_email_migrated || $chada_travel_pages_migrated || $chada_travel_policy_migrated
            || $chada_travel_country_resources_migrated || $chada_travel_digital_wallet_migrated
            || $chada_travel_region_options_removed
        ) {
            $chada_travel_wpdb->insert($chada_travel_wpdb->prefix . 'chada_travel_events', [
                'chada_travel_record_type'  => 'system',
                'chada_travel_event_type'   => 'chada_travel_settings_migrated',
                'chada_travel_event_message'=> CHADA_TRAVEL_PROJECT_NAME . ' settings migrated to their current normalized '
                    . 'structure and defaults.',
                'chada_travel_event_meta'   => function_exists('wp_json_encode') ? wp_json_encode([
                    'bank_migrated' => $chada_travel_bank_migrated,
                    'admin_notification_email_migrated' => $chada_travel_admin_email_migrated,
                    'pages_migrated' => $chada_travel_pages_migrated,
                    'policy_migrated' => $chada_travel_policy_migrated,
                    'country_resources_migrated' => $chada_travel_country_resources_migrated,
                    'digital_wallet_migrated' => $chada_travel_digital_wallet_migrated,
                    'region_autofill_options_removed' => $chada_travel_region_options_removed,
                ]) : '{}',
                'chada_travel_actor_type'   => 'system',
                'chada_travel_created_at'   => gmdate('Y-m-d H:i:s'),
            ], ['%s', '%s', '%s', '%s', '%s', '%s']);
        }
    }

    /** Removes only the obsolete regional-autofill options and preserves manual regional settings. */
    private static function migrate_removed_region_autofill_options(): bool {
        $chada_travel_removed = false;
        foreach (['chada_travel_region_autofill_enabled', 'chada_travel_region_autofill_state'] as $chada_travel_option) {
            if (function_exists('delete_option') && get_option($chada_travel_option, null) !== null) {
                delete_option($chada_travel_option);
                $chada_travel_removed = true;
            }
        }
        return $chada_travel_removed;
    }

    /** Adds Phase 2 country resource keys while preserving any existing table-backed guide associations. */
    private static function migrate_country_resources(object $chada_travel_wpdb): bool {
        $chada_travel_existing = (array) get_option('chada_travel_country_fees', []);
        $chada_travel_migrated = CHADA_TRAVEL_Country_Repository::migrate_option_shape($chada_travel_wpdb, $chada_travel_existing);
        if ($chada_travel_existing === $chada_travel_migrated) {
            return false;
        }
        update_option('chada_travel_country_fees', $chada_travel_migrated, false);
        CHADA_TRAVEL_Country_Repository::sync_all($chada_travel_wpdb, $chada_travel_migrated);
        return true;
    }

    /** Non-destructively migrates the previous singular Bank record; never duplicates it on a repeated call. */
    private static function migrate_previous_bank_account(): bool {
        $chada_travel_existing = self::get_bank_accounts();
        if ($chada_travel_existing) {
            return false;
        }
        $chada_travel_previous = (array) get_option('chada_travel_bank_account_details', []);
        $chada_travel_has_data = (string) ($chada_travel_previous['bank_name'] ?? '') !== ''
            || (string) ($chada_travel_previous['account_name'] ?? '') !== ''
            || (string) ($chada_travel_previous['account_number'] ?? '') !== '';
        if (!$chada_travel_has_data) {
            return false;
        }
        self::save_bank_accounts([[
            'id'             => self::generate_bank_account_id(),
            'label'          => 'Primary Account',
            'bank_name'      => (string) ($chada_travel_previous['bank_name'] ?? ''),
            'account_name'   => (string) ($chada_travel_previous['account_name'] ?? ''),
            'account_number' => (string) ($chada_travel_previous['account_number'] ?? ''),
            'branch'         => '',
            'account_type'   => '',
            'instructions'   => (string) ($chada_travel_previous['instructions'] ?? ''),
            'is_active'      => 1,
            'sort_order'     => 1,
        ]]);
        return true;
    }

    /**
     * Non-destructively migrates the apparently-unused `chada_travel_bank_payment_email` fallback option into
     * the new `chada_travel_email_admin_notification_address` option: only when the previous option holds a valid,
     * non-empty address and the new option has not already been explicitly set - never overwrites an
     * administrator-set new value, never duplicates the address, never deletes the previous option (kept read-only
     * for any old code path still referencing it), and is a no-op on every repeated call once migrated.
     */
    private static function migrate_previous_admin_notification_email(): bool {
        $chada_travel_new_value = (string) get_option('chada_travel_email_admin_notification_address', '');
        if ($chada_travel_new_value !== '') {
            return false;
        }
        $chada_travel_previous = trim((string) get_option('chada_travel_bank_payment_email', ''));
        $chada_travel_valid  = $chada_travel_previous !== ''
            && (function_exists('is_email') ? (bool) is_email($chada_travel_previous) : (bool) filter_var(
                $chada_travel_previous,
                FILTER_VALIDATE_EMAIL
            ));
        if (!$chada_travel_valid) {
            return false;
        }
        update_option('chada_travel_email_admin_notification_address', $chada_travel_previous, false);
        return true;
    }

    /**
     * Non-destructively migrates previous `chada_travel_gcash_*` Settings options to their canonical
     * `chada_travel_digital_wallet_*` equivalents for an installation upgrading from before the Digital Wallet rename:
     * only fills a still-empty/zero canonical option (never overwrites an administrator's own new-shape value),
     * converts a `chada_travel_gcash` value inside `chada_travel_payment_methods`/`chada_travel_default_payment_method` to
     * `chada_travel_digital_wallet` in place (preserving order and administrator intent), and never writes or deletes
     * the `chada_travel_gcash_*` options themselves - they remain untouched, rollback-safe, unused prior data.
     * A missing provider-name option always resolves to "GCash" for an upgraded configuration that had
     * any other Digital Wallet data (the only provider this codebase ever supported before this release)
     * - never guesses any other provider label, and never runs at all for a clean install with no prior data.
     * Idempotent on every repeated call: nothing is left to migrate once every canonical option/value is set.
     */
    private static function migrate_previous_digital_wallet_options(): bool {
        $chada_travel_migrated = false;

        $chada_travel_had_previous_data = (string) get_option('chada_travel_gcash_account_name', '') !== ''
            || (string) get_option('chada_travel_gcash_account_number', '') !== ''
            || (int) get_option('chada_travel_gcash_qr_attachment_id', 0) > 0;

        if ($chada_travel_had_previous_data && (string) get_option('chada_travel_digital_wallet_name', '') === '') {
            update_option('chada_travel_digital_wallet_name', 'GCash', false);
            $chada_travel_migrated = true;
        }
        if ((string) get_option('chada_travel_digital_wallet_account_name', '') === '') {
            $chada_travel_previous_account_name = (string) get_option('chada_travel_gcash_account_name', '');
            if ($chada_travel_previous_account_name !== '') {
                update_option('chada_travel_digital_wallet_account_name', $chada_travel_previous_account_name, false);
                $chada_travel_migrated = true;
            }
        }
        if ((string) get_option('chada_travel_digital_wallet_account_number', '') === '') {
            $chada_travel_previous_account_number = (string) get_option('chada_travel_gcash_account_number', '');
            if ($chada_travel_previous_account_number !== '') {
                update_option('chada_travel_digital_wallet_account_number', $chada_travel_previous_account_number, false);
                $chada_travel_migrated = true;
            }
        }
        if ((int) get_option('chada_travel_digital_wallet_qr_attachment_id', 0) <= 0) {
            $chada_travel_previous_attachment_id = (int) get_option('chada_travel_gcash_qr_attachment_id', 0);
            if ($chada_travel_previous_attachment_id > 0) {
                update_option('chada_travel_digital_wallet_qr_attachment_id', $chada_travel_previous_attachment_id, false);
                $chada_travel_migrated = true;
            }
        }
        if ((string) get_option('chada_travel_digital_wallet_qr_url', '') === '') {
            $chada_travel_previous_qr_url = (string) get_option('chada_travel_gcash_qr_url', '');
            if ($chada_travel_previous_qr_url !== '') {
                update_option('chada_travel_digital_wallet_qr_url', $chada_travel_previous_qr_url, false);
                $chada_travel_migrated = true;
            }
        }

        $chada_travel_methods = (array) get_option('chada_travel_payment_methods', []);
        $chada_travel_methods_index = array_search('chada_travel_gcash', $chada_travel_methods, true);
        if ($chada_travel_methods_index !== false) {
            $chada_travel_methods[$chada_travel_methods_index] = 'chada_travel_digital_wallet';
            update_option('chada_travel_payment_methods', array_values($chada_travel_methods), false);
            $chada_travel_migrated = true;
        }
        if ((string) get_option('chada_travel_default_payment_method', '') === 'chada_travel_gcash') {
            update_option('chada_travel_default_payment_method', 'chada_travel_digital_wallet', false);
            $chada_travel_migrated = true;
        }

        return $chada_travel_migrated;
    }

    /**
     * Non-destructively discovers the three Pages & Links workflow page ids (see CHADA_TRAVEL_Page_Registry) for an
     * install upgrading from before this option existed. Any option that already holds a nonzero id is left
     * completely untouched (an administrator's own assignment always wins); a zero/missing option is passed to
     * CHADA_TRAVEL_Page_Settings::discover_page_id(), which itself never creates a page and never guesses when more
     * than one candidate matches. Safe to call on every activation - re-running finds nothing left to migrate
     * once every option is set (whether by this method or by an administrator).
     */
    private static function migrate_page_settings(): bool {
        $chada_travel_migrated = false;
        foreach (CHADA_TRAVEL_Page_Registry::get_definitions() as $chada_travel_definition) {
            $chada_travel_option = $chada_travel_definition['option'];
            if ((int) get_option($chada_travel_option, 0) > 0) {
                continue;
            }
            $chada_travel_discovered = CHADA_TRAVEL_Page_Settings::discover_page_id(
                $chada_travel_definition['fallback_path'],
                $chada_travel_definition['shortcode']
            );
            if ($chada_travel_discovered > 0) {
                update_option($chada_travel_option, $chada_travel_discovered, false);
                $chada_travel_migrated = true;
            }
        }
        return $chada_travel_migrated;
    }

    /**
     * Version 7 (Policies & Consent): conservatively discovers the three new policy page-id options for an
     * install upgrading from before this feature existed. Every existing `chada_travel_policy_version` and
     * `chada_travel_*_url` option is preserved exactly as-is - this method only ever fills a still-empty page-id
     * option, never overwrites an administrator's own assignment, never touches an order's consent timestamps,
     * and never creates/publishes/edits a page. Two conservative sources are tried, in order:
     *
     * 1. For the Privacy Policy only: WordPress's own `wp_page_for_privacy_policy` assignment, when one exists
     *    and resolves to a real Page - integrated without ever modifying that core WordPress option itself.
     * 2. For any policy still unset after step 1: a same-site URL-to-page match against its own configured
     *    external URL option (CHADA_TRAVEL_Policy_Settings::discover_page_id_from_url()) - never a cross-policy or
     *    ambiguous guess.
     *
     * A no-op on a clean install (both discovery attempts naturally find nothing) and idempotent on every
     * repeated call (nothing left to discover once every page-id option is set, whether by this method or by
     * an administrator).
     */
    private static function migrate_policy_settings(): bool {
        $chada_travel_migrated = false;

        if ((int) get_option('chada_travel_privacy_policy_page_id', 0) <= 0) {
            $chada_travel_wp_privacy_page_id = (int) get_option('wp_page_for_privacy_policy', 0);
            if ($chada_travel_wp_privacy_page_id > 0 && function_exists('get_post')) {
                $chada_travel_post = get_post($chada_travel_wp_privacy_page_id);
                if ($chada_travel_post && $chada_travel_post->post_type === 'page' && $chada_travel_post->post_status !== 'trash') {
                    update_option('chada_travel_privacy_policy_page_id', $chada_travel_wp_privacy_page_id, false);
                    $chada_travel_migrated = true;
                }
            }
        }

        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_definition) {
            $chada_travel_page_option = $chada_travel_definition['page_option'];
            if ((int) get_option($chada_travel_page_option, 0) > 0) {
                continue;
            }
            $chada_travel_url = (string) get_option($chada_travel_definition['url_option'], '');
            if ($chada_travel_url === '') {
                continue;
            }
            $chada_travel_discovered = CHADA_TRAVEL_Policy_Settings::discover_page_id_from_url($chada_travel_url);
            if ($chada_travel_discovered > 0) {
                update_option($chada_travel_page_option, $chada_travel_discovered, false);
                $chada_travel_migrated = true;
            }
        }

        return $chada_travel_migrated;
    }
}
