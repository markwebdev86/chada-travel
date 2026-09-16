<?php
/**
 * Plain-text body for the chada_travel_email_payment_failed customer notification. See
 * CHADA_TRAVEL_Email_Service::build_message(). Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body,
 * not markup. The shared footer is appended by the caller, outside this template.
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_failure_reason
 * @var string $chada_travel_checkout_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_failure_reason));
assert(isset($chada_travel_checkout_url));

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are sanitized before assembly and HTML escaping would corrupt the message.
echo "Hi {$chada_travel_booker_name},\n\nWe could not complete your payment for Booking ID {$chada_travel_booking_id}.\n"
    . ($chada_travel_failure_reason !== '' ? 'Reason: ' . $chada_travel_failure_reason . "\n\n" : "\n")
    . "You can try again anytime using the link below.\n\n"
    . $chada_travel_checkout_url;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
