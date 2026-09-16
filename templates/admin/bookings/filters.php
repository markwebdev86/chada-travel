<?php
/**
 * Search + order-status + payment-method filter form. See CHADA_TRAVEL_Admin_Bookings::render_filters().
 *
 * @var string $chada_travel_menu_slug
 * @var string $chada_travel_worklist Raw; escaped here.
 * @var string $chada_travel_search Raw; escaped here.
 * @var string $chada_travel_status_options_html Pre-escaped <option> elements.
 * @var string $chada_travel_method_options_html Pre-escaped <option> elements.
 * @var string $chada_travel_reset_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_menu_slug));
assert(isset($chada_travel_worklist));
assert(isset($chada_travel_search));
assert(isset($chada_travel_status_options_html));
assert(isset($chada_travel_method_options_html));
assert(isset($chada_travel_reset_url));
?>
<form method="get" class="chada-travel-toolbar" data-chada-travel-tour="bookings-filters">
<input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_menu_slug); ?>">
<input type="hidden" name="worklist" value="<?php echo esc_attr($chada_travel_worklist); ?>">
<label for="chada-travel-booking-search"><?php esc_html_e('Search', 'chada-travel'); ?>
<input type="search" id="chada-travel-booking-search" name="s" value="<?php echo esc_attr($chada_travel_search); ?>"
placeholder="<?php echo esc_attr__('Visa Booking ID, Booker, applicant, country', 'chada-travel'); ?>">
</label>
<label for="chada-travel-order-filter"><?php esc_html_e('Order status', 'chada-travel'); ?>
<select id="chada-travel-order-filter" name="order_status">
<option value=""><?php esc_html_e('All statuses', 'chada-travel'); ?></option>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_status_options_html; ?>
</select>
</label>
<label for="chada-travel-method-filter"><?php esc_html_e('Payment method', 'chada-travel'); ?>
<select id="chada-travel-method-filter" name="payment_method">
<option value=""><?php esc_html_e('All methods', 'chada-travel'); ?></option>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_method_options_html; ?>
</select>
</label>
<button type="submit" class="button"><?php esc_html_e('Filter', 'chada-travel'); ?></button>
<a class="button" href="<?php echo esc_url($chada_travel_reset_url); ?>"><?php esc_html_e('Reset', 'chada-travel'); ?></a>
</form>
