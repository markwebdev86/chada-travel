<?php
/** Tour duplication confirmation modal and its nonce-protected form. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_action_url Raw admin-post URL; escaped here. */
/** @var string|null $chada_travel_duplicate_action Raw action name; escaped here. */
/** @var string|null $chada_travel_nonce_html Pre-escaped nonce HTML. */

assert(isset($chada_travel_action_url));
assert(isset($chada_travel_duplicate_action));
assert(isset($chada_travel_nonce_html));
?>
<div id="chada-travel-tour-duplicate-modal" class="chada-travel-confirmation-modal" hidden>
<div class="chada-travel-confirmation-modal__backdrop" data-chada-travel-duplicate-cancel></div>
<div class="chada-travel-confirmation-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="chada-travel-tour-duplicate-title" tabindex="-1">
<h2 id="chada-travel-tour-duplicate-title"><?php esc_html_e('Duplicate Tour?', 'chada-travel'); ?></h2>
<p><?php esc_html_e('This will create an active copy of the Tour with a unique duplicate name and code. Continue?', 'chada-travel'); ?></p>
<p><button type="button" class="button" data-chada-travel-duplicate-cancel><?php esc_html_e('Cancel', 'chada-travel'); ?></button> <button type="button" class="button button-primary" data-chada-travel-duplicate-confirm><?php esc_html_e('Duplicate Tour', 'chada-travel'); ?></button></p>
<form method="post" action="<?php echo esc_url($chada_travel_action_url); ?>" id="chada-travel-tour-duplicate-form" hidden>
<input type="hidden" name="action" value="<?php echo esc_attr($chada_travel_duplicate_action); ?>">
<input type="hidden" name="tour_id" id="chada-travel-tour-duplicate-id" value="0">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped nonce HTML. ?>
<?php echo $chada_travel_nonce_html; ?>
</form>
</div></div>
