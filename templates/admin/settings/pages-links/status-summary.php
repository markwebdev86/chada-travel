<?php
/**
 * Page status grid, one row per CHADA_TRAVEL_Page_Registry definition. See
 * CHADA_TRAVEL_Admin_Settings::build_pages_links_status_summary_html().
 *
 * @var string $chada_travel_rows_html Pre-escaped <dt>/<dd> pairs.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_rows_html));
?>
<div class="chada-travel-section"><h2><?php esc_html_e('Page status', 'chada-travel'); ?></h2>
<div class="chada-travel-detail-grid">

<?php echo wp_kses_post($chada_travel_rows_html); ?>
</div></div>
