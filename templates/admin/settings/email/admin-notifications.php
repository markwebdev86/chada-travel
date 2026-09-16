<?php
/**
 * Administrator Notifications fieldset: the enable checkbox + Admin Notification Email field (via the shared
 * text-field partial). See CHADA_TRAVEL_Admin_Settings::build_admin_notifications_section_html().
 *
 * @var bool   $chada_travel_enabled
 * @var string $chada_travel_admin_notifications_notice
 * @var string $chada_travel_admin_notification_field_html Pre-escaped.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_enabled));
assert(isset($chada_travel_admin_notifications_notice));
assert(isset($chada_travel_admin_notification_field_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Administrator Notifications', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><?php esc_html_e('Send Admin Payment Notifications', 'chada-travel'); ?></th><td>
<label><input type="checkbox" name="chada_travel_email_admin_notifications_enabled" value="1"
    <?php checked($chada_travel_enabled); ?>>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Email an administrator when a Bank Deposit Slip or Digital Wallet payment is submitted for review.', 'chada-travel'); ?>
</label>
<p class="description"><?php echo esc_html($chada_travel_admin_notifications_notice); ?></p></td></tr>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_admin_notification_field_html; ?>
</tbody></table></fieldset>
