<?php
/**
 * Company Contact Information fieldset: Support Email, Phone, Mobile, Address lines, City, Province, Postal
 * Code, Address Country. See CHADA_TRAVEL_Admin_Settings::build_company_contact_section_html().
 *
 * @var string $chada_travel_fields_html Pre-escaped HTML: the nine form-table field rows.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_fields_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Company Contact Information', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>

<?php echo wp_kses_post($chada_travel_fields_html); ?>
</tbody></table></fieldset>
