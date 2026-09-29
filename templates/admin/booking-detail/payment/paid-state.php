<?php
/**
 * Paid payment state: the historical Bank proof panel (Bank only, no-op otherwise) and a 3-item detail grid
 * (Method/Transaction ID/Paid at). See CHADA_TRAVEL_Admin_Booking_Detail::render_paid_payment_state().
 *
 * @var string $chada_travel_historical_proof_html Pre-rendered HTML; '' for non-Bank payments.
 * @var string $chada_travel_grid_html Pre-built HTML blob of the dt/dd pairs.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_historical_proof_html));
assert(isset($chada_travel_grid_html));
?>

<?php echo wp_kses_post($chada_travel_historical_proof_html); ?>
<div class="chada-travel-detail-grid"><?php
echo wp_kses_post($chada_travel_grid_html); ?></div>
