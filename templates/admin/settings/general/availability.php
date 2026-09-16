<?php
/**
 * Operational Availability fieldset: Company Status radio + always-rendered (JS-hidden, never server-hidden)
 * Maintenance Message textarea. See CHADA_TRAVEL_Admin_Settings::build_availability_section_html().
 *
 * @var string $chada_travel_status_options_html Pre-escaped radio <label> elements, including their checked state.
 * @var string $chada_travel_maintenance_message Raw value; escaped here (via esc_textarea()).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_status_options_html));
assert(isset($chada_travel_maintenance_message));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Operational Availability', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><?php esc_html_e('Company Status', 'chada-travel'); ?></th>
<td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_status_options_html; ?>
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Under Maintenance blocks new customer checkout starts immediately and shows the message below; an in-progress checkout, Bank proof, and document upload all stay active.', 'chada-travel'); ?>
</p>
</td></tr>
<tr data-chada-travel-maintenance-message-row><th scope="row"><label for="chada-travel-company-maintenance-message">
<?php esc_html_e('Maintenance Message', 'chada-travel'); ?>
</label></th>
<td><textarea class="large-text" rows="3" id="chada-travel-company-maintenance-message"
    name="chada_travel_company_maintenance_message"><?php echo esc_textarea($chada_travel_maintenance_message); ?></textarea>
<p class="description">
<?php esc_html_e('Shown to customers while Company Status is Under Maintenance; required in that case.', 'chada-travel'); ?>
</p>
</td></tr>
</tbody></table></fieldset>
