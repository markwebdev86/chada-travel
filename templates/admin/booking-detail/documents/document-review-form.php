<?php
/**
 * The per-document review decision form (Accept/Reject/Request Replacement + an optional note). See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_document_review_form(). The nonce field is resolved in the PHP builder,
 * never here.
 *
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_review_action Raw value; escaped here.
 * @var string $chada_travel_document_id Raw value; escaped here.
 * @var string $chada_travel_order_id Raw value; escaped here.
 * @var string $chada_travel_nonce_html Pre-built HTML from wp_nonce_field(..., true, false).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_review_action));
assert(isset($chada_travel_document_id));
assert(isset($chada_travel_order_id));
assert(isset($chada_travel_nonce_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_review_action); ?>">
<input type="hidden" name="document_id" value="<?php echo esc_attr($chada_travel_document_id); ?>">
<input type="hidden" name="order_id" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>
<select name="status">
<option value="chada_travel_accepted"><?php esc_html_e('Accept', 'chada-travel'); ?></option>
<option value="chada_travel_rejected"><?php esc_html_e('Reject', 'chada-travel'); ?></option>
<option value="chada_travel_replacement_required"><?php esc_html_e('Request Replacement', 'chada-travel'); ?></option>
</select>
<input type="text" name="review_note" placeholder="<?php
esc_attr_e('Review note (required to reject/replace)', 'chada-travel'); ?>">
<button type="submit" class="button button-small"><?php esc_html_e('Save Decision', 'chada-travel'); ?></button>
</form>
