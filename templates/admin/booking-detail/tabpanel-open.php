<?php
/**
 * The opening <section role="tabpanel"> tag shared by all four Booking Detail tabs. The caller renders that
 * tab's own content immediately after this call and closes the </section> itself (mirrors
 * templates/admin/shared/page-header.php's "opening half only" convention) because the section's body comes
 * from a callable, not a value this template could receive. See CHADA_TRAVEL_Admin_Booking_Detail::render_tabpanel().
 *
 * @var string $chada_travel_tab_key Raw value; escaped here.
 * @var bool   $chada_travel_visible When false, the panel is rendered with the `hidden` attribute.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_tab_key));
assert(isset($chada_travel_visible));
// Only Payment needs a panel-level anchor: its content is one of eight mutually-exclusive dynamic states, so the
// tab panel itself - present regardless of which state renders - is the one stable, always-present highlight
// target. The other three tabs anchor their own specific inner sections instead (see their own templates).
$chada_travel_tour_anchor = $chada_travel_tab_key === 'payment' ? ' data-chada-travel-tour="booking-payment-tab"' : '';
?>
<section class="chada-travel-tabpanel" role="tabpanel" id="chada-travel-panel-<?php
echo esc_attr($chada_travel_tab_key); ?>" aria-labelledby="chada-travel-tab-<?php
echo esc_attr($chada_travel_tab_key);
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from a code-owned fixed literal, not user data.
?>"<?php echo $chada_travel_visible ? '' : ' hidden'; echo $chada_travel_tour_anchor; ?>>
