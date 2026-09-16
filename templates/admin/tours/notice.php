<?php
/** Tours manager notice. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

// phpcs:disable Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded

/** @var string|null $chada_travel_notice Raw notice key. */
/** @var string|null $chada_travel_message Raw localized notice message. */

assert(isset($chada_travel_notice)); assert(isset($chada_travel_message));

if ($chada_travel_notice !== ''):
    $chada_travel_class = $chada_travel_notice === 'duplicate_error' ? 'error' : 'success';
    ?><div class="notice notice-<?php echo esc_attr($chada_travel_class); ?> is-dismissible"><p><?php echo esc_html($chada_travel_message); ?></p></div><?php
endif;
