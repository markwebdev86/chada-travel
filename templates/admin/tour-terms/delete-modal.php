<?php
/** Archived Tour taxonomy deletion confirmation modal. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div id="chada-travel-tour-term-delete-modal" class="chada-travel-confirmation-modal" hidden>
<div class="chada-travel-confirmation-modal__backdrop" data-chada-travel-delete-term-cancel></div>
<div class="chada-travel-confirmation-modal__dialog" role="dialog" aria-modal="true"
    aria-labelledby="chada-travel-tour-term-delete-title" tabindex="-1">
<h2 id="chada-travel-tour-term-delete-title"><?php esc_html_e('Delete Archived Record?', 'chada-travel'); ?></h2>
<p><?php esc_html_e('This permanently deletes the archived record.', 'chada-travel'); ?>
<?php esc_html_e('It cannot be deleted while any Tour uses it. Continue?', 'chada-travel'); ?></p>
<p><button type="button" class="button" data-chada-travel-delete-term-cancel>
<?php esc_html_e('Cancel', 'chada-travel'); ?></button> <button type="button" class="button button-primary"
    data-chada-travel-delete-term-confirm><?php esc_html_e('Delete Record', 'chada-travel'); ?></button></p>
</div>
</div>
