<?php
/** One Tour grid row. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var int|null $chada_travel_id Raw Tour ID; escaped here. */
/** @var int|null $chada_travel_row_number 1-based position in the current filtered result set; escaped here. */
/** @var string|null $chada_travel_tour_name Raw Tour name; escaped here. */
/** @var string|null $chada_travel_permalink Canonical front-end Tour URL; escaped here. */
/** @var string|null $chada_travel_code Raw Tour code; escaped here. */
/** @var string|null $chada_travel_image_url Raw thumbnail URL; escaped here. */
/** @var string|null $chada_travel_dates_html Pre-escaped date labels. */
/** @var string|null $chada_travel_price Raw formatted price; escaped here. */
/** @var string|null $chada_travel_status Raw status label; escaped here. */
/** @var bool|null $chada_travel_is_active True when the Tour is active. */
/** @var bool|null $chada_travel_is_featured True when the Tour is Featured. */
/** @var string|null $chada_travel_toggle_action Raw action name; escaped here. */
/** @var string|null $chada_travel_toggle_url Raw admin-post URL; escaped here. */
/** @var string|null $chada_travel_toggle_nonce_html Pre-escaped nonce HTML. */
/** @var string|null $chada_travel_delete_action Raw action name; escaped here. */
/** @var string|null $chada_travel_delete_nonce_html Pre-escaped nonce HTML. */

assert(isset($chada_travel_id)); assert(isset($chada_travel_row_number)); assert(isset($chada_travel_tour_name)); assert(isset($chada_travel_permalink)); assert(isset($chada_travel_code)); assert(isset($chada_travel_image_url));
assert(isset($chada_travel_dates_html)); assert(isset($chada_travel_price)); assert(isset($chada_travel_status)); assert(isset($chada_travel_is_active)); assert(isset($chada_travel_is_featured));
assert(isset($chada_travel_toggle_action)); assert(isset($chada_travel_toggle_url)); assert(isset($chada_travel_toggle_nonce_html)); assert(isset($chada_travel_delete_action)); assert(isset($chada_travel_delete_nonce_html));
?>
<?php /* translators: %s: Tour name. */ ?>
<tr data-chada-travel-tour-row="<?php echo esc_attr((string) $chada_travel_id); ?>"><td class="chada-travel-row-number-column" data-label="<?php esc_attr_e('No.', 'chada-travel'); ?>"><?php echo esc_html((string) $chada_travel_row_number); ?></td><td><strong><?php if ($chada_travel_permalink !== ''): ?><a class="chada-travel-tour-name-link" data-chada-travel-tour-url-open href="<?php echo esc_url($chada_travel_permalink); ?>" target="_blank" rel="noopener"><?php echo esc_html($chada_travel_tour_name); ?></a><?php else: ?><?php echo esc_html($chada_travel_tour_name); ?><?php endif; ?></strong><br><span class="description"><?php echo esc_html($chada_travel_code); ?></span></td><td class="chada-travel-tour-grid-image"><?php if ($chada_travel_image_url !== ''): ?><img src="<?php echo esc_url($chada_travel_image_url); ?>" width="50" height="50" alt="<?php echo esc_attr(sprintf(__('%s featured image', 'chada-travel'), $chada_travel_tour_name)); ?>"><?php else: ?><span class="description"><?php esc_html_e('No image', 'chada-travel'); ?></span><?php endif; ?></td><td></td></tr>
