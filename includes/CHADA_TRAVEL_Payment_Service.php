<?php
/**
 * Bank and Digital Wallet manual-payment business rules: Booking ID finalization, Awaiting Proof/Verification
 * transitions, administrator confirm/reject, and the one atomic Unique Transaction ID generation.
 *
 * PHP remains authoritative here; the Booker never supplies a Transaction ID, and every transition is
 * validated against the code-owned CHADA_TRAVEL_Workflow transition map before it is written.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Payment_Service {
    /** Established method identifiers remain readable for existing records; Free does not register new methods here. */
    public const METHOD_BANK = 'chada_travel_bank';
    public const METHOD_DIGITAL_WALLET = 'chada_travel_digital_wallet';
    public const MAX_DIGITAL_WALLET_REFERENCE_LENGTH = 190;

    /**
     * @param array<string, mixed> $chada_travel_order    Current order row.
     * @param array<string, mixed> $chada_travel_settings CHADA_TRAVEL_Config::get_settings() result.
     * @return array{
     *     errors: list<string>,
     *     order?: array<string, mixed>,
     *     payment?: array<string, mixed>,
     *     proof_token?: ?string
     * }
     */
    public static function select_bank_payment(object $chada_travel_wpdb, array $chada_travel_order, array $chada_travel_settings): array {
        if (!self::stage_four_reachable($chada_travel_order)) {
            return ['errors' => ['Complete Review and Edit before selecting a payment method.']];
        }

        $chada_travel_active = CHADA_TRAVEL_Payment_Repository::get_active_payment(
            $chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id']
        );
        if (self::is_same_method_committed($chada_travel_active, self::METHOD_BANK)) {
            // Idempotent: the Booker re-clicked Select Bank Payment; return the existing attempt without re-emailing.
            return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_active, 'proof_token' => null];
        }
        if (self::is_other_method_committed($chada_travel_active, self::METHOD_BANK)) {
            return ['errors' => ['A payment is already submitted for review. Contact support to change the method.']];
        }

        $chada_travel_order = CHADA_TRAVEL_Order_Repository::finalize_booking_id(
            $chada_travel_wpdb,
            $chada_travel_order,
            (string) $chada_travel_settings['chada_travel_booking_id_prefix']
        );
        $chada_travel_token_pair = CHADA_TRAVEL_Payment_Repository::generate_proof_token();
        $chada_travel_expires_at = self::hours_from_now((int) $chada_travel_settings['chada_travel_payment_proof_token_hours']);

        if ($chada_travel_active && $chada_travel_active['chada_travel_payment_method'] !== self::METHOD_BANK) {
            self::cancel_uncommitted_attempt($chada_travel_wpdb, $chada_travel_order, $chada_travel_active);
            $chada_travel_active = null;
        }

        // Snapshots the exact active accounts shown to this Booker at selection time; later settings changes
        // must never rewrite this snapshot or alter what an already-pending Booker sees for this attempt.
        $chada_travel_snapshot = self::encode_snapshot([
            'bank_accounts' => CHADA_TRAVEL_Config::get_active_bank_accounts(),
        ]);
        if ($chada_travel_active && $chada_travel_active['chada_travel_payment_method'] === self::METHOD_BANK
            && $chada_travel_active['chada_travel_payment_status'] === 'chada_travel_rejected'
        ) {
            $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::update($chada_travel_wpdb, $chada_travel_active, [
                'chada_travel_payment_status'         => 'chada_travel_awaiting_proof',
                'chada_travel_proof_token_hash'       => $chada_travel_token_pair['hash'],
                'chada_travel_proof_token_expires_at' => $chada_travel_expires_at,
                'chada_travel_rejection_reason'       => null,
                'chada_travel_verified_by_user_id'    => null,
                'chada_travel_verified_at'            => null,
            ]);
        } else {
            $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::create(
                $chada_travel_wpdb,
                $chada_travel_order,
                self::METHOD_BANK,
                'chada_travel_awaiting_proof',
                [
                    'chada_travel_proof_token_hash'         => $chada_travel_token_pair['hash'],
                    'chada_travel_proof_token_expires_at'   => $chada_travel_expires_at,
                    'chada_travel_payment_details_snapshot' => $chada_travel_snapshot,
                ]
            );
        }

        $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_order,
            'chada_travel_pending_payment',
            'chada_travel_awaiting_proof',
            5
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_bank_selected',
            'booker'
        );
        CHADA_TRAVEL_Email_Service::queue('bank_selected', (int) $chada_travel_payment['chada_travel_payment_id'], [
            'proof_token' => $chada_travel_token_pair['token'],
        ]);

        return [
            'errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment,
            'proof_token' => $chada_travel_token_pair['token'],
        ];
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_settings
     * @return array{errors: list<string>, order?: array<string, mixed>, payment?: array<string, mixed>}
     */
    public static function submit_digital_wallet_payment(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_settings,
        string $chada_travel_reference_no
    ): array {
        if (!self::stage_four_reachable($chada_travel_order)) {
            return ['errors' => ['Complete Review and Edit before selecting a payment method.']];
        }
        $chada_travel_reference_no = self::clean_text($chada_travel_reference_no, self::MAX_DIGITAL_WALLET_REFERENCE_LENGTH);
        if ($chada_travel_reference_no === '') {
            return ['errors' => ['Enter the Reference No. before submitting.']];
        }

        $chada_travel_active = CHADA_TRAVEL_Payment_Repository::get_active_payment(
            $chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id']
        );
        if (self::is_same_method_committed($chada_travel_active, self::METHOD_DIGITAL_WALLET)) {
            // Idempotent: a repeated submission never duplicates the row or re-sends the pending email.
            return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_active];
        }
        if (self::is_other_method_committed($chada_travel_active, self::METHOD_DIGITAL_WALLET)) {
            return ['errors' => ['A payment is already submitted for review. Contact support to change the method.']];
        }

        $chada_travel_order = CHADA_TRAVEL_Order_Repository::finalize_booking_id(
            $chada_travel_wpdb,
            $chada_travel_order,
            (string) $chada_travel_settings['chada_travel_booking_id_prefix']
        );
        if ($chada_travel_active && $chada_travel_active['chada_travel_payment_method'] !== self::METHOD_DIGITAL_WALLET) {
            self::cancel_uncommitted_attempt($chada_travel_wpdb, $chada_travel_order, $chada_travel_active);
            $chada_travel_active = null;
        }

        $chada_travel_now      = self::now();
        // Freezes the provider/account destination the Booker actually saw at submission time; a later Settings
        // provider-name/account change must never relabel or rewrite an already-created payment (see
        // resolve_digital_wallet_snapshot()).
        $chada_travel_snapshot = self::encode_snapshot([
            'digital_wallet_name'           => $chada_travel_settings['chada_travel_digital_wallet_name'],
            'digital_wallet_account_name'   => $chada_travel_settings['chada_travel_digital_wallet_account_name'],
            'digital_wallet_account_number' => $chada_travel_settings['chada_travel_digital_wallet_account_number'],
        ]);
        if ($chada_travel_active && $chada_travel_active['chada_travel_payment_method'] === self::METHOD_DIGITAL_WALLET
            && $chada_travel_active['chada_travel_payment_status'] === 'chada_travel_rejected'
        ) {
            $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::update($chada_travel_wpdb, $chada_travel_active, [
                'chada_travel_digital_wallet_reference_no' => $chada_travel_reference_no,
                'chada_travel_payment_status'      => 'chada_travel_awaiting_verification',
                'chada_travel_submitted_at'        => $chada_travel_now,
                'chada_travel_rejection_reason'    => null,
                'chada_travel_verified_by_user_id' => null,
                'chada_travel_verified_at'         => null,
            ]);
        } else {
            $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::create(
                $chada_travel_wpdb,
                $chada_travel_order,
                self::METHOD_DIGITAL_WALLET,
                'chada_travel_awaiting_verification',
                [
                    'chada_travel_digital_wallet_reference_no' => $chada_travel_reference_no,
                    'chada_travel_submitted_at'             => $chada_travel_now,
                    'chada_travel_payment_details_snapshot' => $chada_travel_snapshot,
                ]
            );
        }

        $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_order,
            'chada_travel_payment_review',
            'chada_travel_awaiting_verification',
            5
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_digital_wallet_submitted',
            'booker'
        );
        CHADA_TRAVEL_Email_Service::queue('digital_wallet_submitted', (int) $chada_travel_payment['chada_travel_payment_id']);
        CHADA_TRAVEL_Email_Service::queue_admin('digital_wallet_submitted', (int) $chada_travel_payment['chada_travel_payment_id']);

        return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment];
    }

    /**
     * Normalizes a Digital Wallet payment snapshot for read-only display: canonical keys are used as-is. A
     * a snapshot created before the Digital Wallet rename (containing only the old gcash_account_name/
     * gcash_account_number keys) is mapped into the canonical shape here without ever rewriting the stored
     * snapshot - this only changes how it is read. Such a snapshot has no provider-name key at all, so it
     * always resolves to "GCash" (the only provider this codebase ever supported before this release) - never
     * the current Settings provider name, which would incorrectly relabel a historical record.
     *
     * @param array<string, mixed> $chada_travel_snapshot Decoded payment snapshot (see decode_snapshot()).
     * @return array{name: string, account_name: string, account_number: string}
     */
    public static function resolve_digital_wallet_snapshot(array $chada_travel_snapshot): array {
        $chada_travel_is_canonical = array_key_exists('digital_wallet_account_name', $chada_travel_snapshot)
            || array_key_exists('digital_wallet_name', $chada_travel_snapshot);
        if ($chada_travel_is_canonical) {
            return [
                'name'           => (string) ($chada_travel_snapshot['digital_wallet_name'] ?? ''),
                'account_name'   => (string) ($chada_travel_snapshot['digital_wallet_account_name'] ?? ''),
                'account_number' => (string) ($chada_travel_snapshot['digital_wallet_account_number'] ?? ''),
            ];
        }
        return [
            'name'           => 'GCash',
            'account_name'   => (string) ($chada_travel_snapshot['gcash_account_name'] ?? ''),
            'account_number' => (string) ($chada_travel_snapshot['gcash_account_number'] ?? ''),
        ];
    }

    /**
     * Resolves the "Method" label for an EXISTING payment row in administrator contexts (Payment Review,
     * Booking Detail and Dashboard: Bank keeps its fixed label; Digital Wallet uses this
     * payment's own immutable snapshot provider name (never current Settings), mirrored into the same
     * "{Provider} Payment" shape those fixed labels already use, so a later Settings provider-name change never
     * relabels an already-created payment.
     *
     * @param array<string, mixed> $chada_travel_payment
     */
    public static function resolve_method_label(array $chada_travel_payment): string {
        $chada_travel_method = (string) ($chada_travel_payment['chada_travel_payment_method'] ?? '');
        if ($chada_travel_method !== self::METHOD_DIGITAL_WALLET) {
            return $chada_travel_method === self::METHOD_BANK ? 'Bank Payment' : $chada_travel_method;
        }
        $chada_travel_snapshot = self::resolve_digital_wallet_snapshot(
            self::decode_snapshot($chada_travel_payment['chada_travel_payment_details_snapshot'] ?? null)
        );
        return $chada_travel_snapshot['name'] . ' Payment';
    }

    /**
     * Resolves the Reference No. actually stored for a payment row, preferring the canonical column and falling
     * back to the previous column only for a payment created before the Digital Wallet rename (canonical column
     * left NULL on that historical row).
     *
     * @param array<string, mixed> $chada_travel_payment
     */
    public static function resolve_reference_no(array $chada_travel_payment): string {
        $chada_travel_canonical = (string) ($chada_travel_payment['chada_travel_digital_wallet_reference_no'] ?? '');
        return $chada_travel_canonical !== '' ? $chada_travel_canonical : (string) ($chada_travel_payment['chada_travel_gcash_reference_no'] ?? '');
    }

    /**
     * @param array<string, mixed>                                       $chada_travel_order
     * @param array<string, mixed>                                       $chada_travel_payment Resolved active Bank attempt.
     * @param array{name?:string,type?:string,tmp_name?:string,size?:int,error?:int} $chada_travel_file
     * @param array{mime_types: list<string>, max_bytes: int}            $chada_travel_upload_rules
     * @return array{
     *     errors: list<string>,
     *     order?: array<string, mixed>,
     *     payment?: array<string, mixed>,
     *     duplicate?: bool
     * }
     */
    public static function submit_bank_proof(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_payment,
        array $chada_travel_file,
        array $chada_travel_upload_rules
    ): array {
        if ($chada_travel_payment['chada_travel_payment_method'] !== self::METHOD_BANK) {
            return ['errors' => ['This Booking ID cannot accept a Deposit Slip.']];
        }
        if (in_array($chada_travel_payment['chada_travel_payment_status'], ['chada_travel_awaiting_verification', 'chada_travel_paid'], true)) {
            // Idempotent: proof was already submitted/confirmed; a second file is never processed again.
            return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment, 'duplicate' => true];
        }
        $chada_travel_can_await_verification = CHADA_TRAVEL_Workflow::can_transition_payment(
            (string) $chada_travel_payment['chada_travel_payment_status'],
            'chada_travel_awaiting_verification'
        );
        if (!$chada_travel_can_await_verification) {
            return ['errors' => ['This Booking ID cannot accept a Deposit Slip right now.']];
        }

        if (empty($chada_travel_upload_rules['mime_types']) || (int) $chada_travel_upload_rules['max_bytes'] <= 0) {
            // Fail closed: no valid positive effective rule exists (e.g. the server upload limit could not be
            // resolved) - never fall through to store_upload(), whose own max_bytes<=0 has an unrelated
            // unbounded-value meaning it must never inherit. See CHADA_TRAVEL_Upload_Settings::get_effective_proof_rules().
            return ['errors' => ['Bank Payment Proof uploads are temporarily unavailable. Try again later.']];
        }
        $chada_travel_stored = CHADA_TRAVEL_Upload_Service::store_upload($chada_travel_file, $chada_travel_upload_rules, 'proofs');
        if ($chada_travel_stored['errors']) {
            return ['errors' => $chada_travel_stored['errors']];
        }

        $chada_travel_now      = self::now();
        $chada_travel_snapshot = self::decode_snapshot($chada_travel_payment['chada_travel_payment_details_snapshot'] ?? null);
        $chada_travel_snapshot['proof_storage_path']      = $chada_travel_stored['storage_path'];
        $chada_travel_snapshot['proof_original_filename'] = $chada_travel_stored['original_filename'];
        $chada_travel_snapshot['proof_mime_type']         = $chada_travel_stored['mime_type'];

        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::update($chada_travel_wpdb, $chada_travel_payment, [
            'chada_travel_payment_status'           => 'chada_travel_awaiting_verification',
            // A dedicated storage-key column does not exist on this frozen MVP schema; the physical path lives in
            // the safe JSON snapshot above, and this column is repurposed as a simple "proof attached" marker.
            'chada_travel_proof_attachment_id'      => (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_payment_details_snapshot' => self::encode_snapshot($chada_travel_snapshot),
            'chada_travel_submitted_at'             => $chada_travel_now,
            'chada_travel_proof_token_hash'         => null,
            'chada_travel_proof_token_expires_at'   => null,
        ]);
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_order,
            'chada_travel_payment_review',
            'chada_travel_awaiting_verification',
            5
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_bank_proof_submitted',
            'booker',
            ['mime_type' => $chada_travel_stored['mime_type'], 'size' => $chada_travel_stored['size']]
        );
        CHADA_TRAVEL_Email_Service::queue('bank_proof_submitted', (int) $chada_travel_payment['chada_travel_payment_id']);
        CHADA_TRAVEL_Email_Service::queue_admin('bank_proof_submitted', (int) $chada_travel_payment['chada_travel_payment_id']);

        return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment];
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_settings
     * @return array{
     *     errors: list<string>,
     *     order?: array<string, mixed>,
     *     payment?: array<string, mixed>,
     *     already_confirmed?: bool
     * }
     */
    public static function admin_confirm(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_payment,
        int $chada_travel_admin_user_id,
        array $chada_travel_settings
    ): array {
        if ($chada_travel_payment['chada_travel_payment_status'] === 'chada_travel_paid') {
            // Idempotent: repeated admin confirmation never issues a second Transaction ID or email.
            return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment, 'already_confirmed' => true];
        }
        if (!CHADA_TRAVEL_Workflow::can_transition_payment((string) $chada_travel_payment['chada_travel_payment_status'], 'chada_travel_paid')) {
            return ['errors' => ['This payment cannot be confirmed from its current status.']];
        }

        $chada_travel_now             = self::now();
        $chada_travel_prefix          = (string) $chada_travel_settings['chada_travel_transaction_id_prefix'];
        $chada_travel_transaction_id  = CHADA_TRAVEL_Order_Repository::generate_public_id($chada_travel_prefix);
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::update($chada_travel_wpdb, $chada_travel_payment, [
            'chada_travel_payment_status'              => 'chada_travel_paid',
            'chada_travel_transaction_id'              => $chada_travel_transaction_id,
            'chada_travel_transaction_id_generated_at' => $chada_travel_now,
            'chada_travel_verified_by_user_id'         => $chada_travel_admin_user_id,
            'chada_travel_verified_at'                 => $chada_travel_now,
            'chada_travel_paid_at'                      => $chada_travel_now,
        ]);
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_order,
            'chada_travel_paid',
            'chada_travel_paid',
            5
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_payment_confirmed',
            'admin',
            ['transaction_id' => $chada_travel_transaction_id],
            $chada_travel_admin_user_id
        );
        $chada_travel_upload = CHADA_TRAVEL_Order_Repository::issue_upload_token(
            $chada_travel_wpdb,
            $chada_travel_order,
            (int) ($chada_travel_settings['chada_travel_upload_token_hours'] ?? CHADA_TRAVEL_Config::DEFAULT_UPLOAD_TOKEN_HOURS)
        );
        $chada_travel_order = $chada_travel_upload['order'];
        CHADA_TRAVEL_Email_Service::queue('payment_confirmed', (int) $chada_travel_payment['chada_travel_payment_id'], [
            'upload_token' => $chada_travel_upload['token'],
        ]);

        return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment];
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @return array{
     *     errors: list<string>,
     *     order?: array<string, mixed>,
     *     payment?: array<string, mixed>,
     *     already_rejected?: bool
     * }
     */
    public static function admin_reject(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_payment,
        int $chada_travel_admin_user_id,
        string $chada_travel_reason
    ): array {
        $chada_travel_reason = self::clean_text($chada_travel_reason, 500);
        if ($chada_travel_reason === '') {
            return ['errors' => ['A rejection reason is required.']];
        }
        if ($chada_travel_payment['chada_travel_payment_status'] === 'chada_travel_rejected') {
            // Idempotent: repeated admin rejection never re-writes history or re-sends the email.
            return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment, 'already_rejected' => true];
        }
        $chada_travel_can_reject = CHADA_TRAVEL_Workflow::can_transition_payment(
            (string) $chada_travel_payment['chada_travel_payment_status'],
            'chada_travel_rejected'
        );
        if (!$chada_travel_can_reject) {
            return ['errors' => ['This payment cannot be rejected from its current status.']];
        }

        $chada_travel_now     = self::now();
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::update($chada_travel_wpdb, $chada_travel_payment, [
            'chada_travel_payment_status'      => 'chada_travel_rejected',
            'chada_travel_rejection_reason'    => $chada_travel_reason,
            'chada_travel_verified_by_user_id' => $chada_travel_admin_user_id,
            'chada_travel_verified_at'         => $chada_travel_now,
        ]);
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_order,
            'chada_travel_payment_rejected',
            'chada_travel_rejected',
            5
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            'chada_travel_payment_rejected',
            'admin',
            ['reason' => $chada_travel_reason],
            $chada_travel_admin_user_id
        );
        CHADA_TRAVEL_Email_Service::queue('payment_rejected', (int) $chada_travel_payment['chada_travel_payment_id']);

        return ['errors' => [], 'order' => $chada_travel_order, 'payment' => $chada_travel_payment];
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     */
    public static function stage_four_reachable(array $chada_travel_order): bool {
        return CHADA_TRAVEL_Workflow::can_access_stage(
            (string) $chada_travel_order['chada_travel_order_status'],
            4,
            (int) $chada_travel_order['chada_travel_checkout_stage']
        );
    }

    /** @param array<string, mixed>|null $chada_travel_active */
    public static function is_same_method_committed(?array $chada_travel_active, string $chada_travel_method): bool {
        $chada_travel_committed_statuses = ['chada_travel_awaiting_proof', 'chada_travel_awaiting_verification', 'chada_travel_paid'];
        return $chada_travel_active !== null && $chada_travel_active['chada_travel_payment_method'] === $chada_travel_method
            && in_array($chada_travel_active['chada_travel_payment_status'], $chada_travel_committed_statuses, true);
    }

    /** @param array<string, mixed>|null $chada_travel_active */
    public static function is_other_method_committed(?array $chada_travel_active, string $chada_travel_method): bool {
        return $chada_travel_active !== null && $chada_travel_active['chada_travel_payment_method'] !== $chada_travel_method
            && in_array($chada_travel_active['chada_travel_payment_status'], ['chada_travel_awaiting_verification', 'chada_travel_paid'], true);
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_active
     */
    public static function cancel_uncommitted_attempt(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_active
    ): void {
        CHADA_TRAVEL_Payment_Repository::update(
            $chada_travel_wpdb, $chada_travel_active, ['chada_travel_payment_status' => 'chada_travel_cancelled']
        );
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_active['chada_travel_payment_id'],
            'chada_travel_payment_method_switched',
            'booker'
        );
    }

    private static function hours_from_now(int $chada_travel_hours): string {
        $chada_travel_timestamp = time() + max(1, $chada_travel_hours) * 3600;
        return gmdate('Y-m-d H:i:s', $chada_travel_timestamp);
    }

    public static function now(): string {
        return gmdate('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $chada_travel_data */
    public static function encode_snapshot(array $chada_travel_data): string {
        return function_exists('wp_json_encode') ? (string) wp_json_encode($chada_travel_data)
            : (string) json_encode($chada_travel_data);
    }

    /** @return array<string, mixed> */
    public static function decode_snapshot(?string $chada_travel_json): array {
        if (!$chada_travel_json) {
            return [];
        }
        $chada_travel_decoded = json_decode($chada_travel_json, true);
        return is_array($chada_travel_decoded) ? $chada_travel_decoded : [];
    }

    private static function clean_text(string $chada_travel_value, int $chada_travel_max_length): string {
        if (function_exists('sanitize_text_field')) {
            return mb_substr(sanitize_text_field($chada_travel_value), 0, $chada_travel_max_length);
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
        return mb_substr(trim(strip_tags($chada_travel_value)), 0, $chada_travel_max_length);
    }
}
