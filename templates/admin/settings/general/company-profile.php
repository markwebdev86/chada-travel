<?php
/**
 * Company Profile fieldset: Name, Legal Name, Logo, Website URL, Country, Tagline. See
 * CHADA_TRAVEL_Admin_Settings::build_company_profile_section_html().
 *
 * @var string $chada_travel_fields_html Pre-escaped HTML: the six form-table field rows.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_fields_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Company Profile', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_fields_html; ?>
</tbody></table></fieldset>
