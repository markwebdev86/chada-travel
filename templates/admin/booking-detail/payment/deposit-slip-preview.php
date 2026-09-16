<?php
/**
 * The inline protected Deposit Slip preview image. SECURITY: $chada_travel_view_url must always be the protected,
 * nonce-bearing admin-post.php View URL the caller already resolved (see
 * CHADA_TRAVEL_Admin_Booking_Detail::build_bank_proof_panel_html()) - this template never receives, and must never be
 * given, the raw proof storage key/physical path. The caller (build_deposit_slip_preview_html()) decides
 * whether to render this template at all - it is only ever called for a non-empty storage key and a supported
 * image MIME type. See CHADA_TRAVEL_Admin_Booking_Detail::render_deposit_slip_preview().
 *
 * @var string $chada_travel_view_url Raw value; escaped here. The protected nonce-bearing View URL - never a storage
 *      key/physical path/unprotected URL.
 * @var string $chada_travel_alt_text Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_view_url));
assert(isset($chada_travel_alt_text));
?>
<div class="chada-travel-deposit-slip-preview-wrapper" data-chada-travel-deposit-slip-preview>
<img src="<?php echo esc_url($chada_travel_view_url); ?>" class="chada-travel-deposit-slip-preview"
    alt="<?php echo esc_attr($chada_travel_alt_text); ?>" decoding="async" loading="eager"
    referrerpolicy="same-origin" data-chada-travel-deposit-slip-preview-image>
<p class="chada-travel-deposit-slip-preview-fallback chada-travel-muted chada-travel-small" hidden
    data-chada-travel-deposit-slip-preview-fallback><?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal, matching the original source's own convention.
esc_html_e('Deposit Slip preview could not be loaded. Use View or Download to inspect the file.', 'chada-travel'); ?></p>
</div>
