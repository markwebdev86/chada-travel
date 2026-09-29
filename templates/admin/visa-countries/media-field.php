<?php
/**
 * One <tr> form-table row: Media Library attachment picker (Guide image or Documents Checklist file). See
 * CHADA_TRAVEL_Admin_Visa_Countries::build_media_field_html().
 *
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var int $chada_travel_attachment_id Raw value; escaped here.
 * @var string $chada_travel_error Raw value; escaped here. '' when there is no validation error to show.
 * @var string $chada_travel_kind Raw value; escaped here. 'image' or 'checklist'.
 * @var string $chada_travel_title Raw value; escaped here. '' when there is no attachment or no resolvable title.
 * @var string $chada_travel_preview_url Raw value; escaped here. Thumbnail URL or the plugin PDF icon URL.
 * @var string $chada_travel_preview_alt Raw value; escaped here.
 * @var string $chada_travel_instruction Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_field_name));
assert(isset($chada_travel_id));
assert(isset($chada_travel_label));
assert(isset($chada_travel_attachment_id));
assert(isset($chada_travel_error));
assert(isset($chada_travel_kind));
assert(isset($chada_travel_title));
assert(isset($chada_travel_preview_url));
assert(isset($chada_travel_preview_alt));
assert(isset($chada_travel_instruction));
$chada_travel_media_name = $chada_travel_title !== ''
    ? $chada_travel_title
    : __('No file selected', 'chada-travel');
$chada_travel_media_button = $chada_travel_attachment_id > 0
    ? __('Replace', 'chada-travel')
    : __('Select file', 'chada-travel');
?>
<tr><th scope="row"><?php echo esc_html($chada_travel_label); ?></th><td>
<div class="chada-travel-country-media" data-chada-travel-country-media="<?php echo esc_attr($chada_travel_kind); ?>">
<?php $chada_travel_preview_hidden = $chada_travel_preview_url !== '' ? '' : ' hidden'; ?>
<?php $chada_travel_preview_image_class = 'chada-travel-country-media-preview-image'; ?>
<?php if ($chada_travel_label === 'Step-by-Step Guide'): ?>
<?php $chada_travel_preview_image_class .= ' step-by-step-guide'; ?>
<?php endif; ?>
<span class="chada-travel-country-media-preview"
    data-chada-travel-media-preview-container=""<?php echo esc_attr($chada_travel_preview_hidden); ?>>
<img class="<?php echo esc_attr($chada_travel_preview_image_class); ?>" data-chada-travel-media-preview=""
    src="<?php echo esc_url($chada_travel_preview_url); ?>"
    alt="<?php echo esc_attr($chada_travel_preview_alt); ?>" />
</span>
<input type="hidden"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    id="<?php echo esc_attr($chada_travel_id); ?>"
    value="<?php echo esc_attr((string) $chada_travel_attachment_id); ?>">
<span class="chada-travel-country-media-name" data-chada-travel-media-name="">
<?php echo esc_html($chada_travel_media_name); ?>
</span>
<button type="button" class="button" data-chada-travel-select-media>
<?php echo esc_html($chada_travel_media_button); ?>
</button>
<button type="button" class="button" data-chada-travel-remove-media<?php echo $chada_travel_attachment_id > 0 ? '' : ' hidden'; ?>>
<?php esc_html_e('Remove', 'chada-travel'); ?>
</button>
</div>
<p class="description">
<?php echo esc_html($chada_travel_instruction); ?>
</p>
<?php if ($chada_travel_error !== ''): ?>
<p class="description chada-travel-field-error"><?php echo esc_html($chada_travel_error); ?></p>
<?php endif; ?>
</td></tr>
