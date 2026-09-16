<?php
/**
 * One <tr data-country> row in the Visa Countries table. See CHADA_TRAVEL_Admin_Visa_Countries::build_row_html().
 *
 * @var string $chada_travel_search Raw value; escaped here. Lowercased "name code" used by client-side search JS.
 * @var string $chada_travel_status Raw value; escaped here. 'active' or 'archived'.
 * @var string $chada_travel_country_name Raw value; escaped here. Named to avoid colliding with
 *      CHADA_TRAVEL_Template::render()'s own $chada_travel_name parameter.
 * @var string $chada_travel_fee Raw value; escaped here. Unformatted numeric string used by client-side sort JS.
 * @var string $chada_travel_order Raw value; escaped here.
 * @var string $chada_travel_code Raw value; escaped here.
 * @var string $chada_travel_fee_formatted Raw value; escaped here. Currency-formatted for display.
 * @var string $chada_travel_checklist_version Raw value; escaped here.
 * @var int $chada_travel_requirements_count Raw value; escaped here.
 * @var bool $chada_travel_guide_ready
 * @var string $chada_travel_guide_thumbnail_url Raw value; escaped here. WordPress thumbnail-size URL.
 * @var bool $chada_travel_frontend_ready True when the guide, checklist, and full details are all complete.
 * @var bool $chada_travel_is_active
 * @var string $chada_travel_toggle_action Raw value; escaped here.
 * @var string $chada_travel_toggle_url Raw value; escaped here.
 * @var string $chada_travel_toggle_nonce_html Pre-escaped HTML from wp_nonce_field().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_search));
assert(isset($chada_travel_status));
assert(isset($chada_travel_country_name));
assert(isset($chada_travel_fee));
assert(isset($chada_travel_order));
assert(isset($chada_travel_code));
assert(isset($chada_travel_fee_formatted));
assert(isset($chada_travel_checklist_version));
assert(isset($chada_travel_requirements_count));
assert(isset($chada_travel_guide_ready));
assert(isset($chada_travel_guide_thumbnail_url));
assert(isset($chada_travel_frontend_ready));
assert(isset($chada_travel_is_active));
assert(isset($chada_travel_toggle_action));
assert(isset($chada_travel_toggle_url));
assert(isset($chada_travel_toggle_nonce_html));
?>
<tr data-country
    data-search="<?php echo esc_attr($chada_travel_search); ?>"
    data-status="<?php echo esc_attr($chada_travel_status); ?>"
    data-name="<?php echo esc_attr($chada_travel_country_name); ?>"
    data-fee="<?php echo esc_attr($chada_travel_fee); ?>"
    data-order="<?php echo esc_attr($chada_travel_order); ?>"
    data-code="<?php echo esc_attr($chada_travel_code); ?>">
<td><strong><?php echo esc_html($chada_travel_country_name); ?></strong><br>
<span class="description">
<?php echo esc_html($chada_travel_code); ?> &middot;
<?php esc_html_e('Display order', 'chada-travel'); ?>
<?php echo (int) $chada_travel_order; ?>
</span></td>
<td><?php echo esc_html($chada_travel_fee_formatted); ?></td>
<td><?php echo esc_html($chada_travel_checklist_version); ?></td>
<td><?php echo esc_html($chada_travel_requirements_count . ' active'); ?></td>
<td>
<?php if ($chada_travel_guide_thumbnail_url !== ''): ?>
<img class="chada-travel-country-guide-thumbnail" src="<?php echo esc_url($chada_travel_guide_thumbnail_url); ?>"
    width="35" height="35"
    <?php /* translators: %s: country name. */ ?>
    alt="<?php echo esc_attr(sprintf(__('%s Step-by-Step Guide thumbnail', 'chada-travel'), $chada_travel_country_name)); ?>">
<?php else: ?>
<?php esc_html_e('Missing', 'chada-travel'); ?>
<?php endif; ?><br>
<span class="description">
<?php echo esc_html($chada_travel_frontend_ready
    ? __('Frontend and email ready', 'chada-travel')
    : __('Incomplete setup', 'chada-travel')); ?>
</span>
</td>
<td><span class="chada-travel-country-status-badge">
<?php echo $chada_travel_is_active ? esc_html__('Active', 'chada-travel') : esc_html__('Archived', 'chada-travel'); ?>
</span></td>
<td>
<button type="button" class="button button-small" data-edit-country="<?php echo esc_attr($chada_travel_code); ?>">
<?php esc_html_e('Edit', 'chada-travel'); ?>
</button>
<form method="post" action="<?php echo esc_url($chada_travel_toggle_url); ?>" class="chada-travel-inline-form">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_toggle_action); ?>">
<input type="hidden" name="code" value="<?php echo esc_attr($chada_travel_code); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_toggle_nonce_html; ?>
<button type="submit" class="button button-small">
<?php echo $chada_travel_is_active ? esc_html__('Archive', 'chada-travel') : esc_html__('Activate', 'chada-travel'); ?>
</button>
</form>
</td>
</tr>
