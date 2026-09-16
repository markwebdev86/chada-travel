<?php
/**
 * 2-letter country-code form-table row. See CHADA_TRAVEL_Admin_Settings::build_country_field_html().
 *
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var string $chada_travel_value Raw value; escaped here.
 * @var string $chada_travel_description Raw value; escaped here. '' falls back to a default hint.
 * @var bool $chada_travel_required
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_label));
assert(isset($chada_travel_value));
assert(isset($chada_travel_description));
assert(isset($chada_travel_required));
?>
<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_id); ?>">
<?php echo esc_html($chada_travel_label); ?>
<?php if ($chada_travel_required): ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
<?php endif; ?>
</label></th>
<td><input type="text" class="small-text" style="text-transform:uppercase;" maxlength="2"
    pattern="[A-Za-z]{2}"
    id="<?php echo esc_attr($chada_travel_id); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    value="<?php echo esc_attr($chada_travel_value); ?>"
<?php echo $chada_travel_required ? ' required' : ''; ?>>
<p class="description">
<?php if ($chada_travel_description !== ''): ?>
<?php echo esc_html($chada_travel_description); ?>
<?php else: ?>
<?php esc_html_e('Two-letter country code, e.g. PH.', 'chada-travel'); ?>
<?php endif; ?>
</p>
</td></tr>
