<?php
/**
 * A WordPress Page <select>, shared by the Pages & Links and Policies & Consent tabs (both assign a WordPress
 * Page id to an option via this exact same dropdown shape) - converted once here as the single source of truth
 * rather than duplicated per tab. See CHADA_TRAVEL_Admin_Settings::build_page_dropdown_html().
 *
 * @var string $chada_travel_field_id
 * @var string $chada_travel_field_name The HTML name attribute (the settings option name this dropdown writes to).
 * @var string $chada_travel_options_html Pre-escaped <option> elements, including their selected state.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_field_id));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_options_html));
?>
<select id="<?php echo esc_attr($chada_travel_field_id); ?>" name="<?php echo esc_attr($chada_travel_field_name); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select>
