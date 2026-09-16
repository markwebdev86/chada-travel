<?php
/**
 * See CHADA_TRAVEL_Admin_Dashboard::render_latest_bookings_table().
 *
 * @var string $chada_travel_rows_html Pre-escaped HTML: zero or more <tr> rows, each from latest-booking-row.php.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
?>
<table class="wp-list-table widefat fixed striped chada-travel-responsive-table">
<thead><tr>
<th><?php esc_html_e('Visa Booking / Booker', 'chada-travel'); ?></th>
<th><?php esc_html_e('Applications', 'chada-travel'); ?></th>
<th><?php esc_html_e('Payment', 'chada-travel'); ?></th>
<th><?php esc_html_e('Workflow', 'chada-travel'); ?></th>
<th><?php esc_html_e('Total', 'chada-travel'); ?></th>
<th><?php esc_html_e('Created', 'chada-travel'); ?></th>
<th><?php esc_html_e('Action', 'chada-travel'); ?></th>
</tr></thead>
<tbody>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
</tbody>
</table>
