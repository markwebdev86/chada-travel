<?php
/**
 * A single message paragraph - used by render_payment_tab()'s two guard clauses (capability-denied and
 * no-payment-yet). Deviation from the Phase 5.6 prompt's suggested file list: both guard clauses render the
 * identical bare-<p> shape, so one shared partial is used instead of two near-duplicate templates.
 *
 * @var string $chada_travel_message Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_message));
?>
<p><?php echo esc_html($chada_travel_message); ?></p>
