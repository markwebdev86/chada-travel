<?php
/**
 * Each policy's safe resolved link exactly as recorded in this order's immutable snapshot at consent time, or
 * the existing-record notice when no detailed snapshot exists. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_policy_snapshot_links() - the snapshot-vs-current-Settings distinction is
 * load-bearing and must not be changed by this conversion.
 *
 * @var string $chada_travel_grid_html Pre-built HTML blob of the dt/dd pairs (either the single existing-record pair, or
 *      one pair per policy definition, each dd containing a link or a "Not available" fallback).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_grid_html));
?>
<div class="chada-travel-detail-grid"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html()/esc_url() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_grid_html; ?></div>
