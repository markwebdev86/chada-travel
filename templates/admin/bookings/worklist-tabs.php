<?php
/**
 * All/Needs Review/Active/Archived worklist tabs. See CHADA_TRAVEL_Admin_Bookings::render_worklist_tabs().
 *
 * @var string $chada_travel_aria_label
 * @var list<array{label: string, count: int, url: string, selected: bool}> $chada_travel_tabs
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_aria_label));
assert(isset($chada_travel_tabs));
?>
<div class="chada-travel-worklist-tabs" role="tablist" aria-label="<?php echo esc_attr($chada_travel_aria_label); ?>"
data-chada-travel-tour="bookings-tabs">
<?php foreach ($chada_travel_tabs as $chada_travel_tab): ?>
<?php
$chada_travel_aria_selected = $chada_travel_tab['selected'] ? 'true' : 'false';
$chada_travel_tabindex      = $chada_travel_tab['selected'] ? '0' : '-1';
?>
<a role="tab" aria-selected="<?php echo esc_attr($chada_travel_aria_selected); ?>"
tabindex="<?php echo esc_attr($chada_travel_tabindex); ?>" href="<?php echo esc_url($chada_travel_tab['url']); ?>">
<?php echo esc_html($chada_travel_tab['label']); ?> <span class="chada-travel-badge"><?php echo (int) $chada_travel_tab['count']; ?></span>
</a>
<?php endforeach; ?>
</div>
