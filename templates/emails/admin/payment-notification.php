<?php
/**
 * Plain-text body shared by both administrator payment-review notification events
 * (chada_travel_email_admin_bank_proof_submitted and chada_travel_email_admin_digital_wallet_submitted) - one shape, parameterized by
 * title/lines, not two templates. See CHADA_TRAVEL_Email_Service::build_admin_message(). Plain text: no
 * esc_html()/esc_attr() here, this is a wp_mail() body, not markup.
 *
 * @var string $chada_travel_company_name
 * @var string $chada_travel_title
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_method_label
 * @var string $chada_travel_status_label
 * @var string $chada_travel_booker_name '' when not yet available; the Booker line is omitted entirely then.
 * @var string $chada_travel_review_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_company_name));
assert(isset($chada_travel_title));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_method_label));
assert(isset($chada_travel_status_label));
assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_review_url));

$chada_travel_lines = [
    $chada_travel_company_name,
    '',
    $chada_travel_title,
    '',
    'Booking ID: ' . $chada_travel_booking_id,
    'Payment Method: ' . $chada_travel_method_label,
    'Status: ' . $chada_travel_status_label,
];
if ($chada_travel_booker_name !== '') {
    $chada_travel_lines[] = 'Booker: ' . $chada_travel_booker_name;
}
$chada_travel_lines[] = '';
$chada_travel_lines[] = 'Review: ' . $chada_travel_review_url;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are sanitized before assembly and HTML escaping would corrupt the message.
echo implode("\n", $chada_travel_lines);
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
