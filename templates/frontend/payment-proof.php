<?php
/**
 * Frontend shell for the `chada_travel_payment_proof` shortcode. See CHADA_TRAVEL_Payment_Proof::render().
 *
 * @var string $chada_travel_brand_header_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::company_brand_header().
 * @var string $chada_travel_status_bar_html Pre-escaped HTML: the Awaiting Proof/Verification badge status bar.
 * @var string $chada_travel_main_aside_html Pre-escaped HTML: instructions panel + submission form panel.
 * @var string $chada_travel_after_submit_notice_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::notice().
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 * @var string $chada_travel_noscript_notice_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::notice().
 * @var string $chada_travel_contact_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::company_contact_block();
 *      '' when nothing is configured.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_brand_header_html));
assert(isset($chada_travel_status_bar_html));
assert(isset($chada_travel_main_aside_html));
assert(isset($chada_travel_after_submit_notice_html));
assert(isset($chada_travel_actions_html));
assert(isset($chada_travel_noscript_notice_html));
assert(isset($chada_travel_contact_html));
?>
<div class="chada-travel-app chada-travel-proof-app">

<?php echo wp_kses_post($chada_travel_brand_header_html); ?>
<main class="chada-travel-screen"><h2>Upload Bank Payment Proof</h2>

<?php echo wp_kses_post($chada_travel_status_bar_html); ?>

<?php echo wp_kses_post($chada_travel_main_aside_html); ?>

<?php echo wp_kses_post($chada_travel_after_submit_notice_html); ?>
<p class="chada-travel-status-note">Unique Transaction ID is generated only after administrator confirmation.</p>
<p class="chada-travel-screen-status" data-chada-travel-proof-live-status aria-live="polite"></p>

<?php echo wp_kses_post($chada_travel_actions_html); ?>

<noscript><?php echo wp_kses_post($chada_travel_noscript_notice_html); ?></noscript>
</main>
<?php if ($chada_travel_contact_html !== ''): ?>

<footer class="chada-travel-app-footer"><?php echo wp_kses_post($chada_travel_contact_html); ?></footer>
<?php endif; ?>
</div>
