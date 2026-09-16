<?php
/**
 * One dismissible admin notice. See CHADA_TRAVEL_Admin_Settings::render_notices().
 *
 * @var string $chada_travel_type Raw value; escaped here. 'success' or 'error'.
 * @var string $chada_travel_message Raw value; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_type));
assert(isset($chada_travel_message));
?>
<div class="notice notice-<?php echo esc_attr($chada_travel_type); ?> is-dismissible">
<p><?php echo esc_html($chada_travel_message); ?></p>
</div>
