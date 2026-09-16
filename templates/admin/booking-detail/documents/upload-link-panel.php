<?php
/**
 * The booking-level Visa-Document Upload Link panel: a has-token/no-token status line (with a valid/expired
 * sub-state), then - only for a capability-holding viewer - an independently-gated Issue-or-Reissue form
 * (label depends on has-token; only when payment status is Paid) and a separately-gated Revoke form (only
 * when a token exists). See CHADA_TRAVEL_Admin_Booking_Detail::render_upload_link_panel(). The capability check and
 * both nonce fields are resolved in the PHP builder, never here.
 *
 * @var string $chada_travel_status_text Raw value; escaped here.
 * @var bool   $chada_travel_can_manage
 * @var bool   $chada_travel_show_issue Only meaningful when $chada_travel_can_manage.
 * @var string $chada_travel_action_url Raw value; escaped here. Shared by both forms.
 * @var string $chada_travel_order_id Raw value; escaped here. Shared by both forms.
 * @var string $chada_travel_issue_action Raw value; escaped here. Only meaningful when $chada_travel_show_issue.
 * @var string $chada_travel_issue_nonce_html Pre-built HTML from wp_nonce_field(..., true, false). Only meaningful
 *      when $chada_travel_show_issue.
 * @var string $chada_travel_issue_label Raw value; escaped here. Only meaningful when $chada_travel_show_issue.
 * @var bool   $chada_travel_show_revoke Only meaningful when $chada_travel_can_manage.
 * @var string $chada_travel_revoke_action Raw value; escaped here. Only meaningful when $chada_travel_show_revoke.
 * @var string $chada_travel_revoke_nonce_html Pre-built HTML from wp_nonce_field(..., true, false). Only meaningful
 *      when $chada_travel_show_revoke.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_status_text));
assert(isset($chada_travel_can_manage));
assert(isset($chada_travel_show_issue));
assert(isset($chada_travel_action_url));
assert(isset($chada_travel_order_id));
assert(isset($chada_travel_issue_action));
assert(isset($chada_travel_issue_nonce_html));
assert(isset($chada_travel_issue_label));
assert(isset($chada_travel_show_revoke));
assert(isset($chada_travel_revoke_action));
assert(isset($chada_travel_revoke_nonce_html));
?>
<div class="chada-travel-section" data-chada-travel-tour="booking-documents-upload-link">
<h2><?php esc_html_e('Visa-Document Upload Link', 'chada-travel'); ?></h2>
<p><?php echo esc_html($chada_travel_status_text); ?></p>
<?php if ($chada_travel_can_manage): ?>
<?php if ($chada_travel_show_issue): ?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" style="display:inline-block;margin-right:8px;">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_issue_action); ?>">
<input type="hidden" name="order_id" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_issue_nonce_html; ?>
<button type="submit" class="button"><?php echo esc_html($chada_travel_issue_label); ?></button>
</form>
<?php endif; ?>
<?php if ($chada_travel_show_revoke): ?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" style="display:inline-block;">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_revoke_action); ?>">
<input type="hidden" name="order_id" value="<?php echo esc_attr($chada_travel_order_id); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() output, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_revoke_nonce_html; ?>
<button type="submit" class="button"><?php esc_html_e('Revoke Upload Link', 'chada-travel'); ?></button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
