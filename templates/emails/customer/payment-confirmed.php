<?php
/**
 * Plain-text body for the chada_travel_email_payment_confirmed customer notification. See
 * CHADA_TRAVEL_Email_Service::build_message(). Plain text: no esc_html()/esc_attr() here, this is a wp_mail() body,
 * not markup. The shared footer is appended by the caller, outside this template.
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_transaction_id
 * @var string $chada_travel_checklist_body Pre-built by CHADA_TRAVEL_Email_Service::build_checklist_body(), plain text.
 * @var string $chada_travel_upload_url
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_transaction_id));
assert(isset($chada_travel_checklist_body));
assert(isset($chada_travel_upload_url));

return 'Hi ' . sanitize_text_field($chada_travel_booker_name)
    . ",\n\nYour payment for Booking ID " . sanitize_text_field($chada_travel_booking_id) . " is confirmed.\n"
    . 'Unique Transaction ID: ' . sanitize_text_field($chada_travel_transaction_id) . "\n\n"
    . sanitize_textarea_field($chada_travel_checklist_body)
    . "\n\nUpload your visa documents using this secure link:\n"
    . esc_url_raw($chada_travel_upload_url) . "\n\n"
    . 'This link expires after the configured upload-token lifetime.';
