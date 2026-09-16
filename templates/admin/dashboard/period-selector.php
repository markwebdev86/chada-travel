<?php
/**
 * Read-only GET period-selector form. See CHADA_TRAVEL_Admin_Dashboard::render_period_selector().
 *
 * @var string $chada_travel_menu_slug
 * @var string $chada_travel_period_label Translated "Period" label.
 * @var string $chada_travel_options_html Pre-escaped <option> elements.
 * @var string $chada_travel_apply_label Translated "Apply" label.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_menu_slug));
assert(isset($chada_travel_period_label));
assert(isset($chada_travel_options_html));
assert(isset($chada_travel_apply_label));
?>
<form method="get" class="chada-travel-toolbar chada-travel-dashboard-period-form" data-chada-travel-tour="dashboard-period">
<input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_menu_slug); ?>">
<label for="chada-travel-dashboard-period"><?php echo esc_html($chada_travel_period_label); ?>
<select id="chada-travel-dashboard-period" name="period">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_options_html; ?>
</select>
</label>
<button type="submit" class="button"><?php echo esc_html($chada_travel_apply_label); ?></button>
</form>
