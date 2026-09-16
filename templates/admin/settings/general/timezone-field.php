<?php
/**
 * Company Timezone form-table row: a full IANA timezone <select> when wp_timezone_choice() is available (real
 * WordPress), else a plain text fallback (non-WordPress test harnesses). See
 * CHADA_TRAVEL_Admin_Settings::build_timezone_field_html().
 *
 * @var string $chada_travel_current Raw value; escaped here (fallback branch only).
 * @var bool $chada_travel_has_choices
 * @var string $chada_travel_options_html Pre-escaped <option> markup from wp_timezone_choice(); '' when !$chada_travel_has_choices.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_current));
assert(isset($chada_travel_has_choices));
assert(isset($chada_travel_options_html));
?>
<tr><th scope="row"><label for="chada-travel-company-timezone"><?php esc_html_e('Company Timezone', 'chada-travel'); ?></label></th>
<td>
<?php if ($chada_travel_has_choices): ?>
<select id="chada-travel-company-timezone" name="chada_travel_company_timezone">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select>
<?php else: ?>
<input type="text" class="regular-text" id="chada-travel-company-timezone" name="chada_travel_company_timezone"
    value="<?php echo esc_attr($chada_travel_current); ?>">
<?php endif; ?>
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Select an editable IANA timezone for company and booking operations.', 'chada-travel'); ?>
</p>
</td></tr>
