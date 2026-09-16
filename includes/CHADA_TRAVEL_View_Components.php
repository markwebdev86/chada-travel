<?php
/**
 * Reusable customer-view primitives based on the approved HTML wireframe.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_View_Components {
    /**
     * Customer-facing brand header: shows the Company Logo (falling back to Company Name text when no valid
     * logo is configured, per CHADA_TRAVEL_Config::get_company_logo_url()'s own broken-image-safe fallback), the
     * optional tagline, and links the brand to the Company Website URL when configured. Company Name is always
     * the logo's alt text. Replaces the previous plugin-identity-only site_heading().
     *
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     */
    public static function company_brand_header(array $chada_travel_settings): string {
        $chada_travel_profile = CHADA_TRAVEL_Config::get_company_profile($chada_travel_settings);
        $chada_travel_mark = $chada_travel_profile['logo_url'] !== ''
            ? '<img class="chada-travel-brand-logo" src="' . self::escape_attr($chada_travel_profile['logo_url']) . '" alt="'
                . self::escape_attr($chada_travel_profile['name']) . '">'
            : self::escape_html($chada_travel_profile['name']);
        if ($chada_travel_profile['website_url'] !== '') {
            $chada_travel_mark = '<a class="chada-travel-brand-link" href="' . self::escape_attr($chada_travel_profile['website_url'])
                . '">' . $chada_travel_mark . '</a>';
        }
        $chada_travel_tagline = $chada_travel_profile['tagline'] !== ''
            ? '<p class="chada-travel-brand-tagline">' . self::escape_html($chada_travel_profile['tagline']) . '</p>' : '';
        return '<header class="chada-travel-site-heading"><div class="chada-travel-site-heading__inner"><h1>' . $chada_travel_mark
            . '</h1>' . $chada_travel_tagline . '</div></header>';
    }

    /**
     * Formatted company-contact block containing only configured values (Support Email as a safe mailto: link,
     * Phone/Mobile as safe tel: links, and the formatted Company Address); never renders an empty row,
     * separator, or punctuation for a value that is not configured. Returns '' when nothing is configured.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    public static function company_contact_block(array $chada_travel_settings): string {
        $chada_travel_contact = CHADA_TRAVEL_Config::get_company_contact($chada_travel_settings);
        $chada_travel_lines   = [];
        if (!empty($chada_travel_contact['support_email'])) {
            $chada_travel_lines[] = '<a href="mailto:' . self::escape_attr($chada_travel_contact['support_email']) . '">'
                . self::escape_html($chada_travel_contact['support_email']) . '</a>';
        }
        foreach (['phone', 'mobile'] as $chada_travel_key) {
            if (empty($chada_travel_contact[$chada_travel_key])) {
                continue;
            }
            $chada_travel_tel = preg_replace('/[^0-9+]/', '', $chada_travel_contact[$chada_travel_key]) ?? '';
            $chada_travel_lines[] = '<a href="tel:' . self::escape_attr($chada_travel_tel) . '">'
                . self::escape_html($chada_travel_contact[$chada_travel_key]) . '</a>';
        }
        $chada_travel_address = CHADA_TRAVEL_Config::get_company_address($chada_travel_settings);
        if ($chada_travel_address !== '') {
            $chada_travel_lines[] = '<span>' . self::escape_html($chada_travel_address) . '</span>';
        }
        return $chada_travel_lines ? '<div class="chada-travel-company-contact">' . implode('<br>', $chada_travel_lines) . '</div>' : '';
    }

    /**
     * Renders buttons only for server-authorized stages; inaccessible stages are non-interactive.
     *
     * @param list<int> $chada_travel_accessible_stages Stages authorized by PHP.
     */
    public static function progress(int $chada_travel_current_stage, array $chada_travel_accessible_stages): string {
        $chada_travel_items = [];
        $chada_travel_progress_stages = array_slice(CHADA_TRAVEL_Workflow::get_checkout_stages(), 0, 4, true);
        foreach ($chada_travel_progress_stages as $chada_travel_stage => $chada_travel_definition) {
            $chada_travel_is_current  = $chada_travel_stage === $chada_travel_current_stage;
            $chada_travel_is_allowed  = in_array($chada_travel_stage, $chada_travel_accessible_stages, true);
            $chada_travel_tag         = $chada_travel_is_allowed ? 'button' : 'span';
            $chada_travel_attributes = $chada_travel_is_allowed
                ? ' type="button" data-chada-travel-stage-target="' . $chada_travel_stage . '"' : '';
            $chada_travel_attributes .= $chada_travel_is_current ? ' aria-current="step"'
                : ($chada_travel_is_allowed ? '' : ' aria-disabled="true"');
            $chada_travel_class       = 'chada-travel-progress-step' . ($chada_travel_is_current ? ' is-active' : '');
            $chada_travel_items[]     = '<' . $chada_travel_tag . ' class="' . $chada_travel_class . '"' . $chada_travel_attributes
                . ' data-mobile-label="Stage ' . $chada_travel_stage . ' of 4"><span class="chada-travel-progress-step__number">'
                . $chada_travel_stage . '</span><span class="chada-travel-progress-step__label">'
                . self::escape_html($chada_travel_definition['label']) . '</span></' . $chada_travel_tag . '>';
        }
        return '<div class="chada-travel-progress-shell"><nav class="chada-travel-progress" aria-label="Visa application stages">'
            . implode('', $chada_travel_items) . '</nav></div>';
    }

    public static function screen(string $chada_travel_title, string $chada_travel_content, string $chada_travel_class = ''): string {
        return '<main class="chada-travel-screen ' . self::escape_attr($chada_travel_class) . '"><h2>'
            . self::escape_html($chada_travel_title)
            . '</h2>' . $chada_travel_content . '</main>';
    }

    public static function main_aside(string $chada_travel_main, string $chada_travel_aside): string {
        return '<div class="chada-travel-grid-main-aside"><div>' . $chada_travel_main . '</div><aside>' . $chada_travel_aside
            . '</aside></div>';
    }

    public static function panel(
        string $chada_travel_title,
        string $chada_travel_body,
        string $chada_travel_class = '',
        string $chada_travel_header_actions = ''
    ): string {
        $chada_travel_header = $chada_travel_title !== '' ? '<div class="chada-travel-panel__header"><h3>'
            . self::escape_html($chada_travel_title) . '</h3>' . $chada_travel_header_actions . '</div>' : '';
        return '<section class="chada-travel-panel ' . self::escape_attr($chada_travel_class) . '">' . $chada_travel_header
            . '<div class="chada-travel-panel__body">' . $chada_travel_body . '</div></section>';
    }

    /** @param list<string> $chada_travel_controls Escaped control markup. */
    public static function form_grid(array $chada_travel_controls): string {
        return '<div class="chada-travel-form-grid">' . implode('', $chada_travel_controls) . '</div>';
    }

    /**
     * Renders a basic field primitive. Select options are value => label pairs.
     *
     * @param array<string, mixed> $chada_travel_settings Safe field settings.
     */
    public static function control(array $chada_travel_settings): string {
        $chada_travel_label    = self::escape_html((string) ($chada_travel_settings['label'] ?? ''));
        $chada_travel_name     = self::escape_attr((string) ($chada_travel_settings['name'] ?? ''));
        $chada_travel_type     = self::escape_attr((string) ($chada_travel_settings['type'] ?? 'text'));
        $chada_travel_value    = self::escape_attr((string) ($chada_travel_settings['value'] ?? ''));
        $chada_travel_required = empty($chada_travel_settings['required']) ? '' : ' required aria-required="true"';
        $chada_travel_full     = empty($chada_travel_settings['full']) ? '' : ' chada-travel-field--full';
        $chada_travel_label_mark = empty($chada_travel_settings['required']) ? '' : ' *';
        $chada_travel_options  = $chada_travel_settings['options'] ?? [];
        if (is_array($chada_travel_options) && $chada_travel_options) {
            $chada_travel_control = '<select class="chada-travel-control" name="' . $chada_travel_name . '"'
                . $chada_travel_required . '>';
            foreach ($chada_travel_options as $chada_travel_option_value => $chada_travel_option_label) {
                $chada_travel_selected = (string) $chada_travel_option_value === (string) ($chada_travel_settings['value'] ?? '')
                    ? ' selected' : '';
                $chada_travel_control .= '<option value="' . self::escape_attr((string) $chada_travel_option_value) . '"'
                    . $chada_travel_selected
                    . '>' . self::escape_html((string) $chada_travel_option_label) . '</option>';
            }
            $chada_travel_control .= '</select>';
        } elseif ($chada_travel_type === 'textarea') {
            $chada_travel_control = '<textarea class="chada-travel-control" name="' . $chada_travel_name . '"' . $chada_travel_required . '>'
                . self::escape_html((string) ($chada_travel_settings['value'] ?? '')) . '</textarea>';
        } else {
            $chada_travel_control = '<input class="chada-travel-control" type="' . $chada_travel_type . '" name="' . $chada_travel_name
                . '" value="' . $chada_travel_value . '"' . $chada_travel_required . '>';
        }
        return '<label class="chada-travel-field' . $chada_travel_full . '"><span>' . $chada_travel_label . $chada_travel_label_mark . '</span>'
            . $chada_travel_control . '</label>';
    }

    /** Renders a required consent checkbox. $chada_travel_label_html is trusted, code-owned markup, not escaped. */
    public static function check_row(string $chada_travel_name, string $chada_travel_label_html, string $chada_travel_id = ''): string {
        $chada_travel_id_attribute = $chada_travel_id !== '' ? ' id="' . self::escape_attr($chada_travel_id) . '"' : '';
        return '<label class="chada-travel-check-row chada-travel-field--full"><input type="checkbox" name="'
            . self::escape_attr($chada_travel_name) . '"' . $chada_travel_id_attribute . ' required aria-required="true">'
            . '<span>' . $chada_travel_label_html . '</span></label>';
    }

    /**
     * Action definitions accept label, variant, disabled, hidden, type, and safe data attributes.
     *
     * @param list<array<string, mixed>> $chada_travel_actions Safe action settings.
     */
    public static function actions(array $chada_travel_actions): string {
        $chada_travel_buttons = [];
        foreach ($chada_travel_actions as $chada_travel_action) {
            $chada_travel_variant  = ($chada_travel_action['variant'] ?? '') === 'primary' ? ' chada-travel-button--primary' : '';
            $chada_travel_disabled = empty($chada_travel_action['disabled']) ? '' : ' disabled';
            $chada_travel_hidden   = empty($chada_travel_action['hidden']) ? '' : ' hidden';
            $chada_travel_type     = self::escape_attr((string) ($chada_travel_action['type'] ?? 'button'));
            $chada_travel_data     = '';
            foreach (($chada_travel_action['data'] ?? []) as $chada_travel_name => $chada_travel_value) {
                $chada_travel_data .= ' data-' . self::escape_attr((string) $chada_travel_name) . '="'
                    . self::escape_attr((string) $chada_travel_value) . '"';
            }
            $chada_travel_buttons[] = '<button class="chada-travel-button' . $chada_travel_variant . '" type="' . $chada_travel_type . '"'
                . $chada_travel_disabled . $chada_travel_hidden . $chada_travel_data . '>'
                . self::escape_html((string) ($chada_travel_action['label'] ?? '')) . '</button>';
        }
        return '<div class="chada-travel-actions">' . implode('', $chada_travel_buttons) . '</div>';
    }

    /** @param array<string, string> $chada_travel_rows Label and formatted amount rows. */
    public static function summary(array $chada_travel_rows, string $chada_travel_total_label, string $chada_travel_total): string {
        $chada_travel_lines = [];
        foreach ($chada_travel_rows as $chada_travel_label => $chada_travel_amount) {
            $chada_travel_lines[] = '<div class="chada-travel-summary-line"><span>' . self::escape_html((string) $chada_travel_label)
                . '</span><span>' . self::escape_html((string) $chada_travel_amount) . '</span></div>';
        }
        $chada_travel_lines[] = '<div class="chada-travel-summary-line chada-travel-summary-line--total"><span>'
            . self::escape_html($chada_travel_total_label) . '</span><span>' . self::escape_html($chada_travel_total)
            . '</span></div>';
        return '<div class="chada-travel-summary-list">' . implode('', $chada_travel_lines) . '</div>';
    }

    /** @param array<string, string> $chada_travel_rows Label and value pairs, e.g. Company Bank Account details. */
    public static function key_value(array $chada_travel_rows): string {
        $chada_travel_lines = [];
        foreach ($chada_travel_rows as $chada_travel_label => $chada_travel_value) {
            $chada_travel_lines[] = '<dt>' . self::escape_html((string) $chada_travel_label) . ':</dt><dd>'
                . self::escape_html((string) $chada_travel_value) . '</dd>';
        }
        return '<dl class="chada-travel-key-value">' . implode('', $chada_travel_lines) . '</dl>';
    }

    /** Renders one Stage 4 payment-method radio card. $chada_travel_mark is a short escaped 1-4 character glyph. */
    public static function payment_method_card(
        string $chada_travel_method,
        string $chada_travel_label,
        string $chada_travel_mark,
        bool $chada_travel_selected,
        bool $chada_travel_is_default
    ): string {
        $chada_travel_class = 'chada-travel-payment-option' . ($chada_travel_selected ? ' is-selected' : '');
        $chada_travel_badge = $chada_travel_is_default ? '<span class="chada-travel-badge">Default</span>' : '';
        return '<button class="' . $chada_travel_class . '" type="button" role="radio" aria-checked="'
            . ($chada_travel_selected ? 'true' : 'false') . '" data-chada-travel-payment-method="'
            . self::escape_attr($chada_travel_method) . '">'
            . '<span class="chada-travel-payment-option__mark" aria-hidden="true">' . self::escape_html($chada_travel_mark)
            . '</span>'
            . '<span class="chada-travel-payment-option__label">' . self::escape_html($chada_travel_label) . '</span>' . $chada_travel_badge
            . '<span class="chada-travel-payment-option__radio" aria-hidden="true"></span></button>';
    }

    /**
     * @param list<array{title:string, body:string}> $chada_travel_items
     */
    public static function instruction_list(array $chada_travel_items): string {
        $chada_travel_rows = [];
        foreach ($chada_travel_items as $chada_travel_index => $chada_travel_item) {
            $chada_travel_rows[] = '<li class="chada-travel-instruction-item"><span class="chada-travel-instruction-number">'
                . ($chada_travel_index + 1) . '</span><div><h3>' . self::escape_html($chada_travel_item['title'])
                . '</h3><p>' . self::escape_html($chada_travel_item['body']) . '</p></div></li>';
        }
        return '<ol class="chada-travel-instruction-list">' . implode('', $chada_travel_rows) . '</ol>';
    }

    /** Renders the Stage 5 checkmark + Booking ID + "save this ID" banner. */
    public static function booking_banner(string $chada_travel_booking_id, string $chada_travel_saved_notice): string {
        return '<div class="chada-travel-confirmation-banner"><span class="chada-travel-confirmation-banner__check" '
            . 'aria-hidden="true">&#10003;</span><div><h3>Booking ID: <span data-chada-travel-booking-id>'
            . self::escape_html($chada_travel_booking_id) . '</span></h3><p>' . self::escape_html($chada_travel_saved_notice)
            . '</p></div></div>';
    }

    public static function badge(string $chada_travel_label, string $chada_travel_status = ''): string {
        return '<span class="chada-travel-badge" data-status="' . self::escape_attr($chada_travel_status) . '">'
            . self::escape_html($chada_travel_label) . '</span>';
    }

    public static function notice(string $chada_travel_message, string $chada_travel_type = 'info'): string {
        return '<div class="chada-travel-notice" data-type="' . self::escape_attr($chada_travel_type) . '" role="status">'
            . self::escape_html($chada_travel_message) . '</div>';
    }

    /**
     * @param list<string>      $chada_travel_headers Table headers.
     * @param list<list<mixed>> $chada_travel_rows    Table rows.
     */
    public static function table(array $chada_travel_headers, array $chada_travel_rows, string $chada_travel_tbody_id = ''): string {
        $chada_travel_head = implode('', array_map(static fn(string $chada_travel_header): string => '<th scope="col">'
            . self::escape_html($chada_travel_header) . '</th>', $chada_travel_headers));
        $chada_travel_body = '';
        foreach ($chada_travel_rows as $chada_travel_row) {
            $chada_travel_body .= '<tr>' . implode('', array_map(static fn($chada_travel_cell): string => '<td>'
                . self::escape_html((string) $chada_travel_cell) . '</td>', $chada_travel_row)) . '</tr>';
        }
        $chada_travel_tbody_attribute = $chada_travel_tbody_id !== '' ? ' id="' . self::escape_attr($chada_travel_tbody_id) . '"' : '';
        return '<div class="chada-travel-table-wrap"><table class="chada-travel-table"><thead><tr>' . $chada_travel_head
            . '</tr></thead><tbody' . $chada_travel_tbody_attribute . '>' . $chada_travel_body . '</tbody></table></div>';
    }

    /** @param array<string, mixed> $chada_travel_rules PHP-authoritative upload rules. */
    public static function upload_zone(string $chada_travel_name, array $chada_travel_rules): string {
        $chada_travel_accept = self::escape_attr(implode(',', $chada_travel_rules['mime_types'] ?? []));
        $chada_travel_limit  = (int) ($chada_travel_rules['max_bytes'] ?? 0);
        return '<label class="chada-travel-upload-zone"><strong>' . self::escape_html('Click to upload or drag and drop')
            . '</strong><span>' . self::escape_html('Maximum ' . self::format_bytes($chada_travel_limit))
            . '</span><input type="file" name="'
            . self::escape_attr($chada_travel_name) . '" accept="' . $chada_travel_accept . '"></label>';
    }

    private static function format_bytes(int $chada_travel_bytes): string {
        return $chada_travel_bytes >= 1048576 ? rtrim(rtrim(number_format($chada_travel_bytes / 1048576, 1), '0'), '.') . ' MB'
            : number_format($chada_travel_bytes / 1024) . ' KB';
    }

    public static function escape_html(string $chada_travel_value): string {
        return function_exists('esc_html') ? esc_html($chada_travel_value)
            : htmlspecialchars($chada_travel_value, ENT_QUOTES, 'UTF-8');
    }

    public static function escape_attr(string $chada_travel_value): string {
        return function_exists('esc_attr') ? esc_attr($chada_travel_value)
            : htmlspecialchars($chada_travel_value, ENT_QUOTES, 'UTF-8');
    }

    /** Adds response headers the ZAP baseline scan expects on the checkout/proof/upload screens, when not already sent. */
    public static function send_customer_security_headers(): void {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
    }
}
