<?php
/**
 * The 7-tab list for Instructions. Unlike templates/admin/settings/shared/tablist.php (whose href is a full
 * admin URL and whose aria-controls intentionally points to a non-existent id, keeping its tabs a navigation-
 * only full-page-reload bar), every href/data-chada-travel-href here is a bare "#<slug>" URL hash and aria-controls is
 * the bare slug itself - matching the real, always-rendered `<div id="<slug>" role="tabpanel">` each tab
 * addresses, so assets/js/chada-travel-admin.js's shared activateTab()/activateFromHash() toggle between them
 * client-side instead of falling through to a page reload. See CHADA_TRAVEL_Admin_Instructions::render_tablist().
 *
 * @var list<array{key: string, label: string, selected: bool}> $chada_travel_tabs Raw values; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_tabs));
?>
<div class="chada-travel-tablist" role="tablist"
    aria-label="<?php echo esc_attr__('Instructions sections', 'chada-travel'); ?>"
    data-chada-travel-tablist="instructions">
<?php foreach ($chada_travel_tabs as $chada_travel_tab): ?>
<a class="chada-travel-tab" role="tab" id="chada-travel-instructions-tab-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    href="#<?php echo esc_attr($chada_travel_tab['key']); ?>"
    aria-selected="<?php echo $chada_travel_tab['selected'] ? 'true' : 'false'; ?>"
    aria-controls="<?php echo esc_attr($chada_travel_tab['key']); ?>"
    tabindex="<?php echo $chada_travel_tab['selected'] ? '0' : '-1'; ?>"
    data-chada-travel-href="#<?php echo esc_attr($chada_travel_tab['key']); ?>">
<?php echo esc_html($chada_travel_tab['label']); ?>
</a>
<?php endforeach; ?>
</div>
