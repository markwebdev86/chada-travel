<?php
/** Tours pagination. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var list<array{page: int, url: string, is_current: bool}>|null $chada_travel_pages Raw page links; escaped here. */

assert(isset($chada_travel_pages));
?>
<?php if (count($chada_travel_pages) > 1): ?><div class="tablenav"><div class="tablenav-pages"><?php foreach ($chada_travel_pages as $chada_travel_page): ?><a class="page-numbers<?php echo $chada_travel_page['is_current'] ? ' current' : ''; ?>" href="<?php echo esc_url($chada_travel_page['url']); ?>"><?php echo (int) $chada_travel_page['page']; ?></a> <?php endforeach; ?></div></div><?php endif; ?>
