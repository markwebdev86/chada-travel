<?php
/**
 * General tab: form wrapper around the 5 sections. See CHADA_TRAVEL_Admin_Settings::render_general_tab().
 *
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_save_action Raw value; escaped here.
 * @var string $chada_travel_nonce_html Pre-escaped HTML from wp_nonce_field().
 * @var string $chada_travel_company_profile_html Pre-escaped HTML from build_company_profile_section_html().
 * @var string $chada_travel_contact_html Pre-escaped HTML from build_company_contact_section_html().
 * @var string $chada_travel_regional_html Pre-escaped HTML from build_regional_section_html().
 * @var string $chada_travel_reference_html Pre-escaped HTML from build_reference_section_html().
 * @var string $chada_travel_availability_html Pre-escaped HTML from build_availability_section_html().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_company_profile_html));
assert(isset($chada_travel_contact_html));
assert(isset($chada_travel_regional_html));
assert(isset($chada_travel_reference_html));
assert(isset($chada_travel_availability_html));
?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_company_profile_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_contact_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_regional_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_reference_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_availability_html; ?>
<p class="submit"><button type="submit" class="button button-primary">
<?php esc_html_e('Save General Settings', 'chada-travel'); ?>
</button>
</p>
</form>
