<?php
/** Shared Add/Edit/View Tour taxonomy form. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded, WordPress.Security.EscapeOutput.OutputNotEscaped

/** @var string|null $chada_travel_menu_slug Raw page slug; escaped here. */
/** @var string|null $chada_travel_taxonomy Raw taxonomy key; escaped here. */
/** @var string|null $chada_travel_label Raw form label; escaped here. */
/** @var int|null $chada_travel_id Raw term ID; escaped here. */
/** @var string|null $chada_travel_term_name Raw term name; escaped here. */
/** @var string|null $chada_travel_mode Raw form mode; escaped here. */
/** @var bool|null $chada_travel_open True when the form should be visible. */
/** @var bool|null $chada_travel_is_archived True when the selected record is archived. */
/** @var int|null $chada_travel_tour_count Number of Tours using the selected record. */
/** @var array<string, string>|null $chada_travel_errors Raw validation errors; escaped here. */
/** @var string|null $chada_travel_action_url Raw admin-post URL; escaped here. */
/** @var string|null $chada_travel_save_action Raw action name; escaped here. */
/** @var string|null $chada_travel_delete_action Raw delete action name; escaped here. */
/** @var string|null $chada_travel_nonce_html Pre-escaped nonce HTML. */
/** @var string|null $chada_travel_delete_nonce_html Pre-escaped delete nonce HTML. */

assert(isset($chada_travel_menu_slug)); assert(isset($chada_travel_taxonomy)); assert(isset($chada_travel_label)); assert(isset($chada_travel_id));
assert(isset($chada_travel_term_name)); assert(isset($chada_travel_mode)); assert(isset($chada_travel_open)); assert(isset($chada_travel_is_archived)); assert(isset($chada_travel_tour_count)); assert(isset($chada_travel_errors));
assert(isset($chada_travel_action_url)); assert(isset($chada_travel_save_action)); assert(isset($chada_travel_delete_action)); assert(isset($chada_travel_nonce_html)); assert(isset($chada_travel_delete_nonce_html));
$chada_travel_readonly = $chada_travel_mode === 'view' ? ' readonly disabled' : '';
$chada_travel_title = $chada_travel_id > 0 ? ($chada_travel_mode === 'view' ? __('View Record', 'chada-travel') : __('Edit Record', 'chada-travel')) : __('Add Record', 'chada-travel');
$chada_travel_usage_message = '';
if ($chada_travel_is_archived && $chada_travel_tour_count > 0) {
    /* translators: %d: number of Tours using this record. */
    $chada_travel_usage_label = _n(
        '%d Tour uses this record; deletion is unavailable.',
        '%d Tours use this record; deletion is unavailable.',
        $chada_travel_tour_count,
        'chada-travel'
    );
    $chada_travel_usage_message = sprintf($chada_travel_usage_label, $chada_travel_tour_count);
}
?>
<div class="chada-travel-term-form-card" id="chada-travel-term-form" data-chada-travel-term-form-mode="<?php echo esc_attr($chada_travel_mode); ?>"<?php echo $chada_travel_open ? '' : ' hidden'; ?>><h2 id="chada-travel-term-form-title"><?php echo esc_html($chada_travel_title); ?></h2>
<?php if ($chada_travel_errors): ?><div class="notice notice-error"><p><?php echo esc_html(implode(' ', $chada_travel_errors)); ?></p></div><?php endif; ?>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" id="chada-travel-term-form-fields"><input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_save_action); ?>"><input type="hidden" name="term_id" id="chada-travel-term-id" value="<?php echo esc_attr((string) $chada_travel_id); ?>"><input type="hidden" name="taxonomy" value="<?php echo esc_attr($chada_travel_taxonomy); ?>">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?>
<?php echo $chada_travel_nonce_html; ?>
<p><label for="chada-travel-term-name"><strong><?php echo esc_html($chada_travel_label); ?></strong></label><br><input class="regular-text" id="chada-travel-term-name" name="name" maxlength="100" value="<?php echo esc_attr($chada_travel_term_name); ?>" required<?php echo $chada_travel_readonly; ?>></p>
<p><button type="submit" class="button button-primary" id="chada-travel-save-tour-term"><?php esc_html_e('Save Record', 'chada-travel'); ?></button> <button type="button" class="button" id="chada-travel-edit-tour-term" hidden><?php esc_html_e('Edit Record', 'chada-travel'); ?></button><?php if ($chada_travel_is_archived && $chada_travel_id > 0 && $chada_travel_tour_count === 0): ?> <button type="button" class="button button-link-delete" data-delete-tour-term="<?php echo esc_attr((string) $chada_travel_id); ?>" data-delete-form="chada-travel-term-delete-form"><?php esc_html_e('Delete', 'chada-travel'); ?></button><?php elseif ($chada_travel_usage_message !== ''): ?> <span class="description"><?php echo esc_html($chada_travel_usage_message); ?></span><?php endif; ?> <button type="button" class="button" id="chada-travel-cancel-tour-term"><?php esc_html_e('Cancel', 'chada-travel'); ?></button></p></form><?php if ($chada_travel_is_archived && $chada_travel_id > 0 && $chada_travel_tour_count === 0): ?><form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" id="chada-travel-term-delete-form" hidden><input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_delete_action); ?>"><input type="hidden" name="term_id" value="<?php echo esc_attr((string) $chada_travel_id); ?>"><input type="hidden" name="taxonomy" value="<?php echo esc_attr($chada_travel_taxonomy); ?>"><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?><?php echo $chada_travel_delete_nonce_html; ?></form><?php endif; ?></div>
