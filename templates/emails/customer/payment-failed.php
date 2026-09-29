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

return 'Hi ' . sanitize_text_field($chada_travel_booker_name)
    . ",\n\nWe could not complete your payment for Booking ID " . sanitize_text_field($chada_travel_booking_id) . ".\n"
    . ($chada_travel_failure_reason !== '' ? 'Reason: ' . sanitize_textarea_field($chada_travel_failure_reason) . "\n\n" : "\n")
    . "You can try again anytime using the link below.\n\n"
    . esc_url_raw($chada_travel_checkout_url);
