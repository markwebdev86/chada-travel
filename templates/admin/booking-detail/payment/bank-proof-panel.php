<?php
/**
 * The Deposit Slip controls (View/Download), the inline protected preview when applicable, and the
 * configured-destination detail grid, in that order - for any Bank payment that has a proof on file, regardless
 * of payment status. Reused by the actionable state and, via build_historical_bank_proof_html(), by the
 * Rejected/Paid/Refunded states. See CHADA_TRAVEL_Admin_Booking_Detail::render_bank_proof_panel(). The capability
 * check is resolved in the PHP builder, never here.
 *
 * @var bool   $chada_travel_has_proof_and_can_verify
 * @var string $chada_travel_view_url Raw value; escaped here. Only meaningful when $chada_travel_has_proof_and_can_verify.
 * @var string $chada_travel_download_url Raw value; escaped here. Only meaningful when $chada_travel_has_proof_and_can_verify.
 * @var string $chada_travel_deposit_slip_preview_html Pre-rendered HTML; '' when the proof is missing, unsupported
 *      MIME, or the viewer lacks capability.
 * @var string $chada_travel_destination_label Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_has_proof_and_can_verify));
assert(isset($chada_travel_view_url));
assert(isset($chada_travel_download_url));
assert(isset($chada_travel_deposit_slip_preview_html));
assert(isset($chada_travel_destination_label));
?>
<?php if ($chada_travel_has_proof_and_can_verify): ?>
<p><strong><?php esc_html_e('Deposit Slip', 'chada-travel'); ?></strong><br>
<a class="button button-small" href="<?php echo esc_url($chada_travel_view_url); ?>"><?php esc_html_e('View', 'chada-travel'); ?></a>
<a class="button button-small" href="<?php
echo esc_url($chada_travel_download_url); ?>"><?php esc_html_e('Download', 'chada-travel'); ?></a></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_deposit_slip_preview_html; ?>
<?php else: ?>
<p><?php esc_html_e('No Deposit Slip on file.', 'chada-travel'); ?></p>
<?php endif; ?>
<div class="chada-travel-detail-grid"><dt><?php
esc_html_e('Configured destination', 'chada-travel'); ?></dt><dd><?php
echo esc_html($chada_travel_destination_label); ?></dd></div>
