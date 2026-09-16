<?php
/**
 * Currency Display form-table row (Symbol only / Code only / Symbol + Code). See
 * CHADA_TRAVEL_Admin_Settings::build_currency_display_field_html().
 *
 * @var string $chada_travel_options_html Pre-escaped <option> elements, including their selected state.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_options_html));
?>
<tr><th scope="row"><label for="chada-travel-currency-display"><?php esc_html_e('Currency Display', 'chada-travel'); ?></label></th>
<td><select id="chada-travel-currency-display" name="chada_travel_currency_display">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select></td></tr>
