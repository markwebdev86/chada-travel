<?php
/**
 * The header's Archive/Restore form, shown only to users with CHADA_TRAVEL_Admin_Booking_Detail::CAPABILITY.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_header().
 *
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_confirm_text Raw value; escaped here.
 * @var string $chada_travel_action Raw value; escaped here. CHADA_TRAVEL_Admin_Bookings::ARCHIVE_ACTION or ::RESTORE_ACTION.
 * @var string $chada_travel_order_id Raw value; escaped here.
 * @var string $chada_travel_nonce_html Pre-built HTML from wp_nonce_field(..., true, false) (includes the referer field).
 * @var string $chada_travel_button_label Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_confirm_text));
assert(isset($chada_travel_action));
assert(isset($chada_travel_order_id));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_button_label));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>"
    data-chada-travel-confirm="<?php echo esc_attr($chada_travel_confirm_text); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_action); ?>">
<input type="hidden" name="order_id" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<input type="hidden" name="chada_travel_return_to_detail" value="1">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>
<button type="submit" class="button"><?php echo esc_html($chada_travel_button_label); ?></button>
</form>
