<?php
/**
 * Method availability status grid. See CHADA_TRAVEL_Admin_Settings::build_status_summary_html().
 *
 * @var string $chada_travel_rows_html Pre-escaped <dt>/<dd> pairs, one per payment method.
 * @var bool   $chada_travel_has_any_ready
 * @var string $chada_travel_none_ready_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_has_any_ready));
assert(isset($chada_travel_none_ready_notice));
?>
<div class="chada-travel-section"><h2><?php esc_html_e('Method availability', 'chada-travel'); ?></h2>
<div class="chada-travel-detail-grid">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
</div>
<?php if (!$chada_travel_has_any_ready) : ?>
<p class="description"><?php echo esc_html($chada_travel_none_ready_notice); ?></p>
<?php endif; ?>
</div>
