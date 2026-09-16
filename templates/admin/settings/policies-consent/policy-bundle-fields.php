<?php
/**
 * Policy Version and Effective Date fieldset. See CHADA_TRAVEL_Admin_Settings::build_policy_bundle_fields_html().
 *
 * @var string $chada_travel_policy_version
 * @var string $chada_travel_version_notice
 * @var string $chada_travel_effective_date
 * @var string $chada_travel_date_notice
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_policy_version));
assert(isset($chada_travel_version_notice));
assert(isset($chada_travel_effective_date));
assert(isset($chada_travel_date_notice));
?>
<fieldset class="chada-travel-section"><legend><h2>
<?php esc_html_e('Policy Version and Effective Date', 'chada-travel'); ?>
</h2></legend>
<table class="form-table" role="presentation"><tbody>

<tr><th scope="row"><label for="chada-travel-policy-version">
<?php esc_html_e('Policy Version', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<input type="text" class="regular-text" maxlength="40" id="chada-travel-policy-version" name="chada_travel_policy_version"
    value="<?php echo esc_attr($chada_travel_policy_version); ?>" required>
<p class="description"><?php echo esc_html($chada_travel_version_notice); ?></p></td></tr>

<tr><th scope="row"><label for="chada-travel-policy-effective-date">
<?php esc_html_e('Policy Effective Date', 'chada-travel'); ?>
<span aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e('(required)', 'chada-travel'); ?></span>
</label></th>
<td>
<input type="date" class="regular-text" id="chada-travel-policy-effective-date" name="chada_travel_policy_effective_date"
    value="<?php echo esc_attr($chada_travel_effective_date); ?>" required>
<p class="description"><?php echo esc_html($chada_travel_date_notice); ?></p></td></tr>

</tbody></table></fieldset>
