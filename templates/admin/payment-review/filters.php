<?php
/**
 * Search + payment-method filter form. See CHADA_TRAVEL_Admin_Payment_Review::render_filters().
 *
 * @var string $chada_travel_menu_slug
 * @var string $chada_travel_search Raw; escaped here.
 * @var string $chada_travel_method_options_html Pre-escaped <option> elements.
 * @var string $chada_travel_reset_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_menu_slug));
assert(isset($chada_travel_search));
assert(isset($chada_travel_method_options_html));
assert(isset($chada_travel_reset_url));
?>
<form method="get" class="chada-travel-toolbar" data-chada-travel-tour="payment-review-filters">
<input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_menu_slug); ?>">
<label for="chada-travel-queue-search"><?php esc_html_e('Search', 'chada-travel'); ?>
<input type="search" id="chada-travel-queue-search" name="s" value="<?php echo esc_attr($chada_travel_search); ?>"
placeholder="<?php echo esc_attr__(
    'Visa Booking ID, Booker, payment reference, Digital Wallet reference',
    'chada-travel'
); ?>"
>
</label>
<label for="chada-travel-queue-method"><?php esc_html_e('Method', 'chada-travel'); ?>
<select id="chada-travel-queue-method" name="method">
<option value=""><?php esc_html_e('All methods', 'chada-travel'); ?></option>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_method_options_html; ?>
</select>
</label>
<button type="submit" class="button"><?php esc_html_e('Filter', 'chada-travel'); ?></button>
<a class="button" href="<?php echo esc_url($chada_travel_reset_url); ?>"><?php esc_html_e('Reset', 'chada-travel'); ?></a>
</form>
