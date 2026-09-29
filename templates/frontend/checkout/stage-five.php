<?php
/**
 * Checkout Stage 5 (Confirmation) body: only the fixed shell; assets/js/chada-travel-checkout.js fills the real
 * status-specific content client-side. See CHADA_TRAVEL_Checkout::render_stage_five().
 *
 * @var string $chada_travel_transaction_disclaimer Raw settings value; escaped here.
 * @var string $chada_travel_company_legal_name Raw settings value; escaped here. '' when not configured (no line then).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_transaction_disclaimer));
assert(isset($chada_travel_company_legal_name));
?>
<div id="chada-travel-confirmation-banner"></div>
<div id="chada-travel-confirmation-content"></div>
<p class="chada-travel-disclaimer"><?php echo esc_html($chada_travel_transaction_disclaimer); ?></p>
<?php if ($chada_travel_company_legal_name !== ''): ?>
<p class="chada-travel-legal-footer"><?php echo esc_html($chada_travel_company_legal_name); ?></p>
<?php endif; ?>
<div class="chada-travel-actions" id="chada-travel-confirmation-actions"></div>
