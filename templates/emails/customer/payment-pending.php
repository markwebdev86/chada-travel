<?php
/**
 * Plain-text body for the chada_travel_email_payment_pending (Digital Wallet) customer notification. See
 * CHADA_TRAVEL_Email_Service::build_message(). Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body,
 * not markup. The shared footer is appended by the caller, outside this template.
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_provider_name
 * @var string $chada_travel_reference_no
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_provider_name));
assert(isset($chada_travel_reference_no));

return 'Hi ' . sanitize_text_field($chada_travel_booker_name)
    . ",\n\nYour Booking ID is " . sanitize_text_field($chada_travel_booking_id) . ".\n"
    . sanitize_text_field($chada_travel_provider_name) . ' Reference No.: '
    . sanitize_text_field($chada_travel_reference_no) . ".\n"
    . 'Status: Awaiting Verification. An administrator will review your payment shortly.';
