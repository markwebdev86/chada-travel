<?php
/**
 * One <tr> form-table row: label + Full Details WordPress editor + hint + optional validation error. See
 * CHADA_TRAVEL_Admin_Visa_Countries::build_textarea_field_html().
 *
 * @var string $chada_travel_id Raw value; escaped here.
 * @var string $chada_travel_label Raw value; escaped here.
 * @var string $chada_travel_field_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_value Raw value; passed to wp_editor() or escaped in the fallback textarea.
 * @var string $chada_travel_error Raw value; escaped here. '' when there is no validation error to show.
 * @var int $chada_travel_max_length Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_id));
assert(isset($chada_travel_label));
assert(isset($chada_travel_field_name));
assert(isset($chada_travel_value));
assert(isset($chada_travel_error));
assert(isset($chada_travel_max_length));
$chada_travel_escaped_value = function_exists('esc_textarea') ? esc_textarea($chada_travel_value) : esc_html($chada_travel_value);
$chada_travel_editor_settings = [
    'textarea_name' => $chada_travel_field_name,
    'textarea_rows' => 28,
    'media_buttons' => false,
    'quicktags'     => false,
    'teeny'         => false,
    'tinymce'       => [
        'wpautop'          => true,
        'paste_remove_styles' => true,
        'paste_webkit_styles' => 'none',
        'paste_retain_style_properties' => '',
        'toolbar1'         => 'formatselect,fontselect,fontsizeselect,bold,italic,alignleft,aligncenter,alignright,'
            . 'alignjustify,bullist,numlist,outdent,indent,link,unlink',
        'toolbar2'         => '',
        'fontsize_formats' => '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
        'font_formats'    => 'Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;'
            . 'Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;'
            . 'Calibri=calibri,sans-serif;Calibri 10=calibri,sans-serif;'
            . 'Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;'
            . 'Georgia=georgia,palatino;Helvetica=helvetica,arial,sans-serif;Impact=impact,chicago;'
            . 'Poppins=poppins,sans-serif;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;'
            . 'Terminal=terminal,monaco;Times New Roman=times new roman,times;'
            . 'Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;'
            . 'Wingdings=wingdings,zapf dingbats',
    ],
];
?>
<tr><th scope="row">
<label for="<?php echo esc_attr($chada_travel_id); ?>"><?php echo esc_html($chada_travel_label); ?></label>
</th><td>
<?php if (function_exists('wp_editor')): ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_editor() emits its own escaped editor markup. ?>
<?php wp_editor($chada_travel_value, $chada_travel_id, $chada_travel_editor_settings); ?>
<?php else: ?>
<textarea class="large-text"
    rows="28"
    maxlength="<?php echo esc_attr((string) $chada_travel_max_length); ?>"
    name="<?php echo esc_attr($chada_travel_field_name); ?>"
    id="<?php echo esc_attr($chada_travel_id); ?>"
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped above via esc_textarea()/esc_html(). ?>
><?php echo $chada_travel_escaped_value; ?></textarea>
<?php endif; ?>
<p class="description">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Formatted text shown on Stage 1. Use the editor toolbar for headings, font size, alignment, links, indentation, and lists.', 'chada-travel'); ?>
</p>
<?php if ($chada_travel_error !== ''): ?>
<p class="description chada-travel-field-error"><?php echo esc_html($chada_travel_error); ?></p>
<?php endif; ?>
</td></tr>
