<?php
/**
 * The four-tab list (Overview / Payment Review / Applications & Documents / Activity) for Booking Detail.
 * See CHADA_TRAVEL_Admin_Booking_Detail::render_tablist().
 *
 * @var list<array{key: string, label: string, href: string, selected: bool}> $chada_travel_tabs Raw values;
 *      escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_tabs));
?>
<div class="chada-travel-tablist" role="tablist"
    aria-label="<?php echo esc_attr__('Visa Booking sections', 'chada-travel'); ?>"
    data-chada-travel-tablist="detail" data-chada-travel-tour="booking-detail-tabs">
<?php foreach ($chada_travel_tabs as $chada_travel_tab): ?>
<a class="chada-travel-tab" role="tab" id="chada-travel-tab-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    href="<?php echo esc_url($chada_travel_tab['href']); ?>"
    aria-selected="<?php echo $chada_travel_tab['selected'] ? 'true' : 'false'; ?>"
    aria-controls="chada-travel-panel-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    tabindex="<?php echo $chada_travel_tab['selected'] ? '0' : '-1'; ?>"
    data-chada-travel-href="<?php echo esc_attr($chada_travel_tab['href']); ?>">
<?php echo esc_html($chada_travel_tab['label']); ?>
</a>
<?php endforeach; ?>
</div>
