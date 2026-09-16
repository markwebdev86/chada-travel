<?php
/**
 * Refunded payment state: the historical Bank proof panel (Bank only, no-op otherwise) and a single sentence
 * naming the original transaction ID. See CHADA_TRAVEL_Admin_Booking_Detail::render_refunded_payment_state().
 *
 * @var string $chada_travel_historical_proof_html Pre-rendered HTML; '' for non-Bank payments.
 * @var string $chada_travel_sentence Raw value (already sprintf'd); escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_historical_proof_html));
assert(isset($chada_travel_sentence));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_historical_proof_html; ?>
<p><?php echo esc_html($chada_travel_sentence); ?></p>
