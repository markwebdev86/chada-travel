<?php
/**
 * Form-validation-error notice with an intro sentence and a bulleted list. See
 * CHADA_TRAVEL_Admin_Settings::render_notices().
 *
 * @var string $chada_travel_intro Raw value; escaped here.
 * @var list<string> $chada_travel_errors Raw values; escaped here.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_intro));
assert(isset($chada_travel_errors));
?>
<div class="notice notice-error">
<p><?php echo esc_html($chada_travel_intro); ?></p>
<ul style="list-style:disc;margin-left:1.5em;">
<?php foreach ($chada_travel_errors as $chada_travel_message): ?>
<li><?php echo esc_html($chada_travel_message); ?></li>
<?php endforeach; ?>
</ul>
</div>
