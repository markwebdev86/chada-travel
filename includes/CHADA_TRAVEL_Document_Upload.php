<?php
/**
 * Public protected visa-document upload/checklist page, matching the approved wireframe's protected-page
 * conventions (same layout language as CHADA_TRAVEL_Payment_Proof). Reachable only from the emailed secure link
 * (?token=...) or the paid Stage 5 "Upload Initial Files"/"View Guide & Checklists" actions; PHP renders only
 * the fixed shell, chada-travel-document-upload.js fills the real per-application requirement list from
 * documents/list once the token is verified server-side. Does not display the Stage 1-4 progress row.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Document_Upload {
    public const SHORTCODE_TAG = 'chada_travel_visa_documents';

    public static function register(): void {
        add_shortcode(self::SHORTCODE_TAG, [self::class, 'render']);
    }

    /** @param array<string, mixed>|string $chada_travel_atts Unused; the upload page has no shortcode attributes. */
    public static function render($chada_travel_atts = []): string {
        CHADA_TRAVEL_View_Components::send_customer_security_headers();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only token prefill, no state change.
        $chada_travel_token    = CHADA_TRAVEL_Config::sanitize_request_get('token');
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();

        return CHADA_TRAVEL_Template::render('frontend/document-upload', [
            'chada_travel_brand_header_html'    => CHADA_TRAVEL_View_Components::company_brand_header($chada_travel_settings),
            'chada_travel_token'                => $chada_travel_token,
            'chada_travel_actions_html'         => CHADA_TRAVEL_View_Components::actions([
                ['label' => 'Return to Home', 'data' => ['chada-travel-documents-back' => '1']],
            ]),
            'chada_travel_noscript_notice_html' => CHADA_TRAVEL_View_Components::notice(
                'Enable JavaScript to view and upload visa documents.',
                'error'
            ),
            'chada_travel_contact_html'         => CHADA_TRAVEL_View_Components::company_contact_block($chada_travel_settings),
        ]);
    }
}
