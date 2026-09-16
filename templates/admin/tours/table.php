<?php
/** Tours grid table. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_rows_html Pre-rendered escaped row HTML. */

assert(isset($chada_travel_rows_html));
?>
<table class="wp-list-table widefat fixed striped" data-chada-travel-tour="tours-table"><thead><tr><th class="chada-travel-row-number-column" scope="col"><?php esc_html_e('No.', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Tour', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Featured Image', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Travel Dates', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Price', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Status', 'chada-travel'); ?></th><th scope="col"><?php esc_html_e('Actions', 'chada-travel'); ?></th></tr></thead><tbody><?php if ($chada_travel_rows_html === ''): ?><tr><td colspan="7"><?php esc_html_e('No Tours found.', 'chada-travel'); ?></td></tr><?php else: ?><?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-rendered escaped rows. ?><?php echo $chada_travel_rows_html; ?><?php endif; ?></tbody></table>
