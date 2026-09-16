<?php
/**
 * Plugin Name:       Chada Travel - Agency Manager
 * Plugin URI:		  https://github.com/markwebdev86/chada-travel.git
 * Description:       Chada Travel - Agency Manager for Tours, Visa Countries, Bookings, Documents, and Manual Payments.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            markwebdev86
 * Author URI:		  https://github.com/markwebdev86
 * License:           GPL-2.0-or-later
 * License URI:		  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       chada-travel
 * Domain Path:       /languages
 *
 * @package Chada_Travel
 */

defined('ABSPATH') || exit;

define('CHADA_TRAVEL_PROJECT_NAME', 'Chada Travel - Agency Manager');
define('CHADA_TRAVEL_URL_SEGMENT', 'chada-travel');
define('CHADA_TRAVEL_PLUGIN_SLUG', 'chada-travel');
define('CHADA_TRAVEL_PHP_SLUG', 'chada-travel');
define('CHADA_TRAVEL_PLUGIN_FOLDER', 'chada-travel');
define('CHADA_TRAVEL_TEXT_DOMAIN', 'chada-travel');
define('CHADA_TRAVEL_PREFIX', 'chada_travel_');
define('CHADA_TRAVEL_REST_NAMESPACE', 'chada-travel/v1');
define('CHADA_TRAVEL_VERSION', '1.0.0');
define('CHADA_TRAVEL_DB_VERSION', '3.3.0');
define('CHADA_TRAVEL_PLUGIN_FILE', __FILE__);
define('CHADA_TRAVEL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHADA_TRAVEL_PLUGIN_URL', plugin_dir_url(__FILE__));

// PHP 7.4 compatibility: str_starts_with() below (and str_contains()/str_ends_with() elsewhere) do not exist
// natively before PHP 8.0. Must load before anything - including the autoloader fallback closure just below -
// that might call one of the three.
require CHADA_TRAVEL_PLUGIN_DIR . 'includes/CHADA_TRAVEL_Php74_Polyfills.php';

$chada_travel_composer_autoloader = CHADA_TRAVEL_PLUGIN_DIR . 'vendor/autoload.php';
if (is_readable($chada_travel_composer_autoloader)) {
    require $chada_travel_composer_autoloader;
} else {
    spl_autoload_register(static function (string $chada_travel_class_name): void {
        $chada_travel_namespace_prefix = 'CHADA_TRAVEL\\';
        if (!str_starts_with($chada_travel_class_name, $chada_travel_namespace_prefix)) {
            return;
        }
        $chada_travel_relative_class = substr($chada_travel_class_name, strlen($chada_travel_namespace_prefix));
        $chada_travel_class_file     = CHADA_TRAVEL_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $chada_travel_relative_class) . '.php';
        if (is_readable($chada_travel_class_file)) {
            require $chada_travel_class_file;
        }
    });
}

CHADA_TRAVEL\CHADA_TRAVEL_Runtime_Limits::apply();

register_activation_hook(CHADA_TRAVEL_PLUGIN_FILE, ['CHADA_TRAVEL\\CHADA_TRAVEL_Installer', 'activate']);
register_deactivation_hook(CHADA_TRAVEL_PLUGIN_FILE, ['CHADA_TRAVEL\\CHADA_TRAVEL_Installer', 'deactivate']);

add_filter('plugin_action_links_' . plugin_basename(CHADA_TRAVEL_PLUGIN_FILE), static function (array $chada_travel_links): array {
    $chada_travel_action_links = [];
    if (current_user_can('manage_chada_travel_visa_settings')) {
        $chada_travel_action_links['chada-travel-settings'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url(admin_url('admin.php?page=chada-travel-settings')),
            esc_html__('Settings', 'chada-travel')
        );
    }
    if (current_user_can('manage_chada_travel_visa_applications')) {
        $chada_travel_action_links['chada-travel-dashboard'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            esc_url(admin_url('admin.php?page=chada-travel-dashboard')),
            esc_html__('Dashboard', 'chada-travel')
        );
    }
    if (isset($chada_travel_links['deactivate'])) {
        $chada_travel_deactivate = ['deactivate' => $chada_travel_links['deactivate']];
        unset($chada_travel_links['deactivate']);
        $chada_travel_links = array_merge($chada_travel_links, $chada_travel_deactivate, $chada_travel_action_links);
    } else {
        $chada_travel_links = array_merge($chada_travel_links, $chada_travel_action_links);
    }
    return $chada_travel_links;
});

add_action('plugins_loaded', static function (): void {
    CHADA_TRAVEL\CHADA_TRAVEL_Plugin::instance()->run();
});
