<?php
/**
 * Bank Payment Proof fieldset: allowed proof image types and Payment Proof Link Expiry. See
 * CHADA_TRAVEL_Admin_Settings::build_bank_proof_fieldset_html().
 *
 * @var string $chada_travel_mime_checkboxes_html Pre-escaped.
 * @var int    $chada_travel_proof_hours
 * @var string $chada_travel_hours_min
 * @var string $chada_travel_hours_max
 * @var string $chada_travel_hours_preview
 * @var string $chada_travel_reissue_note
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_mime_checkboxes_html));
assert(isset($chada_travel_proof_hours));
assert(isset($chada_travel_hours_min));
assert(isset($chada_travel_hours_max));
assert(isset($chada_travel_hours_preview));
assert(isset($chada_travel_reissue_note));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Bank Payment Proof', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>

<tr><th scope="row"><?php esc_html_e('Allowed Proof Image Types', 'chada-travel'); ?></th><td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_mime_checkboxes_html; ?>
<p class="description"><?php esc_html_e('At least one type must remain selected.', 'chada-travel'); ?></p></td></tr>

<tr><th scope="row"><label for="chada-travel-payment-proof-token-hours">
<?php esc_html_e('Payment Proof Link Expiry', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<input type="number" min="<?php echo esc_attr($chada_travel_hours_min); ?>" max="<?php echo esc_attr($chada_travel_hours_max); ?>"
    step="1" class="small-text" id="chada-travel-payment-proof-token-hours" name="chada_travel_payment_proof_token_hours"
    value="<?php echo esc_attr((string) $chada_travel_proof_hours); ?>" required> <?php esc_html_e('hours', 'chada-travel'); ?>
<p class="description"><?php echo esc_html($chada_travel_hours_preview); ?> <?php echo esc_html($chada_travel_reissue_note); ?></p>
</td></tr>

</tbody></table></fieldset>
