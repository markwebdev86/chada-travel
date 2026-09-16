<?php
/** Shared Add/Edit/View Tour form. */

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded, WordPress.Security.EscapeOutput.OutputNotEscaped

/** @var array<string, mixed>|null $chada_travel_tour Raw Tour values; escaped at output. */
/** @var list<array<string, mixed>>|null $chada_travel_dates Raw date ranges; escaped at output. */
/** @var list<array<string, mixed>>|null $chada_travel_destinations Raw destination terms; escaped at output. */
/** @var list<array<string, mixed>>|null $chada_travel_types Raw type terms; escaped at output. */
/** @var bool|null $chada_travel_free_type_mode True when only built-in types may be selected. */
/** @var list<int|string>|null $chada_travel_destination_ids Selected destination IDs. */
/** @var list<int|string>|null $chada_travel_type_ids Selected type IDs. */
/** @var list<array{id: int, name: string, url: string, mime: string, size: int, order: int}>|null $chada_travel_files Raw file metadata; escaped at output. */
/** @var string|null $chada_travel_duration Computed first-range duration; escaped at output. */
/** @var array<string, string>|null $chada_travel_errors Raw validation errors; escaped at output. */
/** @var string|null $chada_travel_currency Raw currency code; escaped here. */
/** @var string|null $chada_travel_created_at Formatted created timestamp; escaped here. */
/** @var string|null $chada_travel_updated_at Formatted modified timestamp; escaped here. */
/** @var bool|null $chada_travel_show_metadata True when an existing Tour record is selected. */
/** @var string|null $chada_travel_mode Raw form mode; escaped here. */
/** @var bool|null $chada_travel_open True when the form should be visible. */
/** @var bool|null $chada_travel_is_archived True when the selected Tour is archived. */
/** @var string|null $chada_travel_nonce_html Pre-escaped nonce HTML. */
/** @var string|null $chada_travel_action_url Raw admin-post URL; escaped here. */
/** @var string|null $chada_travel_save_action Raw action name; escaped here. */
/** @var string|null $chada_travel_delete_action Raw delete action name; escaped here. */
/** @var string|null $chada_travel_delete_nonce_html Pre-escaped delete nonce HTML. */
/** @var string|null $chada_travel_image_url Raw thumbnail URL; escaped here. */
/** @var int|null $chada_travel_image_id Raw attachment ID; escaped here. */
/** @var string|null $chada_travel_tour_permalink Canonical Tour permalink; escaped here. */
/** @var string|null $chada_travel_permalink_prefix URL prefix for the editable slug; escaped here. */
/** @var string|null $chada_travel_permalink_suffix URL suffix for the editable slug; escaped here. */
/** @var string|null $chada_travel_readonly Code-controlled attribute fragment. */
/** @var list<string>|null $chada_travel_mime_types Allowed MIME types; escaped by JSON attribute output. */
/** @var list<string>|null $chada_travel_currency_options Currency codes; escaped at output. */
/** @var int|null $chada_travel_image_width Final Featured Image width. */
/** @var int|null $chada_travel_image_height Final Featured Image height. */
/** @var int|null $chada_travel_image_max_mb Maximum source image size in MB. */

assert(isset($chada_travel_tour));
assert(isset($chada_travel_dates));
assert(isset($chada_travel_destinations));
assert(isset($chada_travel_types));
assert(isset($chada_travel_free_type_mode));
assert(isset($chada_travel_destination_ids));
assert(isset($chada_travel_type_ids));
assert(isset($chada_travel_files));
assert(isset($chada_travel_duration));
assert(isset($chada_travel_errors));
assert(isset($chada_travel_currency));
assert(isset($chada_travel_mode));
assert(isset($chada_travel_open));
assert(isset($chada_travel_is_archived));
assert(isset($chada_travel_nonce_html));
assert(isset($chada_travel_action_url));
assert(isset($chada_travel_save_action));
assert(isset($chada_travel_delete_action));
assert(isset($chada_travel_delete_nonce_html));
assert(isset($chada_travel_image_url));
assert(isset($chada_travel_image_id));
assert(isset($chada_travel_tour_permalink));
assert(isset($chada_travel_permalink_prefix));
assert(isset($chada_travel_permalink_suffix));
assert(isset($chada_travel_readonly));
assert(isset($chada_travel_mime_types));
assert(isset($chada_travel_currency_options));
assert(isset($chada_travel_created_at));
assert(isset($chada_travel_updated_at));
assert(isset($chada_travel_show_metadata));
assert(isset($chada_travel_image_width));
assert(isset($chada_travel_image_height));
assert(isset($chada_travel_image_max_mb));

