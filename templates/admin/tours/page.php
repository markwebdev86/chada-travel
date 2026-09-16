<?php
/** Tours manager page shell. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_form_html Pre-rendered escaped form HTML. */
/** @var string|null $chada_travel_notice_html Pre-rendered escaped notice HTML. */
/** @var string|null $chada_travel_duplicate_modal_html Pre-rendered escaped modal HTML. */
/** @var string|null $chada_travel_delete_modal_html Pre-rendered escaped modal HTML. */
/** @var string|null $chada_travel_transfer_html Optional Pro-owned transfer panel HTML. */
/** @var string|null $chada_travel_filters_html Pre-rendered escaped filter HTML. */
/** @var string|null $chada_travel_table_html Pre-rendered escaped table HTML. */
/** @var string|null $chada_travel_pagination_html Pre-rendered escaped pagination HTML. */

assert(isset($chada_travel_form_html)); assert(isset($chada_travel_notice_html)); assert(isset($chada_travel_duplicate_modal_html));
assert(isset($chada_travel_delete_modal_html)); assert(isset($chada_travel_transfer_html));
assert(isset($chada_travel_filters_html)); assert(isset($chada_travel_table_html)); assert(isset($chada_travel_pagination_html));
?>
<div class="wrap chada-travel-admin chada-travel-tours-admin"><h1 class="wp-heading-inline" data-chada-travel-tour="tours-header" tabindex="-1"><?php esc_html_e('Tours', 'chada-travel'); ?></h1> <button type="button" class="page-title-action" id="chada-travel-add-tour"><?php esc_html_e('Add Tour', 'chada-travel'); ?></button><p class="description"><?php esc_html_e('Manage tour details, schedules, destinations, types, and pricing.', 'chada-travel'); ?></p><hr class="wp-header-end">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered notice HTML. ?><?php echo $chada_travel_notice_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered form HTML. ?><?php echo $chada_travel_form_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered modal HTML. ?><?php echo $chada_travel_duplicate_modal_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered modal HTML. ?><?php echo $chada_travel_delete_modal_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pro-owned transfer HTML is escaped by its renderer. ?><?php echo $chada_travel_transfer_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered filters HTML. ?><?php echo $chada_travel_filters_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered table HTML. ?><?php echo $chada_travel_table_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered pagination HTML. ?><?php echo $chada_travel_pagination_html; ?></div>
