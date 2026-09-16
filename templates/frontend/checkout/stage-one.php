<?php
/**
 * Checkout Stage 1 (Visa Countries) body. See CHADA_TRAVEL_Checkout::render_stage_one().
 *
 * @var string $chada_travel_cart_status_html Pre-escaped HTML from CHADA_TRAVEL_Checkout::render_cart_status().
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_cart_status_html));
assert(isset($chada_travel_actions_html));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_cart_status_html; ?>
<div class="chada-travel-country-browser">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong -- compact accessibility status markup. ?>
<div class="chada-travel-country-browser__status"><p class="chada-travel-screen-status" data-chada-travel-country-browser-status aria-live="polite"></p></div>
<div class="chada-travel-country-accordion" data-chada-travel-country-accordion></div>
<aside class="chada-travel-country-details" data-chada-travel-country-details aria-live="polite"></aside>
</div>
<p class="chada-travel-notice" data-type="info" data-chada-travel-country-empty hidden></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_actions_html; ?>
<p class="chada-travel-status-note" data-chada-travel-stage-instructions>Select a country to view its visa requirements, processing
fee,
and step-by-step guide. Choose how many applicants need that visa and click Apply, repeating for each country you need.
Once you have applied for at least one country, continue to enter Booker and Applicant details.</p>
