<?php
/** Tours Settings tab: default currency and Featured Image normalization limits. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string $chada_travel_action_url */
/** @var string $chada_travel_save_action */
/** @var string $chada_travel_nonce_html Pre-escaped nonce HTML. */
/** @var string $chada_travel_currency_options_html Pre-escaped option markup. */
/** @var int $chada_travel_image_width */
/** @var int $chada_travel_image_height */
/** @var int $chada_travel_image_max_mb */
?>
<p><?php esc_html_e(
    'Configure the defaults used when administrators create Tours and normalize their Featured Images. Existing Tour currency values are preserved.',
    'chada-travel'
); ?></p>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?>
<?php echo $chada_travel_nonce_html; ?>
<table class="form-table" role="presentation"><tbody>
<tr>
<th scope="row"><label for="chada-travel-tour-default-currency"><?php esc_html_e('Default Tour Currency', 'chada-travel'); ?></label></th>
<td>
<select id="chada-travel-tour-default-currency" name="chada_travel_tour_default_currency">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- options escaped during construction. ?>
<?php echo $chada_travel_currency_options_html; ?>
</select>
<p class="description"><?php esc_html_e('New Tours use this currency by default. Each Tour can select its own currency when saved.', 'chada-travel'); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="chada-travel-tour-featured-image-width"><?php esc_html_e('Featured Image Width', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-tour-featured-image-width"
name="chada_travel_tour_featured_image_width" value="<?php echo esc_attr((string) $chada_travel_image_width); ?>"
min="1" max="5000" step="1" required> <?php esc_html_e('px', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('Final Tour Featured Images are resized and center-cropped to this width.', 'chada-travel'); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="chada-travel-tour-featured-image-height"><?php esc_html_e('Featured Image Height', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-tour-featured-image-height"
name="chada_travel_tour_featured_image_height" value="<?php echo esc_attr((string) $chada_travel_image_height); ?>"
min="1" max="5000" step="1" required> <?php esc_html_e('px', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('Final Tour Featured Images are resized and center-cropped to this height.', 'chada-travel'); ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="chada-travel-tour-featured-image-max-mb"><?php esc_html_e('Maximum Featured Image File Size', 'chada-travel'); ?></label></th>
<td>
<input type="number" class="small-text" id="chada-travel-tour-featured-image-max-mb"
name="chada_travel_tour_featured_image_max_mb" value="<?php echo esc_attr((string) $chada_travel_image_max_mb); ?>"
min="1" max="100" step="1" required> <?php esc_html_e('MB', 'chada-travel'); ?>
<p class="description"><?php esc_html_e('The original JPEG/JPG or PNG selected for a Tour must not exceed this limit before processing.', 'chada-travel'); ?></p>
</td>
</tr>
</tbody></table>
<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e('Save Tour Settings', 'chada-travel'); ?></button></p>
</form>
