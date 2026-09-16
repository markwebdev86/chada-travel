<?php
/**
 * Bank payment, awaiting proof: a 2-item detail grid (Payment reference/Amount expected) and a fixed
 * "awaiting proof" note. See CHADA_TRAVEL_Admin_Booking_Detail::render_bank_awaiting_proof().
 *
 * @var string $chada_travel_grid_html Pre-built HTML blob of the dt/dd pairs.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_grid_html));
?>
<div class="chada-travel-detail-grid"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_grid_html; ?></div>
<p><?php
// phpcs:ignore Generic.Files.LineLength.TooLong -- single translatable string literal, matching the original source's own convention.
esc_html_e('Awaiting the Booker to upload a Deposit Slip. No decision is available until proof is submitted.', 'chada-travel'); ?></p>
