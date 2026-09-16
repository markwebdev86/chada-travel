<?php
/**
 * Sender Identity fieldset: From Name / From Email Address / Reply-To Email Address, each built via the shared
 * CHADA_TRAVEL_Admin_Settings::build_text_field_html() (see templates/admin/settings/shared/text-field.php). See
 * CHADA_TRAVEL_Admin_Settings::build_sender_identity_section_html().
 *
 * @var string $chada_travel_from_name_field_html Pre-escaped.
 * @var string $chada_travel_from_address_field_html Pre-escaped.
 * @var string $chada_travel_reply_to_field_html Pre-escaped.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_from_name_field_html));
assert(isset($chada_travel_from_address_field_html));
assert(isset($chada_travel_reply_to_field_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Sender Identity', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_from_name_field_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_from_address_field_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_reply_to_field_html; ?>
</tbody></table></fieldset>
