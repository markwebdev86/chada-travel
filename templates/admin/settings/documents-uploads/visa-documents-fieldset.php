<?php
/**
 * Visa Documents fieldset: Maximum File Size, allowed MIME types, and Document Upload Link Expiry. See
 * CHADA_TRAVEL_Admin_Settings::build_visa_documents_fieldset_html().
 *
 * @var string $chada_travel_mb_value
 * @var string $chada_travel_max_attr Empty when the server limit is unavailable (omits the max="" attribute).
 * @var string $chada_travel_mb_notice
 * @var string $chada_travel_mime_checkboxes_html Pre-escaped.
 * @var string $chada_travel_doc_types_notice
 * @var int    $chada_travel_doc_hours
 * @var string $chada_travel_hours_min
 * @var string $chada_travel_hours_max
 * @var string $chada_travel_hours_preview
 * @var string $chada_travel_reissue_note
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_mb_value));
assert(isset($chada_travel_max_attr));
assert(isset($chada_travel_mb_notice));
assert(isset($chada_travel_mime_checkboxes_html));
assert(isset($chada_travel_doc_types_notice));
assert(isset($chada_travel_doc_hours));
assert(isset($chada_travel_hours_min));
assert(isset($chada_travel_hours_max));
assert(isset($chada_travel_hours_preview));
assert(isset($chada_travel_reissue_note));
?>
<fieldset class="chada-travel-section"><legend><h2><?php esc_html_e('Visa Documents', 'chada-travel'); ?></h2></legend>
<table class="form-table" role="presentation"><tbody>

<tr><th scope="row"><label for="chada-travel-upload-max-mb">
<?php esc_html_e('Maximum File Size', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<input type="number" min="0.0001" step="0.0001" class="regular-text" id="chada-travel-upload-max-mb"
    name="chada_travel_upload_max_mb" value="<?php echo esc_attr($chada_travel_mb_value); ?>"
    <?php echo $chada_travel_max_attr !== '' ? 'max="' . esc_attr($chada_travel_max_attr) . '"' : ''; ?> required>
<?php esc_html_e('MB', 'chada-travel'); ?>
<p class="description"><?php echo esc_html($chada_travel_mb_notice); ?></p></td></tr>

<tr><th scope="row"><?php esc_html_e('Allowed Visa Document Types', 'chada-travel'); ?></th><td>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_mime_checkboxes_html; ?>
<p class="description"><?php echo esc_html($chada_travel_doc_types_notice); ?></p></td></tr>

<tr><th scope="row"><label for="chada-travel-upload-token-hours">
<?php esc_html_e('Document Upload Link Expiry', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<input type="number" min="<?php echo esc_attr($chada_travel_hours_min); ?>" max="<?php echo esc_attr($chada_travel_hours_max); ?>"
    step="1" class="small-text" id="chada-travel-upload-token-hours" name="chada_travel_upload_token_hours"
    value="<?php echo esc_attr((string) $chada_travel_doc_hours); ?>" required> <?php esc_html_e('hours', 'chada-travel'); ?>
<p class="description"><?php echo esc_html($chada_travel_hours_preview); ?> <?php echo esc_html($chada_travel_reissue_note); ?></p>
</td></tr>

</tbody></table></fieldset>
