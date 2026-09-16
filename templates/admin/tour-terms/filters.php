<?php
/** Tour taxonomy manager filters. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_menu_slug Raw page slug. */
/** @var string|null $chada_travel_search Raw search value. */
/** @var string|null $chada_travel_status Raw status value. */

assert(isset($chada_travel_menu_slug)); assert(isset($chada_travel_search)); assert(isset($chada_travel_status));
?>
<form class="chada-travel-toolbar" method="get"><input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_menu_slug); ?>"><label><?php esc_html_e('Search', 'chada-travel'); ?><input type="search" name="s" value="<?php echo esc_attr($chada_travel_search); ?>"></label><label><?php esc_html_e('Status', 'chada-travel'); ?><select name="status"><option value="active"<?php selected($chada_travel_status, 'active'); ?>><?php esc_html_e('Active', 'chada-travel'); ?></option><option value="archived"<?php selected($chada_travel_status, 'archived'); ?>><?php esc_html_e('Archived', 'chada-travel'); ?></option></select></label><button class="button" type="submit"><?php esc_html_e('Filter', 'chada-travel'); ?></button></form>
