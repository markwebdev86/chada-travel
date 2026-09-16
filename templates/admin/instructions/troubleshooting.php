<?php
/**
 * "Troubleshooting" tab of the Instructions screen: the most common "why doesn't this work" questions, grounded
 * in CHADA_TRAVEL_Checkout::render()'s two real gating checks (Company Status and the Policies & Consent bundle
 * readiness) plus the Bank/Digital Wallet manual-confirmation requirement. See
 * CHADA_TRAVEL_Admin_Instructions::render_troubleshooting_tab().
 *
 * @var string $chada_travel_general_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_policies_url '' when the current user cannot manage Settings.
 * @var string $chada_travel_payment_review_url '' when the current user cannot verify payments.
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_general_url));
assert(isset($chada_travel_policies_url));
assert(isset($chada_travel_payment_review_url));
?>
<dl>
<dt>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('"My checkout page is showing a maintenance message instead of the application form."', 'chada-travel'); ?>
</dt>
<dd>
<?php esc_html_e('Go to the', 'chada-travel'); ?> <?php if ($chada_travel_general_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_general_url); ?>"><?php esc_html_e('General tab', 'chada-travel'); ?></a>
<?php else: ?>
<?php esc_html_e('General tab', 'chada-travel'); ?>
<?php endif; ?> <?php esc_html_e('and confirm Company Status is set to Active.', 'chada-travel'); ?>
</dd>
<dt><?php esc_html_e('"My checkout page says policies/consent are not available."', 'chada-travel'); ?></dt>
<dd>
<?php esc_html_e('Go to the', 'chada-travel'); ?> <?php if ($chada_travel_policies_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_policies_url); ?>"><?php esc_html_e('Policies & Consent tab', 'chada-travel'); ?></a>
<?php else: ?>
<?php esc_html_e('Policies & Consent tab', 'chada-travel'); ?>
<?php endif; ?> <?php esc_html_e('and make sure your required policy text is filled in and published.', 'chada-travel'); ?>
</dd>
<dt><?php esc_html_e('"A Bank or Digital Wallet payment is not showing as confirmed."', 'chada-travel'); ?></dt>
<dd>
<?php esc_html_e('Check', 'chada-travel'); ?> <?php if ($chada_travel_payment_review_url !== ''): ?>
<a href="<?php echo esc_url($chada_travel_payment_review_url); ?>"><?php esc_html_e('Payment Review', 'chada-travel'); ?></a>
<?php else: ?>
<?php esc_html_e('Payment Review', 'chada-travel'); ?>
<?php endif; ?> &ndash;
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('these two methods always require manual confirmation before the booking resources are released.', 'chada-travel'); ?>
</dd>
<dt><?php esc_html_e('"The shortcode is not showing anything at all."', 'chada-travel'); ?></dt>
<dd>
<?php // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- single translatable string literal. ?>
<?php esc_html_e('Confirm the shortcode is spelled exactly as shown above (no extra characters) and that it is the only one of its kind on the page.', 'chada-travel'); ?>
</dd>
</dl>
