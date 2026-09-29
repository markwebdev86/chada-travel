<?php
/**
 * Maintenance-mode screen shown instead of the checkout when Company Status is "Under Maintenance".
 * See CHADA_TRAVEL_Checkout::render_maintenance().
 *
 * @var string $chada_travel_brand_header_html
 * @var string $chada_travel_notice_html
 * @var string $chada_travel_footer_html
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_brand_header_html));
assert(isset($chada_travel_notice_html));
assert(isset($chada_travel_footer_html));
?>
<div class="chada-travel-app chada-travel-app--maintenance">

<?php echo wp_kses_post($chada_travel_brand_header_html); ?>
<main class="chada-travel-screen" role="alert">
<h2>Visa Applications Temporarily Unavailable</h2>

<?php echo wp_kses_post($chada_travel_notice_html); ?>

<?php echo wp_kses_post($chada_travel_footer_html); ?>
</main>
</div>
