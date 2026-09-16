<?php
/**
 * Plain-text "Documents Checklist:" section nested inside the payment-confirmed customer email. See
 * CHADA_TRAVEL_Email_Service::build_checklist_body(), which fetches this template's structured data (never cached, so
 * a slow-to-send queued email still reflects the latest requirement configuration) and renders this template.
 * Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body, not markup.
 *
 * @var list<array{country_name: string, applicant: string, requirement_labels: list<string>,
 *      guide_url: string}> $chada_travel_applications
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_applications));

if (!$chada_travel_applications) {
    echo 'Your Documents Checklist is available on the secure upload page below.';
    return;
}

$chada_travel_lines = ['Documents Checklist:'];
foreach ($chada_travel_applications as $chada_travel_application) {
    $chada_travel_lines[] = '- ' . $chada_travel_application['country_name'] . ' (' . $chada_travel_application['applicant'] . '):';
    foreach ($chada_travel_application['requirement_labels'] as $chada_travel_requirement_label) {
        $chada_travel_lines[] = '    * ' . $chada_travel_requirement_label;
    }
    if ($chada_travel_application['guide_url'] !== '') {
        $chada_travel_lines[] = '    Step-by-Step Guide: ' . $chada_travel_application['guide_url'];
    }
}

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are sanitized before assembly and HTML escaping would corrupt the message.
echo implode("\n", $chada_travel_lines);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
