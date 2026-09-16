<?php
/** Front-end Featured Tours card grid. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var list<array{name: string, description: string, duration: string, code: string, destinations: string, price: string, image_url: string, permalink: string}>|null $chada_travel_cards */
/** @var string|null $chada_travel_design */
/** @var int|null $chada_travel_rows */
/** @var int|null $chada_travel_columns */
/** @var string|null $chada_travel_component */
/** @var string|null $chada_travel_label */
assert(isset($chada_travel_cards));
assert(isset($chada_travel_design));
assert(isset($chada_travel_rows));
assert(isset($chada_travel_columns));
$chada_travel_component = isset($chada_travel_component) ? (string) $chada_travel_component : 'featured-tours';
$chada_travel_label = isset($chada_travel_label) ? (string) $chada_travel_label : __('Featured Tours', 'chada-travel');
$chada_travel_component_class = $chada_travel_component === 'featured-tours' ? '' : ' chada-travel-' . $chada_travel_component;
?>
<?php
printf(
    '<section class="chada-travel-featured-tours chada-travel-featured-tours--%s%s"'
    . ' data-chada-travel-component="%s" data-design="%s" aria-label="%s">',
    esc_attr($chada_travel_design),
    esc_attr($chada_travel_component_class),
    esc_attr($chada_travel_component),
    esc_attr($chada_travel_design),
    esc_attr($chada_travel_label)
);
?>
<?php
printf(
    '<div class="chada-travel-featured-tours__grid" data-rows="%s" data-columns="%s">',
    esc_attr((string) $chada_travel_rows),
    esc_attr((string) $chada_travel_columns)
);
?>
<?php foreach ($chada_travel_cards as $chada_travel_card): ?>
<article class="chada-travel-featured-tours__card">
<div class="chada-travel-featured-tours__media">
<div class="chada-travel-featured-tours__image-wrap">
<?php if ($chada_travel_card['image_url'] !== ''): ?><?php
printf(
    '<img class="chada-travel-featured-tours__image" src="%s" alt="%s" loading="lazy">',
    esc_url($chada_travel_card['image_url']),
    esc_attr($chada_travel_card['name'])
);
?>
<?php else: ?><div class="chada-travel-featured-tours__image-placeholder" aria-hidden="true"></div><?php endif; ?>
</div>
<div class="chada-travel-featured-tours__overlay" aria-hidden="true"></div>
<div class="chada-travel-featured-tours__content">
<h3 class="chada-travel-featured-tours__name"><?php echo esc_html($chada_travel_card['name']); ?></h3>
<?php
$chada_travel_destinations_html = $chada_travel_card['destinations'] !== '' ? $chada_travel_card['destinations'] : esc_html('—');
echo '<div class="chada-travel-featured-tours__details">'
    . esc_html($chada_travel_card['duration'] !== '' ? $chada_travel_card['duration'] : '—') . ' | '
    . esc_html($chada_travel_card['code'] !== '' ? $chada_travel_card['code'] : '—') . ' | '
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- destinations pre-escaped by CHADA_TRAVEL_Featured_Tours::prepare_cards().
    . $chada_travel_destinations_html . '</div>';
?>
<div class="chada-travel-featured-tours__price-and-button">
<span class="chada-travel-featured-tours__price"><?php echo esc_html($chada_travel_card['price']); ?></span>
<?php /* translators: %s: Tour name. */ ?>
<?php $chada_travel_view_more_label = sprintf(__('View more about %s', 'chada-travel'), $chada_travel_card['name']); ?>
<?php
printf(
    '<a class="chada-travel-featured-tours__button" href="%s" aria-label="%s">%s</a>',
    esc_url($chada_travel_card['permalink']),
    esc_attr($chada_travel_view_more_label),
    esc_html(__('VIEW MORE', 'chada-travel'))
);
?>
</div>
</div>
</div>
</article>
<?php endforeach; ?>
</div>
</section>
