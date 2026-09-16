<?php
/**
 * Email tab: main Save form (Sender Identity + Administrator Notifications) followed by Delivery Status and
 * the separate Send Test Email form - rendered strictly after the main form closes. See
 * CHADA_TRAVEL_Admin_Settings::render_email_tab().
 *
 * @var string $chada_travel_action_url
 * @var string $chada_travel_save_action
 * @var string $chada_travel_nonce_html Pre-escaped, from wp_nonce_field().
 * @var string $chada_travel_sender_identity_html
 * @var string $chada_travel_admin_notifications_html
 * @var string $chada_travel_smtp_html
 * @var string $chada_travel_delivery_status_html
 * @var string $chada_travel_send_test_email_html
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_sender_identity_html));
assert(isset($chada_travel_admin_notifications_html));
assert(isset($chada_travel_smtp_html));
assert(isset($chada_travel_delivery_status_html));
assert(isset($chada_travel_send_test_email_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_sender_identity_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_admin_notifications_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller. ?>
<?php echo $chada_travel_smtp_html; ?>

<p class="submit"><button type="submit" class="button button-primary">
<?php esc_html_e('Save Email Settings', 'chada-travel'); ?>
</button></p>
</form>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_delivery_status_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_send_test_email_html; ?>
