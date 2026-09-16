<?php
/**
 * See CHADA_TRAVEL_Admin_Dashboard::render_setup_status_section().
 *
 * @var bool $chada_travel_all_ready
 * @var list<array{label: string, detail: string, configure_url: string}> $chada_travel_unready_items Empty when
 *      $chada_travel_all_ready is true. Each configure_url is '' when the current user cannot manage Settings.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_all_ready));
assert(isset($chada_travel_unready_items));
?>
<div class="chada-travel-section chada-travel-dashboard-status" data-chada-travel-tour="dashboard-setup-status">
<h2><?php esc_html_e('Setup & System Status', 'chada-travel'); ?></h2>
<?php if ($chada_travel_all_ready): ?>
<p><span class="chada-travel-badge is-success"><?php esc_html_e('Ready', 'chada-travel'); ?></span>
<?php
// phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal.
esc_html_e('Company Status, Pages & Links, Payment Methods, Policies & Consent, Email, and Documents & Uploads are all ready.', 'chada-travel');
?>
</p>
<?php else: ?>
<p><span class="chada-travel-badge is-warning"><?php esc_html_e('Needs attention', 'chada-travel'); ?></span></p>
<ul class="chada-travel-dashboard-status-list">
<?php foreach ($chada_travel_unready_items as $chada_travel_item): ?>
<li><strong><?php echo esc_html($chada_travel_item['label']); ?>:</strong> <?php echo esc_html($chada_travel_item['detail']); ?>
<?php if ($chada_travel_item['configure_url'] !== ''): ?>
 <a href="<?php echo esc_url($chada_travel_item['configure_url']); ?>"><?php esc_html_e('Configure', 'chada-travel'); ?></a>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>
