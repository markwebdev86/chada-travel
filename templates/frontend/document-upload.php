<?php
/**
 * Frontend shell for the `chada_travel_visa_documents` shortcode. See CHADA_TRAVEL_Document_Upload::render().
 *
 * @var string $chada_travel_brand_header_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::company_brand_header().
 * @var string $chada_travel_token Raw token value from the request query string; escaped here.
 * @var string $chada_travel_actions_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::actions().
 * @var string $chada_travel_noscript_notice_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::notice().
 * @var string $chada_travel_contact_html Pre-escaped HTML from CHADA_TRAVEL_View_Components::company_contact_block();
 *      '' when nothing is configured.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_brand_header_html));
assert(isset($chada_travel_token));
assert(isset($chada_travel_actions_html));
assert(isset($chada_travel_noscript_notice_html));
assert(isset($chada_travel_contact_html));
?>
<div class="chada-travel-app chada-travel-documents-app">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_brand_header_html; ?>
<main class="chada-travel-screen"><h2>Documents Checklist</h2>
<input type="hidden" id="chada-travel-documents-token" value="<?php echo esc_attr($chada_travel_token); ?>">
<div id="chada-travel-documents-list" data-chada-travel-documents-list></div>
<p class="chada-travel-screen-status" data-chada-travel-documents-live-status aria-live="polite"></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_actions_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<noscript><?php echo $chada_travel_noscript_notice_html; ?></noscript>
</main>
<?php if ($chada_travel_contact_html !== ''): ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<footer class="chada-travel-app-footer"><?php echo $chada_travel_contact_html; ?></footer>
<?php endif; ?>
</div>
