<?php
/** Optional built-in SMTP settings. The password is write-only and never rendered. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

/** @var array<string, mixed> $chada_travel_settings */
$chada_travel_settings = is_array($chada_travel_settings ?? null) ? $chada_travel_settings : [];
?>
<fieldset class="chada-travel-section">
<legend><h2><?php esc_html_e('Built-in SMTP (Optional)', 'chada-travel'); ?></h2></legend>
<p class="description">
    <?php esc_html_e(
        'The plugin uses WordPress wp_mail() by default. Enable this section only when this site should connect directly to an SMTP server.',
        'chada-travel'
    ); ?>
</p>
<table class="form-table" role="presentation">
<tbody>
<tr>
<th scope="row"><?php esc_html_e('Enable Built-in SMTP', 'chada-travel'); ?></th>
<td><label>
<input type="checkbox" name="chada_travel_smtp_enabled" value="1" <?php checked(!empty($chada_travel_settings['chada_travel_smtp_enabled'])); ?>>
<?php esc_html_e('Use the plugin-owned SMTP settings for wp_mail().', 'chada-travel'); ?></label></td></tr>
<tr>
<th scope="row">
    <label for="chada-travel-smtp-host"><?php esc_html_e('SMTP Host', 'chada-travel'); ?></label>
</th>
<td>
<input type="text" class="regular-text" id="chada-travel-smtp-host" name="chada_travel_smtp_host"
    value="<?php echo esc_attr((string) ($chada_travel_settings['chada_travel_smtp_host'] ?? '')); ?>" autocomplete="off">
</td></tr>
<tr>
<th scope="row">
    <label for="chada-travel-smtp-port"><?php esc_html_e('SMTP Port', 'chada-travel'); ?></label>
</th>
<td>
<input type="number" class="small-text" min="1" max="65535" id="chada-travel-smtp-port" name="chada_travel_smtp_port"
    value="<?php echo esc_attr((string) ($chada_travel_settings['chada_travel_smtp_port'] ?? 587)); ?>">
</td></tr>
<tr>
<th scope="row">
    <label for="chada-travel-smtp-encryption"><?php esc_html_e('Encryption', 'chada-travel'); ?></label>
</th>
<td>
<?php $chada_travel_encryption_options = [
    'none' => __('None', 'chada-travel'),
    'tls' => __('TLS', 'chada-travel'),
    'ssl' => __('SSL', 'chada-travel'),
]; ?>
<select id="chada-travel-smtp-encryption" name="chada_travel_smtp_encryption">
<?php foreach ($chada_travel_encryption_options as $chada_travel_value => $chada_travel_label) : ?>
<option value="<?php echo esc_attr($chada_travel_value); ?>"
    <?php selected((string) ($chada_travel_settings['chada_travel_smtp_encryption'] ?? 'tls'), $chada_travel_value); ?>>
    <?php echo esc_html($chada_travel_label); ?>
</option>
<?php endforeach; ?>
</select>
</td></tr>
<tr>
<th scope="row"><?php esc_html_e('Authentication', 'chada-travel'); ?></th>
<td><label>
<?php $chada_travel_smtp_authentication = !empty($chada_travel_settings['chada_travel_smtp_authentication']); ?>
<input type="checkbox" name="chada_travel_smtp_authentication" value="1" <?php checked($chada_travel_smtp_authentication); ?>>
<?php esc_html_e('Use an SMTP username and password.', 'chada-travel'); ?></label></td></tr>
<tr>
<th scope="row">
    <label for="chada-travel-smtp-username"><?php esc_html_e('SMTP Username', 'chada-travel'); ?></label>
</th>
<td>
<input type="text" class="regular-text" id="chada-travel-smtp-username" name="chada_travel_smtp_username"
    value="<?php echo esc_attr((string) ($chada_travel_settings['chada_travel_smtp_username'] ?? '')); ?>" autocomplete="username">
</td></tr>
<tr>
<th scope="row">
    <label for="chada-travel-smtp-password"><?php esc_html_e('SMTP Password', 'chada-travel'); ?></label>
</th>
<td>
<input type="password" class="regular-text" id="chada-travel-smtp-password" name="chada_travel_smtp_password" value="" autocomplete="new-password">
<?php if (!empty($chada_travel_settings['chada_travel_smtp_password'])) : ?>
<p class="description">
    <?php esc_html_e('A password is stored encrypted. Leave blank to keep it unchanged.', 'chada-travel'); ?>
</p>
<label><input type="checkbox" name="chada_travel_smtp_clear_password" value="1">
    <?php esc_html_e('Clear the stored password.', 'chada-travel'); ?></label>
<?php endif; ?>
</td></tr>
</tbody>
</table>
</fieldset>
