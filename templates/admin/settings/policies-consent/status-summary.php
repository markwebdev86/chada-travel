<?php
/**
 * Policy bundle status grid: overall bundle, Policy Version, Policy Effective Date, then one row per policy. See
 * CHADA_TRAVEL_Admin_Settings::build_policies_status_summary_html().
 *
 * @var string $chada_travel_rows_html Pre-escaped <dt>/<dd> pairs.
 * @var bool   $chada_travel_bundle_ready
 * @var string $chada_travel_not_ready_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_bundle_ready));
assert(isset($chada_travel_not_ready_notice));
?>
<div class="chada-travel-section"><h2><?php esc_html_e('Policy bundle status', 'chada-travel'); ?></h2>
<div class="chada-travel-detail-grid">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_rows_html; ?>
</div>
<?php if (!$chada_travel_bundle_ready) : ?>
<p class="description"><?php echo esc_html($chada_travel_not_ready_notice); ?></p>
<?php endif; ?>
</div>
