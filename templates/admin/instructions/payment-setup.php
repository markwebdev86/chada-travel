<?php
/**
 * "Payment Setup" tab of the Instructions screen. See CHADA_TRAVEL_Admin_Instructions::render_payment_setup_tab().
 *
 * @var string $chada_travel_payment_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_payment_review_url '' when the current user cannot verify payments.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_payment_url));
assert(isset($chada_travel_payment_review_url));
?>
<ul>
<li><strong><?php esc_html_e('Company Bank Accounts', 'chada-travel'); ?></strong>
&mdash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Add Company Bank Accounts. Applicants who choose Bank Payment upload proof via the [chada_travel_payment_proof] page; you confirm or reject it from Payment Review.', 'chada-travel'); ?>
</li>
<li><strong><?php esc_html_e('Digital Wallet', 'chada-travel'); ?></strong>
&mdash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Add a provider name (e.g. GCash, Maya), account name/number, and upload a QR code from the Media Library. Works the same manual-review way as Bank transfer.', 'chada-travel'); ?>
</li>
</ul>
<p>
<?php if ($chada_travel_payment_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_payment_url); ?>"><?php esc_html_e('Open the Payment Method tab', 'chada-travel'); ?></a>
<?php endif; ?>
<?php if ($chada_travel_payment_url !== '' && $chada_travel_payment_review_url !== ''): ?> &middot; <?php endif; ?>
<?php if ($chada_travel_payment_review_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_payment_review_url); ?>"><?php esc_html_e('Open Payment Review', 'chada-travel'); ?></a>
<?php endif; ?>
</p>
