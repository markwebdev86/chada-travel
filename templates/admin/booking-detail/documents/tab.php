<?php
/**
 * The Applications & Documents tab: an early "No applications on this booking." state, or an application-picker
 * tablist (a SECOND, narrower tablist nested inside this tab panel - distinct from the screen-level tablist
 * converted in Phase 5.5) plus one tabpanel per application, followed by the booking-level Upload Link panel
 * (shown once, not per-application). See CHADA_TRAVEL_Admin_Booking_Detail::render_documents_tab().
 *
 * @var bool $chada_travel_has_applications
 * @var list<array{key: string, label: string, href: string, selected: bool}> $chada_travel_tabs Raw values; escaped
 *      here. Only meaningful when $chada_travel_has_applications.
 * @var string $chada_travel_panels_html Pre-built HTML blob of the per-application <section> tabpanels (each already
 *      containing its own escaped content). Only meaningful when $chada_travel_has_applications.
 * @var string $chada_travel_upload_link_panel_html Pre-rendered HTML. Only meaningful when $chada_travel_has_applications.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_has_applications));
assert(isset($chada_travel_tabs));
assert(isset($chada_travel_panels_html));
assert(isset($chada_travel_upload_link_panel_html));
?>
<?php if (!$chada_travel_has_applications): ?>
<p><?php esc_html_e('No applications on this booking.', 'chada-travel'); ?></p>
<?php else: ?>
<div class="chada-travel-application-picker chada-travel-tablist" role="tablist"
    aria-label="<?php echo esc_attr__('Applications', 'chada-travel'); ?>" data-chada-travel-tablist="applications"
    data-chada-travel-tour="booking-documents-tabs">
<?php foreach ($chada_travel_tabs as $chada_travel_tab): ?>
<a class="chada-travel-tab" role="tab" id="chada-travel-apptab-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    href="<?php echo esc_url($chada_travel_tab['href']); ?>"
    aria-selected="<?php echo $chada_travel_tab['selected'] ? 'true' : 'false'; ?>"
    aria-controls="chada-travel-apppanel-<?php echo esc_attr($chada_travel_tab['key']); ?>"
    tabindex="<?php echo $chada_travel_tab['selected'] ? '0' : '-1'; ?>"
    data-chada-travel-href="<?php echo esc_attr($chada_travel_tab['href']); ?>">
<?php echo esc_html($chada_travel_tab['label']); ?>
</a>
<?php endforeach; ?>
</div>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from escaped parts, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_panels_html; ?>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered by CHADA_TRAVEL_Template::render(), see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_upload_link_panel_html; ?>
<?php endif; ?>
