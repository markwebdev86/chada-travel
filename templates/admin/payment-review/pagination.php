<?php
/**
 * See CHADA_TRAVEL_Admin_Payment_Review::render_pagination(). The caller only invokes this template when there is
 * more than one page - unlike Bookings' pagination, this one never shows a "displaying-num" count and is
 * skipped entirely at <=1 page. The two are genuinely different, so they stay separate templates rather than
 * being unified into one shared partial.
 *
 * @var list<array{page: int, url: string, is_current: bool}> $chada_travel_pages
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_pages));
?>
<p class="tablenav">
<?php foreach ($chada_travel_pages as $chada_travel_page): ?>
<?php if ($chada_travel_page['is_current']): ?>
<strong><?php echo (int) $chada_travel_page['page']; ?></strong>
<?php else: ?>
<a href="<?php echo esc_url($chada_travel_page['url']); ?>"><?php echo (int) $chada_travel_page['page']; ?></a>
<?php endif; ?>
<?php endforeach; ?>
</p>
