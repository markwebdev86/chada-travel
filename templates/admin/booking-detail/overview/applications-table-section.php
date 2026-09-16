<?php
/**
 * The Visa Applications table: one row per application plus a computed running total. See
 * CHADA_TRAVEL_Admin_Booking_Detail::render_applications_table_section().
 *
 * @var string $chada_travel_documents_url Raw value; escaped here.
 * @var string $chada_travel_rows_html Pre-built HTML blob: either the per-application <tr> rows, or the single
 *      colspan=9 empty-state row when there are no applications.
 * @var string $chada_travel_tfoot_html Pre-built HTML blob: the <tfoot> total row, or '' when there are no
 *      applications.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_documents_url));
assert(isset($chada_travel_rows_html));
assert(isset($chada_travel_tfoot_html));
?>
<div class="chada-travel-section" data-chada-travel-tour="booking-overview-applications">
<h2><?php esc_html_e('Visa Applications', 'chada-travel'); ?> <a class="button button-small"
    href="<?php echo esc_url($chada_travel_documents_url); ?>"><?php esc_html_e('Review documents', 'chada-travel'); ?></a></h2>
<table class="wp-list-table widefat fixed striped"><thead><tr><th><?php
esc_html_e('Reference', 'chada-travel'); ?></th><th><?php
esc_html_e('Applicant', 'chada-travel'); ?></th><th><?php
esc_html_e('Date of Birth', 'chada-travel'); ?></th><th><?php
esc_html_e('Target Travel Date', 'chada-travel'); ?></th><th><?php
esc_html_e('Country', 'chada-travel'); ?></th><th><?php
esc_html_e('File status', 'chada-travel'); ?></th><th><?php
esc_html_e('Checklist snapshot', 'chada-travel'); ?></th><th><?php
esc_html_e('Fee', 'chada-travel'); ?></th><th><?php
esc_html_e('Action', 'chada-travel'); ?></th></tr></thead><tbody><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_rows_html; ?></tbody><?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built from esc_html() calls, see the _html naming convention documented in CHADA_TRAVEL_Template.
echo $chada_travel_tfoot_html; ?></table></div>
