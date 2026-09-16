<?php
/** Archived Tour deletion confirmation modal. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div id="chada-travel-tour-delete-modal" class="chada-travel-confirmation-modal" hidden>
<div class="chada-travel-confirmation-modal__backdrop" data-chada-travel-delete-cancel></div>
<div class="chada-travel-confirmation-modal__dialog" role="dialog" aria-modal="true"
    aria-labelledby="chada-travel-tour-delete-title" tabindex="-1">
<h2 id="chada-travel-tour-delete-title"><?php esc_html_e('Delete Archived Tour?', 'chada-travel'); ?></h2>
<p><?php esc_html_e('This permanently deletes the archived Tour, its dates, and internal relationships.', 'chada-travel'); ?>
<?php esc_html_e('Shared Media Library attachments are kept. Continue?', 'chada-travel'); ?></p>
<p><button type="button" class="button" data-chada-travel-delete-cancel>
<?php esc_html_e('Cancel', 'chada-travel'); ?></button> <button type="button" class="button button-primary"
    data-chada-travel-delete-confirm><?php esc_html_e('Delete Tour', 'chada-travel'); ?></button></p>
</div>
</div>
