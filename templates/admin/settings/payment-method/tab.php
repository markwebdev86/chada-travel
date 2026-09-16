<?php
/**
 * Payment Method tab for the Free manual-payment providers.
 *
 * @var string $chada_travel_action_url
 * @var string $chada_travel_save_action
 * @var string $chada_travel_nonce_html Pre-escaped, from wp_nonce_field().
 * @var string $chada_travel_status_summary_html
 * @var string $chada_travel_general_html
 * @var string $chada_travel_bank_html
 * @var string $chada_travel_digital_wallet_html
 * @var string $chada_travel_extensions_html
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_status_summary_html));
assert(isset($chada_travel_general_html));
assert(isset($chada_travel_bank_html));
assert(isset($chada_travel_digital_wallet_html));
assert(isset($chada_travel_extensions_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_status_summary_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_general_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_bank_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_digital_wallet_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extension owns and pre-escapes this HTML. ?>
<?php echo $chada_travel_extensions_html; ?>

<p class="submit"><button type="submit" class="button button-primary">
<?php esc_html_e('Save Payment Settings', 'chada-travel'); ?>
</button></p>
</form>
