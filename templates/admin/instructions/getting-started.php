<?php
/**
 * "Getting Started" tab of the Instructions screen: the recommended first-time setup order across Settings
 * tabs, flagging the two tabs that gate whether the checkout displays at all. See
 * CHADA_TRAVEL_Admin_Instructions::render_getting_started_tab().
 *
 * @var string $chada_travel_general_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_policies_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_payment_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_pages_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_email_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_documents_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_workflow_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_privacy_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_tours_url '' when the current user cannot manage Tours.
 * @var string $chada_travel_visa_countries_settings_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_tour_destinations_url '' when the current user cannot manage Tours.
 * @var string $chada_travel_tour_types_url '' when the current user cannot manage Tour Types or Pro is inactive.
 * @var string $chada_travel_wizard_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_tour_url '' when the current user cannot manage Settings.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

assert(isset($chada_travel_general_url));
assert(isset($chada_travel_policies_url));
assert(isset($chada_travel_payment_url));
assert(isset($chada_travel_pages_url));
assert(isset($chada_travel_email_url));
assert(isset($chada_travel_documents_url));
assert(isset($chada_travel_workflow_url));
assert(isset($chada_travel_privacy_url));
assert(isset($chada_travel_tours_url));
assert(isset($chada_travel_tour_destinations_url));
assert(isset($chada_travel_tour_types_url));
assert(isset($chada_travel_wizard_url));
assert(isset($chada_travel_tour_url));
?>
<p>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Follow these steps in order the first time you configure the plugin. Two of them are hard requirements - the checkout will not display to visitors at all until they are done (marked below).', 'chada-travel'); ?>
</p>
<?php if ($chada_travel_wizard_url !== ''): ?>
<p>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('A guided Setup Wizard walks through this same checklist interactively - it opens automatically the first time the plugin is activated, and can be reopened anytime from here or from the Settings screen header.', 'chada-travel'); ?>
<a href="<?php echo esc_url($chada_travel_wizard_url); ?>"><?php esc_html_e('Open Setup Wizard', 'chada-travel'); ?></a>
</p>
<?php endif; ?>
<ol>
<li><strong><?php esc_html_e('General', 'chada-travel'); ?></strong>
<?php if ($chada_travel_general_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_general_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash; <?php esc_html_e('Set Company Status to Active.', 'chada-travel'); ?>
<strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('(Required - while Company Status is Under Maintenance, the checkout page shows a maintenance notice instead of the application form.)', 'chada-travel'); ?>
</strong>
</li>
<li><strong><?php esc_html_e('Policies & Consent', 'chada-travel'); ?></strong>
<?php if ($chada_travel_policies_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_policies_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The Privacy Policy, Terms and Conditions, and Cancellation and Refund Policy pages are created automatically with generic starter content - review and customize them, or select different pages of your own.', 'chada-travel'); ?>
<strong>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('(Required - the checkout shows a policies-unavailable notice instead of the form until this is complete.)', 'chada-travel'); ?>
</strong>
</li>
<li><strong><?php esc_html_e('Payment Method', 'chada-travel'); ?></strong>
<?php if ($chada_travel_payment_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_payment_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Set up at least one payment option: a Company Bank Account and/or a Digital Wallet with a QR code.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Pages & Links', 'chada-travel'); ?></strong>
<?php if ($chada_travel_pages_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_pages_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('A WordPress Page for each workflow is created automatically with the required shortcode already in place - review it here, or select a different page of your own, so the plugin\'s own emails and links point to the right page.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Email', 'chada-travel'); ?></strong>
<?php if ($chada_travel_email_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_email_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash; <?php esc_html_e('Review the notification templates sent to applicants and staff.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Documents & Uploads', 'chada-travel'); ?></strong>
<?php if ($chada_travel_documents_url !== ''): ?>
(<a href="<?php echo esc_url($chada_travel_documents_url); ?>"><?php esc_html_e('open tab', 'chada-travel'); ?></a>)
<?php endif; ?>
&mdash; <?php esc_html_e('Set which documents are required and configure upload rules.', 'chada-travel'); ?>
</li>
<?php if ($chada_travel_tours_url !== ''): ?><li><strong><a href="<?php echo esc_url($chada_travel_tours_url); ?>"><?php esc_html_e('Tours', 'chada-travel'); ?></a></strong>
 &mdash; <?php esc_html_e('Create Tours with multiple Travel Date ranges, Featured Images, Downloadable Files, destinations, types, Featured state, filters, and duplicate-ready records.', 'chada-travel'); ?></li><?php endif; ?>
<?php if (isset($chada_travel_visa_countries_settings_url) && $chada_travel_visa_countries_settings_url !== ''): ?><li><strong><a href="<?php echo esc_url($chada_travel_visa_countries_settings_url); ?>"><?php esc_html_e('Visa Countries Settings', 'chada-travel'); ?></a></strong>
 &mdash; <?php esc_html_e('Configure the source-size limit and final dimensions for Visa Country Step-by-Step Guide images.', 'chada-travel'); ?></li><?php endif; ?>
<?php if ($chada_travel_tour_destinations_url !== ''): ?><li><strong><a href="<?php echo esc_url($chada_travel_tour_destinations_url); ?>"><?php esc_html_e('Tour Destinations', 'chada-travel'); ?></a></strong>
&mdash; <?php esc_html_e('Maintain reusable destination records, review how many Tours use each one, and permanently delete only archived unused records.', 'chada-travel'); ?></li><?php endif; ?>
<?php if ($chada_travel_tour_types_url !== ''): ?><li><strong><a href="<?php echo esc_url($chada_travel_tour_types_url); ?>"><?php esc_html_e('Tour Types', 'chada-travel'); ?></a></strong>
&mdash; <?php esc_html_e('Maintain reusable type records, review Tour usage counts, and permanently delete only archived unused records.', 'chada-travel'); ?></li><?php endif; ?>
<?php if ($chada_travel_tour_types_url === ''): ?><li><strong><?php esc_html_e('Tour Types', 'chada-travel'); ?></strong>
&mdash; <?php esc_html_e('Use the supplied built-in Tour Types when creating Tours. Custom Tour Type management is available only through the active Pro extension.', 'chada-travel'); ?></li><?php endif; ?>
</ol>
<?php if ($chada_travel_tour_url !== ''): ?>
<p>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Use the Instructions tabs and the standard WordPress admin menu to review each workflow without changing data.', 'chada-travel'); ?>
</p>
<p class="submit">
<a class="button button-primary" href="<?php echo esc_url($chada_travel_tour_url); ?>"
    title="<?php echo esc_attr__('Start an interactive walkthrough of this plugin', 'chada-travel'); ?>">
<?php esc_html_e('Review Instructions', 'chada-travel'); ?>
</a>
</p>
<?php endif; ?>
