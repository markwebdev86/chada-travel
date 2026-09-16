<?php
/**
 * Digital Wallet Reference No. and configured-destination detail grid. See
 * CHADA_TRAVEL_Admin_Booking_Detail::build_digital_wallet_details_html(). Values are resolved from this specific
 * payment's own immutable snapshot, never current Settings - a Settings provider-name change must never relabel
 * or rewrite an already-created payment's destination.
 *
 * @var string $chada_travel_provider_name
 * @var string $chada_travel_reference_no
 * @var string $chada_travel_destination Pre-built "account name &middot; account number" text; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_provider_name));
assert(isset($chada_travel_reference_no));
assert(isset($chada_travel_destination));
?>
<div class="chada-travel-detail-grid">
<dt><?php esc_html_e('Provider', 'chada-travel'); ?></dt><dd><?php echo esc_html($chada_travel_provider_name); ?></dd>
<dt><?php esc_html_e('Digital Wallet Reference No.', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_reference_no); ?></dd>
<dt><?php esc_html_e('Configured destination', 'chada-travel'); ?></dt><dd><?php echo esc_html($chada_travel_destination); ?></dd>
</div>
