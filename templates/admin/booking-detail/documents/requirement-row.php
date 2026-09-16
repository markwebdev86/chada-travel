<?php
/**
 * One document requirement's row: a header (label, Required/Optional badge, document-status-or-Missing badge),
 * a description + allowed-types/max-size line, an optional blank-form download link, and - only when a
 * document has been uploaded - a file-detail line plus independently-conditional reviewed/review-note lines
 * and independently-capability-gated View/Download links and review form. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_requirement_row(). Both capability checks (view, review) are resolved in
 * the PHP builder, never here - this template only echoes the pre-built blobs/pre-rendered HTML.
 *
 * @var string $chada_travel_header_html Pre-built HTML blob (label + badges).
 * @var string $chada_travel_description Raw value; escaped here.
 * @var string $chada_travel_allowed_text Raw value (already sprintf'd); escaped here.
 * @var string $chada_travel_blank_form_html Pre-built HTML; '' when no blank form is configured/available.
 * @var bool   $chada_travel_has_document
 * @var string $chada_travel_file_detail_text Raw value (already sprintf'd); escaped here. Only meaningful when
 *      $chada_travel_has_document.
 * @var string $chada_travel_reviewed_text Raw value (already sprintf'd); escaped here. '' when not yet reviewed.
 * @var string $chada_travel_review_note_text Raw value (already sprintf'd); escaped here. '' when no review note.
 * @var string $chada_travel_view_download_html Pre-built HTML; '' when the viewer lacks capability.
 * @var string $chada_travel_review_form_html Pre-rendered HTML; '' when the viewer lacks review capability.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_header_html));
assert(isset($chada_travel_description));
assert(isset($chada_travel_allowed_text));
assert(isset($chada_travel_blank_form_html));
assert(isset($chada_travel_has_document));
assert(isset($chada_travel_file_detail_text));
assert(isset($chada_travel_reviewed_text));
assert(isset($chada_travel_review_note_text));
assert(isset($chada_travel_view_download_html));
assert(isset($chada_travel_review_form_html));
?>
<div class="chada-travel-section"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_header_html; ?>
<p class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_description); ?><br><?php
echo esc_html($chada_travel_allowed_text); ?></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html()/esc_url() calls, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_blank_form_html; ?>
<?php if ($chada_travel_has_document): ?>
<p class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_file_detail_text); ?></p>
<?php if ($chada_travel_reviewed_text !== ''): ?>
<p class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_reviewed_text); ?></p>
<?php endif; ?>
<?php if ($chada_travel_review_note_text !== ''): ?>
<p class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_review_note_text); ?></p>
<?php endif; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html()/esc_url() calls, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_view_download_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_review_form_html; ?>
<?php endif; ?>
</div>
