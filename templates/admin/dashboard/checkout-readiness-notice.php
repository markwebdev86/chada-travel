<?php
/**
 * Read-only Dashboard notification for incomplete customer checkout configuration.
 *
 * @var list<array{key: string, label: string, details: list<string>, impact: string, tab: string, configure_url: string}> $chada_travel_issues
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_issues));
?>
<div class="notice notice-warning chada-travel-dashboard-readiness" role="alert"
 aria-labelledby="chada-travel-dashboard-readiness-title">
<h2 id="chada-travel-dashboard-readiness-title"><?php
esc_html_e('Visa Application Checkout Setup Needs Attention', 'chada-travel');
?></h2>
<p><?php esc_html_e('Review the following settings before relying on the customer Visa Application workflow.', 'chada-travel'); ?></p>
<ul class="chada-travel-dashboard-readiness-list">
<?php foreach ($chada_travel_issues as $chada_travel_issue): ?>
<li>
<strong><?php echo esc_html($chada_travel_issue['label']); ?>:</strong>
<ul>
<?php foreach ($chada_travel_issue['details'] as $chada_travel_detail): ?>
<li><?php echo esc_html($chada_travel_detail); ?></li>
<?php endforeach; ?>
</ul>
<p class="description"><?php echo esc_html($chada_travel_issue['impact']); ?>
<?php if ($chada_travel_issue['configure_url'] !== ''): ?>
<a href="<?php echo esc_url($chada_travel_issue['configure_url']); ?>"><?php esc_html_e('Configure', 'chada-travel'); ?></a>
<?php endif; ?>
</p>
</li>
<?php endforeach; ?>
</ul>
</div>
