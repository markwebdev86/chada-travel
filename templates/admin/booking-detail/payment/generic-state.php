<?php
/**
 * Generic/fallback payment state: a single "Status: X" line, used for any payment status not explicitly
 * handled by one of the other six states. See CHADA_TRAVEL_Admin_Booking_Detail::render_generic_payment_state().
 *
 * @var string $chada_travel_sentence Raw value (already sprintf'd); escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_sentence));
?>
<p><?php echo esc_html($chada_travel_sentence); ?></p>
