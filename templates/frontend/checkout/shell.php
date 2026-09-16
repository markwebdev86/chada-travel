<?php
/**
 * Outer shell for the `chada_travel_visa_application` shortcode: brand header, progress bar, Stages 1-5, confirmation
 * dialog, and footer. See CHADA_TRAVEL_Checkout::render().
 *
 * @var string $chada_travel_brand_header_html
 * @var string $chada_travel_progress_html
 * @var string $chada_travel_stage_one_html
 * @var string $chada_travel_stage_two_html
 * @var string $chada_travel_stage_three_html
 * @var string $chada_travel_stage_four_html
 * @var string $chada_travel_stage_five_html
 * @var string $chada_travel_confirmation_dialog_html
 * @var string $chada_travel_noscript_notice_html
 * @var string $chada_travel_footer_html
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_brand_header_html));
assert(isset($chada_travel_progress_html));
assert(isset($chada_travel_stage_one_html));
assert(isset($chada_travel_stage_two_html));
assert(isset($chada_travel_stage_three_html));
assert(isset($chada_travel_stage_four_html));
assert(isset($chada_travel_stage_five_html));
assert(isset($chada_travel_confirmation_dialog_html));
assert(isset($chada_travel_noscript_notice_html));
assert(isset($chada_travel_footer_html));
?>
<div class="chada-travel-app">
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_brand_header_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_progress_html; ?>
<main>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_stage_one_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_stage_two_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_stage_three_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_stage_four_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_stage_five_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_confirmation_dialog_html; ?>
<p class="chada-travel-screen-status" data-chada-travel-live-status aria-live="polite"></p>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<noscript><?php echo $chada_travel_noscript_notice_html; ?></noscript>
</main>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_footer_html; ?>
</div>
