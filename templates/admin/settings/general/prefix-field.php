<?php
/**
 * Prefix text field with a live example ID. See CHADA_TRAVEL_Admin_Settings::build_prefix_field_html().
 *
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var string $chada_travel_value Raw value; escaped here.
 * @var string $chada_travel_note Raw value; escaped here.
 * @var string $chada_travel_example_label Raw, already-sprintf'd value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_label));
assert(isset($chada_travel_value));
assert(isset($chada_travel_note));
assert(isset($chada_travel_example_label));
?>
<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_id); ?>">
<?php echo esc_html($chada_travel_label); ?> <span aria-hidden="true">*</span>
<span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td><input type="text" class="regular-text" style="text-transform:uppercase;" maxlength="20"
    id="<?php echo esc_attr($chada_travel_id); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    value="<?php echo esc_attr($chada_travel_value); ?>" required>
<p class="description">
<?php echo esc_html($chada_travel_note); ?>
<?php echo esc_html($chada_travel_example_label); ?>
</p>
</td></tr>
