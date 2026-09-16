<?php
/**
 * The four-step workflow progress bar (Booking Created / Payment Review / Document Review / Completed).
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_workflow_progress().
 *
 * @var string $chada_travel_steps_html Pre-built HTML blob of the step <div> elements; each step's marker/label pairing
 *      must stay byte-adjacent to the marker, so it is built once in PHP rather than looped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_steps_html));
?>
<div class="chada-travel-progress" aria-label="<?php esc_attr_e('Visa Booking workflow', 'chada-travel'); ?>"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_attr()/esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_steps_html; ?></div>
