<?php
/**
 * Plain-text body for the chada_travel_email_bank_proof_requested customer notification. See
 * CHADA_TRAVEL_Email_Service::build_message(). Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body,
 * not markup. The shared footer is appended by the caller, outside this template.
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_proof_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_proof_url));

return 'Hi ' . sanitize_text_field($chada_travel_booker_name)
    . ",\n\nYour Booking ID is " . sanitize_text_field($chada_travel_booking_id) . ".\n"
    . "Status: Awaiting Proof.\n\n"
    . 'Deposit your payment to the Company Bank Account, take a clear photo of the Deposit Slip, '
    . "then submit it using the secure link below or the Upload Bank Payment Proof page.\n\n"
    . esc_url_raw($chada_travel_proof_url) . "\n\n"
    . 'This link expires after the configured proof-token lifetime.';
