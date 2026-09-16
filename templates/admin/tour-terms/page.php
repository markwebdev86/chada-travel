<?php
/** Shared Tour Destinations and Tour Types manager page. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_label Raw screen label; escaped here. */
/** @var string|null $chada_travel_menu_slug Raw page slug; escaped here. */
/** @var string|null $chada_travel_form_html Pre-rendered escaped form HTML. */
/** @var string|null $chada_travel_notice_html Pre-rendered escaped notice HTML. */
/** @var string|null $chada_travel_rows_html Pre-rendered escaped row HTML. */
/** @var string|null $chada_travel_filters_html Pre-rendered escaped filter HTML. */
/** @var string|null $chada_travel_table_html Pre-rendered escaped table HTML. */
/** @var string|null $chada_travel_delete_modal_html Pre-rendered escaped modal HTML. */
/** @var string|null $chada_travel_search Raw search value; escaped here. */
/** @var string|null $chada_travel_status Raw status value; escaped here. */

assert(isset($chada_travel_label)); assert(isset($chada_travel_menu_slug)); assert(isset($chada_travel_form_html)); assert(isset($chada_travel_notice_html));
assert(isset($chada_travel_rows_html)); assert(isset($chada_travel_filters_html)); assert(isset($chada_travel_table_html)); assert(isset($chada_travel_delete_modal_html)); assert(isset($chada_travel_search)); assert(isset($chada_travel_status));
?>
<div class="wrap chada-travel-admin chada-travel-term-admin"><h1 class="wp-heading-inline"><?php echo esc_html($chada_travel_label); ?></h1> <button type="button" class="page-title-action" id="chada-travel-add-tour-term"><?php esc_html_e('Add New', 'chada-travel'); ?></button><p class="description"><?php esc_html_e('Manage the reusable records available when creating Tours.', 'chada-travel'); ?></p><hr class="wp-header-end">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered notice HTML. ?><?php echo $chada_travel_notice_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered form template. ?><?php echo $chada_travel_form_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered filters HTML. ?><?php echo $chada_travel_filters_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered table HTML. ?><?php echo $chada_travel_table_html; ?></div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered modal HTML. ?><?php echo $chada_travel_delete_modal_html; ?>
