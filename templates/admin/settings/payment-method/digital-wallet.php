<?php
/**
 * Digital Wallet Payment fieldset: provider name + account name/number fields + QR image preview + Select/Remove
 * buttons. See CHADA_TRAVEL_Admin_Settings::build_digital_wallet_section_html().
 *
 * @var string $chada_travel_provider_name
 * @var string $chada_travel_account_name
 * @var string $chada_travel_account_number
 * @var string $chada_travel_qr_url
 * @var int    $chada_travel_attachment_id
 * @var bool   $chada_travel_has_real_qr
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_provider_name));
assert(isset($chada_travel_account_name));
assert(isset($chada_travel_account_number));
assert(isset($chada_travel_qr_url));
assert(isset($chada_travel_attachment_id));
assert(isset($chada_travel_has_real_qr));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Digital Wallet', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><label for="chada-travel-digital-wallet-name">
<?php esc_html_e('Digital Wallet Name', 'chada-travel'); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td><input type="text" class="regular-text" id="chada-travel-digital-wallet-name" name="digital_wallet_name"
    value="<?php echo esc_attr($chada_travel_provider_name); ?>" maxlength="50">
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The payment provider or company shown to customers at checkout, for example GCash or Maya.', 'chada-travel'); ?>
</p></td></tr>
<tr><th scope="row"><label for="chada-travel-digital-wallet-account-name">
<?php esc_html_e('Account name', 'chada-travel'); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td><input type="text" class="regular-text" id="chada-travel-digital-wallet-account-name" name="digital_wallet_account_name"
    value="<?php echo esc_attr($chada_travel_account_name); ?>"></td></tr>
<tr><th scope="row"><label for="chada-travel-digital-wallet-account-number">
<?php esc_html_e('Account/mobile number', 'chada-travel'); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td><input type="text" class="regular-text" id="chada-travel-digital-wallet-account-number"
    name="digital_wallet_account_number" value="<?php echo esc_attr($chada_travel_account_number); ?>"></td></tr>
<tr><th scope="row"><?php esc_html_e('QR Code', 'chada-travel'); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</th><td>
<div data-chada-travel-digital-wallet-qr>
<img src="<?php echo esc_url($chada_travel_qr_url); ?>"
    alt="<?php esc_attr_e('Current Digital Wallet QR Code', 'chada-travel'); ?>"
    width="180" height="180" style="max-width:180px;height:auto;display:block;margin-bottom:8px;"
    data-chada-travel-digital-wallet-qr-preview>
<?php if (!$chada_travel_has_real_qr) : ?>
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Showing the bundled placeholder image; Digital Wallet is not Ready until a real QR Code is selected.', 'chada-travel'); ?>
</p>
<?php endif; ?>
<input type="hidden" name="digital_wallet_qr_attachment_id"
    value="<?php echo esc_attr((string) $chada_travel_attachment_id); ?>" data-chada-travel-digital-wallet-qr-input>
<input type="hidden" name="digital_wallet_qr_remove" value="0" data-chada-travel-digital-wallet-qr-remove-input>
<button type="button" class="button" data-chada-travel-select-digital-wallet-qr>
<?php esc_html_e('Select QR Code', 'chada-travel'); ?>
</button>
<button type="button" class="button"
    data-chada-travel-remove-digital-wallet-qr<?php echo $chada_travel_has_real_qr ? '' : ' hidden'; ?>>
<?php esc_html_e('Remove Image', 'chada-travel'); ?>
</button>
</div>
</td></tr>
</tbody></table></fieldset>
