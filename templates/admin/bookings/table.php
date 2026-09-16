<?php
/**
 * See CHADA_TRAVEL_Admin_Bookings::render_table(). Empty-state wording differs by whether filters are active, exactly
 * matching the original conditional.
 *
 * @var string $chada_travel_rows_html Pre-escaped HTML: zero or more <tr> rows from table-row.php; '' when there are
 *      no orders (the empty-state <tr> below is used instead).
 * @var bool $chada_travel_show_default_empty_message True for "No bookings exist yet.", false for
 *      "No bookings match these filters." - only meaningful when $chada_travel_rows_html is ''.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_show_default_empty_message));
?>
<table class="wp-list-table widefat fixed striped chada-travel-responsive-table" data-chada-travel-tour="bookings-table">
<thead><tr>
<th><?php esc_html_e('Visa Booking / Booker', 'chada-travel'); ?></th>
<th><?php esc_html_e('Applications', 'chada-travel'); ?></th>
<th><?php esc_html_e('Payment', 'chada-travel'); ?></th>
<th><?php esc_html_e('Workflow', 'chada-travel'); ?></th>
<th><?php esc_html_e('Total', 'chada-travel'); ?></th>
<th><?php esc_html_e('Action', 'chada-travel'); ?></th>
</tr></thead>
<tbody>
<?php if ($chada_travel_rows_html === ''): ?>
<tr><td colspan="6">
<?php if ($chada_travel_show_default_empty_message): ?>
<?php esc_html_e('No Visa Bookings exist yet.', 'chada-travel'); ?>
<?php else: ?>
<?php esc_html_e('No Visa Bookings match these filters.', 'chada-travel'); ?>
<?php endif; ?>
</td></tr>
<?php else: ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
<?php endif; ?>
</tbody>
</table>
