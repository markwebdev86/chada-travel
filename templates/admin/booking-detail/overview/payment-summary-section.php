<?php
/**
 * Payment summary section: an early "No payment attempt yet." state, or a five-item detail grid.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_payment_summary_section().
 *
 * @var string $chada_travel_review_url Raw value; escaped here.
 * @var bool   $chada_travel_has_payment
 * @var string $chada_travel_detail_grid_html Pre-built HTML blob of the five dt/dd pairs; '' when $chada_travel_has_payment
 *      is false.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_review_url));
assert(isset($chada_travel_has_payment));
assert(isset($chada_travel_detail_grid_html));
?>
<div class="chada-travel-section" data-chada-travel-tour="booking-overview-payment-summary">
<h2><?php esc_html_e('Payment summary', 'chada-travel'); ?> <a class="button button-small"
    href="<?php echo esc_url($chada_travel_review_url); ?>"><?php esc_html_e('Review payment', 'chada-travel'); ?></a></h2>
<?php if (!$chada_travel_has_payment): ?>
<p><?php esc_html_e('No payment attempt yet.', 'chada-travel'); ?></p></div>
<?php else: ?>
<div class="chada-travel-detail-grid"><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_detail_grid_html; ?></div></div>
<?php endif; ?>
