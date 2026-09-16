<?php
/**
 * Checkout Stage 3 (Review and Edit) body. See CHADA_TRAVEL_Checkout::render_stage_three().
 *
 * @var string $chada_travel_main_aside_html Pre-escaped HTML: Booker/Applications panels + Order Total aside.
 * @var string $chada_travel_policy_panel_html Pre-escaped HTML: Cancellation/Refund consent checkbox + caption.
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_main_aside_html));
assert(isset($chada_travel_policy_panel_html));
assert(isset($chada_travel_actions_html));
?>
<div class="chada-travel-stage-status"><p class="chada-travel-screen-status" data-chada-travel-review-status aria-live="polite"></p></div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_main_aside_html; ?>
<form id="chada-travel-review-form" novalidate>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_policy_panel_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_actions_html; ?>
<p class="chada-travel-status-note" data-chada-travel-stage-instructions>Review the Booker details and each visa application listed
above for accuracy, using Edit or Remove to make changes. Acknowledge the Cancellation and Refund Policy to continue
to payment.</p>
</form>
