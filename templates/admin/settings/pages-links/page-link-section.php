<?php
/**
 * One page's assignment fieldset: the shared Page dropdown, description, required shortcode, status, current
 * URL, and View/Edit links. See CHADA_TRAVEL_Admin_Settings::build_page_link_section_html().
 *
 * @var string $chada_travel_label
 * @var string $chada_travel_field_id
 * @var string $chada_travel_dropdown_html Pre-escaped (shared/page-dropdown.php).
 * @var string $chada_travel_description
 * @var string $chada_travel_shortcode
 * @var string $chada_travel_status_label
 * @var string $chada_travel_current_url
 * @var string $chada_travel_page_links_html Pre-escaped; empty when there are no links to show.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_label));
assert(isset($chada_travel_field_id));
assert(isset($chada_travel_dropdown_html));
assert(isset($chada_travel_description));
assert(isset($chada_travel_shortcode));
assert(isset($chada_travel_status_label));
assert(isset($chada_travel_current_url));
assert(isset($chada_travel_page_links_html));
?>
<fieldset class="chada-travel-section"><legend><h2><?php echo esc_html($chada_travel_label); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>
<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_field_id); ?>">
<?php echo esc_html($chada_travel_label); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_dropdown_html; ?>
<p class="description"><?php echo esc_html($chada_travel_description); ?></p>
<p class="description"><?php esc_html_e('Required shortcode:', 'chada-travel'); ?>
<code>[<?php echo esc_html($chada_travel_shortcode); ?>]</code></p>
<p><strong><?php esc_html_e('Status:', 'chada-travel'); ?></strong> <?php echo esc_html($chada_travel_status_label); ?></p>
<p><strong><?php esc_html_e('Current URL:', 'chada-travel'); ?></strong>
<code><?php echo esc_html($chada_travel_current_url); ?></code></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_page_links_html; ?>
</td></tr>
</tbody></table></fieldset>
