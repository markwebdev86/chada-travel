<?php
/**
 * Generic label+input(+description) form-table row, shared across Settings tabs. See
 * CHADA_TRAVEL_Admin_Settings::build_text_field_html().
 *
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var string $chada_travel_value Raw value; escaped here.
 * @var bool $chada_travel_required
 * @var string $chada_travel_type Raw value; escaped here.
 * @var string $chada_travel_description Raw value; escaped here. '' to omit the description paragraph.
 * @var string $chada_travel_extra_attrs_html Pre-escaped extra input attributes; always a fixed literal string supplied
 *      by the caller in code, never user input. '' when there are none.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_label));
assert(isset($chada_travel_value));
assert(isset($chada_travel_required));
assert(isset($chada_travel_type));
assert(isset($chada_travel_description));
assert(isset($chada_travel_extra_attrs_html));
?>
<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_id); ?>">
<?php echo esc_html($chada_travel_label); ?>
<?php if ($chada_travel_required): ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
<?php endif; ?>
</label></th>
<td><input type="<?php echo esc_attr($chada_travel_type); ?>" class="regular-text"
    id="<?php echo esc_attr($chada_travel_id); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    value="<?php echo esc_attr($chada_travel_value); ?>"
<?php echo $chada_travel_required ? ' required' : ''; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_extra_attrs_html !== '' ? ' ' . $chada_travel_extra_attrs_html : ''; ?>>
<?php if ($chada_travel_description !== ''): ?>
<p class="description"><?php echo esc_html($chada_travel_description); ?></p>
<?php endif; ?>
</td></tr>
