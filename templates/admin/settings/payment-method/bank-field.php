<?php
/**
 * One labeled text input within a Bank Account row. See CHADA_TRAVEL_Admin_Settings::build_bank_field_html().
 *
 * @var string $chada_travel_id
 * @var string $chada_travel_field_name The HTML name attribute, e.g. "bank_accounts[0][bank_name]".
 * @var string $chada_travel_label
 * @var string $chada_travel_value
 * @var bool   $chada_travel_required
 * @var string $chada_travel_required_indicator_html Pre-escaped; empty unless $chada_travel_required.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_label));
assert(isset($chada_travel_value));
assert(isset($chada_travel_required));
assert(isset($chada_travel_required_indicator_html));
?>
<label for="<?php echo esc_attr($chada_travel_id); ?>">
<?php echo esc_html($chada_travel_label); ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_required_indicator_html; ?><br>
<input type="text" class="regular-text" id="<?php echo esc_attr($chada_travel_id); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>" value="<?php echo esc_attr($chada_travel_value); ?>"
    <?php echo $chada_travel_required ? 'required' : ''; ?>></label>
