<?php
/**
 * A checkbox list for one MIME-type registry (Visa Documents or Bank Payment Proof). Deliberately placed under
 * documents-uploads/ rather than shared/: grepped every call site in CHADA_TRAVEL_Admin_Settings.php and confirmed
 * this is only ever called from within this tab (Visa Documents and Bank Payment Proof), unlike
 * shared/text-field.php or shared/page-dropdown.php which are genuinely reused across tabs. See
 * CHADA_TRAVEL_Admin_Settings::build_mime_checkboxes_html().
 *
 * @var string $chada_travel_checkboxes_html Pre-escaped.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_checkboxes_html));
?>
<fieldset><legend class="screen-reader-text"><?php esc_html_e('Allowed file types', 'chada-travel'); ?></legend>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_checkboxes_html; ?>
</fieldset>
