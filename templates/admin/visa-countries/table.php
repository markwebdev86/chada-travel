<?php
/**
 * See CHADA_TRAVEL_Admin_Visa_Countries::render_table(). No empty-state branch - the original always renders the
 * <tbody>, even with zero rows (client-side JS handles search/status/sort filtering and display).
 *
 * @var string $chada_travel_rows_html Pre-escaped HTML: zero or more <tr> rows from row.php.
 * @var string $chada_travel_count_text Raw, already-sprintf'd value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_count_text));
?>
<table class="wp-list-table widefat fixed striped" data-chada-travel-tour="visa-countries-table">
<thead><tr>
<th><?php esc_html_e('Country', 'chada-travel'); ?></th>
<th><?php esc_html_e('Processing Fee', 'chada-travel'); ?></th>
<th><?php esc_html_e('Checklist', 'chada-travel'); ?></th>
<th><?php esc_html_e('Requirements', 'chada-travel'); ?></th>
<th><?php esc_html_e('Guide', 'chada-travel'); ?></th>
<th><?php esc_html_e('Status', 'chada-travel'); ?></th>
<th><?php esc_html_e('Actions', 'chada-travel'); ?></th>
</tr></thead>
<tbody id="chada-travel-country-rows">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
</tbody></table>
<p class="description" id="chada-travel-country-count"><?php echo esc_html($chada_travel_count_text); ?></p>
