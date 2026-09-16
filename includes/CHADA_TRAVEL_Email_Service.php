<?php
/**
 * Idempotent Bank/Digital Wallet notification queueing through the plugin-owned WP-Cron queue and wp_mail(), for
 * the Booker, an administrator for actionable payment-review submissions, and one paid resource message per country.
 *
 * Queue arguments carry only a payment ID, event slug, audience, and a short-lived link token where the email
 * itself requires one (e.g. the Bank proof link) - never payment-provider secrets, full Booker profile data, or
 * document contents. The message body is rendered at send time from the current database rows so it never goes stale.
 * A prior dispatch only suppresses a duplicate send once it is recorded as actually sent (see
 * already_dispatched()); a failed wp_mail() attempt is recorded but never blocks a later retry of the same
 * payment/event/audience.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Email_Service {
    private const ACTION_HOOK = 'chada_travel_dispatch_payment_email';
    public const QUEUE_HOOK = 'chada_travel_email_queue_worker';
    private const QUEUE_BATCH_SIZE = 10;
    public const AUDIENCE_CUSTOMER = 'customer';
    public const AUDIENCE_ADMIN = 'admin';
    public const AUDIENCE_COUNTRY_RESOURCE = 'country_resource';
    public const COUNTRY_RESOURCE_EVENT = 'chada_travel_email_country_resources';

    /** Registers the plugin-owned queue worker and established internal dispatch callback. */
    public static function register(): void {
        add_action(self::ACTION_HOOK, static function (array $chada_travel_args = []): void {
            self::dispatch($chada_travel_args);
        });
        add_action(self::QUEUE_HOOK, [self::class, 'process_queue']);
        add_filter('cron_schedules', [self::class, 'add_queue_schedule']);
    }

    /**
     * Adds a one-minute interval for hosts that inspect the queue schedule.
     *
     * @param array<string, array{interval: int, display: string}> $chada_travel_schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public static function add_queue_schedule(array $chada_travel_schedules): array {
        if (!isset($chada_travel_schedules['chada_travel_one_minute'])) {
            $chada_travel_schedules['chada_travel_one_minute'] = [
                'interval' => 60,
                'display' => __('Every minute', 'chada-travel'),
            ];
        }
        return $chada_travel_schedules;
    }

    /**
     * Queues a Booker-facing manual-payment notification for durable WP-Cron delivery.
     *
     * @param array<string, mixed> $chada_travel_context Short-lived, non-secret data the template needs (e.g. a token).
     */
    public static function queue(string $chada_travel_action, int $chada_travel_payment_id, array $chada_travel_context = []): void {
        $chada_travel_event = CHADA_TRAVEL_Workflow::get_payment_email_events()[$chada_travel_action] ?? null;
        self::enqueue($chada_travel_event, $chada_travel_payment_id, $chada_travel_context, self::AUDIENCE_CUSTOMER);
    }

    /**
     * Queues an administrator payment-review notification for the same $chada_travel_action key used by queue(), so
     * one Payment Service call site can trigger both audiences for the same underlying action. An action with no
     * matching administrator event (see CHADA_TRAVEL_Workflow::get_admin_payment_email_events()) is silently ignored,
     * matching queue()'s own behavior for an unmapped action. Whether administrator notifications are currently
     * enabled is re-checked at send time (see dispatch_admin()), not here, so a setting change between queueing
     * and a delayed send is always honored.
     *
     * @param array<string, mixed> $chada_travel_context
     */
    public static function queue_admin(string $chada_travel_action, int $chada_travel_payment_id, array $chada_travel_context = []): void {
        $chada_travel_event = CHADA_TRAVEL_Workflow::get_admin_payment_email_events()[$chada_travel_action] ?? null;
        self::enqueue($chada_travel_event, $chada_travel_payment_id, $chada_travel_context, self::AUDIENCE_ADMIN);
    }

    /** Queues one resource email per unique country, carrying stable IDs only. */
    public static function queue_country_resources(int $chada_travel_payment_id): void {
        global $wpdb;
        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_id($wpdb, $chada_travel_payment_id);
        if (!$chada_travel_payment || !CHADA_TRAVEL_Workflow::can_release_paid_resources(
            (string) $chada_travel_payment['chada_travel_payment_status']
        )) {
            return;
        }
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications(
            $wpdb,
            (int) $chada_travel_payment['chada_travel_order_id']
        );
        $chada_travel_country_ids = array_values(array_unique(array_filter(array_map(
            static fn(array $chada_travel_application): int => (int) $chada_travel_application['chada_travel_country_id'],
            $chada_travel_applications
        ))));
        foreach ($chada_travel_country_ids as $chada_travel_country_id) {
            self::enqueue(
                self::COUNTRY_RESOURCE_EVENT,
                $chada_travel_payment_id,
                ['country_id' => $chada_travel_country_id],
                self::AUDIENCE_COUNTRY_RESOURCE
            );
        }
    }

    /** @param array<string, mixed> $chada_travel_context */
    private static function enqueue(
        ?string $chada_travel_event,
        int $chada_travel_payment_id,
        array $chada_travel_context,
        string $chada_travel_audience
    ): void {
        if (!$chada_travel_event || $chada_travel_payment_id <= 0) {
            return;
        }
        global $wpdb;
        if (!is_object($wpdb) || !method_exists($wpdb, 'get_var')) {
            self::dispatch([
                'event' => $chada_travel_event,
                'payment_id' => $chada_travel_payment_id,
                'context' => self::sanitize_queue_context($chada_travel_context),
                'audience' => $chada_travel_audience,
            ]);
            return;
        }
        $chada_travel_context = self::sanitize_queue_context($chada_travel_context);
        $chada_travel_context_json = function_exists('wp_json_encode')
            ? wp_json_encode($chada_travel_context) : json_encode($chada_travel_context);
        $chada_travel_encrypted_context = CHADA_TRAVEL_Credential_Cipher::encrypt_for_purpose(
            (string) $chada_travel_context_json,
            'email_queue'
        );
        if ($chada_travel_encrypted_context === null) {
            return;
        }
        $chada_travel_queue_id = CHADA_TRAVEL_Email_Queue_Repository::enqueue(
            $wpdb,
            $chada_travel_event,
            $chada_travel_payment_id,
            $chada_travel_audience,
            max(0, (int) ($chada_travel_context['country_id'] ?? 0)),
            ['encrypted' => $chada_travel_encrypted_context]
        );
        if ($chada_travel_queue_id > 0) {
            self::schedule_worker();
        }
    }

    /**
     * Sanitizes the minimal context permitted in durable queue storage.
     *
     * @param array<string, mixed> $chada_travel_context
     * @return array<string, int|string>
     */
    private static function sanitize_queue_context(array $chada_travel_context): array {
        $chada_travel_clean = [];
        foreach (['country_id' => 'int', 'proof_token' => 'token', 'upload_token' => 'token'] as $chada_travel_key => $chada_travel_type) {
            if (!array_key_exists($chada_travel_key, $chada_travel_context)) {
                continue;
            }
            if ($chada_travel_type === 'int') {
                $chada_travel_value = (int) $chada_travel_context[$chada_travel_key];
                if ($chada_travel_value > 0) {
                    $chada_travel_clean[$chada_travel_key] = $chada_travel_value;
                }
                continue;
            }
            $chada_travel_value = preg_replace('/[^a-f0-9]/i', '', (string) $chada_travel_context[$chada_travel_key]);
            if (preg_match('/\A[a-f0-9]{32,128}\z/i', (string) $chada_travel_value)) {
                $chada_travel_clean[$chada_travel_key] = $chada_travel_value;
            }
        }
        return $chada_travel_clean;
    }

    /** Schedules one worker when queue rows exist; the worker schedules the next run when needed. */
    private static function schedule_worker(): void {
        if (function_exists('wp_next_scheduled') && wp_next_scheduled(self::QUEUE_HOOK) !== false) {
            return;
        }
        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 60, self::QUEUE_HOOK);
        }
    }

    /** Claims and processes a bounded batch, retrying transient wp_mail failures with bounded backoff. */
    public static function process_queue(): void {
        global $wpdb;
        foreach (CHADA_TRAVEL_Email_Queue_Repository::claim_due($wpdb, self::QUEUE_BATCH_SIZE) as $chada_travel_row) {
            $chada_travel_id = (int) ($chada_travel_row['chada_travel_id'] ?? 0);
            $chada_travel_envelope = (array) json_decode((string) ($chada_travel_row['chada_travel_context'] ?? ''), true);
            $chada_travel_plaintext = CHADA_TRAVEL_Credential_Cipher::decrypt_for_purpose(
                (string) ($chada_travel_envelope['encrypted'] ?? ''),
                'email_queue'
            );
            $chada_travel_context = $chada_travel_plaintext === null ? null : json_decode($chada_travel_plaintext, true);
            if ($chada_travel_id <= 0 || !is_array($chada_travel_context)) {
                CHADA_TRAVEL_Email_Queue_Repository::fail(
                    $wpdb, $chada_travel_id, CHADA_TRAVEL_Email_Queue_Repository::MAX_ATTEMPTS, 'context_invalid'
                );
                continue;
            }
            $chada_travel_dispatch_success = self::dispatch([
                'event' => (string) ($chada_travel_row['chada_travel_event'] ?? ''),
                'payment_id' => (int) ($chada_travel_row['chada_travel_payment_id'] ?? 0),
                'context' => $chada_travel_context,
                'audience' => (string) ($chada_travel_row['chada_travel_audience'] ?? self::AUDIENCE_CUSTOMER),
            ]);
            if ($chada_travel_dispatch_success) {
                CHADA_TRAVEL_Email_Queue_Repository::complete($wpdb, $chada_travel_id);
            } else {
                CHADA_TRAVEL_Email_Queue_Repository::fail(
                    $wpdb,
                    $chada_travel_id,
                    (int) ($chada_travel_row['chada_travel_attempts'] ?? 1),
                    'mail_failed'
                );
            }
        }
        if (CHADA_TRAVEL_Email_Queue_Repository::has_active_items($wpdb)) {
            self::schedule_worker();
        }
    }

    /**
     * Internal queue callback. Re-checks idempotency at send time so a duplicate queue entry or a
     * replayed action never sends the same notification twice.
     *
     * @param array{event?: string, payment_id?: int, context?: array<string, mixed>, audience?: string} $chada_travel_args
     */
    public static function dispatch(array $chada_travel_args): bool {
        global $wpdb;
        $chada_travel_event      = (string) ($chada_travel_args['event'] ?? '');
        $chada_travel_payment_id = (int) ($chada_travel_args['payment_id'] ?? 0);
        $chada_travel_context    = (array) ($chada_travel_args['context'] ?? []);
        $chada_travel_audience   = (string) ($chada_travel_args['audience'] ?? self::AUDIENCE_CUSTOMER);
        if ($chada_travel_event === '' || $chada_travel_payment_id <= 0) {
            return true;
        }
        $chada_travel_country_id = max(0, (int) ($chada_travel_context['country_id'] ?? 0));
        if (self::already_dispatched(
            $wpdb,
            $chada_travel_payment_id,
            $chada_travel_event,
            $chada_travel_audience,
            $chada_travel_country_id
        )) {
            return true;
        }

        $chada_travel_payment = CHADA_TRAVEL_Payment_Repository::find_by_id($wpdb, $chada_travel_payment_id);
        $chada_travel_order   = $chada_travel_payment
            ? CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, (int) $chada_travel_payment['chada_travel_order_id']) : null;
        if (!$chada_travel_order || !$chada_travel_payment) {
            return true;
        }

        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        if ($chada_travel_audience === self::AUDIENCE_COUNTRY_RESOURCE) {
            return self::dispatch_country_resource(
                $wpdb,
                $chada_travel_event,
                $chada_travel_order,
                $chada_travel_payment,
                $chada_travel_country_id,
                $chada_travel_settings
            );
        }
        if ($chada_travel_audience === self::AUDIENCE_ADMIN) {
            return self::dispatch_admin($wpdb, $chada_travel_event, $chada_travel_order, $chada_travel_payment, $chada_travel_settings);
        }
        return self::dispatch_customer(
            $wpdb,
            $chada_travel_event,
            $chada_travel_order,
            $chada_travel_payment,
            $chada_travel_context,
            $chada_travel_settings
        );
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_context
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function dispatch_customer(
        object $chada_travel_wpdb,
        string $chada_travel_event,
        array $chada_travel_order,
        array $chada_travel_payment,
        array $chada_travel_context,
        array $chada_travel_settings
    ): bool {
        $chada_travel_message = self::build_message(
            $chada_travel_wpdb,
            $chada_travel_event,
            $chada_travel_order,
            $chada_travel_payment,
            $chada_travel_context,
            $chada_travel_settings
        );
        if (!$chada_travel_message) {
            return true;
        }
        $chada_travel_result = self::send_mail(
            (string) $chada_travel_order['chada_travel_booker_email'],
            $chada_travel_message['subject'],
            $chada_travel_message['body'],
            CHADA_TRAVEL_Config::build_email_headers($chada_travel_settings)
        );
        self::log_dispatch(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            $chada_travel_event,
            self::AUDIENCE_CUSTOMER,
            $chada_travel_result['sent'],
            $chada_travel_result['failure_code']
        );
        if ($chada_travel_event === 'chada_travel_email_payment_confirmed' && $chada_travel_result['sent']) {
            self::queue_country_resources((int) $chada_travel_payment['chada_travel_payment_id']);
        }
        return $chada_travel_result['sent'];
    }

    /**
     * Sends the latest verified guide/checklist pair for one country after re-checking authoritative paid state.
     *
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function dispatch_country_resource(
        object $chada_travel_wpdb,
        string $chada_travel_event,
        array $chada_travel_order,
        array $chada_travel_payment,
        int $chada_travel_country_id,
        array $chada_travel_settings
    ): bool {
        if ($chada_travel_event !== self::COUNTRY_RESOURCE_EVENT || $chada_travel_country_id <= 0
            || !CHADA_TRAVEL_Workflow::can_release_paid_resources((string) $chada_travel_payment['chada_travel_payment_status'])
            || (string) $chada_travel_order['chada_travel_payment_status'] !== 'chada_travel_paid') {
            return true;
        }
        $chada_travel_selected_country_ids = array_map(
            static fn(array $chada_travel_application): int => (int) $chada_travel_application['chada_travel_country_id'],
            CHADA_TRAVEL_Order_Repository::get_applications($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'])
        );
        if (!in_array($chada_travel_country_id, $chada_travel_selected_country_ids, true)) {
            self::log_dispatch($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'],
                (int) $chada_travel_payment['chada_travel_payment_id'], $chada_travel_event, self::AUDIENCE_COUNTRY_RESOURCE,
                false, '', $chada_travel_country_id, 'country_not_selected');
            return true;
        }
        $chada_travel_country = CHADA_TRAVEL_Country_Repository::find_by_id($chada_travel_wpdb, $chada_travel_country_id);
        if (!$chada_travel_country) {
            self::log_dispatch($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'],
                (int) $chada_travel_payment['chada_travel_payment_id'], $chada_travel_event, self::AUDIENCE_COUNTRY_RESOURCE,
                false, '', $chada_travel_country_id, 'country_unavailable');
            return true;
        }
        $chada_travel_guide = CHADA_TRAVEL_Media_Validator::local_attachment(
            (int) ($chada_travel_country['chada_travel_guide_attachment_id'] ?? 0),
            CHADA_TRAVEL_Media_Validator::IMAGE_MIME_TYPES
        );
        $chada_travel_checklist = CHADA_TRAVEL_Media_Validator::local_attachment(
            (int) ($chada_travel_country['chada_travel_checklist_attachment_id'] ?? 0),
            CHADA_TRAVEL_Media_Validator::CHECKLIST_MIME_TYPES
        );
        if (!$chada_travel_guide['valid'] || !$chada_travel_checklist['valid']) {
            $chada_travel_reason = !$chada_travel_guide['valid'] ? $chada_travel_guide['reason'] : $chada_travel_checklist['reason'];
            self::log_dispatch($chada_travel_wpdb, (int) $chada_travel_order['chada_travel_order_id'],
                (int) $chada_travel_payment['chada_travel_payment_id'], $chada_travel_event, self::AUDIENCE_COUNTRY_RESOURCE,
                false, '', $chada_travel_country_id, $chada_travel_reason);
            return true;
        }
        $chada_travel_message = self::build_country_resource_message(
            $chada_travel_order,
            (string) $chada_travel_country['chada_travel_country_name'],
            $chada_travel_settings
        );
        $chada_travel_result = self::send_mail(
            (string) $chada_travel_order['chada_travel_booker_email'],
            $chada_travel_message['subject'],
            $chada_travel_message['body'],
            CHADA_TRAVEL_Config::build_email_headers($chada_travel_settings),
            [$chada_travel_guide['path'], $chada_travel_checklist['path']]
        );
        self::log_dispatch(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            $chada_travel_event,
            self::AUDIENCE_COUNTRY_RESOURCE,
            $chada_travel_result['sent'],
            $chada_travel_result['failure_code'],
            $chada_travel_country_id,
            $chada_travel_result['sent'] ? '' : 'mail_failed'
        );
        return $chada_travel_result['sent'];
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_settings
     * @return array{subject: string, body: string}
     */
    private static function build_country_resource_message(
        array $chada_travel_order,
        string $chada_travel_country_name,
        array $chada_travel_settings
    ): array {
        $chada_travel_booker_name = trim((string) $chada_travel_order['chada_travel_booker_first_name'] . ' '
            . (string) $chada_travel_order['chada_travel_booker_last_name']);
        return [
            'subject' => 'Visa Guide and Documents Checklist - ' . $chada_travel_country_name,
            'body'    => CHADA_TRAVEL_Template::render('emails/customer/country-resource', [
                'chada_travel_booker_name'  => $chada_travel_booker_name,
                'chada_travel_country_name' => $chada_travel_country_name,
                'chada_travel_booking_id'   => (string) $chada_travel_order['chada_travel_booking_id'],
                'chada_travel_footer'       => CHADA_TRAVEL_Config::get_email_footer_text($chada_travel_settings),
            ]),
        ];
    }

    /**
     * Sends the administrator payment-review notification. The enabled flag is the authoritative, send-time gate
     * (never checked only at queue time - see queue_admin()); when disabled, or when no recipient/admin_email
     * resolves, nothing is sent and nothing is logged, so a later re-enable is unaffected. Never sends the same
     * event/payment/audience twice (see already_dispatched()).
     *
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_settings
     */
    private static function dispatch_admin(
        object $chada_travel_wpdb,
        string $chada_travel_event,
        array $chada_travel_order,
        array $chada_travel_payment,
        array $chada_travel_settings
    ): bool {
        if (empty($chada_travel_settings['chada_travel_email_admin_notifications_enabled'])) {
            return true;
        }
        $chada_travel_recipient = CHADA_TRAVEL_Config::resolve_admin_notification_email($chada_travel_settings);
        $chada_travel_message   = self::build_admin_message($chada_travel_event, $chada_travel_order, $chada_travel_payment, $chada_travel_settings);
        if ($chada_travel_recipient === '' || !$chada_travel_message) {
            return true;
        }
        $chada_travel_result = self::send_mail(
            $chada_travel_recipient,
            $chada_travel_message['subject'],
            $chada_travel_message['body'],
            CHADA_TRAVEL_Config::build_email_headers($chada_travel_settings)
        );
        self::log_dispatch(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            (int) $chada_travel_payment['chada_travel_payment_id'],
            $chada_travel_event,
            self::AUDIENCE_ADMIN,
            $chada_travel_result['sent'],
            $chada_travel_result['failure_code']
        );
        return $chada_travel_result['sent'];
    }

    /**
     * Sends through wp_mail(), capturing only a safe, short PHPMailer exception-code diagnostic on failure - never
     * the exception message (which can echo raw SMTP/server responses) and never the `wp_mail_failed` WP_Error's
     * `get_error_data()` payload (which includes the recipient/subject/message/headers). Satisfies "record the
     * attempt and whether it succeeded" without ever risking an infrastructure-detail or credential leak into the
     * `chada_travel_events` audit trail.
     *
     * @param list<string> $chada_travel_headers
     * @param list<string> $chada_travel_attachments
     * @return array{sent: bool, failure_code: string}
     */
    private static function send_mail(
        string $chada_travel_to,
        string $chada_travel_subject,
        string $chada_travel_body,
        array $chada_travel_headers,
        array $chada_travel_attachments = []
    ): array {
        if (!function_exists('wp_mail')) {
            return ['sent' => false, 'failure_code' => 'mail_unavailable'];
        }
        $chada_travel_failure_code = '';
        $chada_travel_capture = static function ($chada_travel_error) use (&$chada_travel_failure_code): void {
            if (!is_object($chada_travel_error) || !method_exists($chada_travel_error, 'get_error_data')) {
                return;
            }
            $chada_travel_data = $chada_travel_error->get_error_data();
            if (is_array($chada_travel_data) && isset($chada_travel_data['phpmailer_exception_code'])) {
                $chada_travel_failure_code = (string) (int) $chada_travel_data['phpmailer_exception_code'];
            }
        };
        if (function_exists('add_action')) {
            add_action('wp_mail_failed', $chada_travel_capture);
        }
        $chada_travel_sent = wp_mail($chada_travel_to, $chada_travel_subject, $chada_travel_body, $chada_travel_headers, $chada_travel_attachments);
        if (function_exists('remove_action')) {
            remove_action('wp_mail_failed', $chada_travel_capture);
        }
        return ['sent' => (bool) $chada_travel_sent, 'failure_code' => $chada_travel_sent ? '' : $chada_travel_failure_code];
    }

    /**
     * True only when a prior attempt for this exact payment/event/audience already succeeded. A historical event
     * logged before the audience field existed is treated as a customer-audience record (backward compatible -
     * an old customer dispatch never re-sends after this upgrade). A failed prior attempt (`sent` false) is
     * recorded but does not count, so it never permanently blocks a later legitimate retry of the same
     * notification.
     */
    private static function already_dispatched(
        object $chada_travel_wpdb,
        int $chada_travel_payment_id,
        string $chada_travel_event,
        string $chada_travel_audience,
        int $chada_travel_country_id = 0
    ): bool {
        $chada_travel_table = $chada_travel_wpdb->prefix . 'chada_travel_events';
        $chada_travel_sql   = $chada_travel_wpdb->prepare(
            "SELECT * FROM {$chada_travel_table} WHERE chada_travel_record_type = %s "
                . 'AND chada_travel_record_id = %d AND chada_travel_event_type = %s',
            'payment',
            $chada_travel_payment_id,
            'chada_travel_email_dispatched'
        );
        foreach ($chada_travel_wpdb->get_results($chada_travel_sql, ARRAY_A) ?: [] as $chada_travel_row) {
            $chada_travel_meta = json_decode((string) ($chada_travel_row['chada_travel_event_meta'] ?? ''), true) ?: [];
            if (($chada_travel_meta['event'] ?? null) !== $chada_travel_event) {
                continue;
            }
            $chada_travel_record_audience = (string) ($chada_travel_meta['audience'] ?? self::AUDIENCE_CUSTOMER);
            if ($chada_travel_record_audience !== $chada_travel_audience) {
                continue;
            }
            if ($chada_travel_audience === self::AUDIENCE_COUNTRY_RESOURCE
                && (int) ($chada_travel_meta['country_id'] ?? 0) !== $chada_travel_country_id) {
                continue;
            }
            if (!empty($chada_travel_meta['sent'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Records one dispatch attempt (success or failure) with a safe audience identifier and, only on failure, a
     * short numeric PHPMailer exception-code diagnostic (see send_mail()) - never a provider error message.
     */
    private static function log_dispatch(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        int $chada_travel_payment_id,
        string $chada_travel_event,
        string $chada_travel_audience,
        bool $chada_travel_sent,
        string $chada_travel_failure_code = '',
        int $chada_travel_country_id = 0,
        string $chada_travel_failure_reason = ''
    ): void {
        $chada_travel_meta = ['event' => $chada_travel_event, 'audience' => $chada_travel_audience, 'sent' => $chada_travel_sent];
        if (!$chada_travel_sent && $chada_travel_failure_code !== '') {
            $chada_travel_meta['failure_code'] = $chada_travel_failure_code;
        }
        if ($chada_travel_country_id > 0) {
            $chada_travel_meta['country_id'] = $chada_travel_country_id;
        }
        if (!$chada_travel_sent && $chada_travel_failure_reason !== '') {
            $chada_travel_meta['failure_reason'] = $chada_travel_failure_reason;
        }
        CHADA_TRAVEL_Payment_Repository::log_event(
            $chada_travel_wpdb,
            $chada_travel_order_id,
            $chada_travel_payment_id,
            'chada_travel_email_dispatched',
            'system',
            $chada_travel_meta
        );
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_context
     * @param array<string, mixed> $chada_travel_settings
     * @return array{subject: string, body: string}|null
     */
    private static function build_message(
        object $chada_travel_wpdb,
        string $chada_travel_event,
        array $chada_travel_order,
        array $chada_travel_payment,
        array $chada_travel_context,
        array $chada_travel_settings
    ): ?array {
        $chada_travel_booking_id   = (string) ($chada_travel_order['chada_travel_booking_id'] ?? '');
        $chada_travel_booker_name  = trim(
            (string) $chada_travel_order['chada_travel_booker_first_name'] . ' ' . (string) $chada_travel_order['chada_travel_booker_last_name']
        );
        $chada_travel_company_name = (string) $chada_travel_settings['chada_travel_company_name'];
        $chada_travel_footer       = CHADA_TRAVEL_Config::get_email_footer_text($chada_travel_settings);

        switch ($chada_travel_event) {
            case 'chada_travel_email_bank_proof_requested':
                $chada_travel_message = [
                    'subject' => "Your {$chada_travel_company_name} Booking ID and Bank Payment Instructions",
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/bank-proof-requested', [
                        'chada_travel_booker_name' => $chada_travel_booker_name,
                        'chada_travel_booking_id'  => $chada_travel_booking_id,
                        'chada_travel_proof_url'   => CHADA_TRAVEL_Config::get_payment_proof_url(
                            (string) ($chada_travel_context['proof_token'] ?? '')
                        ),
                    ]),
                ];
                break;
            case 'chada_travel_email_bank_proof_received':
                $chada_travel_message = [
                    'subject' => 'Bank Payment Proof Received',
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/bank-proof-received', [
                        'chada_travel_booker_name' => $chada_travel_booker_name,
                        'chada_travel_booking_id'  => $chada_travel_booking_id,
                    ]),
                ];
                break;
            case 'chada_travel_email_payment_pending':
                $chada_travel_message = [
                    'subject' => 'Your Digital Wallet Payment Details Were Received',
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/payment-pending', [
                        'chada_travel_booker_name'   => $chada_travel_booker_name,
                        'chada_travel_booking_id'    => $chada_travel_booking_id,
                        'chada_travel_provider_name' => CHADA_TRAVEL_Payment_Service::resolve_digital_wallet_snapshot(
                            CHADA_TRAVEL_Payment_Service::decode_snapshot(
                                $chada_travel_payment['chada_travel_payment_details_snapshot'] ?? null
                            )
                        )['name'],
                        'chada_travel_reference_no'  => CHADA_TRAVEL_Payment_Service::resolve_reference_no($chada_travel_payment),
                    ]),
                ];
                break;
            case 'chada_travel_email_payment_confirmed':
                $chada_travel_message = [
                    'subject' => 'Payment Confirmed - ' . (string) ($chada_travel_payment['chada_travel_transaction_id'] ?? ''),
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/payment-confirmed', [
                        'chada_travel_booker_name'    => $chada_travel_booker_name,
                        'chada_travel_booking_id'     => $chada_travel_booking_id,
                        'chada_travel_transaction_id' => (string) ($chada_travel_payment['chada_travel_transaction_id'] ?? ''),
                        'chada_travel_checklist_body' => self::build_checklist_body($chada_travel_wpdb, $chada_travel_order),
                        'chada_travel_upload_url'     => CHADA_TRAVEL_Config::get_document_upload_url(
                            (string) ($chada_travel_context['upload_token'] ?? '')
                        ),
                    ]),
                ];
                break;
            case 'chada_travel_email_payment_rejected':
                $chada_travel_message = [
                    'subject' => 'Action Required: Payment Not Confirmed',
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/payment-rejected', [
                        'chada_travel_booker_name'      => $chada_travel_booker_name,
                        'chada_travel_booking_id'       => $chada_travel_booking_id,
                        'chada_travel_rejection_reason' => (string) ($chada_travel_payment['chada_travel_rejection_reason'] ?? ''),
                        'chada_travel_checkout_url'     => CHADA_TRAVEL_Config::get_checkout_url(),
                    ]),
                ];
                break;
            case 'chada_travel_email_payment_failed':
                $chada_travel_message = [
                    'subject' => 'Payment Not Completed - ' . $chada_travel_booking_id,
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/payment-failed', [
                        'chada_travel_booker_name'    => $chada_travel_booker_name,
                        'chada_travel_booking_id'     => $chada_travel_booking_id,
                        'chada_travel_failure_reason' => (string) ($chada_travel_payment['chada_travel_failure_message'] ?? ''),
                        'chada_travel_checkout_url'   => CHADA_TRAVEL_Config::get_checkout_url(),
                    ]),
                ];
                break;
            case 'chada_travel_email_upload_link_reissued':
                $chada_travel_message = [
                    'subject' => 'Your Visa-Document Upload Link Was Reissued',
                    'body'    => CHADA_TRAVEL_Template::render('emails/customer/upload-link-reissued', [
                        'chada_travel_booker_name' => $chada_travel_booker_name,
                        'chada_travel_booking_id'  => $chada_travel_booking_id,
                        'chada_travel_upload_url'  => CHADA_TRAVEL_Config::get_document_upload_url(
                            (string) ($chada_travel_context['upload_token'] ?? '')
                        ),
                    ]),
                ];
                break;
            default:
                $chada_travel_message = null;
                break;
        }

        return $chada_travel_message === null ? null : [
            'subject' => $chada_travel_message['subject'],
            'body'    => $chada_travel_message['body'] . $chada_travel_footer,
        ];
    }

    /**
     * Builds the concise, plain-text administrator payment-review notification: Company Name, event title,
     * Booking ID, Payment Method, current payment status, Booker name (only when already available from the
     * authoritative order record), and a protected WordPress administrator link to the Booking Detail Payment
     * Review tab. Deliberately omits payment secrets, tokens, raw provider snapshots, full Digital Wallet/bank
     * account details, deposit-slip files, and any other unnecessary personal information - the administrator
     * must still log into WordPress with the correct capability to see any of that.
     *
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_settings
     * @return array{subject: string, body: string}|null
     */
    private static function build_admin_message(
        string $chada_travel_event,
        array $chada_travel_order,
        array $chada_travel_payment,
        array $chada_travel_settings
    ): ?array {
        $chada_travel_title = self::admin_event_titles()[$chada_travel_event] ?? null;
        if ($chada_travel_title === null) {
            return null;
        }

        $chada_travel_company_name = (string) $chada_travel_settings['chada_travel_company_name'];
        $chada_travel_booking_id   = (string) ($chada_travel_order['chada_travel_booking_id'] ?? '');
        // Uses this specific payment's own immutable snapshot, never current Settings, so a later Settings
        // provider-name change never relabels an already-sent/already-queued notification's payment method.
        $chada_travel_method_label = CHADA_TRAVEL_Payment_Service::resolve_method_label($chada_travel_payment);
        $chada_travel_status_label = CHADA_TRAVEL_Workflow::get_status_labels()[
            (string) ($chada_travel_payment['chada_travel_payment_status'] ?? '')
        ] ?? (string) ($chada_travel_payment['chada_travel_payment_status'] ?? '');
        $chada_travel_booker_name = trim(
            (string) ($chada_travel_order['chada_travel_booker_first_name'] ?? '')
                . ' ' . (string) ($chada_travel_order['chada_travel_booker_last_name'] ?? '')
        );

        return [
            'subject' => "[{$chada_travel_company_name}] {$chada_travel_title} - {$chada_travel_booking_id}",
            'body'    => CHADA_TRAVEL_Template::render('emails/admin/payment-notification', [
                'chada_travel_company_name' => $chada_travel_company_name,
                'chada_travel_title'        => $chada_travel_title,
                'chada_travel_booking_id'   => $chada_travel_booking_id,
                'chada_travel_method_label' => $chada_travel_method_label,
                'chada_travel_status_label' => $chada_travel_status_label,
                'chada_travel_booker_name'  => $chada_travel_booker_name,
                'chada_travel_review_url'   => self::admin_review_url((int) ($chada_travel_order['chada_travel_order_id'] ?? 0)),
            ]),
        ];
    }

    /** @return array<string, string> Administrator-notification event slug => plain-text event title. */
    private static function admin_event_titles(): array {
        return [
            'chada_travel_email_admin_bank_proof_submitted' => 'Bank Deposit Slip Submitted - Awaiting Verification',
            'chada_travel_email_admin_digital_wallet_submitted' => 'Digital Wallet Payment Submitted - Awaiting Verification',
        ];
    }

    /** Protected (login + capability required) Booking Detail Payment Review tab link; never a public URL. */
    private static function admin_review_url(int $chada_travel_order_id): string {
        if (!function_exists('admin_url') || $chada_travel_order_id <= 0) {
            return '';
        }
        return admin_url(
            'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . $chada_travel_order_id . '&tab=payment'
        );
    }

    /**
     * Builds the plain-text Step-by-Step/Documents Checklist section from the current database state (never
     * cached), so a slow-to-send queued email still reflects the latest requirement configuration. Lists each
     * application's country guide URL (when configured) and its active requirement labels.
     *
     * @param array<string, mixed> $chada_travel_order
     */
    private static function build_checklist_body(object $chada_travel_wpdb, array $chada_travel_order): string {
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id']
        );

        $chada_travel_checklist_applications = [];
        foreach ($chada_travel_applications as $chada_travel_application) {
            $chada_travel_country_id = (int) $chada_travel_application['chada_travel_country_id'];
            $chada_travel_applicant  = trim(
                (string) $chada_travel_application['chada_travel_first_name'] . ' ' . (string) $chada_travel_application['chada_travel_last_name']
            );
            $chada_travel_requirements = CHADA_TRAVEL_Requirement_Repository::get_active_for_country(
                $chada_travel_wpdb,
                $chada_travel_country_id,
                (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
            );
            $chada_travel_country   = CHADA_TRAVEL_Country_Repository::find_by_id($chada_travel_wpdb, $chada_travel_country_id);
            $chada_travel_guide_id  = (int) ($chada_travel_country['chada_travel_guide_attachment_id'] ?? 0);
            $chada_travel_guide_url = '';
            if ($chada_travel_guide_id > 0 && function_exists('wp_get_attachment_url')) {
                $chada_travel_guide_url = (string) (wp_get_attachment_url($chada_travel_guide_id) ?: '');
            }
            $chada_travel_checklist_applications[] = [
                'country_name'       => (string) $chada_travel_application['chada_travel_country_name_snapshot'],
                'applicant'          => $chada_travel_applicant,
                'requirement_labels' => array_map(
                    static fn(array $chada_travel_requirement): string
                        => (string) $chada_travel_requirement['chada_travel_requirement_label'],
                    $chada_travel_requirements
                ),
                'guide_url'          => $chada_travel_guide_url,
            ];
        }

        return CHADA_TRAVEL_Template::render('emails/partials/checklist-body', [
            'chada_travel_applications' => $chada_travel_checklist_applications,
        ]);
    }
}
