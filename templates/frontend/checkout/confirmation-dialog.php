<?php
/**
 * One native accessible dialog reused for every destructive customer action.
 * See CHADA_TRAVEL_Checkout::render_confirmation_dialog(). No arguments: this fragment has no dynamic content.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<dialog class="chada-travel-confirm-dialog" data-chada-travel-confirm-dialog
    aria-labelledby="chada-travel-confirm-title" aria-describedby="chada-travel-confirm-message">
<form method="dialog">
<h2 id="chada-travel-confirm-title">Confirm action</h2>
<p id="chada-travel-confirm-message" data-chada-travel-confirm-message></p>
<div class="chada-travel-actions">
<button class="chada-travel-button" value="cancel">Keep Application</button>
<button class="chada-travel-button chada-travel-button--primary" value="confirm" data-chada-travel-confirm-action>Confirm</button>
</div>
</form>
</dialog>
