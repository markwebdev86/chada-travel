<?php
/**
 * Actionable (awaiting_verification) payment state: a two-column layout - left column is the Bank proof panel
 * or a Digital Wallet detail grid (method-dependent), followed by a shared expected-amount/reference/submitted
 * grid; right column is the Confirm/Reject decision forms. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_actionable_payment_state().
 *
 * @var string $chada_travel_method_detail_html Pre-rendered HTML: either the Bank proof panel, or the Digital Wallet
 *      detail grid.
 * @var string $chada_travel_shared_grid_html Pre-built HTML blob of the shared dt/dd pairs (expected amount, payment
 *      reference, proof submitted).
 * @var string $chada_travel_decision_forms_html Pre-rendered HTML; '' when the user lacks capability.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_method_detail_html));
assert(isset($chada_travel_shared_grid_html));
assert(isset($chada_travel_decision_forms_html));
?>
<div class="chada-travel-two-col">
<div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered/pre-built, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_method_detail_html; ?>
<div class="chada-travel-detail-grid"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_shared_grid_html; ?></div>
</div>
<div><h2><?php esc_html_e('Verify manual payment', 'chada-travel'); ?></h2>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_decision_forms_html; ?>
</div>
</div>