$chada_travel_id = (int) ($chada_travel_tour['chada_travel_tour_id'] ?? $chada_travel_tour['tour_id'] ?? 0);
$chada_travel_title = $chada_travel_id > 0 ? ($chada_travel_mode === 'view' ? __('View Tour', 'chada-travel') : __('Edit Tour', 'chada-travel')) : __('Add Tour', 'chada-travel');
$chada_travel_name = (string) ($chada_travel_tour['chada_travel_tour_name'] ?? $chada_travel_tour['tour_name'] ?? '');
$chada_travel_code = (string) ($chada_travel_tour['chada_travel_tour_code'] ?? $chada_travel_tour['tour_code'] ?? '');
$chada_travel_price = (string) ($chada_travel_tour['chada_travel_price'] ?? $chada_travel_tour['price'] ?? '');
$chada_travel_editor_names = ['description'];
$chada_travel_editor_labels = [
    'description' => 'Description', 'trip_includes' => 'Trip Includes', 'trip_excludes' => 'Trip Excludes',
    'basic_visa_requirements' => 'Visa Requirements', 'itinerary' => 'Itinerary',
    'booking_conditions' => 'Booking Conditions',
];
$chada_travel_editor_settings = CHADA_TRAVEL_Admin_Tours::editor_settings();
/* translators: %1$d: maximum image size in MB; %2$d: target image width; %3$d: target image height. */
$chada_travel_featured_image_help = sprintf(
    /* translators: %1$d: maximum image size in MB; %2$d: target image width; %3$d: target image height. */
    __('JPG, JPEG, or PNG only; maximum %1$d MB. The saved image is resized and center-cropped to %2$d × %3$d pixels and named from the Tour Code.', 'chada-travel'),
    $chada_travel_image_max_mb,
    $chada_travel_image_width,
    $chada_travel_image_height
);
$chada_travel_travel_dates_help = __('Add one or more Travel Date ranges. Overlapping and reverse-ordered ranges are allowed; each value must be a valid ISO date.', 'chada-travel');
?>
<div class="chada-travel-tour-form-card" id="chada-travel-tour-form" data-chada-travel-tour="tour-form"
    data-chada-travel-tour-form-mode="<?php echo esc_attr($chada_travel_mode); ?>"<?php echo $chada_travel_open ? '' : ' hidden'; ?>>
