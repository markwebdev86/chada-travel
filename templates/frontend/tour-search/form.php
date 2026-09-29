<?php
/** Front-end Tour Search form. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var string|null $chada_travel_action */
/** @var string|null $chada_travel_search */
/** @var int|null $chada_travel_destination_id */
/** @var int|null $chada_travel_type_id */
/** @var list<array<string, mixed>>|null $chada_travel_destinations */
/** @var list<array<string, mixed>>|null $chada_travel_types */
assert(isset(
    $chada_travel_action,
    $chada_travel_search,
    $chada_travel_destination_id,
    $chada_travel_type_id,
    $chada_travel_destinations,
    $chada_travel_types
));
$chada_travel_field_prefix = function_exists('wp_unique_id')
    ? wp_unique_id('chada-travel-tour-search-')
    : 'chada-travel-tour-search-' . (function_exists('wp_rand') ? wp_rand() : random_int(0, PHP_INT_MAX));
$chada_travel_query_id = $chada_travel_field_prefix . 'query';
$chada_travel_destination_field_id = $chada_travel_field_prefix . 'destination';
$chada_travel_type_field_id = $chada_travel_field_prefix . 'type';
?>
<section class="chada-travel-tour-search" data-chada-travel-component="tour-search-form"
aria-label="<?php esc_attr_e('Search Tours', 'chada-travel'); ?>">
<form class="chada-travel-tour-search__form" method="get" action="<?php echo esc_url($chada_travel_action); ?>">
<div class="chada-travel-tour-search__field">
<label class="chada-travel-tour-search__label" for="<?php echo esc_attr($chada_travel_query_id); ?>">
<?php esc_html_e('Search:', 'chada-travel'); ?></label>
<input class="chada-travel-tour-search__input" id="<?php echo esc_attr($chada_travel_query_id); ?>" type="text"
name="<?php echo esc_attr(CHADA_TRAVEL_Tour_Search::SEARCH_PARAM); ?>" maxlength="100"
value="<?php echo esc_attr($chada_travel_search); ?>" placeholder="<?php esc_attr_e('Ex: Australia', 'chada-travel'); ?>">
<span class="chada-travel-tour-search__help"><?php esc_html_e('Search Tour Name and Description', 'chada-travel'); ?></span>
</div>
<div class="chada-travel-tour-search__field">
<label class="chada-travel-tour-search__label" for="<?php echo esc_attr($chada_travel_destination_field_id); ?>">
<?php esc_html_e('Destinations:', 'chada-travel'); ?></label>
<select class="chada-travel-tour-search__select" id="<?php echo esc_attr($chada_travel_destination_field_id); ?>"
name="<?php echo esc_attr(CHADA_TRAVEL_Tour_Search::DESTINATION_PARAM); ?>">
<option value="0"><?php esc_html_e('All', 'chada-travel'); ?></option>
<?php foreach ($chada_travel_destinations as $chada_travel_destination): ?>
<option value="<?php echo esc_attr((string) $chada_travel_destination['chada_travel_tour_term_id']); ?>"
<?php selected($chada_travel_destination_id, (int) $chada_travel_destination['chada_travel_tour_term_id']); ?>>
<?php echo esc_html((string) $chada_travel_destination['chada_travel_term_name']); ?></option>
<?php endforeach; ?>
</select>
<span class="chada-travel-tour-search__help"><?php esc_html_e('Select Tour Destinations to search', 'chada-travel'); ?></span>
</div>
<div class="chada-travel-tour-search__field">
<label class="chada-travel-tour-search__label" for="<?php echo esc_attr($chada_travel_type_field_id); ?>">
<?php esc_html_e('Types/Activities:', 'chada-travel'); ?></label>
<select class="chada-travel-tour-search__select" id="<?php echo esc_attr($chada_travel_type_field_id); ?>"
name="<?php echo esc_attr(CHADA_TRAVEL_Tour_Search::TYPE_PARAM); ?>">
<option value="0"><?php esc_html_e('All', 'chada-travel'); ?></option>
<?php foreach ($chada_travel_types as $chada_travel_type): ?>
<option value="<?php echo esc_attr((string) $chada_travel_type['chada_travel_tour_term_id']); ?>"
<?php selected($chada_travel_type_id, (int) $chada_travel_type['chada_travel_tour_term_id']); ?>>
<?php echo esc_html((string) $chada_travel_type['chada_travel_term_name']); ?></option>
<?php endforeach; ?>
</select>
<span class="chada-travel-tour-search__help"><?php esc_html_e('Select Tour Types to search', 'chada-travel'); ?></span>
</div>
<div class="chada-travel-tour-search__action">
<span class="chada-travel-tour-search__label chada-travel-tour-search__label--spacer" aria-hidden="true">&nbsp;</span>
<button class="chada-travel-tour-search__button" type="submit"><?php esc_html_e('Search', 'chada-travel'); ?></button>
</div>
</form>
</section>
