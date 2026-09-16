<?php
/**
 * Numbered pagination for the Activity tab, only rendered when more than one page exists. Mirrors the
 * established booking-activity pagination structure for consistency; kept as a separate template because the
 * query-arg shape differs (`activity_page` here vs. `paged` there). See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_activity_tab().
 *
 * @var list<array{page: int, url: string, is_current: bool}> $chada_travel_page_links Raw values; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_page_links));
?>
<p class="tablenav">
<?php foreach ($chada_travel_page_links as $chada_travel_link): ?>
<?php if ($chada_travel_link['is_current']): ?>
<strong><?php echo (int) $chada_travel_link['page']; ?></strong>
<?php else: ?>
<a href="<?php echo esc_url($chada_travel_link['url']); ?>"><?php echo (int) $chada_travel_link['page']; ?></a>
<?php endif; ?>
<?php endforeach; ?>
</p>
