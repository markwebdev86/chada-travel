<?php
/**
 * Transaction Confirmation Disclaimer: a Stage 5 payment-confirmation notice, backed by the existing
 * chada_travel_transaction_disclaimer option - not one of the Stage 1/Stage 3 consent sources above, deliberately its
 * own section, never touching Policy Version, policy sources, or the immutable per-order policy snapshot. See
 * CHADA_TRAVEL_Admin_Settings::build_transaction_confirmation_fields_html().
 *
 * @var string $chada_travel_disclaimer Raw value; escaped here (via esc_textarea()).
 * @var string $chada_travel_disclaimer_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_disclaimer));
assert(isset($chada_travel_disclaimer_notice));
?>
<fieldset class="chada-travel-section" id="chada-travel-transaction-confirmation-section">
<legend><h2><?php esc_html_e('Transaction Confirmation', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><label for="chada-travel-transaction-disclaimer">
<?php esc_html_e('Transaction Confirmation Disclaimer', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<textarea class="large-text" rows="3" maxlength="500" id="chada-travel-transaction-disclaimer"
    name="chada_travel_transaction_disclaimer" required><?php echo esc_textarea($chada_travel_disclaimer); ?></textarea>
<p class="description"><?php echo esc_html($chada_travel_disclaimer_notice); ?></p></td></tr>
</tbody></table></fieldset>
