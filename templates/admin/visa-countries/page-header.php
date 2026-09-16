<?php
/**
 * Static page chrome: heading, Add Country button, description, header rule. Styles are enqueued by the screen. See
 * CHADA_TRAVEL_Admin_Visa_Countries::render_page().
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;
?>
<div class="wrap">
<h1 class="wp-heading-inline" data-chada-travel-tour="visa-countries-header" tabindex="-1">
<?php esc_html_e('Visa Countries', 'chada-travel'); ?></h1>
<button type="button" class="page-title-action" id="chada-travel-add-country">
<?php esc_html_e('Add Country', 'chada-travel'); ?>
</button>
<p class="description">
<?php esc_html_e('Countries, fees, details, and paid resources offered in Visa Application checkout.', 'chada-travel'); ?>
</p>
<hr class="wp-header-end">
