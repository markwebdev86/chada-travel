<?php
/**
 * Safe REST endpoints for the Stage 1-3 checkout. PHP remains authoritative for validation, fee snapshots, and totals.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Rest_Controller {
    private const NONCE_ACTION = 'chada_travel_checkout_nonce';
    private const PROOF_NONCE_ACTION = 'chada_travel_payment_proof_nonce';
    private const UPLOAD_NONCE_ACTION = 'chada_travel_upload_nonce';
    private const RATE_LIMIT_MAX_REQUESTS = 20;
    private const RATE_LIMIT_WINDOW_SECONDS = 300;

    public static function register_routes(): void {
        foreach (self::get_route_definitions() as $chada_travel_path => $chada_travel_args) {
            register_rest_route(CHADA_TRAVEL_REST_NAMESPACE, $chada_travel_path, $chada_travel_args);
        }
    }

    /** @return array<string, array<string, mixed>> route path => WP_REST_Server route args. */
    private static function get_route_definitions(): array {
        $chada_travel_routes = [
            '/checkout/booker' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_booker'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/applications' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_applications'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/review' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_review'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/cancel' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_cancel'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/payments/bank/select' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_bank_select'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/payments/digital-wallet/submit' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_digital_wallet_submit'],
                'permission_callback' => '__return_true',
            ],
            // Public: authenticated by the one-time proof token plus the proof nonce and guest-page binding.
            '/payments/proof/submit' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_proof_submit'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/confirmation' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_confirmation'],
                'permission_callback' => '__return_true',
            ],
            '/checkout/uploads/link' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_upload_link'],
                'permission_callback' => '__return_true',
            ],
            // Public: authenticated by the emailed/minted upload token (chada_travel_upload_nonce), not the checkout nonce.
            '/documents/list' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_documents_list'],
                'permission_callback' => '__return_true',
            ],
            '/documents/upload' => [
                'methods' => 'POST', 'callback' => [self::class, 'handle_document_upload'],
                'permission_callback' => '__return_true',
            ],
        ];
        return function_exists('apply_filters')
            ? (array) apply_filters('chada_travel_rest_route_definitions', $chada_travel_routes)
            : $chada_travel_routes;
    }

    public static function handle_booker(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'booker');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_result = CHADA_TRAVEL_Checkout_Validator::validate_booker($chada_travel_params);
        if ($chada_travel_result['errors']) {
            return self::error_response(422, 'chada_travel_invalid_booker', 'Correct the highlighted fields.', [
                'errors' => $chada_travel_result['errors'],
            ]);
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_token    = (string) ($chada_travel_params['draft_token'] ?? '');
        $chada_travel_order    = $chada_travel_token !== '' ? CHADA_TRAVEL_Order_Repository::find_draft_by_token($wpdb, $chada_travel_token) : null;

        // An expired-but-real draft must never be silently treated as "no such token" - that would fall through
        // to the create-new-draft branch below and silently create a second draft from an expired token.
        if ($chada_travel_order && CHADA_TRAVEL_Order_Repository::is_draft_expired(
            $chada_travel_order,
            CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours($chada_travel_settings)
        )) {
            return self::draft_expired_response();
        }

        if ($chada_travel_order) {
            if (!empty($chada_travel_order['chada_travel_policy_snapshot'])) {
                // Existing order already has an immutable snapshot - continues under it unchanged, regardless
                // of what the current live Settings policy bundle looks like now.
                $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_booker($wpdb, $chada_travel_order, $chada_travel_result['clean']);
            } else {
            // An existing active draft created before chada_travel_policy_snapshot existed: it must re-consent under
                // the current Ready bundle before it can be granted its first snapshot.
                $chada_travel_policy_guard = self::guard_policy_bundle_ready($chada_travel_settings);
                if ($chada_travel_policy_guard) {
                    return $chada_travel_policy_guard;
                }
                $chada_travel_snapshot_json = CHADA_TRAVEL_Policy_Snapshot::encode(CHADA_TRAVEL_Policy_Snapshot::build($chada_travel_settings));
                $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_booker(
                    $wpdb,
                    $chada_travel_order,
                    $chada_travel_result['clean'],
                    (string) $chada_travel_settings['chada_travel_policy_version'],
                    $chada_travel_snapshot_json
                );
            }
            $chada_travel_draft_token = $chada_travel_token;
        } else {
            // A forged/direct request attempting to start a brand-new checkout draft while Company Status is
            // "Under Maintenance" is rejected server-side; an already-in-progress draft (resolved above) is
            // never blocked here, so a Booker mid-checkout when maintenance is switched on can still finish.
            if (!CHADA_TRAVEL_Config::is_accepting_new_applications($chada_travel_settings)) {
                return self::error_response(
                    503,
                    'chada_travel_maintenance_mode',
                    (string) $chada_travel_settings['chada_travel_company_maintenance_message']
                );
            }
            // A brand-new draft can never be created while the current policy bundle is not Ready - this is
            // the authoritative server-side enforcement; the browser's own notice is never relied on alone.
            $chada_travel_policy_guard = self::guard_policy_bundle_ready($chada_travel_settings);
            if ($chada_travel_policy_guard) {
                return $chada_travel_policy_guard;
            }
            $chada_travel_snapshot_json = CHADA_TRAVEL_Policy_Snapshot::encode(CHADA_TRAVEL_Policy_Snapshot::build($chada_travel_settings));
            $chada_travel_created     = CHADA_TRAVEL_Order_Repository::create_draft(
                $wpdb,
                $chada_travel_result['clean'],
                (string) $chada_travel_settings['chada_travel_policy_version'],
                (string) $chada_travel_settings['chada_travel_currency'],
                $chada_travel_snapshot_json
            );
            $chada_travel_order       = $chada_travel_created['order'];
            $chada_travel_draft_token = $chada_travel_created['draft_token'];
        }

        return self::success_response([
            'draftToken'       => $chada_travel_draft_token,
            'checkoutStage'    => (int) $chada_travel_order['chada_travel_checkout_stage'],
            'accessibleStages' => self::accessible_stages($chada_travel_order),
            'booker'           => $chada_travel_result['clean'],
            'policy'           => self::browser_safe_policy($chada_travel_order, $chada_travel_settings),
        ]);
    }

    /**
     * Server-authoritative guard blocking a new policy snapshot (a brand-new draft, or an existing draft's
     * first-time snapshot) from ever being created while the current policy bundle is not Ready. Never relies
     * on the browser having already shown a disabled control.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function guard_policy_bundle_ready(array $chada_travel_settings): ?\WP_REST_Response {
        if (CHADA_TRAVEL_Policy_Settings::bundle_status($chada_travel_settings)['ready']) {
            return null;
        }
        $chada_travel_message = 'New visa applications are temporarily unavailable while required policy information '
            . 'is being finalized. Please try again later.';
        $chada_travel_support_email = trim((string) ($chada_travel_settings['chada_travel_company_support_email'] ?? ''));
        if ($chada_travel_support_email !== '') {
            $chada_travel_message .= ' Contact ' . $chada_travel_support_email . ' for assistance.';
        }
        return self::error_response(503, 'chada_travel_policy_bundle_not_ready', $chada_travel_message);
    }

    /**
     * Returns only the browser-safe policy metadata needed to render/refresh the Stage 1 and Stage 3 consent
     * links, Policy Version, and Effective Date: the order's own immutable snapshot once one exists, otherwise
     * (defensively - every code path above always attaches a snapshot before returning) the current resolved
     * bundle. Never returns a page id, a raw Settings option, or any unrelated order/administrator data.
     *
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_settings
     * @return array{
     *     version: string, effectiveDate: string, privacyUrl: string, termsUrl: string,
     *     cancellationRefundUrl: string
     * }
     */
    private static function browser_safe_policy(array $chada_travel_order, array $chada_travel_settings): array {
        $chada_travel_decoded = CHADA_TRAVEL_Policy_Snapshot::decode($chada_travel_order['chada_travel_policy_snapshot'] ?? null);
        if ($chada_travel_decoded === null) {
            $chada_travel_bundle = CHADA_TRAVEL_Policy_Settings::resolve_effective_bundle($chada_travel_settings);
            return [
                'version'               => (string) $chada_travel_settings['chada_travel_policy_version'],
                'effectiveDate'         => (string) $chada_travel_settings['chada_travel_policy_effective_date'],
                'privacyUrl'            => (string) ($chada_travel_bundle['privacy']['url'] ?? ''),
                'termsUrl'              => (string) ($chada_travel_bundle['terms']['url'] ?? ''),
                'cancellationRefundUrl' => (string) ($chada_travel_bundle['cancellation_refund']['url'] ?? ''),
            ];
        }
        return [
            'version'               => (string) $chada_travel_decoded['policy_version'],
            'effectiveDate'         => (string) $chada_travel_decoded['effective_date'],
            'privacyUrl'            => (string) ($chada_travel_decoded['privacy']['url'] ?? ''),
            'termsUrl'              => (string) ($chada_travel_decoded['terms']['url'] ?? ''),
            'cancellationRefundUrl' => (string) ($chada_travel_decoded['cancellation_refund']['url'] ?? ''),
        ];
    }

    public static function handle_applications(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'applications');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_draft_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        if (CHADA_TRAVEL_Order_Repository::is_draft_expired(
            $chada_travel_order,
            CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours($chada_travel_settings)
        )) {
            return self::draft_expired_response();
        }
        // A draft is the only status Stage 2 (and therefore replace_applications()'s full delete-and-recreate of
        // every applicant/application row) may ever run against - the real UI never resubmits Stage 2 once an
        // order leaves chada_travel_draft, and requiring it explicitly here closes a forged-request path that
        // can_access_stage() alone would not: its stage-access map allows requested stage 2 whenever an order has
        // ever reached checkout_stage 2+, regardless of a later chada_travel_paid/chada_travel_completed status. This matters
        // once an administrator can edit an applicant's Date of Birth/Target Travel Date, so that edit can never
        // be silently wiped by a later Stage 2 resubmission.
        if ((string) $chada_travel_order['chada_travel_order_status'] !== 'chada_travel_draft' || !CHADA_TRAVEL_Workflow::can_access_stage(
            (string) $chada_travel_order['chada_travel_order_status'],
            2,
            (int) $chada_travel_order['chada_travel_checkout_stage']
        )) {
            return self::error_response(409, 'chada_travel_stage_locked', 'Complete Booker Details before continuing.');
        }

        $chada_travel_active_countries = CHADA_TRAVEL_Checkout_Validator::get_active_countries(
            (array) $chada_travel_settings['chada_travel_country_fees'],
            CHADA_TRAVEL_Config::get_currency_code($chada_travel_settings)
        );
        $chada_travel_travel_date_bounds = CHADA_TRAVEL_Config::get_target_travel_date_bounds($chada_travel_settings);
        $chada_travel_result = CHADA_TRAVEL_Checkout_Validator::validate_applications(
            (array) ($chada_travel_params['applications'] ?? []),
            $chada_travel_active_countries,
            CHADA_TRAVEL_Booking_Workflow_Config::max_applicants($chada_travel_settings),
            [
                'first_name' => (string) $chada_travel_order['chada_travel_booker_first_name'],
                'last_name'  => (string) $chada_travel_order['chada_travel_booker_last_name'],
            ],
            $chada_travel_travel_date_bounds['min']
        );
        if ($chada_travel_result['has_error']) {
            return self::error_response(422, 'chada_travel_invalid_applications', 'Correct the highlighted applications.', [
                'errors' => $chada_travel_result['errors'],
            ]);
        }

        $chada_travel_totals = CHADA_TRAVEL_Checkout_Validator::calculate_totals($chada_travel_result['clean'], $chada_travel_active_countries);
        try {
            $chada_travel_saved = CHADA_TRAVEL_Order_Repository::replace_applications(
                $wpdb,
                $chada_travel_order,
                $chada_travel_result['clean'],
                $chada_travel_totals
            );
        } catch (\Throwable $chada_travel_error) {
            return self::error_response(
                500,
                'chada_travel_application_save_failed',
                'The applications could not be saved. Your entered information has been retained in this browser.'
            );
        }

        return self::success_response([
            'checkoutStage'    => (int) $chada_travel_saved['order']['chada_travel_checkout_stage'],
            'accessibleStages' => self::accessible_stages($chada_travel_saved['order']),
            'applications'     => $chada_travel_saved['applications'],
            'subtotal'         => $chada_travel_totals['subtotal'],
            'total'            => $chada_travel_totals['total'],
            'currency'         => $chada_travel_totals['currency'],
            'warnings'         => $chada_travel_result['warnings'],
            'summary'          => $chada_travel_totals['summary'],
        ]);
    }

    public static function handle_cancel(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'cancel');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }
        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_session_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::error_response(404, 'chada_travel_draft_not_found', 'This Visa Application could not be found.');
        }
        if (CHADA_TRAVEL_Order_Repository::is_draft_expired(
            $chada_travel_order,
            CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours(CHADA_TRAVEL_Config::get_settings())
        )) {
            return self::draft_expired_response();
        }
        $chada_travel_result = CHADA_TRAVEL_Checkout_Cancellation_Service::cancel($wpdb, $chada_travel_order);
        if (!$chada_travel_result['cancelled']) {
            return self::error_response(
                $chada_travel_result['status'],
                $chada_travel_result['code'],
                $chada_travel_result['message']
            );
        }
        return self::success_response([
            'cancelled'        => true,
            'orderStatus'      => 'chada_travel_cancelled',
            'accessibleStages' => [1],
        ]);
    }

    public static function handle_review(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'review');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_draft_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }
        if (CHADA_TRAVEL_Order_Repository::is_draft_expired(
            $chada_travel_order,
            CHADA_TRAVEL_Booking_Workflow_Config::draft_expiry_hours(CHADA_TRAVEL_Config::get_settings())
        )) {
            return self::draft_expired_response();
        }
        if (!CHADA_TRAVEL_Workflow::can_access_stage(
            (string) $chada_travel_order['chada_travel_order_status'],
            3,
            (int) $chada_travel_order['chada_travel_checkout_stage']
        )) {
            return self::error_response(
                409,
                'chada_travel_stage_locked',
                'Add at least one Visa Application before continuing.'
            );
        }
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications($wpdb, (int) $chada_travel_order['chada_travel_order_id']);
        if (!$chada_travel_applications) {
            return self::error_response(
                409,
                'chada_travel_no_applications',
                'Add at least one Visa Application before continuing.'
            );
        }

        $chada_travel_result = CHADA_TRAVEL_Checkout_Validator::validate_review($chada_travel_params);
        if ($chada_travel_result['errors']) {
            return self::error_response(
                422,
                'chada_travel_invalid_review',
                'Acknowledge the Cancellation and Refund Policy.',
                ['errors' => $chada_travel_result['errors']]
            );
        }

        $chada_travel_order = CHADA_TRAVEL_Order_Repository::record_review_consent($wpdb, $chada_travel_order);

        return self::success_response([
            'checkoutStage'         => (int) $chada_travel_order['chada_travel_checkout_stage'],
            'accessibleStages'      => self::accessible_stages($chada_travel_order),
            'acknowledged'          => true,
            'paymentStageAvailable' => true,
        ]);
    }

    public static function handle_bank_select(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'payments_bank_select');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_session_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_guard_method = self::guard_method_available(CHADA_TRAVEL_Payment_Service::METHOD_BANK, $chada_travel_settings);
        if ($chada_travel_guard_method) {
            return $chada_travel_guard_method;
        }

        $chada_travel_result = CHADA_TRAVEL_Payment_Service::select_bank_payment($wpdb, $chada_travel_order, $chada_travel_settings);
        if ($chada_travel_result['errors']) {
            return self::error_response(409, 'chada_travel_payment_not_allowed', $chada_travel_result['errors'][0]);
        }

        return self::success_response([
            'bookingId'        => $chada_travel_result['order']['chada_travel_booking_id'],
            'paymentMethod'    => $chada_travel_result['payment']['chada_travel_payment_method'],
            'paymentStatus'    => $chada_travel_result['payment']['chada_travel_payment_status'],
            'proofUrl'         => !empty($chada_travel_result['proof_token'])
                ? CHADA_TRAVEL_Config::get_payment_proof_url((string) $chada_travel_result['proof_token']) : '',
            'accessibleStages' => self::accessible_stages($chada_travel_result['order']),
        ]);
    }

    public static function handle_digital_wallet_submit(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'payments_digital_wallet_submit');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_session_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_guard_method = self::guard_method_available(
            CHADA_TRAVEL_Payment_Service::METHOD_DIGITAL_WALLET,
            $chada_travel_settings
        );
        if ($chada_travel_guard_method) {
            return $chada_travel_guard_method;
        }

        $chada_travel_result = CHADA_TRAVEL_Payment_Service::submit_digital_wallet_payment(
            $wpdb,
            $chada_travel_order,
            $chada_travel_settings,
            (string) ($chada_travel_params['digital_wallet_reference_no'] ?? '')
        );
        if ($chada_travel_result['errors']) {
            return self::error_response(422, 'chada_travel_invalid_digital_wallet_reference', $chada_travel_result['errors'][0]);
        }

        return self::success_response([
            'bookingId'        => $chada_travel_result['order']['chada_travel_booking_id'],
            'paymentMethod'    => $chada_travel_result['payment']['chada_travel_payment_method'],
            'paymentStatus'    => $chada_travel_result['payment']['chada_travel_payment_status'],
            'digitalWalletReferenceNo' => CHADA_TRAVEL_Payment_Service::resolve_reference_no($chada_travel_result['payment']),
            'accessibleStages' => self::accessible_stages($chada_travel_result['order']),
        ]);
    }

    /** Stage 5 confirmation payload: PHP remains authoritative for which single status-specific view is real. */
    public static function handle_confirmation(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'checkout_confirmation');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_session_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }
        $chada_travel_payment_status = (string) $chada_travel_order['chada_travel_payment_status'];
        if (!CHADA_TRAVEL_Workflow::can_access_stage(
            (string) $chada_travel_order['chada_travel_order_status'],
            5,
            (int) $chada_travel_order['chada_travel_checkout_stage']
        ) || !CHADA_TRAVEL_Workflow::is_stage_five_ready($chada_travel_payment_status)) {
            return self::error_response(409, 'chada_travel_confirmation_not_ready', 'Payment confirmation is not ready yet.');
        }

        $chada_travel_order_id     = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_settings     = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_payment      = CHADA_TRAVEL_Payment_Repository::get_active_payment($wpdb, $chada_travel_order_id);
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications($wpdb, $chada_travel_order_id);
        return self::success_response([
            'bookingId'          => $chada_travel_order['chada_travel_booking_id'],
            'orderStatus'        => $chada_travel_order['chada_travel_order_status'],
            'paymentStatus'      => $chada_travel_payment_status,
            'paymentMethod'      => $chada_travel_payment['chada_travel_payment_method'] ?? null,
            'checkoutStage'      => (int) $chada_travel_order['chada_travel_checkout_stage'],
            'accessibleStages'   => self::accessible_stages($chada_travel_order),
            'canReturnToPayment' => CHADA_TRAVEL_Workflow::can_retry_payment($chada_travel_payment_status),
            'booker'             => [
                'firstName' => $chada_travel_order['chada_travel_booker_first_name'],
                'lastName'  => $chada_travel_order['chada_travel_booker_last_name'],
            ],
            'currency'  => $chada_travel_order['chada_travel_currency'],
            'subtotal'  => $chada_travel_order['chada_travel_subtotal'],
            'total'     => $chada_travel_order['chada_travel_total'],
            'createdAt' => CHADA_TRAVEL_Config::format_utc_datetime(
                (string) $chada_travel_order['chada_travel_created_at'],
                $chada_travel_settings
            ),
            'services'  => array_map(static fn(array $chada_travel_application): array => [
                'label'  => $chada_travel_application['chada_travel_country_name_snapshot'] . ' Visa Processing - '
                    . trim($chada_travel_application['chada_travel_first_name'] . ' ' . $chada_travel_application['chada_travel_last_name']),
                'amount' => $chada_travel_application['chada_travel_processing_fee_snapshot'],
            ], $chada_travel_applications),
            'payment' => [
                'transactionId'      => $chada_travel_payment['chada_travel_transaction_id'] ?? null,
                'paidAt'             => !empty($chada_travel_payment['chada_travel_paid_at'])
                    ? CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_payment['chada_travel_paid_at'], $chada_travel_settings)
                    : null,
                'digitalWalletReferenceNo' => $chada_travel_payment
                    ? CHADA_TRAVEL_Payment_Service::resolve_reference_no($chada_travel_payment) : null,
                'rejectionReason'    => $chada_travel_payment['chada_travel_rejection_reason'] ?? null,
                'providerCaptureId'  => null,
            ],
            'uploadAvailable' => CHADA_TRAVEL_Workflow::can_release_paid_resources($chada_travel_payment_status),
        ]);
    }

    /** Mints a fresh hashed/expiring visa-document upload link for the current session's paid order. */
    public static function handle_upload_link(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_request($chada_travel_request, 'checkout_uploads_link');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_order  = self::resolve_session_order($wpdb, $chada_travel_params);
        if (!$chada_travel_order) {
            return self::draft_not_found_response();
        }

        $chada_travel_result = CHADA_TRAVEL_Document_Service::issue_upload_link($wpdb, $chada_travel_order, CHADA_TRAVEL_Config::get_settings());
        if ($chada_travel_result['errors']) {
            return self::error_response(409, 'chada_travel_upload_not_available', $chada_travel_result['errors'][0]);
        }

        return self::success_response(['uploadUrl' => CHADA_TRAVEL_Config::get_document_upload_url($chada_travel_result['token'])]);
    }

    /** Public: authenticated by the emailed/minted visa-document upload token, not the checkout nonce. */
    public static function handle_documents_list(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_upload_request($chada_travel_request, 'documents_list');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params = self::json_params($chada_travel_request);
        $chada_travel_result = CHADA_TRAVEL_Document_Service::build_checklist($wpdb, (string) ($chada_travel_params['token'] ?? ''));
        if ($chada_travel_result['errors']) {
            return self::error_response(404, 'chada_travel_upload_token_invalid', $chada_travel_result['errors'][0]);
        }

        return self::success_response([
            'bookingId'    => $chada_travel_result['order']['chada_travel_booking_id'],
            'applications' => $chada_travel_result['applications'],
        ]);
    }

    /** Public: authenticated by the emailed/minted visa-document upload token, not the checkout nonce. */
    public static function handle_document_upload(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_upload_request($chada_travel_request, 'documents_upload');
        if ($chada_travel_guard) {
            return $chada_travel_guard;
        }

        global $wpdb;
        $chada_travel_params         = $chada_travel_request->get_params();
        $chada_travel_token          = (string) ($chada_travel_params['token'] ?? '');
        $chada_travel_application_id = (int) ($chada_travel_params['application_id'] ?? 0);
        $chada_travel_requirement_id = (int) ($chada_travel_params['requirement_id'] ?? 0);
        $chada_travel_files          = $chada_travel_request->get_file_params();
        $chada_travel_file           = (array) ($chada_travel_files['document_file'] ?? []);
        $chada_travel_uploaded_by    = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        $chada_travel_result = CHADA_TRAVEL_Document_Service::upload(
            $wpdb,
            $chada_travel_token,
            $chada_travel_application_id,
            $chada_travel_requirement_id,
            $chada_travel_file,
            $chada_travel_uploaded_by > 0 ? $chada_travel_uploaded_by : null
        );
        if ($chada_travel_result['errors']) {
            return self::error_response(422, 'chada_travel_invalid_document', $chada_travel_result['errors'][0]);
        }

        return self::success_response([
            'documentStatus' => $chada_travel_result['document']['chada_travel_document_status'],
            'filename'       => $chada_travel_result['document']['chada_travel_original_filename'],
            'fileStatus'     => $chada_travel_result['fileStatus'],
        ]);
    }

    /** Public endpoint: reachable from the emailed secure link or the Bank proof page without a draft token. */
    public static function handle_proof_submit(\WP_REST_Request $chada_travel_request): \WP_REST_Response {
        $chada_travel_guard = self::guard_nonce_and_guest_binding($chada_travel_request, self::PROOF_NONCE_ACTION);
        if ($chada_travel_guard) return $chada_travel_guard;
        if (self::is_rate_limited('payments_proof_submit')) {
            return self::error_response(429, 'chada_travel_rate_limited', 'Too many requests. Wait a moment and try again.');
        }

        global $wpdb;
        $chada_travel_params     = $chada_travel_request->get_params();
        $chada_travel_booking_id = trim((string) ($chada_travel_params['booking_id'] ?? ''));
        $chada_travel_token      = (string) ($chada_travel_params['token'] ?? '');
        if ($chada_travel_token === '') {
            return self::error_response(403, 'chada_travel_proof_token_required', 'Open the secure proof link from your email.');
        }

        $chada_travel_resolved = self::resolve_proof_by_token($wpdb, $chada_travel_token);
        if (!$chada_travel_resolved || ($chada_travel_booking_id !== ''
            && !hash_equals((string) $chada_travel_resolved['order']['chada_travel_booking_id'], $chada_travel_booking_id))) {
            // Deliberately generic: never confirms or denies whether a Booking ID exists.
            return self::error_response(
                404,
                'chada_travel_proof_not_found',
                'We could not match that Booking ID. Check it and try again.'
            );
        }

        $chada_travel_files       = $chada_travel_request->get_file_params();
        $chada_travel_file        = (array) ($chada_travel_files['proof_file'] ?? []);
        $chada_travel_upload_rules = CHADA_TRAVEL_Config::get_upload_rules()['payment_proof'];
        $chada_travel_result = CHADA_TRAVEL_Payment_Service::submit_bank_proof(
            $wpdb,
            $chada_travel_resolved['order'],
            $chada_travel_resolved['payment'],
            $chada_travel_file,
            $chada_travel_upload_rules
        );
        if ($chada_travel_result['errors']) {
            return self::error_response(422, 'chada_travel_invalid_proof', $chada_travel_result['errors'][0]);
        }

        return self::success_response([
            'bookingId'     => $chada_travel_resolved['order']['chada_travel_booking_id'],
            'paymentStatus' => $chada_travel_result['payment']['chada_travel_payment_status'],
            'duplicate'     => $chada_travel_result['duplicate'] ?? false,
        ]);
    }

    /** @return array{order: array<string, mixed>, payment: array<string, mixed>}|null */
    private static function resolve_proof_by_token(object $chada_travel_wpdb, string $chada_travel_token): ?array {
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_valid_proof_token($chada_travel_wpdb, $chada_travel_token);
        if (!$chada_travel_payment) {
            return null;
        }
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($chada_travel_wpdb, (int) $chada_travel_payment['chada_travel_order_id']);
        return $chada_travel_order ? ['order' => $chada_travel_order, 'payment' => $chada_travel_payment] : null;
    }

    /**
     * Resolves the Stage 1-3 draft only; used where re-editing an order that has already moved off chada_travel_draft
     * must not be allowed.
     *
     * @param array<string, mixed> $chada_travel_params
     * @return array<string, mixed>|null
     */
    private static function resolve_draft_order(object $chada_travel_wpdb, array $chada_travel_params): ?array {
        return CHADA_TRAVEL_Order_Repository::find_draft_by_token($chada_travel_wpdb, (string) ($chada_travel_params['draft_token'] ?? ''));
    }

    /**
     * Resolves the same browser-session token at any order status. Stage 4 payment handlers need this: the
     * first successful call (Bank select or Digital Wallet submit) moves the order off chada_travel_draft,
     * so a second call for the same session can still resolve the order,
     * create-order) must still resolve the order instead of 404ing.
     *
     * @param array<string, mixed> $chada_travel_params
     * @return array<string, mixed>|null
     */
    private static function resolve_session_order(object $chada_travel_wpdb, array $chada_travel_params): ?array {
        return CHADA_TRAVEL_Order_Repository::find_by_draft_token($chada_travel_wpdb, (string) ($chada_travel_params['draft_token'] ?? ''));
    }

    private static function draft_not_found_response(): \WP_REST_Response {
        return self::error_response(404, 'chada_travel_draft_not_found', 'Start again from Booker Details.');
    }

    /**
     * A safe, stable response for a draft that resolved (a real, matching token) but is inactivity-expired.
     * Deliberately distinct from draft_not_found_response()'s 404 - this never discloses whether an unknown
     * token exists, since it is only ever returned once a row has already been found by hash match.
     */
    private static function draft_expired_response(): \WP_REST_Response {
        return self::error_response(
            410,
            'chada_travel_draft_expired',
            'This booking session has expired. Start again from Booker Details.'
        );
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @return list<int>
     */
    private static function accessible_stages(array $chada_travel_order): array {
        $chada_travel_accessible = [];
        for ($chada_travel_stage = 1; $chada_travel_stage <= 5; $chada_travel_stage++) {
            if (CHADA_TRAVEL_Workflow::can_access_stage(
                (string) $chada_travel_order['chada_travel_order_status'],
                $chada_travel_stage,
                (int) $chada_travel_order['chada_travel_checkout_stage']
            )) {
                $chada_travel_accessible[] = $chada_travel_stage;
            }
        }
        return $chada_travel_accessible;
    }

    /** @return array<string, mixed> */
    private static function json_params(\WP_REST_Request $chada_travel_request): array {
        $chada_travel_json = $chada_travel_request->get_json_params();
        return is_array($chada_travel_json) && $chada_travel_json ? $chada_travel_json : $chada_travel_request->get_params();
    }

    /** Verifies the project checkout nonce and enforces a simple per-IP rate limit. */
    private static function guard_request(\WP_REST_Request $chada_travel_request, string $chada_travel_action): ?\WP_REST_Response {
        $chada_travel_nonce = (string) $chada_travel_request->get_header('X-CHADA-TRAVEL-Nonce');
        if ($chada_travel_nonce === '' || !wp_verify_nonce($chada_travel_nonce, self::NONCE_ACTION)) {
            return self::error_response(
                403,
                'chada_travel_invalid_nonce',
                'Your session expired. Reload the page and try again.'
            );
        }
        $chada_travel_guest_guard = self::guard_guest_binding($chada_travel_request);
        if ($chada_travel_guest_guard) return $chada_travel_guest_guard;
        if (self::is_rate_limited($chada_travel_action)) {
            return self::error_response(429, 'chada_travel_rate_limited', 'Too many requests. Wait a moment and try again.');
        }
        return null;
    }

    /** Verifies the visa-document upload nonce (distinct from the checkout nonce) and enforces rate limiting. */
    private static function guard_upload_request(
        \WP_REST_Request $chada_travel_request,
        string $chada_travel_action
    ): ?\WP_REST_Response {
        $chada_travel_nonce = (string) $chada_travel_request->get_header('X-CHADA-TRAVEL-Nonce');
        if ($chada_travel_nonce === '' || !wp_verify_nonce($chada_travel_nonce, self::UPLOAD_NONCE_ACTION)) {
            return self::error_response(
                403,
                'chada_travel_invalid_nonce',
                'Your session expired. Reload the page and try again.'
            );
        }
        $chada_travel_guest_guard = self::guard_guest_binding($chada_travel_request);
        if ($chada_travel_guest_guard) return $chada_travel_guest_guard;
        if (self::is_rate_limited($chada_travel_action)) {
            return self::error_response(429, 'chada_travel_rate_limited', 'Too many requests. Wait a moment and try again.');
        }
        return null;
    }

    /** Verifies an action nonce and the page-bound guest token; neither control is treated as user authentication. */
    private static function guard_nonce_and_guest_binding(\WP_REST_Request $chada_travel_request, string $chada_travel_action): ?\WP_REST_Response {
        $chada_travel_nonce = (string) $chada_travel_request->get_header('X-CHADA-TRAVEL-Nonce');
        if ($chada_travel_nonce === '' || !wp_verify_nonce($chada_travel_nonce, $chada_travel_action)) {
            return self::error_response(403, 'chada_travel_invalid_nonce', 'Your session expired. Reload the page and try again.');
        }
        return self::guard_guest_binding($chada_travel_request);
    }

    /** Requires the unguessable token emitted by the same guest page that received the nonce. */
    private static function guard_guest_binding(\WP_REST_Request $chada_travel_request): ?\WP_REST_Response {
        $chada_travel_guest = (string) $chada_travel_request->get_header('X-CHADA-TRAVEL-Guest');
        if (!CHADA_TRAVEL_Config::has_valid_guest_request_token($chada_travel_guest)) {
            return self::error_response(403, 'chada_travel_invalid_guest_binding', 'Reload this page and try again.');
        }
        return null;
    }

    /**
     * Server-authoritative guard against a forged request selecting a disabled or not-yet-ready payment
     * method: the browser only ever offers enabled-and-ready methods, but this re-checks the same resolver
     * here so a direct/forged REST call can never select a method the Settings page would not otherwise allow.
     *
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function guard_method_available(string $chada_travel_method, array $chada_travel_settings): ?\WP_REST_Response {
        if (in_array($chada_travel_method, CHADA_TRAVEL_Payment_Readiness::get_enabled_ready_methods($chada_travel_settings), true)) {
            return null;
        }
        return self::error_response(
            409,
            'chada_travel_payment_method_unavailable',
            'This payment method is not currently available. Choose another payment method.'
        );
    }

    private static function is_rate_limited(string $chada_travel_action): bool {
        $chada_travel_ip = self::client_ip();
        $chada_travel_guest = CHADA_TRAVEL_Config::sanitize_request_cookie('chada_travel_guest_request');
        $chada_travel_key = 'chada_travel_rl_' . hash('sha256', $chada_travel_action . '|' . $chada_travel_ip . '|' . $chada_travel_guest);
        $chada_travel_lock_key = $chada_travel_key . '_lock';
        $chada_travel_lock_acquired = !function_exists('wp_cache_add')
            || wp_cache_add($chada_travel_lock_key, 1, 'chada_travel_rate_limits', 1);
        if (!$chada_travel_lock_acquired) {
            return true;
        }
        $chada_travel_count = (int) get_transient($chada_travel_key);
        if ($chada_travel_count >= self::RATE_LIMIT_MAX_REQUESTS) {
            if (function_exists('wp_cache_delete')) wp_cache_delete($chada_travel_lock_key, 'chada_travel_rate_limits');
            return true;
        }
        set_transient($chada_travel_key, $chada_travel_count + 1, self::RATE_LIMIT_WINDOW_SECONDS);
        if (function_exists('wp_cache_delete')) wp_cache_delete($chada_travel_lock_key, 'chada_travel_rate_limits');
        return false;
    }

    private static function client_ip(): string {
        return CHADA_TRAVEL_Config::sanitize_server_value('REMOTE_ADDR');
    }

    /** @param array<string, mixed> $chada_travel_data */
    private static function success_response(array $chada_travel_data): \WP_REST_Response {
        return new \WP_REST_Response(array_merge(['success' => true], $chada_travel_data), 200);
    }

    /** @param array<string, mixed> $chada_travel_extra */
    private static function error_response(
        int $chada_travel_status,
        string $chada_travel_code,
        string $chada_travel_message,
        array $chada_travel_extra = []
    ): \WP_REST_Response {
        return new \WP_REST_Response(array_merge([
            'success' => false, 'code' => $chada_travel_code, 'message' => $chada_travel_message,
        ], $chada_travel_extra), $chada_travel_status);
    }
}
