<?php
/**
 * Plain-text body for the paid per-country Guide/Checklist resource email. See
 * CHADA_TRAVEL_Email_Service::build_country_resource_message(). Plain text: no esc_html()/esc_attr() here, this is a
 * wp_mail() body, not markup. Unlike the other customer templates, the footer is passed straight through
 * (this message was never routed through build_message()'s separate footer-append step).
 *
 * @var string $chada_travel_booker_name
 * @var string $chada_travel_country_name
 * @var string $chada_travel_booking_id
 * @var string $chada_travel_footer
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

assert(isset($chada_travel_booker_name));
assert(isset($chada_travel_country_name));
assert(isset($chada_travel_booking_id));
assert(isset($chada_travel_footer));

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; values are sanitized before assembly and HTML escaping would corrupt the message.
echo "Hi {$chada_travel_booker_name},\n\nAttached are the latest Step-by-Step Guide and Documents "
    . "Checklist for {$chada_travel_country_name}.\n\nBooking ID: "
    . $chada_travel_booking_id . $chada_travel_footer;
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
