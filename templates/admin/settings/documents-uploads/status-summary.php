<?php
/**
 * Upload configuration status grid. See
 * CHADA_TRAVEL_Admin_Settings::build_documents_uploads_status_summary_html().
 *
 * @var string $chada_travel_overall_class
 * @var string $chada_travel_overall_label
 * @var string $chada_travel_configured_ceiling
 * @var string $chada_travel_effective_wp_limit
 * @var string $chada_travel_effective_ceiling_now
 * @var string $chada_travel_php_upload_max_filesize
 * @var string $chada_travel_php_post_max_size
 * @var bool   $chada_travel_show_over_limit_notice
 * @var string $chada_travel_over_limit_notice
 * @var bool   $chada_travel_show_server_unavailable_notice
 * @var string $chada_travel_server_unavailable_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_overall_class));
assert(isset($chada_travel_overall_label));
assert(isset($chada_travel_configured_ceiling));
assert(isset($chada_travel_effective_wp_limit));
assert(isset($chada_travel_effective_ceiling_now));
assert(isset($chada_travel_php_upload_max_filesize));
assert(isset($chada_travel_php_post_max_size));
assert(isset($chada_travel_show_over_limit_notice));
assert(isset($chada_travel_over_limit_notice));
assert(isset($chada_travel_show_server_unavailable_notice));
assert(isset($chada_travel_server_unavailable_notice));
?>
<div class="chada-travel-section"><h2><?php esc_html_e('Upload configuration status', 'chada-travel'); ?></h2>
<div class="chada-travel-detail-grid">
<dt><?php esc_html_e('Overall configuration', 'chada-travel'); ?></dt>
<dd><span class="chada-travel-badge <?php echo esc_attr($chada_travel_overall_class); ?>">
<?php echo esc_html($chada_travel_overall_label); ?>
</span></dd>
<dt><?php esc_html_e('Configured per-file ceiling', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_configured_ceiling); ?></dd>
<dt><?php esc_html_e('Effective WordPress upload limit', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_effective_wp_limit); ?></dd>
<dt><?php esc_html_e('Effective ceiling in use now', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_effective_ceiling_now); ?></dd>
<dt><?php esc_html_e('PHP upload_max_filesize', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_php_upload_max_filesize); ?></dd>
<dt><?php esc_html_e('PHP post_max_size', 'chada-travel'); ?></dt>
<dd><?php echo esc_html($chada_travel_php_post_max_size); ?></dd>
</div>
<?php if ($chada_travel_show_over_limit_notice) : ?>
<p class="description"><?php echo esc_html($chada_travel_over_limit_notice); ?></p>
<?php endif; ?>
<?php if ($chada_travel_show_server_unavailable_notice) : ?>
<p class="description"><?php echo esc_html($chada_travel_server_unavailable_notice); ?></p>
<?php endif; ?>
</div>
