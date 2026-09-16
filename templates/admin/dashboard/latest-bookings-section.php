<?php
/**
 * See CHADA_TRAVEL_Admin_Dashboard::render_latest_bookings_section(). Latest Bookings is the newest bounded preview,
 * not a replacement for Bookings' own search/filter/pagination.
 *
 * @var string $chada_travel_checkout_url Raw; escaped here. '' when the checkout page is not Ready (link omitted).
 * @var string $chada_travel_table_html Pre-escaped HTML from CHADA_TRAVEL_Admin_Dashboard::render_latest_bookings_table();
 *      '' when there are no bookings yet (the empty-state branch below is used instead).
 * @var string $chada_travel_view_all_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_checkout_url));
assert(isset($chada_travel_table_html));
assert(isset($chada_travel_view_all_url));
?>
<div class="chada-travel-section chada-travel-dashboard-latest-bookings" data-chada-travel-tour="dashboard-latest-bookings">
<h2><?php esc_html_e('Latest Visa Bookings', 'chada-travel'); ?></h2>
<?php if ($chada_travel_table_html === ''): ?>
<p><?php esc_html_e('No Visa Bookings exist yet.', 'chada-travel'); ?>
<?php if ($chada_travel_checkout_url !== ''): ?>
 <a href="<?php echo esc_url($chada_travel_checkout_url); ?>"><?php esc_html_e('Open the checkout page', 'chada-travel'); ?></a>
<?php endif; ?>
</p>
<?php else: ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_table_html; ?>
<p><a href="<?php echo esc_url($chada_travel_view_all_url); ?>">
<?php esc_html_e('View All Visa Bookings', 'chada-travel'); ?></a></p>
<?php endif; ?>
</div>
