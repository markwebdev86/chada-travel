<?php
/**
 * View Page / Edit Page links for one assigned Pages & Links page, status-gated (see
 * CHADA_TRAVEL_Admin_Settings::build_page_links_html() - the View link only appears when
 * CHADA_TRAVEL_Page_Settings::can_offer_view_link() allows it for the current status, and the Edit link is withheld
 * entirely when the status is STATUS_MISSING). Only rendered at all when at least one link applies - the caller
 * does not invoke this template otherwise.
 *
 * @var string $chada_travel_links_html Pre-escaped (each anchor is built from individually escaped url/label parts).
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_links_html));
?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<p><?php echo $chada_travel_links_html; ?></p>
