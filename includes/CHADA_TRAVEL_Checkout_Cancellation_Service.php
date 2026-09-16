<?php
/** Authoritative unpaid Visa Application cancellation with payment-race revalidation and idempotency. */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Checkout_Cancellation_Service {
    /**
     * @param array<string, mixed> $chada_travel_order Previously token-resolved order.
     * @return array{cancelled: bool, status: int, code: string, message: string}
     */
    public static function cancel(object $chada_travel_wpdb, array $chada_travel_order): array {
        $chada_travel_current = CHADA_TRAVEL_Order_Repository::find_by_id(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id']
        );
        if (!$chada_travel_current) {
            return self::failure(404, 'chada_travel_draft_not_found', 'This Visa Application could not be found.');
        }
        if ((string) $chada_travel_current['chada_travel_order_status'] === 'chada_travel_cancelled') {
            return self::success();
        }
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::get_active_payment(
            $chada_travel_wpdb,
            (int) $chada_travel_current['chada_travel_order_id']
        );
        if ((string) $chada_travel_current['chada_travel_payment_status'] === 'chada_travel_paid'
            || (string) $chada_travel_current['chada_travel_order_status'] === 'chada_travel_paid'
            || ($chada_travel_payment && (string) $chada_travel_payment['chada_travel_payment_status'] === 'chada_travel_paid')) {
            return self::failure(
                409,
                'chada_travel_order_paid',
                'Payment is already confirmed. This Visa Application can no longer be cancelled here.'
            );
        }
        if ((int) $chada_travel_current['chada_travel_checkout_stage'] > 4) {
            return self::failure(
                409,
                'chada_travel_cancellation_not_allowed',
                'This Visa Application can only be cancelled before the payment stage is completed.'
            );
        }

        $chada_travel_prior_payment_status = $chada_travel_payment
            ? (string) $chada_travel_payment['chada_travel_payment_status'] : (string) $chada_travel_current['chada_travel_payment_status'];
        if ($chada_travel_payment && $chada_travel_prior_payment_status !== 'chada_travel_cancelled') {
            if (!CHADA_TRAVEL_Workflow::can_transition_payment($chada_travel_prior_payment_status, 'chada_travel_cancelled')) {
                return self::failure(
                    409,
                    'chada_travel_cancellation_not_allowed',
                    'This Visa Application cannot be cancelled from its current payment state.'
                );
            }
            CHADA_TRAVEL_Payment_Repository::update(
                $chada_travel_wpdb,
                $chada_travel_payment,
                ['chada_travel_payment_status' => 'chada_travel_cancelled']
            );
        }

        $chada_travel_updated = CHADA_TRAVEL_Order_Repository::update_order_payment_state(
            $chada_travel_wpdb,
            $chada_travel_current,
            'chada_travel_cancelled',
            'chada_travel_cancelled'
        );
        CHADA_TRAVEL_Order_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_updated['chada_travel_order_id'],
            'chada_travel_application_cancelled',
            'booker',
            [
                'prior_order_status'   => (string) $chada_travel_current['chada_travel_order_status'],
                'prior_payment_status' => $chada_travel_prior_payment_status,
            ]
        );
        return self::success();
    }

    /** @return array{cancelled: true, status: int, code: string, message: string} */
    private static function success(): array {
        return ['cancelled' => true, 'status' => 200, 'code' => '', 'message' => 'Visa Application cancelled.'];
    }

    /** @return array{cancelled: false, status: int, code: string, message: string} */
    private static function failure(int $chada_travel_status, string $chada_travel_code, string $chada_travel_message): array {
        return [
            'cancelled' => false, 'status' => $chada_travel_status, 'code' => $chada_travel_code, 'message' => $chada_travel_message,
        ];
    }
}
