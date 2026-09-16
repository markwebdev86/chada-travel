<?php
/**
 * Bank Payment fieldset with repeatable account rows. See
 * CHADA_TRAVEL_Admin_Settings::build_bank_section_html().
 *
 * @var int    $chada_travel_account_count
 * @var string $chada_travel_rows_html Pre-escaped bank-account-row.php output, one per slot.
 */

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_account_count));
?>
<fieldset class="chada-travel-section" data-chada-travel-bank-repeater>
<legend><h2><?php esc_html_e('Bank Payment', 'chada-travel'); ?></h2></legend>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
<p><button type="button" class="button" data-chada-travel-add-bank-account>
<?php esc_html_e('Add Bank Account', 'chada-travel'); ?>
</button></p>
</fieldset>
