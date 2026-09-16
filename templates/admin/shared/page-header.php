<?php
/**
 * Shared "<div class='wrap'><h1>Title</h1>[badge]" opening used by every CHADA_TRAVEL_Admin_* list screen. The
 * caller closes the wrap div itself once its own body (which may early-return, e.g. an empty-state message)
 * has finished, so this template only ever renders the opening half.
 *
 * @var string $chada_travel_title Raw value; escaped here.
 * @var string $chada_travel_badge_html Pre-escaped HTML shown after the heading (e.g. a total count badge); '' when
 *      this screen has no badge.
 * @var string $chada_travel_tour_anchor Optional data-chada-travel-tour anchor value; '' (the default when the caller omits
 *      it) renders no attribute at all. Only the Dashboard currently passes one.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_title));
assert(isset($chada_travel_badge_html));
$chada_travel_tour_anchor = isset($chada_travel_tour_anchor) ? (string) $chada_travel_tour_anchor : '';
?>
<div class="wrap chada-travel-admin"><h1 class="wp-heading-inline"
<?php if ($chada_travel_tour_anchor !== ''): ?>
 data-chada-travel-tour="<?php echo esc_attr($chada_travel_tour_anchor); ?>" tabindex="-1"
<?php endif; ?>
><?php echo esc_html($chada_travel_title); ?></h1>
<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped by the caller, see the _html naming convention documented in CHADA_TRAVEL_Template. ?>
<?php echo $chada_travel_badge_html; ?>
