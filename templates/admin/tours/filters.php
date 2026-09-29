<?php
/** Tours search and status filters. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_menu_slug Raw page slug; escaped here. */
/** @var string|null $chada_travel_search Raw search value; escaped here. */
/** @var string|null $chada_travel_status Raw status value; escaped here. */
/** @var string|null $chada_travel_featured Raw Featured filter value; escaped here. */
/** @var int|null $chada_travel_destination Raw selected Tour Destination term id (0 = All); escaped here. */
/** @var int|null $chada_travel_type Raw selected Tour Type term id (0 = All); escaped here. */
/** @var list<array<string, mixed>>|null $chada_travel_destinations Raw Tour Destination term rows; escaped here. */
/** @var list<array<string, mixed>>|null $chada_travel_types Raw Tour Type term rows; escaped here. */

assert(isset($chada_travel_menu_slug));
assert(isset($chada_travel_search));
assert(isset($chada_travel_status));
assert(isset($chada_travel_featured));
assert(isset($chada_travel_destination));
assert(isset($chada_travel_type));
assert(isset($chada_travel_destinations));
assert(isset($chada_travel_types));
?>
<form class="chada-travel-toolbar" method="get"><input type="hidden" name="page" value="<?php echo esc_attr($chada_travel_menu_slug); ?>"><label><?php esc_html_e('Search', 'chada-travel'); ?><input type="search" name="s" value="<?php echo esc_attr($chada_travel_search); ?>" placeholder="<?php esc_attr_e('Tour name or code', 'chada-travel'); ?>"></label><label><?php esc_html_e('Status', 'chada-travel'); ?><select name="status"><option value="all"<?php selected($chada_travel_status, 'all'); ?>><?php esc_html_e('All', 'chada-travel'); ?></option><option value="active"<?php selected($chada_travel_status, 'active'); ?>><?php esc_html_e('Active', 'chada-travel'); ?></option><option value="archived"<?php selected($chada_travel_status, 'archived'); ?>><?php esc_html_e('Archived', 'chada-travel'); ?></option></select></label><label><?php esc_html_e('Featured', 'chada-travel'); ?><select name="featured"><option value="all"<?php selected($chada_travel_featured, 'all'); ?>><?php esc_html_e('All', 'chada-travel'); ?></option><option value="featured"<?php selected($chada_travel_featured, 'featured'); ?>><?php esc_html_e('Featured', 'chada-travel'); ?></option><option value="not_featured"<?php selected($chada_travel_featured, 'not_featured'); ?>><?php esc_html_e('Not Featured', 'chada-travel'); ?></option></select></label><label><?php esc_html_e('Tour Destinations', 'chada-travel'); ?><select name="destination"><option value="0"<?php selected($chada_travel_destination, 0); ?>><?php esc_html_e('All', 'chada-travel'); ?></option><?php foreach ($chada_travel_destinations as $chada_travel_destination_term): ?><option value="<?php echo esc_attr((string) $chada_travel_destination_term['chada_travel_tour_term_id']); ?>"<?php selected($chada_travel_destination, (int) $chada_travel_destination_term['chada_travel_tour_term_id']); ?>><?php echo esc_html($chada_travel_destination_term['chada_travel_term_name']); ?><?php echo empty($chada_travel_destination_term['chada_travel_is_active']) ? ' (' . esc_html__('Archived', 'chada-travel') . ')' : ''; ?></option><?php endforeach; ?></select></label><label><?php esc_html_e('Tour Types', 'chada-travel'); ?><select name="type"><option value="0"<?php selected($chada_travel_type, 0); ?>><?php esc_html_e('All', 'chada-travel'); ?></option><?php foreach ($chada_travel_types as $chada_travel_type_term): ?><option value="<?php echo esc_attr((string) $chada_travel_type_term['chada_travel_tour_term_id']); ?>"<?php selected($chada_travel_type, (int) $chada_travel_type_term['chada_travel_tour_term_id']); ?>><?php echo esc_html($chada_travel_type_term['chada_travel_term_name']); ?><?php echo empty($chada_travel_type_term['chada_travel_is_active']) ? ' (' . esc_html__('Archived', 'chada-travel') . ')' : ''; ?></option><?php endforeach; ?></select></label><button class="button" type="submit"><?php esc_html_e('Filter', 'chada-travel'); ?></button></form>
