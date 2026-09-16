<?php
/**
 * Plain-text body for the chada_travel_email_bank_proof_received customer notification. See
 * CHADA_TRAVEL_Email_Service::build_message(). Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body,
 * not markup. The shared footer is appended by the caller, outside this template.
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_booking_id
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booking_id));

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are sanitized before assembly and HTML escaping would corrupt the message.
echo "Hi {$chada_travel_booker_name},\n\nWe received your Deposit Slip for Booking ID "
    . "{$chada_travel_booking_id}.\n"
    . 'Status: Awaiting Verification. An administrator will review your payment shortly.';
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
