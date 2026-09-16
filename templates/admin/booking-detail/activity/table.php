<?php
/**
 * The Activity events table, or an early "No recorded activity yet." state when the filtered result is empty.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_activity_tab(). The Status change column reuses the exact
 * esc_html($from . ' &rarr; ' . $to) double-escaping shape already established in
 * the established booking-activity row structure - preserved as pre-existing, out-of-scope-to-fix behavior,
 * behavior, not a new decision.
 *
 * @var bool   $chada_travel_has_events
 * @var string $chada_travel_rows_html Pre-built HTML blob of the <tr> rows. Only meaningful when $chada_travel_has_events.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_has_events));
assert(isset($chada_travel_rows_html));
?>
<div data-chada-travel-tour="booking-activity-table">
<?php if (!$chada_travel_has_events): ?>
<p><?php esc_html_e('No recorded activity yet.', 'chada-travel'); ?></p>
<?php else: ?>
<table class="wp-list-table widefat fixed striped chada-travel-responsive-table">
<thead><tr>
<th><?php esc_html_e('Time', 'chada-travel'); ?></th>
<th><?php esc_html_e('Event', 'chada-travel'); ?></th>
<th><?php esc_html_e('Related record', 'chada-travel'); ?></th>
<th><?php esc_html_e('Status change', 'chada-travel'); ?></th>
<th><?php esc_html_e('Actor', 'chada-travel'); ?></th>
</tr></thead>
<tbody><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_rows_html; ?></tbody>
</table>
<?php endif; ?>
</div>
