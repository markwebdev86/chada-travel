<?php
/**
 * One policy's assignment fieldset: the shared Page dropdown (templates/admin/settings/shared/page-dropdown.php,
 * same partial Pages & Links uses), External URL fallback, status, resolved URL, and View/Edit links. See
 * CHADA_TRAVEL_Admin_Settings::build_policy_section_html().
 *
 * @var string $chada_travel_section_id
 * @var string $chada_travel_label
 * @var string $chada_travel_description
 * @var string $chada_travel_field_id
 * @var string $chada_travel_dropdown_html Pre-escaped (shared/page-dropdown.php).
 * @var string $chada_travel_url_field_id
 * @var string $chada_travel_url_field_name
 * @var string $chada_travel_url_value
 * @var string $chada_travel_status_class
 * @var string $chada_travel_status_value
 * @var string $chada_travel_status_label
 * @var string $chada_travel_resolved_url Empty when there is none to show.
 * @var string $chada_travel_policy_page_links_html Pre-escaped; empty when there are no links to show.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_section_id));
assert(isset($chada_travel_label));
assert(isset($chada_travel_description));
assert(isset($chada_travel_field_id));
assert(isset($chada_travel_dropdown_html));
assert(isset($chada_travel_url_field_id));
assert(isset($chada_travel_url_field_name));
assert(isset($chada_travel_url_value));
assert(isset($chada_travel_status_class));
assert(isset($chada_travel_status_value));
assert(isset($chada_travel_status_label));
assert(isset($chada_travel_resolved_url));
assert(isset($chada_travel_policy_page_links_html));
?>
<fieldset class="chada-travel-section" id="<?php echo esc_attr($chada_travel_section_id); ?>">
<legend><h2><?php echo esc_html($chada_travel_label); ?></h2></legend>
<p class="description"><?php echo esc_html($chada_travel_description); ?></p>
<table class="form-table" role="presentation"><tbody>

<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_field_id); ?>">
<?php esc_html_e('WordPress Page', 'chada-travel'); ?>
 <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_dropdown_html; ?>
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Published, Draft, Pending, and Private Pages are selectable; only a published Page is Ready.', 'chada-travel'); ?>
</p>
</td></tr>

<tr><th scope="row"><label for="<?php echo esc_attr($chada_travel_url_field_id); ?>">
<?php esc_html_e('External URL', 'chada-travel'); ?>
</label></th>
<td>
<input type="url" class="regular-text" id="<?php echo esc_attr($chada_travel_url_field_id); ?>"
    name="<?php echo esc_attr($chada_travel_url_field_name); ?>" value="<?php echo esc_attr($chada_travel_url_value); ?>">
<p class="description">
<?php esc_html_e('Required only if no WordPress Page is assigned above; otherwise optional.', 'chada-travel'); ?>
</p>
</td></tr>

<tr><th scope="row"><?php esc_html_e('Status', 'chada-travel'); ?></th><td>
<span class="chada-travel-badge <?php echo esc_attr($chada_travel_status_class); ?>"
    data-chada-travel-policy-status="<?php echo esc_attr($chada_travel_status_value); ?>">
<?php echo esc_html($chada_travel_status_label); ?>
</span>
<?php if ($chada_travel_resolved_url !== '') : ?>
<p><strong><?php esc_html_e('Resolved public URL:', 'chada-travel'); ?></strong>
<code><?php echo esc_html($chada_travel_resolved_url); ?></code></p>
<?php endif; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_policy_page_links_html; ?>
</td></tr>

</tbody></table></fieldset>
