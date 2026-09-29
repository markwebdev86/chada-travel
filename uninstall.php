<?php
/**
 * Chada Travel - Agency Manager uninstall policy.
 *
 * Business data is retained unless an owner explicitly defines CHADA_TRAVEL_REMOVE_DATA_ON_UNINSTALL as true.
 *
 * @package Chada_Travel
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('CHADA_TRAVEL_REMOVE_DATA_ON_UNINSTALL') || CHADA_TRAVEL_REMOVE_DATA_ON_UNINSTALL !== true) {
    return;
}

global $wpdb;

require_once __DIR__ . '/includes/CHADA_TRAVEL_Settings_Repository.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Country_Currency_Map.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Checkout.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Payment_Proof.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Document_Upload.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Page_Registry.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Config.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_SMTP_Settings.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Workflow.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Seeder.php';
require_once __DIR__ . '/includes/CHADA_TRAVEL_Installer.php';

$chada_travel_tables = [
    'chada_travel_tour_term_relationships', 'chada_travel_tour_terms', 'chada_travel_tour_files', 'chada_travel_tour_dates', 'chada_travel_tours',
    'chada_travel_documents', 'chada_travel_payments', 'chada_travel_requirements', 'chada_travel_applications',
    'chada_travel_applicants', 'chada_travel_countries', 'chada_travel_events', 'chada_travel_email_queue',
    'chada_travel_orders',
];
foreach ($chada_travel_tables as $chada_travel_table) {
    $chada_travel_table_name = $wpdb->prefix . $chada_travel_table;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-time schema teardown on explicit opt-in uninstall; no cacheable data involved.
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $chada_travel_table_name));
}

$chada_travel_options = array_merge(array_keys(CHADA_TRAVEL\CHADA_TRAVEL_Seeder::get_option_defaults()), [
    'chada_travel_plugin_version', 'chada_travel_db_version', 'chada_travel_settings_version', 'chada_travel_split_architecture',
    // Retired regional-autofill options are included for explicit cleanup on upgraded installations.
    'chada_travel_region_autofill_enabled', 'chada_travel_region_autofill_state',
    // Previous Digital-Wallet-rename options are no longer part of CHADA_TRAVEL_Config::get_default_settings(), but an
    // upgraded installation may still have them; removed only through this same explicit-remove path.
    'chada_travel_gcash_account_name', 'chada_travel_gcash_account_number', 'chada_travel_gcash_qr_attachment_id',
    'chada_travel_gcash_qr_url',
]);
foreach ($chada_travel_options as $chada_travel_option_name) {
    delete_option($chada_travel_option_name);
}
if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('chada_travel_email_queue_worker');
}
$chada_travel_role = get_role('administrator');
foreach (CHADA_TRAVEL\CHADA_TRAVEL_Installer::get_capabilities() as $chada_travel_capability) {
    if ($chada_travel_role) {
        $chada_travel_role->remove_cap($chada_travel_capability);
    }
}
remove_role('chada_travel_manager');

$chada_travel_private_uploads = rtrim((string) wp_upload_dir()['basedir'], '/\\') . '/chada-travel-private';
if (is_dir($chada_travel_private_uploads)) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
    global $wp_filesystem;
    if ($wp_filesystem) {
        $wp_filesystem->delete($chada_travel_private_uploads, true);
    }
}
