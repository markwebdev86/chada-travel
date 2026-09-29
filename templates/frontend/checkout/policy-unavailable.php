<?php
/**
 * Policy-bundle-not-ready screen shown instead of the checkout. See CHADA_TRAVEL_Checkout::render_policy_unavailable().
 *
 * @var string $chada_travel_brand_header_html
 * @var string $chada_travel_notice_html
 * @var string $chada_travel_support_email Raw value; escaped here. '' when not configured (no contact line then).
 * @var string $chada_travel_footer_html
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_brand_header_html));
assert(isset($chada_travel_notice_html));
assert(isset($chada_travel_support_email));
assert(isset($chada_travel_footer_html));

$chada_travel_contact_line_html = $chada_travel_support_email !== '' ? '<p>Contact <a href="mailto:'
    . esc_attr($chada_travel_support_email) . '">' . esc_html($chada_travel_support_email) . '</a> for assistance.</p>' : '';
?>
<div class="chada-travel-app chada-travel-app--maintenance">

<?php echo wp_kses_post($chada_travel_brand_header_html); ?>
<main class="chada-travel-screen" role="alert">
<h2>Visa Applications Temporarily Unavailable</h2>

<?php echo wp_kses_post($chada_travel_notice_html); ?>

<?php echo wp_kses_post($chada_travel_contact_line_html); ?>

<?php echo wp_kses_post($chada_travel_footer_html); ?>
</main>
</div>
