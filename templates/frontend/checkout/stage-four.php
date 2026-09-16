<?php
/**
 * Checkout Stage 4 (Payment) body. See CHADA_TRAVEL_Checkout::render_stage_four().
 *
 * @var string $chada_travel_main_aside_html Pre-escaped HTML: payment-method panels + Order Summary aside.
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_main_aside_html));
assert(isset($chada_travel_actions_html));
?>
<div class="chada-travel-stage-status"><p class="chada-travel-screen-status" data-chada-travel-payment-status aria-live="polite"></p></div>
<div class="chada-travel-payment-options" data-chada-travel-payment-options role="radiogroup" aria-label="Payment methods"></div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_main_aside_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_actions_html; ?>
<p class="chada-travel-status-note" data-chada-travel-stage-instructions>Choose Bank Payment or Digital Wallet and follow the
on-screen steps to complete your payment. Both methods require manual
verification, so your visa guide and checklist are released once payment is confirmed.</p>
