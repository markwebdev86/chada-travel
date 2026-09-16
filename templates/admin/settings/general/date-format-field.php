<?php
/**
 * Date Format form-table row. See CHADA_TRAVEL_Admin_Settings::build_date_format_field_html().
 *
 * @var string $chada_travel_options_html Pre-escaped <option> elements, including their selected state.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_options_html));
?>
<tr><th scope="row"><label for="chada-travel-date-format"><?php esc_html_e('Date Format', 'chada-travel'); ?></label></th>
<td><select id="chada-travel-date-format" name="chada_travel_date_format">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select></td></tr>
