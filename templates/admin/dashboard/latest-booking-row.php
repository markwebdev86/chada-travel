<?php
/**
 * See CHADA_TRAVEL_Admin_Dashboard::build_latest_booking_row_html().
 *
 * @var string $chada_travel_view_url
 * @var string $chada_travel_booking_label_html Pre-escaped HTML from CHADA_TRAVEL_Admin_Bookings::booking_label().
 * @var string $chada_travel_booker_name Raw; escaped here.
 * @var string $chada_travel_booker_email Raw; escaped here.
 * @var bool $chada_travel_is_archived
 * @var int $chada_travel_application_count
 * @var string $chada_travel_country_summary Raw; escaped here.
 * @var string $chada_travel_method_label Raw; escaped here.
 * @var string $chada_travel_payment_status_label Raw; escaped here. '' hides the badge entirely.
 * @var string $chada_travel_order_status_label Raw; escaped here.
 * @var string $chada_travel_file_status_label Raw; escaped here.
 * @var string $chada_travel_total_formatted Raw; escaped here.
 * @var string $chada_travel_created_formatted Raw; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_view_url));
assert(isset($chada_travel_booking_label_html));
assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booker_email));
assert(isset($chada_travel_is_archived));
assert(isset($chada_travel_application_count));
assert(isset($chada_travel_country_summary));
assert(isset($chada_travel_method_label));
assert(isset($chada_travel_payment_status_label));
assert(isset($chada_travel_order_status_label));
assert(isset($chada_travel_file_status_label));
assert(isset($chada_travel_total_formatted));
assert(isset($chada_travel_created_formatted));
?>
<tr>
<td data-label="<?php echo esc_attr__('Visa Booking / Booker', 'chada-travel'); ?>">
<a href="<?php echo esc_url($chada_travel_view_url); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_booking_label_html; ?>
</a><br><span class="chada-travel-muted chada-travel-small">
<?php echo esc_html($chada_travel_booker_name); ?> &middot; <?php echo esc_html($chada_travel_booker_email); ?>
</span>
<?php if ($chada_travel_is_archived): ?>
 <span class="chada-travel-badge"><?php esc_html_e('Archived', 'chada-travel'); ?></span>
<?php endif; ?>
</td>
<td data-label="<?php echo esc_attr__('Applications', 'chada-travel'); ?>" class="chada-travel-stack">
<span><?php echo esc_html(sprintf(
    /* translators: %d: number of visa applications on this booking. */
    _n('%d application', '%d applications', $chada_travel_application_count, 'chada-travel'),
    $chada_travel_application_count
)); ?></span> <span class="chada-travel-muted chada-travel-small">(<?php echo esc_html($chada_travel_country_summary); ?>)</span>
</td>
<td data-label="<?php echo esc_attr__('Payment', 'chada-travel'); ?>" class="chada-travel-stack">
<span><?php echo esc_html($chada_travel_method_label); ?></span>
<?php if ($chada_travel_payment_status_label !== ''): ?>
<span class="chada-travel-badge"><?php echo esc_html($chada_travel_payment_status_label); ?></span>
<?php endif; ?>
</td>
<td data-label="<?php echo esc_attr__('Workflow', 'chada-travel'); ?>" class="chada-travel-stack">
<span class="chada-travel-badge"><?php echo esc_html($chada_travel_order_status_label); ?></span>
<span class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_file_status_label); ?></span>
</td>
<td data-label="<?php echo esc_attr__('Total', 'chada-travel'); ?>"><?php echo esc_html($chada_travel_total_formatted); ?></td>
<td data-label="<?php echo esc_attr__('Created', 'chada-travel'); ?>"><?php echo esc_html($chada_travel_created_formatted); ?></td>
<td data-label="<?php echo esc_attr__('Action', 'chada-travel'); ?>">
<a class="button button-small" href="<?php echo esc_url($chada_travel_view_url); ?>">
<?php esc_html_e('View Visa Booking', 'chada-travel'); ?>
</a>
</td>
</tr>
