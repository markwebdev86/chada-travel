<?php
/**
 * Saved/status-updated/validation-error notices. See CHADA_TRAVEL_Admin_Visa_Countries::render_page().
 *
 * @var string $chada_travel_notice Raw value; used only for identity comparison, never echoed.
 * @var bool $chada_travel_has_errors Whether the most recent form submission failed validation.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_notice));
assert(isset($chada_travel_has_errors));
?>
<?php if ($chada_travel_notice === 'saved'): ?>
<div class="notice notice-success is-dismissible">
<p><?php esc_html_e('Country saved.', 'chada-travel'); ?></p>
</div>
<?php elseif ($chada_travel_notice === 'status'): ?>
<div class="notice notice-success is-dismissible">
<p><?php esc_html_e('Country status updated.', 'chada-travel'); ?></p>
</div>
<?php endif; ?>
<?php if ($chada_travel_has_errors): ?>
<div class="notice notice-error is-dismissible">
<p><?php esc_html_e('Correct the highlighted fields and save again.', 'chada-travel'); ?></p>
</div>
<?php endif; ?>
