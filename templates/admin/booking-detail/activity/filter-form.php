<?php
/**
 * The Activity tab's GET filter form (event type + actor type). See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_activity_tab().
 *
 * @var string $chada_travel_page_slug Raw value; escaped here.
 * @var string $chada_travel_order_id Raw value; escaped here.
 * @var string $chada_travel_tab Raw value; escaped here.
 * @var list<string> $chada_travel_available_types Raw values; escaped here. Real event types from this order's own
 *      event log.
 * @var string $chada_travel_selected_event_type Raw value; used with selected(), not echoed directly.
 * @var list<array{value: string, label: string}> $chada_travel_actor_types Raw values; escaped here. A fixed 5-entry
 *      list.
 * @var string $chada_travel_selected_actor_type Raw value; used with selected(), not echoed directly.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_page_slug));
assert(isset($chada_travel_order_id));
assert(isset($chada_travel_tab));
assert(isset($chada_travel_available_types));
assert(isset($chada_travel_selected_event_type));
assert(isset($chada_travel_actor_types));
assert(isset($chada_travel_selected_actor_type));
?>
<form method="get" class="chada-travel-toolbar" data-chada-travel-tour="booking-activity-filters">
<input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_page_slug); ?>">
<input type="hidden" name="view" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<input type="hidden" name="tab" value="<?php echo esc_attr($chada_travel_tab); ?>">
<label for="chada-travel-activity-event-type"><?php esc_html_e('Event type', 'chada-travel'); ?>
<select id="chada-travel-activity-event-type" name="event_type">
<option value=""><?php esc_html_e('All events', 'chada-travel'); ?></option>
<?php foreach ($chada_travel_available_types as $chada_travel_type): ?>
<option value="<?php echo esc_attr($chada_travel_type); ?>" <?php
echo selected($chada_travel_selected_event_type, $chada_travel_type, false); ?>><?php echo esc_html($chada_travel_type); ?></option>
<?php endforeach; ?>
</select>
</label>
<label for="chada-travel-activity-actor-type"><?php esc_html_e('Actor', 'chada-travel'); ?>
<select id="chada-travel-activity-actor-type" name="actor_type">
<option value=""><?php esc_html_e('All actors', 'chada-travel'); ?></option>
<?php foreach ($chada_travel_actor_types as $chada_travel_actor_type): ?>
<option value="<?php echo esc_attr($chada_travel_actor_type['value']); ?>" <?php
echo selected($chada_travel_selected_actor_type, $chada_travel_actor_type['value'], false); ?>><?php
echo esc_html($chada_travel_actor_type['label']); ?></option>
<?php endforeach; ?>
</select>
</label>
<button type="submit" class="button"><?php esc_html_e('Filter', 'chada-travel'); ?></button>
</form>
