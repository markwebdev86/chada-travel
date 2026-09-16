<?php
/**
 * Scope note explaining this tab sets global ceilings only, with an optional link to Visa Countries. See
 * CHADA_TRAVEL_Admin_Settings::build_documents_uploads_scope_note_html().
 *
 * @var string $chada_travel_scope_note
 * @var bool   $chada_travel_show_link
 * @var string $chada_travel_url Empty when $chada_travel_show_link is false.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_scope_note));
assert(isset($chada_travel_show_link));
assert(isset($chada_travel_url));
?>
<div class="chada-travel-section"><p class="description">
<?php echo esc_html($chada_travel_scope_note); ?>
<?php if ($chada_travel_show_link) : ?>
<a href="<?php echo esc_url($chada_travel_url); ?>">
<?php esc_html_e('Open Visa Countries', 'chada-travel'); ?>
</a>
<?php endif; ?>
</p></div>
