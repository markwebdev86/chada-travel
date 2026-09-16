<?php
/**
 * One Bank Account repeater row/slot. See CHADA_TRAVEL_Admin_Settings::build_bank_account_row_html().
 *
 * @var int    $chada_travel_index       0-based slot index.
 * @var bool   $chada_travel_visible
 * @var string $chada_travel_prefix      e.g. "bank_accounts[0]"; used to build every field's name attribute.
 * @var string $chada_travel_id
 * @var int    $chada_travel_sort_order
 * @var string $chada_travel_fields_html Pre-escaped bank-field.php output for the 6 fields in this row.
 * @var string $chada_travel_instructions Raw value; escaped here (via esc_textarea()).
 * @var bool   $chada_travel_is_active
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_index));
assert(isset($chada_travel_visible));
assert(isset($chada_travel_prefix));
assert(isset($chada_travel_id));
assert(isset($chada_travel_sort_order));
assert(isset($chada_travel_fields_html));
assert(isset($chada_travel_instructions));
assert(isset($chada_travel_is_active));
?>
<div class="chada-travel-section" data-chada-travel-bank-row<?php echo $chada_travel_visible ? '' : ' hidden'; ?>>
<h3><?php echo esc_html(sprintf(
    /* translators: %d: 1-based Bank Account slot number. */
    __('Bank Account %d', 'chada-travel'),
    $chada_travel_index + 1
)); ?></h3>
<input type="hidden" name="<?php echo esc_attr($chada_travel_prefix); ?>[id]"
    value="<?php echo esc_attr($chada_travel_id); ?>">
<input type="hidden" class="chada-travel-bank-sort-order" name="<?php echo esc_attr($chada_travel_prefix); ?>[sort_order]"
    value="<?php echo esc_attr((string) $chada_travel_sort_order); ?>">
<div class="chada-travel-detail-grid">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_fields_html; ?>
</div>
<p><label for="chada-travel-bank-instructions-<?php echo esc_attr((string) $chada_travel_index); ?>">
<?php esc_html_e('Customer instructions (optional)', 'chada-travel'); ?>
</label><br>
<textarea class="large-text" rows="2" id="chada-travel-bank-instructions-<?php echo esc_attr((string) $chada_travel_index); ?>"
    name="<?php echo esc_attr($chada_travel_prefix); ?>[instructions]"
    ><?php echo esc_textarea($chada_travel_instructions); ?></textarea></p>
<p><label><input type="checkbox" name="<?php echo esc_attr($chada_travel_prefix); ?>[is_active]" value="1"
    <?php echo $chada_travel_is_active ? ' checked' : ''; ?>>
<?php esc_html_e('Active (shown to customers)', 'chada-travel'); ?>
</label></p>
<p>
<button type="button" class="button button-small" data-chada-travel-bank-move="up">
<?php esc_html_e('Move Up', 'chada-travel'); ?>
</button>
<button type="button" class="button button-small" data-chada-travel-bank-move="down">
<?php esc_html_e('Move Down', 'chada-travel'); ?>
</button>
<button type="button" class="button button-small" data-chada-travel-remove-bank-account>
<?php esc_html_e('Remove', 'chada-travel'); ?>
</button>
</p>
</div>
