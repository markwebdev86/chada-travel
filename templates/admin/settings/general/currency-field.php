<?php
/**
 * General Currency form-table row. The select keeps a valid existing code as a compatibility option.
 * See CHADA_TRAVEL_Admin_Settings::build_currency_field_html().
 *
 * @var string $chada_travel_current
 * @var string $chada_travel_options_html Pre-escaped <option> elements, including their selected state.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_current));
assert(isset($chada_travel_options_html));
?>
<tr><th scope="row"><label for="chada-travel-currency"><?php esc_html_e('Currency', 'chada-travel'); ?></label></th>
<td><select id="chada-travel-currency" name="chada_travel_currency" aria-describedby="chada-travel-currency-description">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select>
<p class="description" id="chada-travel-currency-description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable setting description. ?>
<?php esc_html_e('USD is the default. Select a common currency, including PHP. Currency settings are configured manually.', 'chada-travel'); ?>
</p></td></tr>
