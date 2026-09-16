<?php
/**
 * One <tr> form-table row: label + text/number input + optional inline validation error. See
 * CHADA_TRAVEL_Admin_Visa_Countries::build_field_html().
 *
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var string $chada_travel_type Raw value; escaped here.
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_value Raw value; escaped here.
 * @var string $chada_travel_error Raw value; escaped here. '' when there is no validation error to show.
 * @var string $chada_travel_attribute_html Pre-escaped extra input attributes (e.g. maxlength/min/step/placeholder).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_label));
assert(isset($chada_travel_type));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_value));
assert(isset($chada_travel_error));
assert(isset($chada_travel_attribute_html));
?>
<tr><th scope="row">
<label for="<?php echo esc_attr($chada_travel_id); ?>"><?php echo esc_html($chada_travel_label); ?></label>
</th>
<td><input class="regular-text"
    type="<?php echo esc_attr($chada_travel_type); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    id="<?php echo esc_attr($chada_travel_id); ?>"
    value="<?php echo esc_attr($chada_travel_value); ?>"
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_attribute_html; ?>>
<?php if ($chada_travel_error !== ''): ?>
<p class="description chada-travel-field-error"><?php echo esc_html($chada_travel_error); ?></p>
<?php endif; ?>
</td></tr>
