<?php
/** Visa Countries Settings tab: Step-by-Step Guide image normalization limits. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string $chada_travel_action_url */
/** @var string $chada_travel_save_action */
/** @var string $chada_travel_nonce_html Pre-escaped nonce HTML. */
/** @var int $chada_travel_visa_guide_image_width */
/** @var int $chada_travel_visa_guide_image_height */
/** @var int $chada_travel_visa_guide_image_max_mb */
?>
<p><?php esc_html_e(
    'Configure the source-size limit and final dimensions used when Visa Country Step-by-Step Guide images are processed.',
    'chada-travel'
); ?></p>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?>
<?php echo $chada_travel_nonce_html; ?>
<table class="form-table" role="presentation"><tbody>
<tr>
<th scope="row"><label for="chada-travel-visa-guide-image-width"><?php esc_html_e('Guide Image Width', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-visa-guide-image-width"
name="chada_travel_visa_guide_image_width" value="<?php echo esc_attr((string) $chada_travel_visa_guide_image_width); ?>"
min="1" max="5000" step="1" required> <?php esc_html_e('px', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('Visa Country Guide images are proportionally resized and center-cropped to this width.', 'chada-travel'); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="chada-travel-visa-guide-image-height"><?php esc_html_e('Guide Image Height', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-visa-guide-image-height"
name="chada_travel_visa_guide_image_height" value="<?php echo esc_attr((string) $chada_travel_visa_guide_image_height); ?>"
min="1" max="5000" step="1" required> <?php esc_html_e('px', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('Visa Country Guide images are proportionally resized and center-cropped to this height.', 'chada-travel'); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="chada-travel-visa-guide-image-max-mb"><?php esc_html_e('Maximum Guide Image File Size', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-visa-guide-image-max-mb"
name="chada_travel_visa_guide_image_max_mb" value="<?php echo esc_attr((string) $chada_travel_visa_guide_image_max_mb); ?>"
min="1" max="3" step="1" required> <?php esc_html_e('MB', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('JPEG/JPG and PNG Visa Country Guide source images must not exceed 3 MB.', 'chada-travel'); ?></p>
</td>
</tr>
</tbody></table>
<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Visa Countries Settings', 'chada-travel'); ?></button></p>
</form>
