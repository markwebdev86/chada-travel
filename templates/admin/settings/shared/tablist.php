<?php
/**
 * The Settings tab list. See CHADA_TRAVEL_Admin_Settings::render_tablist().
 *
 * @var list<array{key: string, label: string, href: string, selected: bool}> $chada_travel_tabs Raw values; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_tabs));
?>
<div class="chada-travel-tablist" role="tablist"
    aria-label="<?php echo esc_attr__('Settings sections', 'chada-travel'); ?>"
    data-chada-travel-tablist="settings" data-chada-travel-tour="settings-tabs">
<?php foreach ($chada_travel_tabs as $chada_travel_tab): ?>
<a class="chada-travel-tab" role="tab" id="chada-travel-settings-tab-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    href="<?php echo esc_url($chada_travel_tab['href']); ?>"
    aria-selected="<?php echo $chada_travel_tab['selected'] ? 'true' : 'false'; ?>"
    aria-controls="chada-travel-settings-panel"
    tabindex="<?php echo $chada_travel_tab['selected'] ? '0' : '-1'; ?>"
    data-chada-travel-href="<?php echo esc_attr($chada_travel_tab['href']); ?>">
<?php echo esc_html($chada_travel_tab['label']); ?>
</a>
<?php endforeach; ?>
</div>
