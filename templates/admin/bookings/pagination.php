<?php
/**
 * See CHADA_TRAVEL_Admin_Bookings::render_pagination(). Always shows the "displaying-num" count, even for a single
 * page - unlike Payment Review's pagination, which hides itself entirely at <=1 page. The two are genuinely
 * different (this one always shows a count; Payment Review's never does), so they stay separate templates
 * rather than being unified into one shared partial.
 *
 * @var string $chada_travel_count_text Raw; escaped here (already-pluralized "N Visa Booking(s)" text).
 * @var list<array{page: int, url: string, is_current: bool}> $chada_travel_pages
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_count_text));
assert(isset($chada_travel_pages));
?>
<p class="tablenav">
<span class="displaying-num"><?php echo esc_html($chada_travel_count_text); ?></span>
<?php foreach ($chada_travel_pages as $chada_travel_page): ?>
<?php if ($chada_travel_page['is_current']): ?>
<strong><?php echo (int) $chada_travel_page['page']; ?></strong>
<?php else: ?>
<a href="<?php echo esc_url($chada_travel_page['url']); ?>"><?php echo (int) $chada_travel_page['page']; ?></a>
<?php endif; ?>
<?php endforeach; ?>
</p>
