<?php
/**
 * Send Test Email: its own separate form (never nested in or merged with the main Save form). See
 * CHADA_TRAVEL_Admin_Settings::build_send_test_email_section_html().
 *
 * @var string $chada_travel_action_url
 * @var string $chada_travel_test_email_action
 * @var string $chada_travel_nonce_html Pre-escaped, from wp_nonce_field().
 * @var string $chada_travel_default_recipient
 * @var string $chada_travel_test_email_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_test_email_action));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_default_recipient));
assert(isset($chada_travel_test_email_notice));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Send Test Email', 'chada-travel'); ?></h2></legend>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_test_email_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><label for="chada-travel-test-email-recipient">
<?php esc_html_e('Test Email Recipient', 'chada-travel'); ?>
</label></th>
<td><input type="email" class="regular-text" id="chada-travel-test-email-recipient" name="chada_travel_test_email_recipient"
    value="<?php echo esc_attr($chada_travel_default_recipient); ?>" required></td></tr>
</tbody></table>
<p class="description"><?php echo esc_html($chada_travel_test_email_notice); ?></p>
<p class="submit"><button type="submit" class="button"><?php esc_html_e('Send Test Email', 'chada-travel'); ?></button></p>
</form></fieldset>
