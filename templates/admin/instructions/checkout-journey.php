<?php
/**
 * "Checkout Journey" tab of the Instructions screen: a plain-language walkthrough of the 5 checkout stages a
 * visitor sees when using the [chada_travel_visa_application] shortcode. See
 * CHADA_TRAVEL_Admin_Instructions::render_checkout_journey_tab(). Takes no arguments.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<p>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('This is what a visitor sees when they use the [chada_travel_visa_application] shortcode, stage by stage.', 'chada-travel'); ?>
</p>
<ol>
<li><strong><?php esc_html_e('Stage 1 - Visa Countries.', 'chada-travel'); ?></strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The applicant browses available countries/visa types. Each country can show a description, a full-view guide image (click to open it full-size, or download it directly), and its own checklist. Linking directly to a country code in the page URL opens that country automatically.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Stage 2 - Booker and Applicants.', 'chada-travel'); ?></strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The person booking enters their own details plus one or more applicants traveling with them, including a required Target Travel Date (must be at least tomorrow).', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Stage 3 - Review and Edit.', 'chada-travel'); ?></strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('A summary of everything entered so far, with the ability to go back and correct any section before payment.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Stage 4 - Payment.', 'chada-travel'); ?></strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The applicant chooses Bank Payment or Digital Wallet (whichever you have enabled in Payment Method) and completes or schedules payment.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Stage 5 - Confirmation.', 'chada-travel'); ?></strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('A confirmation screen and follow-up email with next steps (including document upload links, if required).', 'chada-travel'); ?>
</li>
</ol>
