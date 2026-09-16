<?php
/**
 * The Confirm/Reject payment-decision forms, shown only to users with
 * CHADA_TRAVEL_Admin_Booking_Detail::PAYMENT_CAPABILITY (silently absent otherwise - no message, unlike
 * render_payment_tab()'s own guard clauses). Both forms post to CHADA_TRAVEL_Admin_Payment_Review's handlers, a
 * different class - action names/nonces are resolved in the PHP builder, never here. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_decision_forms(). Rejection requires a reason (the textarea's `required`
 * attribute); confirmation does not - this distinction must not be changed.
 *
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_payment_id Raw value; escaped here.
 * @var string $chada_travel_redirect_to Raw value; escaped here.
 * @var string $chada_travel_confirm_action Raw value; escaped here.
 * @var string $chada_travel_confirm_nonce_html Pre-built HTML from wp_nonce_field(..., true, false).
 * @var string $chada_travel_reject_action Raw value; escaped here.
 * @var string $chada_travel_reject_nonce_html Pre-built HTML from wp_nonce_field(..., true, false).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_payment_id));
assert(isset($chada_travel_redirect_to));
assert(isset($chada_travel_confirm_action));
assert(isset($chada_travel_confirm_nonce_html));
assert(isset($chada_travel_reject_action));
assert(isset($chada_travel_reject_nonce_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_confirm_action); ?>">
<input type="hidden" name="payment_id" value="<?php echo esc_attr($chada_travel_payment_id); ?>">
<input type="hidden" name="redirect_to" value="<?php echo esc_attr($chada_travel_redirect_to); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_confirm_nonce_html; ?>
<p><label for="chada-travel-confirm-note-<?php echo esc_attr($chada_travel_payment_id); ?>"><?php
esc_html_e('Decision note (optional)', 'chada-travel'); ?></label><br>
<textarea id="chada-travel-confirm-note-<?php echo esc_attr($chada_travel_payment_id); ?>" name="confirmation_note" rows="2"
    class="large-text"></textarea></p>
<button type="submit" class="button button-primary"><?php esc_html_e('Confirm Payment', 'chada-travel'); ?></button>
</form>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_reject_action); ?>">
<input type="hidden" name="payment_id" value="<?php echo esc_attr($chada_travel_payment_id); ?>">
<input type="hidden" name="redirect_to" value="<?php echo esc_attr($chada_travel_redirect_to); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_reject_nonce_html; ?>
<p><label for="chada-travel-reject-reason-<?php echo esc_attr($chada_travel_payment_id); ?>"><?php
esc_html_e('Rejection reason (required)', 'chada-travel'); ?></label><br>
<textarea id="chada-travel-reject-reason-<?php echo esc_attr($chada_travel_payment_id); ?>" name="rejection_reason" rows="2"
    class="large-text" required></textarea></p>
<button type="submit" class="button"><?php esc_html_e('Reject Payment', 'chada-travel'); ?></button>
</form>
