<?php
/**
 * Company Logo Media Library picker form-table row. See CHADA_TRAVEL_Admin_Settings::build_logo_field_html().
 *
 * @var string $chada_travel_logo_url Raw value; escaped here. '' when there is no logo.
 * @var bool $chada_travel_has_logo
 * @var int $chada_travel_attachment_id Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_logo_url));
assert(isset($chada_travel_has_logo));
assert(isset($chada_travel_attachment_id));
?>
<tr><th scope="row"><?php esc_html_e('Company Logo', 'chada-travel'); ?></th><td>
<div data-chada-travel-media-control
    data-chada-travel-media-title="<?php echo esc_attr__('Select Company Logo', 'chada-travel'); ?>"
    data-chada-travel-media-button="<?php echo esc_attr__('Use this image', 'chada-travel'); ?>">
<img src="<?php echo esc_url($chada_travel_logo_url); ?>" alt="<?php echo esc_attr__('Current Company Logo', 'chada-travel'); ?>"
    style="max-width:180px;max-height:120px;height:auto;display:block;margin-bottom:8px;"
    data-chada-travel-media-preview<?php echo $chada_travel_has_logo ? '' : ' hidden'; ?>>
<?php if (!$chada_travel_has_logo): ?>
<p class="description" data-chada-travel-media-empty-notice>
<?php esc_html_e('No logo selected yet; Company Name is shown instead.', 'chada-travel'); ?>
</p>
<?php endif; ?>
<input type="hidden" name="chada_travel_company_logo_attachment_id"
    value="<?php echo esc_attr((string) $chada_travel_attachment_id); ?>" data-chada-travel-media-input>
<input type="hidden" name="chada_travel_company_logo_remove" value="0" data-chada-travel-media-remove-input>
<button type="button" class="button" data-chada-travel-select-media
    data-chada-travel-select-label="<?php echo esc_attr__('Select Logo', 'chada-travel'); ?>"
    data-chada-travel-replace-label="<?php echo esc_attr__('Replace Logo', 'chada-travel'); ?>">
<?php echo esc_html($chada_travel_has_logo ? __('Replace Logo', 'chada-travel') : __('Select Logo', 'chada-travel')); ?>
</button>
<button type="button" class="button" data-chada-travel-remove-media<?php echo $chada_travel_has_logo ? '' : ' hidden'; ?>>
<?php esc_html_e('Remove Image', 'chada-travel'); ?>
</button>
</div>
</td></tr>
