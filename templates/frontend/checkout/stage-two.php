<?php
/**
 * Checkout Stage 2 (Booker and Applicants) body. See CHADA_TRAVEL_Checkout::render_stage_two().
 *
 * @var string $chada_travel_cart_status_html Pre-escaped HTML from CHADA_TRAVEL_Checkout::render_cart_status().
 * @var string $chada_travel_main_aside_html Pre-escaped HTML: Booker Details panel + Order Summary panel.
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_cart_status_html));
assert(isset($chada_travel_main_aside_html));
assert(isset($chada_travel_actions_html));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_cart_status_html; ?>
<form id="chada-travel-booker-applicants-form" novalidate>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- compact accessibility status markup. ?>
<div class="chada-travel-stage-status"><p class="chada-travel-screen-status" data-chada-travel-booker-applicants-status aria-live="polite"></p></div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_main_aside_html; ?>
<section class="chada-travel-applicant-section">
<h3>Applicant Details</h3>
<div class="chada-travel-applicant-list" id="chada-travel-applicant-list"></div>
<p class="chada-travel-status-note" data-chada-travel-applicant-cap-note aria-live="polite"></p>
<div class="chada-travel-add-country-row">
<button class="chada-travel-button" type="button" data-chada-travel-add-country>+ Add Another Country</button>
</div>
</section>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_actions_html; ?>
<p class="chada-travel-status-note" data-chada-travel-stage-instructions>Enter the Booker's contact details and accept the Privacy
Policy
and Terms and Conditions. For each applicant, provide their name and target travel date, and check "I am the visa
applicant" if the Booker is also applying. Continue once every applicant's details are complete.</p>
</form>
