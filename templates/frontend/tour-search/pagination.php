<?php
/** Pagination for Tour Search results. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var int|null $chada_travel_current_page */
/** @var int|null $chada_travel_pages */
/** @var string|null $chada_travel_search */
/** @var int|null $chada_travel_destination_id */
/** @var int|null $chada_travel_type_id */
assert(isset(
    $chada_travel_current_page,
    $chada_travel_pages,
    $chada_travel_search,
    $chada_travel_destination_id,
    $chada_travel_type_id
));
?>
<nav class="chada-travel-tour-search-results__pagination"
aria-label="<?php esc_attr_e('Tour Search Results pages', 'chada-travel'); ?>">
<?php for ($chada_travel_index = 1; $chada_travel_index <= $chada_travel_pages; $chada_travel_index++): ?>
<?php $chada_travel_url = CHADA_TRAVEL_Tour_Search::build_results_url([
    CHADA_TRAVEL_Tour_Search::SEARCH_PARAM => $chada_travel_search,
    CHADA_TRAVEL_Tour_Search::DESTINATION_PARAM => $chada_travel_destination_id,
    CHADA_TRAVEL_Tour_Search::TYPE_PARAM => $chada_travel_type_id,
    CHADA_TRAVEL_Tour_Search::PAGE_PARAM => $chada_travel_index,
]); ?>
<?php if ($chada_travel_index === $chada_travel_current_page): ?>
<span class="chada-travel-tour-search-results__page is-current" aria-current="page">
<?php echo esc_html((string) $chada_travel_index); ?></span>
<?php else: ?>
<a class="chada-travel-tour-search-results__page" href="<?php echo esc_url($chada_travel_url); ?>">
<?php echo esc_html((string) $chada_travel_index); ?></a>
<?php endif; ?>
<?php endfor; ?>
</nav>
