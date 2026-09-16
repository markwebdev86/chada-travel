<?php
/**
 * Payment Method "General" fieldset: enabled-methods checkboxes + default-method select. See
 * CHADA_TRAVEL_Admin_Settings::build_general_section_html().
 *
 * @var string $chada_travel_checkboxes_html Pre-escaped <label><input type=checkbox> elements.
 * @var string $chada_travel_enabled_notice
 * @var string $chada_travel_options_html Pre-escaped <option> elements, including their selected state.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_checkboxes_html));
assert(isset($chada_travel_enabled_notice));
assert(isset($chada_travel_options_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('General', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><?php esc_html_e('Enabled methods', 'chada-travel'); ?></th><td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_checkboxes_html; ?>
<p class="description"><?php echo esc_html($chada_travel_enabled_notice); ?></p></td></tr>

<tr><th scope="row"><label for="chada-travel-default-method">
<?php esc_html_e('Default payment method', 'chada-travel'); ?>
</label></th>
<td><select id="chada-travel-default-method" name="chada_travel_default_payment_method">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select><p class="description">
<?php esc_html_e('Must be an enabled and Ready method once at least one is available.', 'chada-travel'); ?>
</p></td></tr>
</tbody></table></fieldset>
