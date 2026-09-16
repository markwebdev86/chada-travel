<?php
/** Front-end Featured Tours compact sidebar list. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var list<array<string, string>>|null $chada_travel_cards */
/** @var int|null $chada_travel_rows */
/** @var int|null $chada_travel_columns */
assert(isset($chada_travel_cards, $chada_travel_rows, $chada_travel_columns));
?>
<section class="chada-travel-featured-tours-sidebar" data-chada-travel-component="featured-tours-sidebar"
aria-label="<?php esc_attr_e('Featured Tours Sidebar', 'chada-travel'); ?>">
<div class="chada-travel-featured-tours-sidebar__list" data-rows="<?php echo esc_attr((string) $chada_travel_rows); ?>"
data-columns="<?php echo esc_attr((string) $chada_travel_columns); ?>">
<?php foreach ($chada_travel_cards as $chada_travel_card): ?>
<?php
$chada_travel_name = (string) $chada_travel_card['name'];
$chada_travel_permalink = (string) $chada_travel_card['permalink'];
/* translators: %s: Tour name. */
$chada_travel_view_label = sprintf(__('View %s', 'chada-travel'), $chada_travel_name);
?>
<article class="chada-travel-featured-tours-sidebar__item">
<?php if ($chada_travel_permalink !== ''): ?><a class="chada-travel-featured-tours-sidebar__image-link"
href="<?php echo esc_url($chada_travel_permalink); ?>" aria-label="<?php echo esc_attr($chada_travel_view_label); ?>">
<?php else: ?><div class="chada-travel-featured-tours-sidebar__image-link">
<?php endif; ?><div class="chada-travel-featured-tours-sidebar__image-frame">
<?php if ((string) $chada_travel_card['image_url'] !== ''): ?>
<img class="chada-travel-featured-tours-sidebar__image" src="<?php echo esc_url($chada_travel_card['image_url']); ?>"
alt="<?php echo esc_attr($chada_travel_name); ?>" loading="lazy">
<?php else: ?><div class="chada-travel-featured-tours-sidebar__image-placeholder" aria-hidden="true"></div><?php endif; ?>
</div>
<?php if ($chada_travel_permalink !== ''): ?></a><?php else: ?></div><?php endif; ?>
<div class="chada-travel-featured-tours-sidebar__content">
<h3 class="chada-travel-featured-tours-sidebar__name">
<?php if ($chada_travel_permalink !== ''): ?><a class="chada-travel-featured-tours-sidebar__name-link"
href="<?php echo esc_url($chada_travel_permalink); ?>" aria-label="<?php echo esc_attr($chada_travel_view_label); ?>"><?php endif; ?>
<?php echo esc_html($chada_travel_name); ?>
<?php if ($chada_travel_permalink !== ''): ?></a><?php endif; ?>
</h3>
<div class="chada-travel-featured-tours-sidebar__meta">
<span class="chada-travel-featured-tours-sidebar__meta-item chada-travel-featured-tours-sidebar__destination">
<svg class="chada-travel-featured-tours-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
<path d="M12 21s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>
<?php $chada_travel_destination_html = $chada_travel_card['destination'] !== '' ? $chada_travel_card['destination'] : esc_html('—'); ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- destination pre-escaped by CHADA_TRAVEL_Featured_Tours::prepare_cards(). ?>
<span><?php echo $chada_travel_destination_html; ?></span>
</span>
<span class="chada-travel-featured-tours-sidebar__meta-item chada-travel-featured-tours-sidebar__days">
<svg class="chada-travel-featured-tours-sidebar__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
<circle cx="12" cy="12" r="8.5"></circle><path d="M12 7v5l3.5 2"></path></svg>
<span><?php echo esc_html($chada_travel_card['days'] !== '' ? $chada_travel_card['days'] : '—'); ?></span>
</span>
</div>
</div>
</article>
<?php endforeach; ?>
</div>
</section>
