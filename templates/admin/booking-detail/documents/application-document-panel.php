<?php
/**
 * One application's document-review panel: a meta line (reference, checklist version, file-status badge) plus
 * an early "No requirements configured for this country yet." state or one row per active requirement. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_application_document_panel().
 *
 * @var string $chada_travel_meta_html Pre-built HTML blob (reference &middot; checklist version &middot; status badge).
 * @var bool   $chada_travel_has_requirements
 * @var string $chada_travel_rows_html Pre-built HTML blob of the per-requirement rows. Only meaningful when
 *      $chada_travel_has_requirements.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_meta_html));
assert(isset($chada_travel_has_requirements));
assert(isset($chada_travel_rows_html));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_meta_html; ?>
<?php if (!$chada_travel_has_requirements): ?>
<p><?php esc_html_e('No requirements configured for this country yet.', 'chada-travel'); ?></p>
<?php else: ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
<?php endif; ?>
