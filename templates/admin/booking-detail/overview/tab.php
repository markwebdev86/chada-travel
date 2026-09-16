<?php
/**
 * The Overview tab: booker + payment summary (two-column), applications table (full width), then tags + note
 * (two-column). See CHADA_TRAVEL_Admin_Booking_Detail::render_overview_tab().
 *
 * @var string $chada_travel_booker_section_html Pre-rendered HTML.
 * @var string $chada_travel_payment_summary_html Pre-rendered HTML.
 * @var string $chada_travel_applications_table_html Pre-rendered HTML.
 * @var string $chada_travel_tags_section_html Pre-rendered HTML.
 * @var string $chada_travel_note_section_html Pre-rendered HTML.
 * @var string $chada_travel_extensions_html Pre-rendered extension HTML.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_section_html));
assert(isset($chada_travel_payment_summary_html));
assert(isset($chada_travel_applications_table_html));
assert(isset($chada_travel_tags_section_html));
assert(isset($chada_travel_note_section_html));
assert(isset($chada_travel_extensions_html));
?>
<div class="chada-travel-two-col">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_booker_section_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_payment_summary_html; ?>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_applications_table_html; ?>
<div class="chada-travel-two-col" data-chada-travel-tour="booking-overview-tags">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_tags_section_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_note_section_html; ?>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extension owns and pre-escapes this HTML. ?>
<?php echo $chada_travel_extensions_html; ?>
