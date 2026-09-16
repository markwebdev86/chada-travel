<?php
/**
 * Static client-side search/status/sort toolbar (JS filters rows via data-* attributes; no server round-trip).
 * See CHADA_TRAVEL_Admin_Visa_Countries::render_filters().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div class="tablenav top" data-chada-travel-tour="visa-countries-filters">
<div class="alignleft actions chada-travel-country-filters">
<label for="chada-travel-country-search" class="screen-reader-text"><?php esc_html_e('Search', 'chada-travel'); ?></label>
<input type="search" id="chada-travel-country-search"
    placeholder="<?php echo esc_attr__('Country name or ISO code', 'chada-travel'); ?>">
<label for="chada-travel-country-status" class="screen-reader-text"><?php esc_html_e('Availability', 'chada-travel'); ?></label>
<select id="chada-travel-country-status">
<option value=""><?php esc_html_e('All records', 'chada-travel'); ?></option>
<option value="active"><?php esc_html_e('Active', 'chada-travel'); ?></option>
<option value="archived"><?php esc_html_e('Archived', 'chada-travel'); ?></option>
</select>
<label for="chada-travel-country-sort" class="screen-reader-text"><?php esc_html_e('Sort by', 'chada-travel'); ?></label>
<select id="chada-travel-country-sort">
<option value="order"><?php esc_html_e('Display order', 'chada-travel'); ?></option>
<option value="name"><?php esc_html_e('Country name', 'chada-travel'); ?></option>
<option value="fee"><?php esc_html_e('Processing fee', 'chada-travel'); ?></option>
</select>
</div>
</div>
