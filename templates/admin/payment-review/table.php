<?php
/**
 * See CHADA_TRAVEL_Admin_Payment_Review::render_table(). No separate row template, matching the original inline
 * foreach. Unlike Bookings/Dashboard, the Booking ID cell here is a plain escaped value, not
 * CHADA_TRAVEL_Admin_Bookings::booking_label() - this queue only ever lists payments on orders that already reached a
 * real Booking ID, so the original code never needed the Draft-fallback styling.
 *
 * @var list<array{review_url: string, booking_id: string, booker_name: string, method_label: string,
 *      amount_formatted: string, reference: string, submitted_formatted: string}> $chada_travel_rows
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows));
?>
<table class="wp-list-table widefat fixed striped chada-travel-responsive-table" data-chada-travel-tour="payment-review-table">
<thead><tr>
<th><?php esc_html_e('Visa Booking / Booker', 'chada-travel'); ?></th>
<th><?php esc_html_e('Method', 'chada-travel'); ?></th>
<th><?php esc_html_e('Amount', 'chada-travel'); ?></th>
<th><?php esc_html_e('Reference', 'chada-travel'); ?></th>
<th><?php esc_html_e('Submitted', 'chada-travel'); ?></th>
<th><?php esc_html_e('Action', 'chada-travel'); ?></th>
</tr></thead>
<tbody>
<?php foreach ($chada_travel_rows as $chada_travel_row): ?>
<tr>
<td data-label="<?php echo esc_attr__('Visa Booking / Booker', 'chada-travel'); ?>">
<strong><?php echo esc_html($chada_travel_row['booking_id']); ?></strong><br>
<span class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_row['booker_name']); ?></span>
</td>
<td data-label="<?php echo esc_attr__('Method', 'chada-travel'); ?>"><?php echo esc_html($chada_travel_row['method_label']); ?></td>
<td data-label="<?php echo esc_attr__('Amount', 'chada-travel'); ?>">
<?php echo esc_html($chada_travel_row['amount_formatted']); ?>
</td>
<td data-label="<?php echo esc_attr__('Reference', 'chada-travel'); ?>"><?php echo esc_html($chada_travel_row['reference']); ?></td>
<td data-label="<?php echo esc_attr__('Submitted', 'chada-travel'); ?>">
<?php echo esc_html($chada_travel_row['submitted_formatted']); ?>
</td>
<td data-label="<?php echo esc_attr__('Action', 'chada-travel'); ?>">
<a class="button button-primary button-small" href="<?php echo esc_url($chada_travel_row['review_url']); ?>">
<?php esc_html_e('Review', 'chada-travel'); ?>
</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
