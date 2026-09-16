<?php
/** One Tour taxonomy grid row. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var int|null $chada_travel_row_number 1-based position in the current filtered result set; escaped here. */
/** @var int|null $chada_travel_id Raw term ID; escaped here. */
/** @var string|null $chada_travel_term_name Raw term name; escaped here. */
/** @var string|null $chada_travel_slug Raw term slug; escaped here. */
/** @var bool|null $chada_travel_is_active True when the term is active. */
/** @var int|null $chada_travel_tour_count Number of Tours using the term. */
/** @var string|null $chada_travel_toggle_action Raw action name; escaped here. */
/** @var string|null $chada_travel_toggle_url Raw admin-post URL; escaped here. */
/** @var string|null $chada_travel_taxonomy Raw taxonomy key; escaped here. */
/** @var string|null $chada_travel_tours_url Raw URL to the Tours grid filtered to this term; escaped here. */
/** @var string|null $chada_travel_nonce_html Pre-escaped nonce HTML. */
/** @var string|null $chada_travel_delete_action Raw delete action name; escaped here. */
/** @var string|null $chada_travel_delete_nonce_html Pre-escaped delete nonce HTML. */

assert(isset($chada_travel_row_number)); assert(isset($chada_travel_id)); assert(isset($chada_travel_term_name)); assert(isset($chada_travel_slug)); assert(isset($chada_travel_is_active)); assert(isset($chada_travel_tour_count));
assert(isset($chada_travel_toggle_action)); assert(isset($chada_travel_toggle_url)); assert(isset($chada_travel_taxonomy)); assert(isset($chada_travel_tours_url)); assert(isset($chada_travel_nonce_html)); assert(isset($chada_travel_delete_action)); assert(isset($chada_travel_delete_nonce_html));
?>
<tr data-chada-travel-tour-term-row="<?php echo esc_attr((string) $chada_travel_id); ?>"><td class="chada-travel-row-number-column" data-label="<?php esc_attr_e('No.', 'chada-travel'); ?>"><?php echo esc_html((string) $chada_travel_row_number); ?></td><td><strong><?php echo esc_html($chada_travel_term_name); ?></strong></td><td><code><?php echo esc_html($chada_travel_slug); ?></code></td><td><?php echo esc_html($chada_travel_is_active ? __('Active', 'chada-travel') : __('Archived', 'chada-travel')); ?></td><td><?php if ($chada_travel_tour_count > 0): ?><a href="<?php echo esc_url($chada_travel_tours_url); ?>"><?php echo esc_html((string) $chada_travel_tour_count); ?></a><?php else: ?><?php echo esc_html((string) $chada_travel_tour_count); ?><?php endif; ?></td><td><button type="button" class="button button-small" data-view-tour-term="<?php echo esc_attr((string) $chada_travel_id); ?>"><?php esc_html_e('View', 'chada-travel'); ?></button> <button type="button" class="button button-small" data-edit-tour-term="<?php echo esc_attr((string) $chada_travel_id); ?>"><?php esc_html_e('Edit', 'chada-travel'); ?></button> <form class="chada-travel-inline-form" method="post" action="<?php echo esc_url($chada_travel_toggle_url); ?>"><input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_toggle_action); ?>"><input type="hidden" name="term_id" value="<?php echo esc_attr((string) $chada_travel_id); ?>"><input type="hidden" name="taxonomy" value="<?php echo esc_attr($chada_travel_taxonomy); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?><?php echo $chada_travel_nonce_html; ?><button type="submit" class="button button-small"><?php echo esc_html($chada_travel_is_active ? __('Archive', 'chada-travel') : __('Restore', 'chada-travel')); ?></button></form><?php if (!$chada_travel_is_active && $chada_travel_tour_count === 0): ?> <form class="chada-travel-inline-form" method="post" action="<?php echo esc_url($chada_travel_toggle_url); ?>"><input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_delete_action); ?>"><input type="hidden" name="term_id" value="<?php echo esc_attr((string) $chada_travel_id); ?>"><input type="hidden" name="taxonomy" value="<?php echo esc_attr($chada_travel_taxonomy); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?><?php echo $chada_travel_delete_nonce_html; ?><button type="button" class="button button-small button-link-delete" data-delete-tour-term="<?php echo esc_attr((string) $chada_travel_id); ?>"><?php esc_html_e('Delete', 'chada-travel'); ?></button></form><?php endif; ?></td></tr>
