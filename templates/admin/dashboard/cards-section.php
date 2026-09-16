<?php
/**
 * Summary-cards section wrap. See CHADA_TRAVEL_Admin_Dashboard::render_cards_section(). Per-card markup is rendered
 * by templates/admin/dashboard/card.php; the caller has already built each card's pre-escaped HTML.
 *
 * @var string $chada_travel_summary_label Translated, screen-reader-only "Summary" heading.
 * @var list<string> $chada_travel_card_html_list Pre-escaped HTML for each card, in display order.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_summary_label));
assert(isset($chada_travel_card_html_list));
?>
<h2 class="screen-reader-text"><?php echo esc_html($chada_travel_summary_label); ?></h2>
<div class="chada-travel-dashboard-cards" data-chada-travel-tour="dashboard-summary">
<?php foreach ($chada_travel_card_html_list as $chada_travel_card_html): ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_card_html; ?>
<?php endforeach; ?>
</div>
