<?php
/**
 * One Dashboard summary card. See CHADA_TRAVEL_Admin_Dashboard::render_card().
 *
 * @var string $chada_travel_label Raw value; escaped here.
 * @var list<string> $chada_travel_values Raw values; escaped here. Rendered as a <ul> when there is more than one,
 *      otherwise a single <p> (missing entirely defaults to '0', matching the original ?? '0' fallback).
 * @var list<string> $chada_travel_secondary Raw values; escaped here.
 * @var string $chada_travel_url Raw value; escaped here.
 * @var string $chada_travel_link_text Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_label));
assert(isset($chada_travel_values));
assert(isset($chada_travel_secondary));
assert(isset($chada_travel_url));
assert(isset($chada_travel_link_text));
?>
<div class="chada-travel-dashboard-card">
<h2 class="chada-travel-dashboard-card-label"><?php echo esc_html($chada_travel_label); ?></h2>
<?php if (count($chada_travel_values) > 1): ?>
<ul class="chada-travel-dashboard-card-value">
<?php foreach ($chada_travel_values as $chada_travel_value): ?>
<li><?php echo esc_html($chada_travel_value); ?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p class="chada-travel-dashboard-card-value"><?php echo esc_html($chada_travel_values[0] ?? '0'); ?></p>
<?php endif; ?>
<?php foreach ($chada_travel_secondary as $chada_travel_secondary_line): ?>
<p class="chada-travel-dashboard-card-secondary"><?php echo esc_html($chada_travel_secondary_line); ?></p>
<?php endforeach; ?>
<a class="chada-travel-dashboard-card-link" href="<?php echo esc_url($chada_travel_url); ?>">
<?php echo esc_html($chada_travel_link_text); ?>
</a>
</div>
