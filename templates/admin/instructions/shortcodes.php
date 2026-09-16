<?php
/**
 * "Shortcodes" tab of the Instructions screen: reference table for the plugin's front-end shortcodes.
 * See CHADA_TRAVEL_Admin_Instructions::render_shortcodes_tab().
 *
 * @var string $chada_travel_pages_url '' when the current user cannot manage Settings.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_pages_url));
$chada_travel_render_copy_shortcode = static function (string $chada_travel_shortcode): void { ?>
<span class="chada-travel-shortcode-copy-control">
<code><?php echo esc_html($chada_travel_shortcode); ?></code>
<button type="button" class="button-link chada-travel-copy-shortcode"
data-chada-travel-copy-shortcode="<?php echo esc_attr($chada_travel_shortcode); ?>"
aria-label="<?php esc_attr_e('Copy shortcode', 'chada-travel'); ?>">
<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
<span class="screen-reader-text"><?php esc_html_e('Copy shortcode', 'chada-travel'); ?></span></button>
<span class="chada-travel-copy-feedback" role="status" aria-live="polite"></span></span>
<?php };
?>
<p>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Paste these into any WordPress Page, block, or supported page-builder shortcode/code module. Featured Tours accepts design, rows, and columns attributes; its output uses compact semantic markup with chada-travel-prefixed classes and data attributes. Use the copy icon beside any shortcode to place its exact text on the clipboard. The other shortcodes use the tag alone.', 'chada-travel'); ?>
</p>
<table class="wp-list-table widefat fixed striped chada-travel-responsive-table">
<thead><tr>
<th scope="col"><?php esc_html_e('Shortcode', 'chada-travel'); ?></th>
<th scope="col"><?php esc_html_e('What it displays', 'chada-travel'); ?></th>
<th scope="col"><?php esc_html_e('Where it is used', 'chada-travel'); ?></th>
</tr></thead>
<tbody>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_visa_application]'); ?></td>
<td>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The full visa application checkout - all 5 stages, from choosing a country through payment and confirmation.', 'chada-travel'); ?>
</td>
<td><?php esc_html_e('Your main "Apply Now" page.', 'chada-travel'); ?> <?php if ($chada_travel_pages_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_pages_url); ?>"><?php esc_html_e('Set it under Pages & Links.', 'chada-travel'); ?></a>
<?php endif; ?>
</td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_payment_proof]'); ?></td>
<td>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('The Bank Deposit Slip upload form applicants use to submit proof of a bank payment.', 'chada-travel'); ?>
</td>
<td>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Applicants reach this via a link in their booking email - you do not need to link to it from your site menu.', 'chada-travel'); ?>
</td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_visa_documents]'); ?></td>
<td><?php esc_html_e('The visa document upload checklist for a specific application.', 'chada-travel'); ?></td>
<td><?php esc_html_e('Also reached via an emailed link, not typically linked from your site menu.', 'chada-travel'); ?></td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_tour_search_form]'); ?></td>
<td><?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
esc_html_e('A public Tour Name/Description search form with Tour Destination and Tour Type filters. The namespaced UI uses only white, black, and #333.', 'chada-travel');
?></td>
<td><?php
esc_html_e(
    'Place on Home or public content; visitors are sent to the configured Tours Page.',
    'chada-travel'
);
?></td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_tour_search_results]'); ?></td>
<td><?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
esc_html_e('A paginated image-left list of matching Active Tours, including non-Featured Tours. Its semantic, builder-safe UI uses only white, black, and #333 while preserving Featured Image colors.', 'chada-travel');
?></td>
<td><?php
esc_html_e(
    'Place on the configured Tours Page; activation creates /tours/ if needed.',
    'chada-travel'
);
?></td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours_sidebar rows="3"]'); ?></td>
<td><?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
esc_html_e('A compact single-column list of Active Featured Tours showing a linked image, Tour Name, one Destination (linked to its archive page), and days-only duration.', 'chada-travel');
?></td>
<td><?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
esc_html_e('Place in a sidebar or other public builder section. Rows may be set from 1 to 10; the UI uses white, black, and #333.', 'chada-travel');
?></td>
</tr>
<tr>
<td><?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours design="bg-image" rows="1" columns="3"]'); ?><br>
<?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours design="bg-image" rows="2" columns="3"]'); ?><br>
<?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours design="top-image" rows="1" columns="3"]'); ?><br>
<?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours design="top-image" rows="2" columns="3"]'); ?><br>
<?php $chada_travel_render_copy_shortcode('[chada_travel_featured_tours design="left-image" rows="3"]'); ?></td>
<td>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Active Featured Tours in responsive card layouts. The bg-image design shows Featured Image, Tour Name, Duration, Tour Code, Tour Destinations, Price, and View More; top-image shows Featured Image, Tour Name, Tour Destinations, Duration, and View Details; left-image shows Featured Image, Tour Name, Tour Code, a plain-text Description excerpt up to 180 characters, Duration, Tour Destinations, Price, and Explore. Each action links to the public Tour detail URL, and each Tour Destination shown links to its own archive page.', 'chada-travel'); ?>
</td>
<td>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('A Home page or other public content section. Use design="bg-image" for the original cards, design="top-image" for image-top cards, or design="left-image" rows="3" for three horizontal cards. Omit design to keep bg-image. The left-image layout stacks on mobile.', 'chada-travel'); ?>
</td>
</tr>
</tbody>
</table>
<p class="chada-travel-muted chada-travel-small">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Tip: the Featured Tours shortcode can be placed in an existing Home page section. Its stylesheet is mobile-first and scoped for page builders; the action controls are styled links to active Tour detail pages. Tour detail tabs use one delegated, namespaced public script. The plugin does not automatically change Home page content or create sample Tour records.', 'chada-travel'); ?>
</p>
<p class="chada-travel-muted chada-travel-small">
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Every active Tour Destination and Tour Type also has its own automatic archive page - no shortcode needed - at /tours/destination/<slug>/ and /tours/type/<slug>/, showing the same search form and alphabetical results as the Tours Page pre-filtered to that one Destination or Type. Link to these directly from a menu or button to let visitors browse by Destination or Type; an unknown or archived slug shows a normal 404.', 'chada-travel'); ?>
</p>
