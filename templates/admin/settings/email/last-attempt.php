<?php
/**
 * The "Last attempted plugin email" line: either the fixed "no email has been attempted yet" sentence, or the
 * event label/audience/status badge/timestamp. See CHADA_TRAVEL_Admin_Settings::build_last_email_attempt_html().
 *
 * @var bool   $chada_travel_has_attempt
 * @var string $chada_travel_label Empty when !$chada_travel_has_attempt.
 * @var string $chada_travel_audience_label Empty when !$chada_travel_has_attempt.
 * @var string $chada_travel_status_class Empty when !$chada_travel_has_attempt.
 * @var string $chada_travel_status_label Empty when !$chada_travel_has_attempt.
 * @var string $chada_travel_attempted_at Empty when !$chada_travel_has_attempt.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_has_attempt));
assert(isset($chada_travel_label));
assert(isset($chada_travel_audience_label));
assert(isset($chada_travel_status_class));
assert(isset($chada_travel_status_label));
assert(isset($chada_travel_attempted_at));
?>
<p><strong><?php esc_html_e('Last attempted plugin email', 'chada-travel'); ?>:</strong>
<?php if (!$chada_travel_has_attempt) : ?>
<?php esc_html_e('No plugin email has been attempted yet.', 'chada-travel'); ?>
<?php else : ?>
<?php echo esc_html($chada_travel_label); ?> (<?php echo esc_html($chada_travel_audience_label); ?>)
<span class="chada-travel-badge <?php echo esc_attr($chada_travel_status_class); ?>">
<?php echo esc_html($chada_travel_status_label); ?>
</span>
<span class="chada-travel-muted chada-travel-small"><?php echo esc_html($chada_travel_attempted_at); ?></span>
<?php endif; ?>
</p>
