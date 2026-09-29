<?php
/**
 * Cart indicator shared by Stages 1 and 2; JavaScript keeps the accessible count synchronized.
 * See CHADA_TRAVEL_Checkout::render_cart_status(). No arguments: this fragment has no dynamic content.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div class="chada-travel-cart-status" role="status" aria-label="Visa application cart">
<span class="chada-travel-cart-status__icon" aria-hidden="true">&#128722;</span>
<span class="chada-travel-badge" data-chada-travel-cart-count aria-hidden="true">0</span>
<span class="chada-travel-sr-only" data-chada-travel-cart-count-label>0 visa applicants selected</span>
</div>
