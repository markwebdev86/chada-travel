<?php
/**
 * Code-owned checkout stages, transitions, email triggers, and release rules.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Workflow {
    /**
     * Returns customer stages in their immutable sequence.
     *
     * @return array<int, array<string, string>>
     */
    public static function get_checkout_stages(): array {
        return [
            1 => ['slug' => 'visa-countries', 'label' => 'Visa Countries'],
            2 => ['slug' => 'booker-applicants', 'label' => 'Booker and Applicants'],
            3 => ['slug' => 'review-edit', 'label' => 'Review and Edit'],
            4 => ['slug' => 'payment', 'label' => 'Payment'],
            5 => ['slug' => 'confirmation', 'label' => 'Confirmation'],
        ];
    }

    /** @return array<string, string> */
    public static function get_status_labels(): array {
        return [
            'chada_travel_draft'                      => 'Draft',
            'chada_travel_pending_payment'            => 'Pending Payment',
            'chada_travel_payment_review'             => 'Payment Review',
            'chada_travel_paid'                       => 'Paid',
            'chada_travel_in_progress'                => 'In Progress',
            'chada_travel_completed'                  => 'Completed',
            'chada_travel_payment_rejected'           => 'Payment Rejected',
            'chada_travel_cancelled'                  => 'Cancelled',
            'chada_travel_refunded'                   => 'Refunded',
            'chada_travel_archived'                   => 'Archived',
            'chada_travel_unpaid'                     => 'Unpaid',
            'chada_travel_processing'                 => 'Processing',
            'chada_travel_awaiting_proof'             => 'Awaiting Proof',
            'chada_travel_awaiting_verification'      => 'Awaiting Verification',
            'chada_travel_rejected'                   => 'Rejected',
            'chada_travel_failed'                     => 'Failed',
            'chada_travel_no_submitted_files'         => 'No Submitted Files',
            'chada_travel_partially_submitted_files'  => 'Partially Submitted Files',
            'chada_travel_submitted_for_review'       => 'Submitted for Review',
            'chada_travel_action_required'            => 'Action Required',
            'chada_travel_complete_files'             => 'Complete Files',
            'chada_travel_missing'                    => 'Missing',
            'chada_travel_uploaded'                   => 'Uploaded',
            'chada_travel_under_review'               => 'Under Review',
            'chada_travel_accepted'                   => 'Accepted',
            'chada_travel_replacement_required'       => 'Replacement Required',
            'chada_travel_superseded'                 => 'Superseded',
            'chada_travel_active'                     => 'Active',
        ];
    }

    /**
     * Returns security-sensitive payment transitions. This map is not stored in options.
     *
     * @return array<string, list<string>>
     */
    public static function get_payment_transitions(): array {
        return [
            'chada_travel_unpaid' => [
                'chada_travel_processing', 'chada_travel_awaiting_proof', 'chada_travel_awaiting_verification', 'chada_travel_cancelled',
            ],
            'chada_travel_processing'            => ['chada_travel_paid', 'chada_travel_failed', 'chada_travel_cancelled'],
            'chada_travel_awaiting_proof'        => ['chada_travel_awaiting_verification', 'chada_travel_cancelled'],
            'chada_travel_awaiting_verification' => ['chada_travel_paid', 'chada_travel_rejected', 'chada_travel_cancelled'],
            'chada_travel_rejected'              => ['chada_travel_awaiting_proof', 'chada_travel_awaiting_verification', 'chada_travel_cancelled'],
            'chada_travel_failed'                => ['chada_travel_processing', 'chada_travel_cancelled'],
            'chada_travel_paid'                  => ['chada_travel_refunded'],
            'chada_travel_cancelled'             => [],
            'chada_travel_refunded'              => [],
        ];
    }

    public static function can_transition_payment(string $chada_travel_from_status, string $chada_travel_to_status): bool {
        return in_array($chada_travel_to_status, self::get_payment_transitions()[$chada_travel_from_status] ?? [], true);
    }

    /**
     * Returns the current-stage navigation ceiling for each order state.
     *
     * @return array<string, int>
     */
    public static function get_stage_access_map(): array {
        return [
            // A draft may view Stage 4 payment methods once Stage 3 is acknowledged; selecting a method is
            // what actually moves the order to chada_travel_pending_payment.
            'chada_travel_draft'            => 4,
            'chada_travel_pending_payment'  => 5,
            'chada_travel_payment_review'   => 5,
            'chada_travel_payment_rejected' => 5,
            'chada_travel_paid'             => 5,
            'chada_travel_in_progress'      => 5,
            'chada_travel_completed'        => 5,
        ];
    }

    /** Checks the code-owned status ceiling and the server-recorded current checkout stage. */
    public static function can_access_stage(
        string $chada_travel_order_status,
        int $chada_travel_requested_stage,
        int $chada_travel_current_stage = 1
    ): bool {
        $chada_travel_maximum_stage = self::get_stage_access_map()[$chada_travel_order_status] ?? 1;
        $chada_travel_accessible_stage = min($chada_travel_maximum_stage, max(1, $chada_travel_current_stage));
        return $chada_travel_requested_stage >= 1 && $chada_travel_requested_stage <= $chada_travel_accessible_stage;
    }

    /** @return array<string, string> */
    public static function get_email_events(): array {
        return [
            'chada_travel_awaiting_proof'        => 'chada_travel_email_bank_proof_requested',
            'chada_travel_awaiting_verification' => 'chada_travel_email_payment_pending',
            'chada_travel_paid'                  => 'chada_travel_email_payment_confirmed',
            'chada_travel_rejected'              => 'chada_travel_email_payment_rejected',
        ];
    }

    /**
     * Distinct manual-payment lifecycle actions and their idempotent email event slug. Bank Payment creation
     * and Bank proof submission share the chada_travel_awaiting_proof/chada_travel_awaiting_verification payment statuses but
     * require different Booker-facing copy, so they are keyed by action instead of by status.
     *
     * @return array<string, string>
     */
    public static function get_payment_email_events(): array {
        return [
            'bank_selected'          => 'chada_travel_email_bank_proof_requested',
            'bank_proof_submitted'   => 'chada_travel_email_bank_proof_received',
            'digital_wallet_submitted' => 'chada_travel_email_payment_pending',
            'payment_confirmed'      => 'chada_travel_email_payment_confirmed',
            'payment_rejected'       => 'chada_travel_email_payment_rejected',
            'upload_link_reissued'   => 'chada_travel_email_upload_link_reissued',
        ];
    }

    /**
     * Administrator payment-review notification events: actionable submissions only (Bank proof submitted,
     * Digital Wallet payment details submitted) - never the initial Bank Payment selection, which still only
     * awaits proof. Keyed by the same action name as get_payment_email_events() so one Payment Service call site
     * can queue both the customer and administrator notification for the same underlying action.
     *
     * @return array<string, string>
     */
    public static function get_admin_payment_email_events(): array {
        return [
            'bank_proof_submitted'     => 'chada_travel_email_admin_bank_proof_submitted',
            'digital_wallet_submitted' => 'chada_travel_email_admin_digital_wallet_submitted',
        ];
    }

    /** Maps a new payment status to the resulting code-owned order status. */
    public static function get_order_status_for_payment_status(string $chada_travel_payment_status): string {
        return [
            'chada_travel_awaiting_proof'        => 'chada_travel_pending_payment',
            'chada_travel_awaiting_verification' => 'chada_travel_payment_review',
            'chada_travel_paid'                  => 'chada_travel_paid',
            'chada_travel_rejected'              => 'chada_travel_payment_rejected',
            'chada_travel_cancelled'             => 'chada_travel_cancelled',
        ][$chada_travel_payment_status] ?? 'chada_travel_pending_payment';
    }

    /** Paid resources are released by this exact code-owned condition only. */
    public static function can_release_paid_resources(string $chada_travel_payment_status): bool {
        return $chada_travel_payment_status === 'chada_travel_paid';
    }

    /**
     * True only for the payment statuses that have a real Stage 5 view (Bank Awaiting Proof, Bank/Digital Wallet
     * Awaiting Verification, Rejected, and Paid).
     */
    public static function is_stage_five_ready(string $chada_travel_payment_status): bool {
        return in_array($chada_travel_payment_status, [
            'chada_travel_awaiting_proof', 'chada_travel_awaiting_verification', 'chada_travel_rejected', 'chada_travel_paid',
        ], true);
    }

    /** Stage 5 shows "Return to Payment" only when the code-owned transition map still permits a retry. */
    public static function can_retry_payment(string $chada_travel_payment_status): bool {
        return self::can_transition_payment($chada_travel_payment_status, 'chada_travel_awaiting_proof')
            || self::can_transition_payment($chada_travel_payment_status, 'chada_travel_awaiting_verification');
    }
}
