<?php
/**
 * The Booker section: a nine-field detail grid plus the policy snapshot links sub-section.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_booker_section().
 *
 * @var string $chada_travel_fields_grid_html Pre-built HTML blob of the nine dt/dd field pairs.
 * @var string $chada_travel_policy_snapshot_links_html Pre-rendered HTML (its own <div class="chada-travel-detail-grid">).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_fields_grid_html));
assert(isset($chada_travel_policy_snapshot_links_html));
?>
<div class="chada-travel-section" data-chada-travel-tour="booking-overview-booker">
<h2><?php esc_html_e('Booker', 'chada-travel'); ?></h2><div class="chada-travel-detail-grid"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_fields_grid_html; ?></div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_policy_snapshot_links_html; ?>
</div>
