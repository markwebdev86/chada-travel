<?php
/**
 * View Page / Edit Page links for one assigned policy page - unlike pages-links/page-links.php, NOT
 * status-gated: offered whenever a page id is assigned at all (a nonexistent/trashed page simply yields no
 * permalink/edit link, so no separate status check is needed here). See
 * CHADA_TRAVEL_Admin_Settings::build_policy_page_links_html(). Only rendered at all when at least one link applies -
 * the caller does not invoke this template otherwise.
 *
 * @var string $chada_travel_links_html Pre-escaped (each anchor is built from individually escaped url/label parts).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_links_html));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<p><?php echo $chada_travel_links_html; ?></p>
