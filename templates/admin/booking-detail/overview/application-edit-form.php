<?php
/**
 * Inline Date of Birth / Target Travel Date correction form for one application, revealed by the row's <details>
 * disclosure. See CHADA_TRAVEL_Admin_Booking_Detail::build_applicant_edit_form_html(). Date of Birth is administrator-only
 * (never customer-submitted, see CHADA_TRAVEL_Order_Repository::replace_applications()); Target Travel Date may also be
 * corrected here without Stage 2's forward-looking "tomorrow or later" minimum.
 *
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_action Raw value; escaped here.
 * @var string $chada_travel_order_id Raw value; escaped here.
 * @var string $chada_travel_application_id Raw value; escaped here.
 * @var string $chada_travel_date_of_birth Raw value (Y-m-d or ''); escaped here.
 * @var string $chada_travel_target_travel_date Raw value (Y-m-d or ''); escaped here.
 * @var string $chada_travel_nonce_html Pre-built HTML blob from wp_nonce_field().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_action));
assert(isset($chada_travel_order_id));
assert(isset($chada_travel_application_id));
assert(isset($chada_travel_date_of_birth));
assert(isset($chada_travel_target_travel_date));
assert(isset($chada_travel_nonce_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" class="chada-travel-applicant-edit-form">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_action); ?>">
<input type="hidden" name="order_id" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<input type="hidden" name="application_id" value="<?php echo esc_attr($chada_travel_application_id); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output is already safe markup. ?>
<?php echo $chada_travel_nonce_html; ?>
<label><?php esc_html_e('Date of Birth', 'chada-travel'); ?>
<input type="date" name="date_of_birth" value="<?php echo esc_attr($chada_travel_date_of_birth); ?>"></label>
<label><?php esc_html_e('Target Travel Date', 'chada-travel'); ?>
<input type="date" name="target_travel_date" required
value="<?php echo esc_attr($chada_travel_target_travel_date); ?>"></label>
<button type="submit" class="button button-primary button-small"><?php esc_html_e('Save', 'chada-travel'); ?></button>
</form>
