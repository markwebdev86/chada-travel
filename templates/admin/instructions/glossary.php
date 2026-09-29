<?php
/**
 * "Glossary" tab of the Instructions screen: core data-model terms, matching the chada_travel_orders /
 * chada_travel_applicants / chada_travel_applications table structure (see CHADA_TRAVEL_Order_Repository). See
 * CHADA_TRAVEL_Admin_Instructions::render_glossary_tab(). Takes no arguments.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded
?>
<dl>
<dt><strong><?php esc_html_e('Booking', 'chada-travel'); ?></strong></dt>
<dd>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The overall record created when someone starts the checkout. Holds the payment/transaction details and one or more Applicants.', 'chada-travel'); ?>
</dd>
<dt><strong><?php esc_html_e('Applicant', 'chada-travel'); ?></strong></dt>
<dd>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('One traveler included in a Booking, with their own personal details and Application record.', 'chada-travel'); ?>
</dd>
<dt><strong><?php esc_html_e('Application', 'chada-travel'); ?></strong></dt>
<dd>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The specific visa application tied to one Applicant within a Booking (country, requirements, documents, status).', 'chada-travel'); ?>
</dd>
<dt><strong><?php esc_html_e('Booker', 'chada-travel'); ?></strong></dt>
<dd>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The person who filled out and submitted the checkout. May also be one of the Applicants, or may be booking only on behalf of others.', 'chada-travel'); ?>
</dd>
<dt><strong><?php esc_html_e('Tour', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('An administrator-managed travel product with descriptive content, price, currency snapshot, one or more Travel Date ranges, taxonomy assignments, and optional public Downloadable Files.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Featured Tour', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('A Tour whose independent Featured flag is checked. Active records display Active (Featured) and can be selected with the Featured filter; active Featured Tours appear in the public shortcode and link to their Tour detail URL.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Travel Date Range', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('A child record belonging to one Tour that stores a start date and end date. Values must be valid ISO dates; ranges may overlap or be reverse-ordered.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Tour Destination / Tour Type', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('Reusable Tour taxonomy records assigned through checkboxes on a Tour. They are managed in separate administrator grids and are not WordPress Post Categories.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Downloadable File', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('A public WordPress Media Library attachment related to a Tour. Tours can include any number of PDF, JPG/JPEG, or PNG files within the configured upload restrictions.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Tour Permalink', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('The required editable SEO-friendly slug used by the public Tour detail URL. It is generated from the Tour Name when blank, normalized to hyphenated lowercase text, and must be unique.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Forced Download', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('A controlled file response that sends Downloadable Files as attachments instead of opening images or PDFs in the browser. Public downloads require an Active Tour relationship; administrator downloads require capability and nonce checks.', 'chada-travel'); ?></dd>
<dt><strong><?php esc_html_e('Archived Deletion', 'chada-travel'); ?></strong></dt>
<dd><?php esc_html_e('A guarded permanent deletion available only for archived Tours or archived, unused Tour taxonomy records. It removes plugin relationships but never shared Media Library attachments.', 'chada-travel'); ?></dd>
</dl>
