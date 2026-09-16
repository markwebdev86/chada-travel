<?php
/**
 * Informational notice about the single operational currency used for new country fees and checkout
 * transactions. See CHADA_TRAVEL_Admin_Visa_Countries::render_currency_notice().
 *
 * @var string $chada_travel_message Raw, already-sprintf'd value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_message));
?>
<div class="notice notice-info"><p><?php echo esc_html($chada_travel_message); ?></p></div>
