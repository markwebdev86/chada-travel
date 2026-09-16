<?php
/**
 * Booking Detail's header: breadcrumb, title (booking label + tag/archived badges), created/updated meta,
 * optional Archive/Restore form, the four-item status/total detail grid, and the workflow progress bar.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_header().
 *
 * @var string $chada_travel_back_url Raw value; escaped here.
 * @var string $chada_travel_booking_label_html Pre-escaped HTML from CHADA_TRAVEL_Admin_Bookings::booking_label().
 * @var string $chada_travel_badges_html Pre-built HTML blob of tag badges plus an optional Archived badge; '' when none.
 * @var string $chada_travel_meta_text Raw value; escaped here.
 * @var string $chada_travel_archive_form_html Pre-built Archive/Restore <form> HTML; '' when the user lacks capability.
 * @var string $chada_travel_detail_grid_html Pre-built HTML blob of the four dt/dd status/total pairs.
 * @var string $chada_travel_workflow_progress_html Pre-built workflow progress bar HTML.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_back_url));
assert(isset($chada_travel_booking_label_html));
assert(isset($chada_travel_badges_html));
assert(isset($chada_travel_meta_text));
assert(isset($chada_travel_archive_form_html));
assert(isset($chada_travel_detail_grid_html));
assert(isset($chada_travel_workflow_progress_html));
?>
<p class="chada-travel-breadcrumb"><a href="<?php
echo esc_url($chada_travel_back_url); ?>"><?php
esc_html_e('&larr; Back to Visa Bookings', 'chada-travel'); ?></a></p>
<div class="chada-travel-detail-header" data-chada-travel-tour="booking-detail-header"><div>
<div class="chada-travel-detail-title"><h1><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped internally, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_booking_label_html; ?></h1><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_badges_html; ?></div>
<p class="chada-travel-detail-meta"><?php echo esc_html($chada_travel_meta_text); ?></p>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from escaped parts, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_archive_form_html; ?>
</div>
<div class="chada-travel-detail-grid">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_detail_grid_html; ?>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_workflow_progress_html; ?>
