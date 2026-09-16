<?php
/** Public full-detail view for one active Tour. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $name Escaped Tour name. */
/** @var string|null $code Escaped Tour code. */
/** @var string|null $price Escaped formatted price. */
/** @var string|null $image_url Escaped Featured Image URL. */
/** @var list<string>|null $destinations Escaped destination names. */
/** @var list<string>|null $types Escaped type names. */
/** @var string|null $description_html Pre-sanitized Description HTML. */
/** @var list<array{label: string}>|null $dates Escaped date labels. */
/** @var string|null $duration Escaped duration. */
/** @var array<string, array{label: string, html: string}>|null $tabs Pre-sanitized tab content. */
/** @var list<array{name: string, display_name: string, url: string}>|null $files Raw file names, labels, and URLs. */
assert(isset($name)); assert(isset($code)); assert(isset($price)); assert(isset($image_url));
assert(isset($destinations)); assert(isset($types)); assert(isset($description_html)); assert(isset($dates));
assert(isset($duration)); assert(isset($tabs)); assert(isset($files));
?>
<main class="chada-travel-tour-details" data-chada-travel-component="tour-details">
<article class="chada-travel-tour-details__article">
<div class="chada-travel-tour-details__featured-media">
<?php if ($image_url !== ''): ?><img class="chada-travel-tour-details__featured-image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>"><?php else: ?><div class="chada-travel-tour-details__image-placeholder" role="img" aria-label="<?php esc_attr_e('No Featured Image available', 'chada-travel'); ?>"></div><?php endif; ?>
</div>
<header class="chada-travel-tour-details__header"><h1 class="chada-travel-tour-details__name"><?php echo esc_html($name); ?></h1></header>
<dl class="chada-travel-tour-details__summary">
<div><dt><?php esc_html_e('Tour Code', 'chada-travel'); ?></dt><dd><?php echo esc_html($code !== '' ? $code : '—'); ?></dd></div>
<div><dt><?php esc_html_e('Price', 'chada-travel'); ?></dt><dd><?php echo esc_html($price); ?></dd></div>
<div><dt><?php esc_html_e('Tour Destinations', 'chada-travel'); ?></dt><dd><?php echo esc_html($destinations ? implode(', ', $destinations) : '—'); ?></dd></div>
<div><dt><?php esc_html_e('Tour Types', 'chada-travel'); ?></dt><dd><?php echo esc_html($types ? implode(', ', $types) : '—'); ?></dd></div>
</dl>
<section class="chada-travel-tour-details__section"><h2><?php esc_html_e('Description / Overview', 'chada-travel'); ?></h2><div class="chada-travel-tour-details__rich-text"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by CHADA_TRAVEL_Tour_Public::rich_text_html(). ?><?php echo $description_html !== '' ? $description_html : esc_html('—'); ?></div></section>
<section class="chada-travel-tour-details__travel-summary"><div class="chada-travel-tour-details__section"><h2><?php esc_html_e('Travel Dates', 'chada-travel'); ?></h2><?php if ($dates): ?><ul class="chada-travel-tour-details__date-list"><?php foreach ($dates as $chada_travel_date): ?><li><?php echo esc_html($chada_travel_date['label']); ?></li><?php endforeach; ?></ul><?php else: ?><p>—</p><?php endif; ?></div><div class="chada-travel-tour-details__section"><h2><?php esc_html_e('Duration', 'chada-travel'); ?></h2><p><?php echo esc_html($duration !== '' ? $duration : '—'); ?></p></div></section>
<section class="chada-travel-tour-details__tabs" data-chada-travel-tabs>
<div class="chada-travel-tour-details__tab-list" role="tablist" aria-label="<?php esc_attr_e('Tour information', 'chada-travel'); ?>">
<?php $chada_travel_tab_index = 0; foreach ($tabs as $chada_travel_tab_key => $chada_travel_tab): $chada_travel_tab_id = 'chada-travel-tour-detail-tab-' . sanitize_html_class($chada_travel_tab_key); $chada_travel_panel_id = 'chada-travel-tour-detail-panel-' . sanitize_html_class($chada_travel_tab_key); ?><button type="button" class="chada-travel-tour-details__tab" id="<?php echo esc_attr($chada_travel_tab_id); ?>" role="tab" aria-controls="<?php echo esc_attr($chada_travel_panel_id); ?>" aria-selected="<?php echo $chada_travel_tab_index === 0 ? 'true' : 'false'; ?>" tabindex="<?php echo $chada_travel_tab_index === 0 ? '0' : '-1'; ?>" data-chada-travel-tab-target="<?php echo esc_attr($chada_travel_panel_id); ?>"><?php echo esc_html($chada_travel_tab['label']); ?></button><?php $chada_travel_tab_index++; endforeach; ?>
</div>
<?php $chada_travel_panel_index = 0; foreach ($tabs as $chada_travel_tab_key => $chada_travel_tab): $chada_travel_panel_id = 'chada-travel-tour-detail-panel-' . sanitize_html_class($chada_travel_tab_key); $chada_travel_tab_id = 'chada-travel-tour-detail-tab-' . sanitize_html_class($chada_travel_tab_key); ?><div class="chada-travel-tour-details__panel" id="<?php echo esc_attr($chada_travel_panel_id); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr($chada_travel_tab_id); ?>" tabindex="0"<?php echo $chada_travel_panel_index === 0 ? '' : ' hidden'; ?>><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by CHADA_TRAVEL_Tour_Public::rich_text_html(). ?><?php echo $chada_travel_tab['html'] !== '' ? $chada_travel_tab['html'] : esc_html('—'); ?></div><?php $chada_travel_panel_index++; endforeach; ?>
</section>
<section class="chada-travel-tour-details__section"><h2><?php esc_html_e('Downloadable Files', 'chada-travel'); ?></h2><?php if ($files): ?><ol class="chada-travel-tour-details__file-list"><?php foreach ($files as $chada_travel_file): ?><li><a href="<?php echo esc_url($chada_travel_file['url']); ?>" download="<?php echo esc_attr($chada_travel_file['name']); ?>"><?php echo esc_html($chada_travel_file['display_name']); ?></a></li><?php endforeach; ?></ol><?php else: ?><p>—</p><?php endif; ?></section>
</article>
</main>
