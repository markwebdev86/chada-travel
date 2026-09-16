<?php
/**
 * Add/Edit Country card and form. Always titled "Add Country" - the heading text and which row is being
 * edited are switched client-side by JS; server-rendered markup does not distinguish add vs. edit. See
 * CHADA_TRAVEL_Admin_Visa_Countries::render_form().
 *
 * @var bool $chada_travel_reopen True when the form should render visible (a validation error just occurred).
 * @var string $chada_travel_action_url Raw value; escaped here.
 * @var string $chada_travel_save_action Raw value; escaped here.
 * @var string $chada_travel_original_code Raw value; escaped here.
 * @var string $chada_travel_nonce_html Pre-escaped HTML from wp_nonce_field().
 * @var string $chada_travel_fields_html Pre-escaped HTML: the six form-table field rows.
 * @var string $chada_travel_created_at Raw, already-formatted timestamp; escaped here.
 * @var string $chada_travel_updated_at Raw, already-formatted timestamp; escaped here.
 * @var bool $chada_travel_show_metadata True when the form is reopening an existing country.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_reopen));
assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_original_code));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_fields_html));
assert(isset($chada_travel_created_at));
assert(isset($chada_travel_updated_at));
assert(isset($chada_travel_show_metadata));
?>
<div class="card chada-travel-country-form" id="chada-travel-country-form"<?php echo $chada_travel_reopen ? '' : ' hidden'; ?>>
<h2 id="chada-travel-country-form-title"><?php esc_html_e('Add Country', 'chada-travel'); ?></h2>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<input type="hidden" name="original_code" id="chada-travel-country-original-code"
    value="<?php echo esc_attr($chada_travel_original_code); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>
<table class="form-table" role="presentation"><tbody>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_fields_html; ?>
<tr data-chada-travel-country-record-metadata<?php echo $chada_travel_show_metadata ? '' : ' hidden'; ?>><th scope="row">
<?php esc_html_e('Record timestamps', 'chada-travel'); ?>
</th><td>
<p><strong><?php esc_html_e('Created', 'chada-travel'); ?></strong><br>
<span data-chada-travel-country-created-at><?php echo esc_html($chada_travel_created_at); ?></span></p>
<p><strong><?php esc_html_e('Modified', 'chada-travel'); ?></strong><br>
<span data-chada-travel-country-updated-at><?php echo esc_html($chada_travel_updated_at); ?></span></p>
</td></tr>
</tbody></table>
<button type="button" class="button" id="chada-travel-cancel-country">
<?php esc_html_e('Cancel', 'chada-travel'); ?>
</button>
<button type="submit" class="button button-primary" id="chada-travel-save-country">
<?php esc_html_e('Save Country', 'chada-travel'); ?>
</button>
</form>
</div>
