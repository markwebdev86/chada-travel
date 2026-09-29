<?php
/**
 * Public Bank Deposit Slip proof-of-payment utility page, matching the approved wireframe layout.
 *
 * Reachable from the emailed secure link (?token=...). The Booking ID remains a confirmation field, but the
 * high-entropy proof token is required for authorization and Booking ID alone can never change payment state.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Proof {
    public const SHORTCODE_TAG = 'chada_travel_payment_proof';

    public static function register(): void {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render']);
    }

    /** @param array<string, mixed>|string $chada_travel_atts Unused; the proof page has no shortcode attributes. */
    public static function render($chada_travel_atts = []): string {
        CHADA_TRAVEL_View_Components::send_customer_security_headers();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only Booking ID prefill, no state change.
        $chada_travel_token       = CHADA_TRAVEL_Config::sanitize_request_get('token');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only Booking ID prefill, no state change.
        $chada_travel_booking_id  = CHADA_TRAVEL_Config::sanitize_request_get('booking_id');
        $chada_travel_badge_label = 'Awaiting Proof';
        $chada_travel_badge_status = 'awaiting';

        if ($chada_travel_token !== '') {
            global $wpdb;
            $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_valid_proof_token($wpdb, $chada_travel_token);
            if ($chada_travel_payment) {
                $chada_travel_order      = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, (int) $chada_travel_payment['chada_travel_order_id']);
                $chada_travel_booking_id = (string) ($chada_travel_order['chada_travel_booking_id'] ?? '');
                if ($chada_travel_payment['chada_travel_payment_status'] === 'chada_travel_awaiting_verification') {
                    $chada_travel_badge_label  = 'Awaiting Verification';
                    $chada_travel_badge_status = 'awaiting-verification';
                }
            }
        }

        $chada_travel_settings     = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_upload_rules = CHADA_TRAVEL_Config::get_upload_rules()['payment_proof'];

        $chada_travel_status_bar = '<div class="chada-travel-panel chada-travel-proof-status">'
            . CHADA_TRAVEL_View_Components::badge($chada_travel_badge_label, $chada_travel_badge_status)
            . '<span>Use the Booking ID from your confirmation email.</span></div>';

        $chada_travel_instruction_list = CHADA_TRAVEL_View_Components::instruction_list([
            [
                'title' => 'Deposit to the Company Bank Account',
                'body'  => 'Make the deposit for your visa application to our company bank account.',
            ],
            [
                'title' => 'Take a clear Deposit Slip picture',
                'body'  => 'Take a clear photo of the Deposit Slip showing the transaction details.',
            ],
            [
                'title' => 'Enter Booking ID and upload proof',
                'body'  => 'Enter your Booking ID and upload the Deposit Slip picture in the form.',
            ],
        ]);
        $chada_travel_instructions = CHADA_TRAVEL_View_Components::panel('Payment Instructions', $chada_travel_instruction_list);

        $chada_travel_form_body = CHADA_TRAVEL_View_Components::control([
            'label' => 'Booking ID', 'name' => 'booking_id', 'required' => true, 'value' => $chada_travel_booking_id,
            'full'  => true,
        ]) . CHADA_TRAVEL_View_Components::upload_zone('proof_file', $chada_travel_upload_rules)
            . CHADA_TRAVEL_View_Components::actions([
                ['label' => 'Submit Proof of Payment', 'variant' => 'primary', 'type' => 'submit'],
            ]);
        $chada_travel_form = '<form id="chada-travel-proof-form" novalidate>'
            . '<input type="hidden" name="token" value="' . CHADA_TRAVEL_View_Components::escape_attr($chada_travel_token) . '">'
            . $chada_travel_form_body . '</form>';
        $chada_travel_form_panel = CHADA_TRAVEL_View_Components::panel('Submit Proof of Payment', $chada_travel_form);

        return CHADA_TRAVEL_Template::render('frontend/payment-proof', [
            'chada_travel_brand_header_html'         => CHADA_TRAVEL_View_Components::company_brand_header($chada_travel_settings),
            'chada_travel_status_bar_html'           => $chada_travel_status_bar,
            'chada_travel_main_aside_html'           => CHADA_TRAVEL_View_Components::main_aside(
                $chada_travel_instructions,
                $chada_travel_form_panel
            ),
            'chada_travel_after_submit_notice_html'  => CHADA_TRAVEL_View_Components::notice(
                'After submission: Status changes to Awaiting Verification and email notifications are sent.',
                'info'
            ),
            'chada_travel_actions_html'              => CHADA_TRAVEL_View_Components::actions([
                ['label' => 'Back to Confirmation', 'data' => ['chada-travel-proof-back' => '1']],
            ]),
            'chada_travel_noscript_notice_html'      => CHADA_TRAVEL_View_Components::notice(
                'Enable JavaScript to submit Bank Payment proof.',
                'error'
            ),
            'chada_travel_contact_html'              => CHADA_TRAVEL_View_Components::company_contact_block($chada_travel_settings),
        ]);
    }
}
