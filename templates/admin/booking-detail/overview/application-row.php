<?php
/**
 * One <tr> in the Visa Applications table. See
 * CHADA_TRAVEL_Admin_Booking_Detail::build_applications_table_section_html().
 *
 * @var string $chada_travel_application_reference Raw value; escaped here.
 * @var string $chada_travel_applicant_name Raw value; escaped here.
 * @var string $chada_travel_date_of_birth_display Raw value (date or "Not provided"); escaped here.
 * @var string $chada_travel_travel_date_display Raw value (date or "Not provided"); escaped here.
 * @var string $chada_travel_country_name Raw value; escaped here.
 * @var string $chada_travel_file_status_label Raw value; escaped here.
 * @var string $chada_travel_checklist_version Raw value; escaped here.
 * @var string $chada_travel_fee_label Raw, already-formatted value; escaped here.
 * @var bool   $chada_travel_can_edit
 * @var string $chada_travel_edit_form_html Pre-built HTML blob from
 *      CHADA_TRAVEL_Admin_Booking_Detail::build_applicant_edit_form_html(); only meaningful when $chada_travel_can_edit.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_application_reference));
assert(isset($chada_travel_applicant_name));
assert(isset($chada_travel_date_of_birth_display));
assert(isset($chada_travel_travel_date_display));
assert(isset($chada_travel_country_name));
assert(isset($chada_travel_file_status_label));
assert(isset($chada_travel_checklist_version));
assert(isset($chada_travel_fee_label));
assert(isset($chada_travel_can_edit));
assert(isset($chada_travel_edit_form_html));
?>
<tr>
<td><?php echo esc_html($chada_travel_application_reference); ?></td>
<td><?php echo esc_html($chada_travel_applicant_name); ?></td>
<td><?php echo esc_html($chada_travel_date_of_birth_display); ?></td>
<td><?php echo esc_html($chada_travel_travel_date_display); ?></td>
<td><?php echo esc_html($chada_travel_country_name); ?></td>
<td><span class="chada-travel-badge"><?php echo esc_html($chada_travel_file_status_label); ?></span></td>
<td><?php echo esc_html($chada_travel_checklist_version); ?></td>
<td><?php echo esc_html($chada_travel_fee_label); ?></td>
<td><?php if ($chada_travel_can_edit): ?>
<details class="chada-travel-applicant-edit">
<summary class="button button-small"><?php esc_html_e('Edit', 'chada-travel'); ?></summary>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_edit_form_html; ?>
</details>
<?php endif; ?></td>
</tr>
