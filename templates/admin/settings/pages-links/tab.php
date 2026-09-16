<?php
/**
 * Pages & Links tab: intro paragraph, status summary, and the Save form (one section per
 * CHADA_TRAVEL_Page_Registry::get_definitions() entry). See CHADA_TRAVEL_Admin_Settings::render_pages_links_tab().
 *
 * @var string $chada_travel_intro
 * @var string $chada_travel_status_summary_html Pre-escaped.
 * @var string $chada_travel_action_url
 * @var string $chada_travel_save_action
 * @var string $chada_travel_nonce_html Pre-escaped, from wp_nonce_field().
 * @var string $chada_travel_sections_html Pre-escaped, one page-link-section.php per definition.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_intro));
assert(isset($chada_travel_status_summary_html));
assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_sections_html));
?>
<p><?php echo esc_html($chada_travel_intro); ?></p>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_status_summary_html; ?>

<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" novalidate data-chada-travel-tour="settings-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_nonce_html; ?>

<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_sections_html; ?>

<p class="submit"><button type="submit" class="button button-primary">
<?php esc_html_e('Save Pages & Links', 'chada-travel'); ?>
</button></p>
</form>
