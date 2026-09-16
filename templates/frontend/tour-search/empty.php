<?php
/** Empty state for Tour Search results. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var string|null $chada_travel_message */
assert(isset($chada_travel_message));
?>
<section class="chada-travel-tour-search-results chada-travel-tour-search-results--empty"
data-chada-travel-component="tour-search-results" aria-label="<?php esc_attr_e('Tour Search Results', 'chada-travel'); ?>">
<p class="chada-travel-tour-search-results__empty"><?php echo esc_html($chada_travel_message); ?></p>
</section>
