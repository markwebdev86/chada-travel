<?php
/**
 * Delivery Status section: provider/queue/sender-identity summary plus the last attempted email. See
 * CHADA_TRAVEL_Admin_Settings::build_delivery_status_section_html().
 *
 * @var string $chada_travel_delivery_provider_label
 * @var string $chada_travel_queue_provider_label
 * @var string $chada_travel_sender_identity_label
 * @var string $chada_travel_last_attempt_html Pre-escaped.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_delivery_provider_label));
assert(isset($chada_travel_queue_provider_label));
assert(isset($chada_travel_sender_identity_label));
assert(isset($chada_travel_last_attempt_html));
?>
<div class="chada-travel-section"><h2><?php esc_html_e('Delivery Status', 'chada-travel'); ?></h2>
<div class="chada-travel-detail-grid">
<dt><?php esc_html_e('Delivery provider', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_delivery_provider_label); ?></dd>
<dt><?php esc_html_e('Queue provider', 'chada-travel'); ?></dt><dd><?php echo esc_html($chada_travel_queue_provider_label); ?></dd>
<dt><?php esc_html_e('Sender identity', 'chada-travel'); ?></dt><dd><?php echo esc_html($chada_travel_sender_identity_label); ?></dd>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_last_attempt_html; ?>
</div>