<h2 id="chada-travel-tour-form-title"><?php echo esc_html($chada_travel_title); ?></h2>
<?php if ($chada_travel_errors): ?><div class="notice notice-error"><p><?php echo esc_html(implode(' ', $chada_travel_errors)); ?></p></div><?php endif; ?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" id="chada-travel-tour-form-fields">
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>">
<input type="hidden" name="tour_id" id="chada-travel-tour-id" value="<?php echo esc_attr((string) $chada_travel_id); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?>
<?php echo $chada_travel_nonce_html; ?>
<div class="chada-travel-tour-field"><label><strong><?php esc_html_e('Featured Image', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><div class="chada-travel-tour-media">
<input type="hidden" name="feature_image_attachment_id" id="chada-travel-tour-image-id" value="<?php echo esc_attr((string) $chada_travel_image_id); ?>" aria-required="true">
<?php if ($chada_travel_image_url !== ''): ?><img id="chada-travel-tour-image-preview" src="<?php echo esc_url($chada_travel_image_url); ?>" alt="" width="120" height="120"><?php endif; ?>
<button type="button" class="button chada-travel-tour-image-control" id="chada-travel-tour-select-image"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php esc_html_e('Select Image', 'chada-travel'); ?></button>
<button type="button" class="button chada-travel-tour-image-control" id="chada-travel-tour-remove-image"<?php echo $chada_travel_image_id > 0 && $chada_travel_readonly === '' ? '' : ' hidden'; ?>><?php esc_html_e('Remove Image', 'chada-travel'); ?></button>
<p class="description chada-travel-tour-image-control"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php echo esc_html($chada_travel_featured_image_help); ?></p>
</div></div>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-featured"><strong><?php esc_html_e('Featured', 'chada-travel'); ?></strong></label><label class="chada-travel-tour-toggle"><input type="checkbox" id="chada-travel-tour-featured" name="is_featured" value="1"<?php checked(!empty($chada_travel_tour['chada_travel_is_featured']) || !empty($chada_travel_tour['is_featured'])); ?><?php echo $chada_travel_readonly; ?>> <?php esc_html_e('Feature this Tour for future front-end displays.', 'chada-travel'); ?></label></div>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-tour_name"><strong><?php esc_html_e('Tour Name', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><input class="regular-text" id="chada-travel-tour-tour_name" name="tour_name" value="<?php echo esc_attr($chada_travel_name); ?>" maxlength="100" required<?php echo $chada_travel_readonly; ?>></div>
<div class="chada-travel-tour-field chada-travel-tour-url-field"><label for="chada-travel-tour-tour_slug"><strong><?php esc_html_e('Permalink', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><div class="chada-travel-tour-url-control" data-chada-travel-tour-url-prefix="<?php echo esc_attr($chada_travel_permalink_prefix); ?>" data-chada-travel-tour-url-suffix="<?php echo esc_attr($chada_travel_permalink_suffix); ?>"><span class="chada-travel-tour-url-prefix" aria-hidden="true"><?php echo esc_html($chada_travel_permalink_prefix); ?></span><input class="regular-text" id="chada-travel-tour-tour_slug" name="tour_slug" value="<?php echo esc_attr((string) ($chada_travel_tour['chada_travel_tour_slug'] ?? $chada_travel_tour['tour_slug'] ?? '')); ?>" maxlength="200" required<?php echo $chada_travel_readonly; ?>><span class="chada-travel-tour-url-suffix" aria-hidden="true"><?php echo esc_html($chada_travel_permalink_suffix); ?></span><a class="chada-travel-tour-url-open" id="chada-travel-tour-url-open" href="<?php echo esc_url($chada_travel_tour_permalink); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e('Open Tour on the front end in a new tab', 'chada-travel'); ?>"<?php echo $chada_travel_tour_permalink === '' ? ' hidden aria-hidden="true"' : ''; ?>><span class="dashicons dashicons-external" aria-hidden="true"></span></a></div><p class="description"><a id="chada-travel-tour-url-preview" href="<?php echo esc_url($chada_travel_tour_permalink); ?>" target="_blank" rel="noopener"<?php echo $chada_travel_tour_permalink === '' ? ' hidden aria-hidden="true"' : ''; ?>><?php echo esc_html($chada_travel_tour_permalink); ?></a></p><p class="description"><?php esc_html_e('Use a unique, SEO-friendly slug for the public Tour URL. Spaces and unsupported characters are normalized automatically; changing the Tour Name does not change an existing slug.', 'chada-travel'); ?></p></div>
<?php foreach ($chada_travel_editor_names as $chada_travel_editor_name): $chada_travel_value = (string) ($chada_travel_tour['chada_travel_' . $chada_travel_editor_name] ?? $chada_travel_tour[$chada_travel_editor_name] ?? ''); $chada_travel_editor_settings['textarea_name'] = $chada_travel_editor_name; ?>
<div class="chada-travel-tour-field chada-travel-tour-editor-field" data-chada-travel-editor-field="<?php echo esc_attr($chada_travel_editor_name); ?>"><label><strong><?php echo esc_html($chada_travel_editor_labels[$chada_travel_editor_name]); ?></strong><?php if (in_array($chada_travel_editor_name, ['description', 'itinerary'], true)): ?> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span><?php endif; ?></label><div class="chada-travel-tour-editor-control"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php wp_editor($chada_travel_value, 'chada-travel-tour-editor-' . $chada_travel_editor_name, $chada_travel_editor_settings); ?></div><div class="chada-travel-tour-content chada-travel-tour-editor-preview" data-chada-travel-editor-preview="<?php echo esc_attr($chada_travel_editor_name); ?>"<?php echo $chada_travel_readonly === '' ? ' hidden' : ''; ?>><?php echo wp_kses_post(wpautop($chada_travel_value)); ?></div></div>
<?php endforeach; ?>
<div class="chada-travel-tour-field"><label><strong><?php esc_html_e('Travel Dates', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><div id="chada-travel-tour-dates">
<?php foreach ($chada_travel_dates as $chada_travel_index => $chada_travel_date): $chada_travel_date_id = (int) ($chada_travel_date['chada_travel_tour_date_id'] ?? $chada_travel_date['date_id'] ?? 0); $chada_travel_start = (string) ($chada_travel_date['chada_travel_start_date'] ?? $chada_travel_date['start_date'] ?? ''); $chada_travel_end = (string) ($chada_travel_date['chada_travel_end_date'] ?? $chada_travel_date['end_date'] ?? ''); ?>
<div class="chada-travel-tour-date-row"><?php if ($chada_travel_date_id > 0): ?><input type="hidden" class="chada-travel-tour-date-id" name="travel_dates[<?php echo (int) $chada_travel_index; ?>][date_id]" value="<?php echo esc_attr((string) $chada_travel_date_id); ?>"><?php endif; ?><input type="date" class="chada-travel-tour-start-date" name="travel_dates[<?php echo (int) $chada_travel_index; ?>][start_date]" value="<?php echo esc_attr($chada_travel_start); ?>" required<?php echo $chada_travel_readonly; ?>> <span>&ndash;</span> <input type="date" class="chada-travel-tour-end-date" name="travel_dates[<?php echo (int) $chada_travel_index; ?>][end_date]" value="<?php echo esc_attr($chada_travel_end); ?>" required<?php echo $chada_travel_readonly; ?>> <button type="button" class="button-link-delete chada-travel-remove-tour-date"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php esc_html_e('Remove', 'chada-travel'); ?></button></div>
<?php endforeach; ?></div><button type="button" class="button" id="chada-travel-add-tour-date"<?php echo $chada_travel_readonly !== '' ? ' disabled aria-disabled="true"' : ''; ?>><?php esc_html_e('Add Travel Date', 'chada-travel'); ?></button><p class="description"><?php echo esc_html($chada_travel_travel_dates_help); ?></p></div>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-duration"><strong><?php esc_html_e('Duration', 'chada-travel'); ?></strong></label><output id="chada-travel-tour-duration" aria-live="polite" aria-readonly="true" data-empty-text="—"><?php echo esc_html($chada_travel_duration !== '' ? $chada_travel_duration : '—'); ?></output></div>
<?php foreach (['trip_includes', 'trip_excludes', 'basic_visa_requirements', 'itinerary', 'booking_conditions'] as $chada_travel_editor_name): $chada_travel_value = (string) ($chada_travel_tour['chada_travel_' . $chada_travel_editor_name] ?? $chada_travel_tour[$chada_travel_editor_name] ?? ''); $chada_travel_editor_settings['textarea_name'] = $chada_travel_editor_name; ?>
<div class="chada-travel-tour-field chada-travel-tour-editor-field" data-chada-travel-editor-field="<?php echo esc_attr($chada_travel_editor_name); ?>"><label><strong><?php echo esc_html($chada_travel_editor_labels[$chada_travel_editor_name]); ?></strong><?php if (in_array($chada_travel_editor_name, ['description', 'itinerary'], true)): ?> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span><?php endif; ?></label><div class="chada-travel-tour-editor-control"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php wp_editor($chada_travel_value, 'chada-travel-tour-editor-' . $chada_travel_editor_name, $chada_travel_editor_settings); ?></div><div class="chada-travel-tour-content chada-travel-tour-editor-preview" data-chada-travel-editor-preview="<?php echo esc_attr($chada_travel_editor_name); ?>"<?php echo $chada_travel_readonly === '' ? ' hidden' : ''; ?>><?php echo wp_kses_post(wpautop($chada_travel_value)); ?></div></div>
<?php endforeach; ?>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-currency"><strong><?php esc_html_e('Currency', 'chada-travel'); ?></strong></label><select class="regular-text" id="chada-travel-tour-currency" name="currency" required<?php echo $chada_travel_readonly; ?>><?php foreach ($chada_travel_currency_options as $chada_travel_currency_option): ?><option value="<?php echo esc_attr($chada_travel_currency_option); ?>"<?php selected($chada_travel_currency_option, $chada_travel_currency); ?>><?php echo esc_html($chada_travel_currency_option); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e('Currency used to display and preserve this Tour’s Price. It is independent from the site-wide transaction currency.', 'chada-travel'); ?></p></div>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-tour_code"><strong><?php esc_html_e('Tour Code', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><input class="regular-text" id="chada-travel-tour-tour_code" name="tour_code" value="<?php echo esc_attr($chada_travel_code); ?>" maxlength="20" required<?php echo $chada_travel_readonly; ?>><p class="description"><?php esc_html_e('A required unique reference code used by administrators and in Tour communications. Use letters, numbers, hyphens, or underscores.', 'chada-travel'); ?></p></div>
<div class="chada-travel-tour-field"><label for="chada-travel-tour-price"><strong><?php esc_html_e('Price', 'chada-travel'); ?></strong> <span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span></label><input class="regular-text" id="chada-travel-tour-price" name="price" value="<?php echo esc_attr($chada_travel_price); ?>" type="number" step="0.01" inputmode="decimal" placeholder="0.00" min="0" required<?php echo $chada_travel_readonly; ?>></div>
<?php foreach ([['destination_ids', 'Tour Destinations', $chada_travel_destinations], ['type_ids', 'Tour Types', $chada_travel_types]] as [$chada_travel_term_name, $chada_travel_term_label, $chada_travel_terms]): ?>
<div class="chada-travel-tour-field"><fieldset><legend><strong><?php echo esc_html($chada_travel_term_label); ?></strong></legend><div class="chada-travel-tour-checkboxes">
<?php foreach ($chada_travel_terms as $chada_travel_term): $chada_travel_term_id = (int) $chada_travel_term['chada_travel_tour_term_id']; $chada_travel_selected_ids = $chada_travel_term_name === 'destination_ids' ? $chada_travel_destination_ids : $chada_travel_type_ids; $chada_travel_checked = in_array($chada_travel_term_id, array_map('intval', $chada_travel_selected_ids), true); $chada_travel_archived = empty($chada_travel_term['chada_travel_is_active']); $chada_travel_preserve_custom = $chada_travel_term_name === 'type_ids' && $chada_travel_free_type_mode && empty($chada_travel_term['chada_travel_is_default']) && $chada_travel_checked; if ($chada_travel_archived && !$chada_travel_checked) continue; ?>
<?php if ($chada_travel_preserve_custom): ?><input type="hidden" name="<?php echo esc_attr($chada_travel_term_name); ?>[]" value="<?php echo esc_attr((string) $chada_travel_term_id); ?>"><?php endif; ?><label><input type="checkbox"<?php echo $chada_travel_preserve_custom ? '' : ' name="' . esc_attr($chada_travel_term_name) . '[]"'; ?> value="<?php echo esc_attr((string) $chada_travel_term_id); ?>"<?php checked($chada_travel_checked); ?><?php echo $chada_travel_readonly . ($chada_travel_preserve_custom ? ' disabled aria-disabled="true"' : ''); ?>> <?php echo esc_html($chada_travel_term['chada_travel_term_name']); ?><?php echo $chada_travel_archived ? ' (' . esc_html__('Archived', 'chada-travel') . ')' : ''; ?><?php echo $chada_travel_preserve_custom ? ' (' . esc_html__('Existing custom assignment', 'chada-travel') . ')' : ''; ?></label>
<?php endforeach; ?></div></fieldset></div>
<?php endforeach; ?>
<div class="chada-travel-tour-field chada-travel-tour-files-field"><label><strong><?php esc_html_e('Downloadable Files', 'chada-travel'); ?></strong></label><ol id="chada-travel-tour-files" data-mime-types="<?php echo esc_attr(wp_json_encode($chada_travel_mime_types)); ?>">
<?php foreach ($chada_travel_files as $chada_travel_file): ?><li class="chada-travel-tour-file-row" data-attachment-id="<?php echo esc_attr((string) $chada_travel_file['id']); ?>"><input type="hidden" name="downloadable_file_ids[]" value="<?php echo esc_attr((string) $chada_travel_file['id']); ?>"><?php if ($chada_travel_file['url'] !== ''): ?><a href="<?php echo esc_url($chada_travel_file['url']); ?>" download="<?php echo esc_attr($chada_travel_file['name']); ?>"><?php echo esc_html($chada_travel_file['name']); ?></a><?php else: ?><span><?php echo esc_html($chada_travel_file['name']); ?></span><?php endif; ?> <span class="description"><?php echo esc_html($chada_travel_file['mime']); ?></span> <button type="button" class="button-link-delete chada-travel-remove-tour-file"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?>><?php esc_html_e('Remove', 'chada-travel'); ?></button> <button type="button" class="button-link chada-travel-move-tour-file chada-travel-move-tour-file-up"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?> aria-label="<?php esc_attr_e('Move file up', 'chada-travel'); ?>">&uarr;</button> <button type="button" class="button-link chada-travel-move-tour-file chada-travel-move-tour-file-down"<?php echo $chada_travel_readonly !== '' ? ' hidden' : ''; ?> aria-label="<?php esc_attr_e('Move file down', 'chada-travel'); ?>">&darr;</button></li>
<?php endforeach; ?></ol><p class="chada-travel-tour-files-actions"><button type="button" class="button chada-travel-tour-file-control" id="chada-travel-tour-select-files"<?php echo $chada_travel_readonly !== '' ? ' disabled aria-disabled="true"' : ''; ?>><?php esc_html_e('Select Files', 'chada-travel'); ?></button> <span id="chada-travel-tour-files-count" class="description"></span></p><p class="description"><?php esc_html_e('Select one or more files. File type and maximum file size follow Documents & Uploads settings.', 'chada-travel'); ?></p></div>
<div class="chada-travel-tour-metadata" data-chada-travel-tour-record-metadata aria-label="<?php esc_attr_e('Tour record timestamps', 'chada-travel'); ?>"<?php echo $chada_travel_show_metadata ? '' : ' hidden'; ?>><div class="chada-travel-tour-metadata-field"><label for="chada-travel-tour-created-at"><?php esc_html_e('Created', 'chada-travel'); ?></label><input type="text" id="chada-travel-tour-created-at" value="<?php echo esc_attr($chada_travel_created_at); ?>" readonly disabled></div><div class="chada-travel-tour-metadata-field"><label for="chada-travel-tour-updated-at"><?php esc_html_e('Modified', 'chada-travel'); ?></label><input type="text" id="chada-travel-tour-updated-at" value="<?php echo esc_attr($chada_travel_updated_at); ?>" readonly disabled></div></div>
<p class="chada-travel-tour-form-actions"><button type="submit" class="button button-primary" id="chada-travel-save-tour"><?php esc_html_e('Save Tour', 'chada-travel'); ?></button> <button type="button" class="button" id="chada-travel-duplicate-tour"<?php echo $chada_travel_id > 0 && $chada_travel_mode === 'edit' ? '' : ' hidden'; ?>><?php esc_html_e('Duplicate', 'chada-travel'); ?></button> <button type="button" class="button" id="chada-travel-edit-tour" hidden><?php esc_html_e('Edit Tour', 'chada-travel'); ?></button><?php if ($chada_travel_is_archived && $chada_travel_id > 0): ?> <button type="button" class="button button-link-delete" id="chada-travel-delete-tour-form-button" data-delete-tour="<?php echo esc_attr((string) $chada_travel_id); ?>" data-delete-form="chada-travel-tour-delete-form"><?php esc_html_e('Delete', 'chada-travel'); ?></button><?php endif; ?> <button type="button" class="button" id="chada-travel-cancel-tour"><?php esc_html_e('Cancel', 'chada-travel'); ?></button></p>
</form><?php if ($chada_travel_is_archived && $chada_travel_id > 0): ?><form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" id="chada-travel-tour-delete-form" hidden><input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_delete_action); ?>"><input type="hidden" name="tour_id" value="<?php echo esc_attr((string) $chada_travel_id); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?><?php echo $chada_travel_delete_nonce_html; ?></form><?php endif; ?></div>
