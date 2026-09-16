<?php
/**
 * Booking Detail / Project View: the four required tabs (Overview, Payment Review, Applications & Documents,
 * Activity) reached from admin.php?page=chada-travel-bookings&view=<order-id>[&tab=...] (docs/wordpress-administrator-
 * project-view.html). This is the authoritative detailed payment-review workspace; CHADA_TRAVEL_Admin_Payment_Review
 * stays a focused work queue that links here for every decision.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Admin_Booking_Detail {
    private const CAPABILITY = 'manage_chada_travel_visa_applications';
    private const PAYMENT_CAPABILITY = 'verify_chada_travel_payments';
    private const DOCUMENT_CAPABILITY = 'view_chada_travel_visa_documents';
    private const REVIEW_CAPABILITY = 'review_chada_travel_visa_documents';

    private const TAB_OVERVIEW = 'overview';
    private const TAB_PAYMENT = 'payment';
    private const TAB_DOCUMENTS = 'documents';
    private const TAB_ACTIVITY = 'activity';

    private const REVIEW_DOCUMENT_ACTION = 'chada_travel_review_document';
    private const VIEW_DOCUMENT_ACTION = 'chada_travel_view_document';
    private const ISSUE_UPLOAD_ACTION = 'chada_travel_issue_upload_link';
    private const REVOKE_UPLOAD_ACTION = 'chada_travel_revoke_upload_link';
    private const UPDATE_APPLICANT_ACTION = 'chada_travel_update_applicant_details';

    public static function register(): void {
        add_action('admin_post_' . self::REVIEW_DOCUMENT_ACTION, [self::class, 'handle_review_document']);
        add_action('admin_post_' . self::VIEW_DOCUMENT_ACTION, [self::class, 'handle_view_document']);
        add_action('admin_post_' . self::ISSUE_UPLOAD_ACTION, [self::class, 'handle_issue_upload_link']);
        add_action('admin_post_' . self::REVOKE_UPLOAD_ACTION, [self::class, 'handle_revoke_upload_link']);
        add_action('admin_post_' . self::UPDATE_APPLICANT_ACTION, [self::class, 'handle_update_applicant_details']);
    }

    /** @return list<string> */
    private static function allowed_tabs(): array {
        return [self::TAB_OVERVIEW, self::TAB_PAYMENT, self::TAB_DOCUMENTS, self::TAB_ACTIVITY];
    }

    public static function render(int $chada_travel_order_id): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view Visa Bookings.', 'chada-travel'));
        }
        global $wpdb;
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, $chada_travel_order_id);
        if (!$chada_travel_order) {
            wp_die(esc_html__('Visa Booking not found.', 'chada-travel'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.
        $chada_travel_tab_raw = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('tab', self::TAB_OVERVIEW));
        $chada_travel_tab     = in_array($chada_travel_tab_raw, self::allowed_tabs(), true) ? $chada_travel_tab_raw : self::TAB_OVERVIEW;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_selected_application_id = CHADA_TRAVEL_Config::sanitize_request_int(
            CHADA_TRAVEL_Config::sanitize_request_get('application', 0)
        );

        $chada_travel_payment      = CHADA_TRAVEL_Payment_Repository::get_active_payment($wpdb, $chada_travel_order_id);
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications($wpdb, $chada_travel_order_id);

        CHADA_TRAVEL_Template::output('admin/booking-detail/page-open');
        self::render_notices();
        self::render_header($wpdb, $chada_travel_order, $chada_travel_payment, $chada_travel_applications);
        self::render_tablist($chada_travel_order_id, $chada_travel_tab);

        self::render_tabpanel(self::TAB_OVERVIEW, $chada_travel_tab, function () use (
            $wpdb, $chada_travel_order, $chada_travel_payment, $chada_travel_applications
        ): void {
            self::render_overview_tab($wpdb, $chada_travel_order, $chada_travel_payment, $chada_travel_applications);
        });
        self::render_tabpanel(self::TAB_PAYMENT, $chada_travel_tab, function () use ($chada_travel_order, $chada_travel_payment): void {
            self::render_payment_tab($chada_travel_order, $chada_travel_payment);
        });
        self::render_tabpanel(self::TAB_DOCUMENTS, $chada_travel_tab, function () use (
            $wpdb, $chada_travel_order, $chada_travel_applications, $chada_travel_selected_application_id
        ): void {
            self::render_documents_tab($wpdb, $chada_travel_order, $chada_travel_applications, $chada_travel_selected_application_id);
        });
        self::render_tabpanel(
            self::TAB_ACTIVITY,
            $chada_travel_tab,
            function () use ($wpdb, $chada_travel_order, $chada_travel_applications): void {
                self::render_activity_tab($wpdb, $chada_travel_order, $chada_travel_applications);
            }
        );

        echo '</div>';
    }

    /** Reads one read-only notice query flag; the resulting text is only used to select a fixed message below. */
    private static function get_notice_param(string $chada_travel_key): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag, no state change.
        return CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get($chada_travel_key));
    }

    private static function render_notices(): void {
        $chada_travel_booking_notice  = self::get_notice_param('chada_travel_booking_notice');
        $chada_travel_payment_notice  = self::get_notice_param('chada_travel_payment_notice');
        $chada_travel_note_notice     = self::get_notice_param('chada_travel_note_notice');
        $chada_travel_tag_notice      = self::get_notice_param('chada_travel_tag_notice');
        $chada_travel_document_notice = self::get_notice_param('chada_travel_document_notice');
        $chada_travel_upload_notice   = self::get_notice_param('chada_travel_upload_notice');
        $chada_travel_applicant_notice = self::get_notice_param('chada_travel_applicant_notice');

        $chada_travel_all = [
            'chada_travel_booking_notice'   => ['archived' => __('Visa Booking archived.', 'chada-travel'),
                'restored' => __('Visa Booking restored.', 'chada-travel')],
            'chada_travel_payment_notice'   => ['confirmed' => __('Payment confirmed.', 'chada-travel'),
                'rejected' => __('Payment rejected.', 'chada-travel'),
                'error' => __('That payment could not be updated from its current status.', 'chada-travel'),
                'invalid' => __('That payment could not be found.', 'chada-travel')],
            'chada_travel_note_notice'      => ['saved' => __('Internal note saved.', 'chada-travel')],
            'chada_travel_tag_notice'       => ['assigned' => __('Tag assigned.', 'chada-travel'),
                'removed' => __('Tag removed.', 'chada-travel'),
                'error'   => __('That tag could not be applied.', 'chada-travel'),
            ],
            'chada_travel_document_notice'  => ['saved' => __('Document decision saved.', 'chada-travel'),
                'error' => __('A review note is required to reject or request a replacement.', 'chada-travel')],
            'chada_travel_upload_notice'    => ['issued' => __('Upload link issued.', 'chada-travel'),
                'revoked' => __('Upload link revoked.', 'chada-travel')],
            'chada_travel_applicant_notice' => ['saved' => __('Applicant details saved.', 'chada-travel'),
                'error' => __('Enter a valid Target Travel Date.', 'chada-travel'),
                'invalid' => __('That application could not be found.', 'chada-travel')],
        ];
        $chada_travel_values = compact(
            'chada_travel_booking_notice',
            'chada_travel_payment_notice',
            'chada_travel_note_notice',
            'chada_travel_tag_notice',
            'chada_travel_document_notice',
            'chada_travel_upload_notice',
            'chada_travel_applicant_notice'
        );
        foreach ($chada_travel_all as $chada_travel_key => $chada_travel_map) {
            $chada_travel_value = $chada_travel_values[$chada_travel_key];
            if ($chada_travel_value !== '' && isset($chada_travel_map[$chada_travel_value])) {
                $chada_travel_type = in_array($chada_travel_value, ['error', 'invalid'], true) ? 'error' : 'success';
                CHADA_TRAVEL_Template::output('admin/booking-detail/notice', [
                    'chada_travel_type'    => $chada_travel_type,
                    'chada_travel_message' => $chada_travel_map[$chada_travel_value],
                ]);
            }
        }
    }

    /**
     * @param array<string, mixed>       $chada_travel_order
     * @param array<string, mixed>|null  $chada_travel_payment
     * @param list<array<string, mixed>> $chada_travel_applications
     */
    private static function render_header(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        ?array $chada_travel_payment,
        array $chada_travel_applications
    ): void {
        $chada_travel_settings  = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_order_id  = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_is_archived   = !empty($chada_travel_order['chada_travel_archived_at']);

        $chada_travel_badges_html = '';
        if ($chada_travel_is_archived) {
            $chada_travel_badges_html .= '<span class="chada-travel-badge is-warning">' . esc_html__('Archived', 'chada-travel')
                . '</span>';
        }

        $chada_travel_meta_text = sprintf(
            /* translators: 1: created date/time, 2: last-updated date/time. */
            __('Created %1$s &middot; Updated %2$s', 'chada-travel'),
            CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_order['chada_travel_created_at'], $chada_travel_settings),
            CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_order['chada_travel_updated_at'], $chada_travel_settings)
        );

        $chada_travel_archive_form_html = current_user_can(self::CAPABILITY)
            ? self::build_archive_form_html($chada_travel_order_id, $chada_travel_is_archived)
            : '';

        $chada_travel_application_count = count($chada_travel_applications);
        $chada_travel_country_count = count(array_unique(array_map(
            static fn(array $chada_travel_application): string => (string) $chada_travel_application['chada_travel_country_code_snapshot'],
            $chada_travel_applications
        )));
        $chada_travel_order_status_label   = $chada_travel_status_labels[$chada_travel_order['chada_travel_order_status']]
            ?? $chada_travel_order['chada_travel_order_status'];
        $chada_travel_payment_status_label = $chada_travel_status_labels[$chada_travel_order['chada_travel_payment_status']]
            ?? $chada_travel_order['chada_travel_payment_status'];
        $chada_travel_detail_grid_html = '<dt>' . esc_html__('Order status', 'chada-travel')
            . '</dt><dd><span class="chada-travel-badge">' . esc_html($chada_travel_order_status_label) . '</span></dd>'
            . '<dt>' . esc_html__('Payment status', 'chada-travel') . '</dt><dd><span class="chada-travel-badge">'
            . esc_html($chada_travel_payment_status_label) . '</span></dd>'
            . '<dt>' . esc_html__('Applications', 'chada-travel') . '</dt><dd>' . esc_html(sprintf(
                /* translators: 1: application count, 2: distinct country count. */
                __('%1$d applications across %2$d countries', 'chada-travel'),
                $chada_travel_application_count,
                $chada_travel_country_count
            )) . '</dd>'
            . '<dt>' . esc_html__('Total', 'chada-travel') . '</dt><dd>' . esc_html(CHADA_TRAVEL_Config::format_money(
                (float) $chada_travel_order['chada_travel_total'],
                (string) $chada_travel_order['chada_travel_currency'],
                $chada_travel_settings
            )) . '</dd>';

        CHADA_TRAVEL_Template::output('admin/booking-detail/header', [
            'chada_travel_back_url'           => admin_url('admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG),
            'chada_travel_booking_label_html' => CHADA_TRAVEL_Admin_Bookings::booking_label($chada_travel_order),
            'chada_travel_badges_html'        => $chada_travel_badges_html,
            'chada_travel_meta_text'          => $chada_travel_meta_text,
            'chada_travel_archive_form_html'  => $chada_travel_archive_form_html,
            'chada_travel_detail_grid_html'   => $chada_travel_detail_grid_html,
            'chada_travel_workflow_progress_html' => self::build_workflow_progress_html(
                $chada_travel_order,
                $chada_travel_payment,
                $chada_travel_applications
            ),
        ]);
    }

    private static function build_archive_form_html(int $chada_travel_order_id, bool $chada_travel_is_archived): string {
        $chada_travel_action = $chada_travel_is_archived
            ? CHADA_TRAVEL_Admin_Bookings::RESTORE_ACTION : CHADA_TRAVEL_Admin_Bookings::ARCHIVE_ACTION;
        return CHADA_TRAVEL_Template::render('admin/booking-detail/archive-form', [
            'chada_travel_action_url'   => admin_url('admin-post.php'),
            'chada_travel_confirm_text' => $chada_travel_is_archived
                ? __('Restore this booking to active worklists?', 'chada-travel')
                : __('Archive this booking? Payment and workflow history are preserved.', 'chada-travel'),
            'chada_travel_action'       => $chada_travel_action,
            'chada_travel_order_id'     => (string) $chada_travel_order_id,
            'chada_travel_nonce_html'   => wp_nonce_field($chada_travel_action . '_' . $chada_travel_order_id, '_wpnonce', true, false),
            'chada_travel_button_label' => $chada_travel_is_archived
                ? __('Restore', 'chada-travel')
                : __('Archive', 'chada-travel'),
        ]);
    }

    /**
     * @param array<string, mixed>       $chada_travel_order
     * @param array<string, mixed>|null  $chada_travel_payment
     * @param list<array<string, mixed>> $chada_travel_applications
     */
    private static function build_workflow_progress_html(
        array $chada_travel_order,
        ?array $chada_travel_payment,
        array $chada_travel_applications
    ): string {
        $chada_travel_payment_status = $chada_travel_payment ? (string) $chada_travel_payment['chada_travel_payment_status'] : '';
        $chada_travel_order_status   = (string) $chada_travel_order['chada_travel_order_status'];

        $chada_travel_pending_payment_statuses = ['chada_travel_awaiting_proof', 'chada_travel_awaiting_verification', 'chada_travel_rejected',
            'chada_travel_processing'];
        $chada_travel_pending_order_statuses = ['chada_travel_pending_payment', 'chada_travel_payment_review', 'chada_travel_payment_rejected'];
        $chada_travel_payment_complete = $chada_travel_payment_status === 'chada_travel_paid';
        $chada_travel_payment_current  = !$chada_travel_payment_complete && (
            in_array($chada_travel_payment_status, $chada_travel_pending_payment_statuses, true)
            || in_array($chada_travel_order_status, $chada_travel_pending_order_statuses, true)
        );

        $chada_travel_has_applications  = count($chada_travel_applications) > 0;
        $chada_travel_incomplete_applications = array_filter(
            $chada_travel_applications,
            static fn(array $chada_travel_application): bool =>
                $chada_travel_application['chada_travel_file_status'] !== 'chada_travel_complete_files'
        );
        $chada_travel_documents_complete = $chada_travel_payment_complete && $chada_travel_has_applications
            && !$chada_travel_incomplete_applications;
        $chada_travel_documents_current = $chada_travel_payment_complete && !$chada_travel_documents_complete;

        $chada_travel_order_complete = $chada_travel_order_status === 'chada_travel_completed';
        $chada_travel_order_current   = !$chada_travel_order_complete && $chada_travel_documents_complete;

        $chada_travel_payment_state   = self::step_state($chada_travel_payment_complete, $chada_travel_payment_current);
        $chada_travel_documents_state = self::step_state($chada_travel_documents_complete, $chada_travel_documents_current);
        $chada_travel_order_state     = self::step_state($chada_travel_order_complete, $chada_travel_order_current);
        $chada_travel_steps = [
            ['label' => __('Booking Created', 'chada-travel'), 'state' => 'complete'],
            ['label' => __('Payment Review', 'chada-travel'), 'state' => $chada_travel_payment_state],
            ['label' => __('Document Review', 'chada-travel'), 'state' => $chada_travel_documents_state],
            ['label' => __('Completed', 'chada-travel'), 'state' => $chada_travel_order_state],
        ];

        $chada_travel_steps_html = '';
        foreach ($chada_travel_steps as $chada_travel_index => $chada_travel_step) {
            $chada_travel_class = 'chada-travel-progress-step is-' . $chada_travel_step['state'];
            $chada_travel_marker = $chada_travel_step['state'] === 'complete' ? '&#10003;' : esc_html((string) ($chada_travel_index + 1));
            $chada_travel_steps_html .= '<div class="' . esc_attr($chada_travel_class) . '"'
                . ($chada_travel_step['state'] === 'current' ? ' aria-current="step"' : '') . '>'
                . '<span class="chada-travel-progress-marker">' . $chada_travel_marker . '</span>'
                . esc_html($chada_travel_step['label'])
                . '</div>';
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/workflow-progress', [
            'chada_travel_steps_html' => $chada_travel_steps_html,
        ]);
    }

    private static function step_state(bool $chada_travel_complete, bool $chada_travel_current): string {
        if ($chada_travel_complete) {
            return 'complete';
        }
        return $chada_travel_current ? 'current' : 'pending';
    }

    private static function render_tablist(int $chada_travel_order_id, string $chada_travel_current_tab): void {
        $chada_travel_tab_labels = [
            self::TAB_OVERVIEW  => __('Overview', 'chada-travel'),
            self::TAB_PAYMENT   => __('Payment Review', 'chada-travel'),
            self::TAB_DOCUMENTS => __('Applications & Documents', 'chada-travel'),
            self::TAB_ACTIVITY  => __('Activity', 'chada-travel'),
        ];
        $chada_travel_tabs = [];
        foreach ($chada_travel_tab_labels as $chada_travel_key => $chada_travel_label) {
            $chada_travel_href = admin_url(
                'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . $chada_travel_order_id . '&tab=' . $chada_travel_key
            );
            $chada_travel_tabs[] = [
                'key'      => $chada_travel_key,
                'label'    => $chada_travel_label,
                'href'     => $chada_travel_href,
                'selected' => $chada_travel_key === $chada_travel_current_tab,
            ];
        }
        CHADA_TRAVEL_Template::output('admin/booking-detail/tablist', ['chada_travel_tabs' => $chada_travel_tabs]);
    }

    private static function render_tabpanel(
        string $chada_travel_tab_key,
        string $chada_travel_current_tab,
        callable $chada_travel_render
    ): void {
        CHADA_TRAVEL_Template::output('admin/booking-detail/tabpanel-open', [
            'chada_travel_tab_key' => $chada_travel_tab_key,
            'chada_travel_visible' => $chada_travel_tab_key === $chada_travel_current_tab,
        ]);
        $chada_travel_render();
        echo '</section>';
    }

    // ============================================================ OVERVIEW TAB

    /**
     * @param array<string, mixed>       $chada_travel_order
     * @param array<string, mixed>|null  $chada_travel_payment
     * @param list<array<string, mixed>> $chada_travel_applications
     */
    private static function render_overview_tab(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        ?array $chada_travel_payment,
        array $chada_travel_applications
    ): void {
        $chada_travel_order_id = (int) $chada_travel_order['chada_travel_order_id'];

        CHADA_TRAVEL_Template::output('admin/booking-detail/overview/tab', [
            'chada_travel_booker_section_html'     => self::build_booker_section_html($chada_travel_order),
            'chada_travel_payment_summary_html'    => self::build_payment_summary_section_html($chada_travel_order, $chada_travel_payment),
            'chada_travel_applications_table_html' => self::build_applications_table_section_html(
                $chada_travel_applications,
                $chada_travel_order
            ),
            'chada_travel_tags_section_html' => '',
            'chada_travel_note_section_html' => '',
            'chada_travel_extensions_html' => function_exists('apply_filters')
                ? (string) apply_filters(
                    'chada_travel_booking_detail_overview_extensions',
                    '',
                    $chada_travel_wpdb,
                    $chada_travel_order,
                    $chada_travel_payment,
                    $chada_travel_applications
                ) : '',
        ]);
    }

    /** @param array<string, mixed> $chada_travel_order */
    private static function build_booker_section_html(array $chada_travel_order): string {
        $chada_travel_settings = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_full_name = trim(
            $chada_travel_order['chada_travel_booker_first_name'] . ' ' . $chada_travel_order['chada_travel_booker_last_name']
        );
        $chada_travel_address    = (string) ($chada_travel_order['chada_travel_booker_full_address'] ?? __('Not provided', 'chada-travel'));
        $chada_travel_cancellation_refund_at = (string) ($chada_travel_order['chada_travel_cancellation_refund_accepted_at'] ?? '');
        $chada_travel_snapshot = CHADA_TRAVEL_Policy_Snapshot::decode($chada_travel_order['chada_travel_policy_snapshot'] ?? null);
        $chada_travel_existing_record_notice = __('Existing record — detailed policy snapshot unavailable.', 'chada-travel');
        $chada_travel_fields = [
            [__('Full name', 'chada-travel'), $chada_travel_full_name],
            [__('Email', 'chada-travel'), (string) $chada_travel_order['chada_travel_booker_email']],
            [__('Mobile', 'chada-travel'), (string) $chada_travel_order['chada_travel_booker_mobile']],
            [__('Address', 'chada-travel'), $chada_travel_address],
            [__('Policy version', 'chada-travel'), (string) $chada_travel_order['chada_travel_policy_version']],
            [__('Policy effective date', 'chada-travel'), $chada_travel_snapshot !== null
                ? (string) $chada_travel_snapshot['effective_date'] : __('Not recorded (existing order)', 'chada-travel')],
            [__('Privacy accepted', 'chada-travel'),
                CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_order['chada_travel_privacy_accepted_at'], $chada_travel_settings)],
            [__('Terms accepted', 'chada-travel'),
                CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_order['chada_travel_terms_accepted_at'], $chada_travel_settings)],
            [__('Cancellation & refund', 'chada-travel'), $chada_travel_cancellation_refund_at !== ''
                ? CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_cancellation_refund_at, $chada_travel_settings)
                : __('Not yet acknowledged', 'chada-travel')],
        ];
        $chada_travel_fields_grid_html = '';
        foreach ($chada_travel_fields as [$chada_travel_label, $chada_travel_value]) {
            $chada_travel_fields_grid_html .= '<dt>' . esc_html($chada_travel_label) . '</dt><dd>' . esc_html($chada_travel_value) . '</dd>';
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/booker-section', [
            'chada_travel_fields_grid_html'           => $chada_travel_fields_grid_html,
            'chada_travel_policy_snapshot_links_html' => self::build_policy_snapshot_links_html(
                $chada_travel_snapshot,
                $chada_travel_existing_record_notice
            ),
        ]);
    }

    /**
     * Renders each policy's safe resolved link exactly as recorded in this order's immutable snapshot at
     * consent time - never substituted with the current Settings policy bundle, which may have changed since.
     * Shows the existing-record notice instead when no detailed snapshot exists (an order created before the
     * chada_travel_policy_snapshot column, or one whose stored JSON failed to decode).
     *
     * @param array<string, mixed>|null $chada_travel_snapshot CHADA_TRAVEL_Policy_Snapshot::decode() result.
     */
    private static function build_policy_snapshot_links_html(
        ?array $chada_travel_snapshot,
        string $chada_travel_existing_record_notice
    ): string {
        if ($chada_travel_snapshot === null) {
            $chada_travel_grid_html = '<dt>' . esc_html__('Policy snapshot', 'chada-travel') . '</dt><dd>'
                . esc_html($chada_travel_existing_record_notice) . '</dd>';
            return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/policy-snapshot-links', [
                'chada_travel_grid_html' => $chada_travel_grid_html,
            ]);
        }
        $chada_travel_labels = CHADA_TRAVEL_Policy_Registry::get_labels();
        $chada_travel_grid_html = '';
        foreach (CHADA_TRAVEL_Policy_Registry::get_definitions() as $chada_travel_key => $chada_travel_definition) {
            $chada_travel_entry = (array) ($chada_travel_snapshot[$chada_travel_definition['snapshot_key']] ?? []);
            $chada_travel_url   = (string) ($chada_travel_entry['url'] ?? '');
            $chada_travel_grid_html .= '<dt>' . esc_html($chada_travel_labels[$chada_travel_key] ?? $chada_travel_key) . '</dt><dd>';
            $chada_travel_grid_html .= $chada_travel_url !== ''
                ? '<a href="' . esc_url($chada_travel_url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html($chada_travel_url) . '</a>'
                : esc_html__('Not available', 'chada-travel');
            $chada_travel_grid_html .= '</dd>';
        }
        return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/policy-snapshot-links', [
            'chada_travel_grid_html' => $chada_travel_grid_html,
        ]);
    }

    /**
     * @param array<string, mixed>      $chada_travel_order
     * @param array<string, mixed>|null $chada_travel_payment
     */
    private static function build_payment_summary_section_html(array $chada_travel_order, ?array $chada_travel_payment): string {
        $chada_travel_payment_url = admin_url(
            'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . (int) $chada_travel_order['chada_travel_order_id']
                . '&tab=payment'
        );

        if (!$chada_travel_payment) {
            return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/payment-summary-section', [
                'chada_travel_review_url'       => $chada_travel_payment_url,
                'chada_travel_has_payment'      => false,
                'chada_travel_detail_grid_html' => '',
            ]);
        }

        $chada_travel_settings      = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_submitted_at  = (string) ($chada_travel_payment['chada_travel_submitted_at'] ?? '');
        // Uses this specific payment's own immutable snapshot, never current Settings, so a later Settings
        // provider-name change never relabels an already-created payment shown in this summary.
        $chada_travel_detail_grid_html = '<dt>' . esc_html__('Method', 'chada-travel') . '</dt><dd>'
            . esc_html(CHADA_TRAVEL_Payment_Service::resolve_method_label($chada_travel_payment)) . '</dd>'
            . '<dt>' . esc_html__('Payment reference', 'chada-travel') . '</dt><dd>'
            . esc_html((string) $chada_travel_payment['chada_travel_payment_reference']) . '</dd>'
            . '<dt>' . esc_html__('Submitted', 'chada-travel') . '</dt><dd>' . esc_html($chada_travel_submitted_at !== ''
                ? CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_submitted_at, $chada_travel_settings)
                : __('Not yet submitted', 'chada-travel')) . '</dd>'
            . '<dt>' . esc_html__('Transaction ID', 'chada-travel') . '</dt><dd>' . esc_html(
                (string) ($chada_travel_payment['chada_travel_transaction_id']
                    ?? __('Generated only after confirmation', 'chada-travel'))
            ) . '</dd>'
            . '<dt>' . esc_html__('Status', 'chada-travel') . '</dt><dd><span class="chada-travel-badge">' . esc_html(
                $chada_travel_status_labels[$chada_travel_payment['chada_travel_payment_status']]
                    ?? $chada_travel_payment['chada_travel_payment_status']
            ) . '</span></dd>';

        return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/payment-summary-section', [
            'chada_travel_review_url'       => $chada_travel_payment_url,
            'chada_travel_has_payment'      => true,
            'chada_travel_detail_grid_html' => $chada_travel_detail_grid_html,
        ]);
    }

    /**
     * @param list<array<string, mixed>> $chada_travel_applications
     * @param array<string, mixed>       $chada_travel_order
     */
    private static function build_applications_table_section_html(
        array $chada_travel_applications,
        array $chada_travel_order
    ): string {
        $chada_travel_settings      = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_documents_url = admin_url(
            'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . (int) $chada_travel_order['chada_travel_order_id']
                . '&tab=documents'
        );

        $chada_travel_order_id = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_not_provided = __('Not provided', 'chada-travel');
        $chada_travel_can_edit = current_user_can(self::CAPABILITY);
        $chada_travel_total = 0.0;
        $chada_travel_rows_html = '';
        foreach ($chada_travel_applications as $chada_travel_application) {
            $chada_travel_total += (float) $chada_travel_application['chada_travel_processing_fee_snapshot'];
            $chada_travel_applicant_name = $chada_travel_application['chada_travel_first_name']
                . ' ' . $chada_travel_application['chada_travel_last_name'];
            $chada_travel_application_id = (int) $chada_travel_application['chada_travel_application_id'];
            $chada_travel_dob_raw = (string) ($chada_travel_application['chada_travel_date_of_birth'] ?? '');
            $chada_travel_travel_date_raw = (string) ($chada_travel_application['chada_travel_target_travel_date'] ?? '');
            $chada_travel_rows_html .= CHADA_TRAVEL_Template::render('admin/booking-detail/overview/application-row', [
                'chada_travel_application_reference' => (string) $chada_travel_application['chada_travel_application_reference'],
                'chada_travel_applicant_name'        => $chada_travel_applicant_name,
                'chada_travel_date_of_birth_display' => $chada_travel_dob_raw !== '' ? $chada_travel_dob_raw : $chada_travel_not_provided,
                'chada_travel_travel_date_display'   => $chada_travel_travel_date_raw !== '' ? $chada_travel_travel_date_raw
                    : $chada_travel_not_provided,
                'chada_travel_country_name'          => (string) $chada_travel_application['chada_travel_country_name_snapshot'],
                'chada_travel_file_status_label'     => (string) ($chada_travel_status_labels[$chada_travel_application['chada_travel_file_status']]
                    ?? $chada_travel_application['chada_travel_file_status']),
                'chada_travel_checklist_version'     => (string) $chada_travel_application['chada_travel_checklist_version_snapshot'],
                'chada_travel_fee_label'             => CHADA_TRAVEL_Config::format_money(
                    (float) $chada_travel_application['chada_travel_processing_fee_snapshot'],
                    (string) $chada_travel_application['chada_travel_currency_snapshot'],
                    $chada_travel_settings
                ),
                'chada_travel_can_edit'              => $chada_travel_can_edit,
                'chada_travel_edit_form_html'        => $chada_travel_can_edit ? self::build_applicant_edit_form_html(
                    $chada_travel_order_id,
                    $chada_travel_application_id,
                    $chada_travel_dob_raw,
                    $chada_travel_travel_date_raw
                ) : '',
            ]);
        }
        if (!$chada_travel_applications) {
            $chada_travel_rows_html = '<tr><td colspan="9">' . esc_html__('No applications on this booking.', 'chada-travel')
                . '</td></tr>';
        }

        $chada_travel_tfoot_html = '';
        if ($chada_travel_applications) {
            $chada_travel_total_label = CHADA_TRAVEL_Config::format_money(
                $chada_travel_total,
                (string) $chada_travel_order['chada_travel_currency'],
                $chada_travel_settings
            );
            $chada_travel_tfoot_html = '<tfoot><tr><th colspan="7">' . esc_html__('Total', 'chada-travel') . '</th><th>'
                . esc_html($chada_travel_total_label) . '</th><th></th></tr></tfoot>';
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/applications-table-section', [
            'chada_travel_documents_url' => $chada_travel_documents_url,
            'chada_travel_rows_html'     => $chada_travel_rows_html,
            'chada_travel_tfoot_html'    => $chada_travel_tfoot_html,
        ]);
    }

    /** Small inline Date of Birth / Target Travel Date correction form for one application row. */
    private static function build_applicant_edit_form_html(
        int $chada_travel_order_id,
        int $chada_travel_application_id,
        string $chada_travel_date_of_birth,
        string $chada_travel_target_travel_date
    ): string {
        return CHADA_TRAVEL_Template::render('admin/booking-detail/overview/application-edit-form', [
            'chada_travel_action_url'          => admin_url('admin-post.php'),
            'chada_travel_action'              => self::UPDATE_APPLICANT_ACTION,
            'chada_travel_order_id'            => (string) $chada_travel_order_id,
            'chada_travel_application_id'      => (string) $chada_travel_application_id,
            'chada_travel_date_of_birth'       => $chada_travel_date_of_birth,
            'chada_travel_target_travel_date'  => $chada_travel_target_travel_date,
            'chada_travel_nonce_html'          => wp_nonce_field(
                self::UPDATE_APPLICANT_ACTION . '_' . $chada_travel_order_id,
                '_wpnonce',
                true,
                false
            ),
        ]);
    }

    /** @param list<array<string, mixed>> $chada_travel_tags */


    /** @return list<array{chada_travel_created_at: string, note: string}> */




    /**
     * Administrator-only correction of one application's Date of Birth (never customer-submitted, see
     * CHADA_TRAVEL_Order_Repository::replace_applications()) and Target Travel Date. Date of Birth may be submitted
     * empty to clear it; Target Travel Date must always remain a valid date - admin corrections are not bound by
     * Stage 2's forward-looking "tomorrow or later" rule, since a booking's applications should always keep some
     * travel date on record.
     */
    public static function handle_update_applicant_details(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Bookings.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs immediately below.
        $chada_travel_order_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('order_id', 0));
        check_admin_referer(self::UPDATE_APPLICANT_ACTION . '_' . $chada_travel_order_id);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above.
        $chada_travel_application_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('application_id', 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above.
        $chada_travel_date_of_birth = CHADA_TRAVEL_Config::sanitize_request_post('date_of_birth');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above.
        $chada_travel_target_travel_date = CHADA_TRAVEL_Config::sanitize_request_post('target_travel_date');

        $chada_travel_dob_valid = $chada_travel_date_of_birth === ''
            || CHADA_TRAVEL_Checkout_Validator::is_valid_birth_date($chada_travel_date_of_birth);
        $chada_travel_travel_date_valid = $chada_travel_target_travel_date !== ''
            && CHADA_TRAVEL_Checkout_Validator::is_valid_travel_date($chada_travel_target_travel_date, '0001-01-01');
        if (!$chada_travel_dob_valid || !$chada_travel_travel_date_valid) {
            wp_safe_redirect(
                self::detail_url($chada_travel_order_id, self::TAB_OVERVIEW, ['chada_travel_applicant_notice' => 'error'])
            );
            exit;
        }

        global $wpdb;
        $chada_travel_updated = CHADA_TRAVEL_Order_Repository::update_applicant_details(
            $wpdb,
            $chada_travel_order_id,
            $chada_travel_application_id,
            $chada_travel_date_of_birth === '' ? null : $chada_travel_date_of_birth,
            $chada_travel_target_travel_date
        );
        if (!$chada_travel_updated) {
            wp_safe_redirect(
                self::detail_url($chada_travel_order_id, self::TAB_OVERVIEW, ['chada_travel_applicant_notice' => 'invalid'])
            );
            exit;
        }
        // Deliberately omits the actual date values - matches this codebase's existing avoidance of sensitive
        // field content in audit metadata (see the Transaction Confirmation Disclaimer precedent).
        CHADA_TRAVEL_Order_Repository::log_event($wpdb, $chada_travel_order_id, 'chada_travel_applicant_details_updated', 'admin', [
            'application_id' => $chada_travel_application_id,
            'actor_user_id'  => get_current_user_id(),
        ]);
        wp_safe_redirect(self::detail_url($chada_travel_order_id, self::TAB_OVERVIEW, ['chada_travel_applicant_notice' => 'saved']));
        exit;
    }

    // ============================================================ PAYMENT REVIEW TAB

    /**
     * @param array<string, mixed>      $chada_travel_order
     * @param array<string, mixed>|null $chada_travel_payment
     */
    private static function render_payment_tab(array $chada_travel_order, ?array $chada_travel_payment): void {
        if (!current_user_can(self::PAYMENT_CAPABILITY)) {
            CHADA_TRAVEL_Template::output('admin/booking-detail/payment/message', [
                'chada_travel_message' => __('You do not have permission to view payment details.', 'chada-travel'),
            ]);
            return;
        }
        if (!$chada_travel_payment) {
            CHADA_TRAVEL_Template::output('admin/booking-detail/payment/message', [
                'chada_travel_message' => __('No payment attempt has been made for this booking yet.', 'chada-travel'),
            ]);
            return;
        }

        $chada_travel_provider = CHADA_TRAVEL_Payment_Provider_Registry::get_definition(
            (string) ($chada_travel_payment['chada_travel_payment_method'] ?? '')
        );
        if ($chada_travel_provider && isset($chada_travel_provider['admin_payment_renderer'])) {
            call_user_func($chada_travel_provider['admin_payment_renderer'], $chada_travel_payment, $chada_travel_order);
            return;
        }

        $chada_travel_status = (string) $chada_travel_payment['chada_travel_payment_status'];

        if ($chada_travel_status === 'chada_travel_awaiting_proof') {
            self::render_bank_awaiting_proof($chada_travel_payment);
        } elseif ($chada_travel_status === 'chada_travel_awaiting_verification') {
            self::render_actionable_payment_state($chada_travel_order, $chada_travel_payment);
        } elseif ($chada_travel_status === 'chada_travel_rejected') {
            self::render_rejected_payment_state($chada_travel_payment);
        } elseif ($chada_travel_status === 'chada_travel_paid') {
            self::render_paid_payment_state($chada_travel_payment);
        } elseif ($chada_travel_status === 'chada_travel_refunded') {
            self::render_refunded_payment_state($chada_travel_payment);
        } else {
            self::render_generic_payment_state($chada_travel_payment);
        }
    }

    /** @param array<string, mixed> $chada_travel_payment */

    /** @param array<string, mixed> $chada_travel_payment */

    /** @param array<string, mixed> $chada_travel_payment */
    private static function render_bank_awaiting_proof(array $chada_travel_payment): void {
        $chada_travel_grid_html = '<dt>' . esc_html__('Payment reference', 'chada-travel') . '</dt><dd>'
            . esc_html((string) $chada_travel_payment['chada_travel_payment_reference']) . '</dd>'
            . '<dt>' . esc_html__('Amount expected', 'chada-travel') . '</dt><dd>' . esc_html(CHADA_TRAVEL_Config::format_money(
                (float) $chada_travel_payment['chada_travel_amount'],
                (string) $chada_travel_payment['chada_travel_currency'],
                CHADA_TRAVEL_Config::get_settings()
            )) . '</dd>';
        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/bank-awaiting-proof', [
            'chada_travel_grid_html' => $chada_travel_grid_html,
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     */
    private static function render_actionable_payment_state(array $chada_travel_order, array $chada_travel_payment): void {
        $chada_travel_settings     = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_method       = (string) $chada_travel_payment['chada_travel_payment_method'];
        $chada_travel_snapshot_raw = $chada_travel_payment['chada_travel_payment_details_snapshot'] ?? null;
        $chada_travel_snapshot     = CHADA_TRAVEL_Payment_Service::decode_snapshot($chada_travel_snapshot_raw);

        if ($chada_travel_method === CHADA_TRAVEL_Payment_Service::METHOD_BANK) {
            $chada_travel_method_detail_html = self::build_bank_proof_panel_html($chada_travel_payment, $chada_travel_snapshot);
        } else {
            $chada_travel_method_detail_html = self::build_digital_wallet_details_html($chada_travel_payment, $chada_travel_snapshot);
        }

        $chada_travel_shared_grid_html = '<dt>' . esc_html__('Expected amount', 'chada-travel') . '</dt><dd>' . esc_html(
            CHADA_TRAVEL_Config::format_money(
                (float) $chada_travel_payment['chada_travel_amount'],
                (string) $chada_travel_payment['chada_travel_currency'],
                $chada_travel_settings
            )
        ) . '</dd>'
            . '<dt>' . esc_html__('Payment reference', 'chada-travel') . '</dt><dd>'
            . esc_html((string) $chada_travel_payment['chada_travel_payment_reference']) . '</dd>'
            . '<dt>' . esc_html__('Proof submitted', 'chada-travel') . '</dt><dd>' . esc_html(
                CHADA_TRAVEL_Config::format_utc_datetime(
                    (string) ($chada_travel_payment['chada_travel_submitted_at'] ?? ''),
                    $chada_travel_settings
                )
            ) . '</dd>';

        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/actionable-state', [
            'chada_travel_method_detail_html'  => $chada_travel_method_detail_html,
            'chada_travel_shared_grid_html'    => $chada_travel_shared_grid_html,
            'chada_travel_decision_forms_html' => self::build_decision_forms_html($chada_travel_order, $chada_travel_payment),
        ]);
    }

    /**
     * Renders the Digital Wallet Reference No. and configured destination detail grid, using this specific
     * payment's own immutable snapshot (via CHADA_TRAVEL_Payment_Service::resolve_digital_wallet_snapshot()) - never
     * current Settings, so a later Settings provider-name change never relabels or rewrites an already-created
     * payment's destination. A pre-rename snapshot (no provider-name key) always resolves to "GCash".
     *
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_snapshot
     */
    private static function build_digital_wallet_details_html(array $chada_travel_payment, array $chada_travel_snapshot): string {
        $chada_travel_resolved = CHADA_TRAVEL_Payment_Service::resolve_digital_wallet_snapshot($chada_travel_snapshot);
        return CHADA_TRAVEL_Template::render('admin/booking-detail/payment/digital-wallet-details', [
            'chada_travel_provider_name' => $chada_travel_resolved['name'],
            'chada_travel_reference_no'  => CHADA_TRAVEL_Payment_Service::resolve_reference_no($chada_travel_payment),
            'chada_travel_destination'   => $chada_travel_resolved['account_name'] . ' &middot; ' . $chada_travel_resolved['account_number'],
        ]);
    }

    /**
     * Renders the Deposit Slip controls (View/Download), the inline protected preview when the proof is a
     * supported image, and the configured-destination detail grid, in that order - for any Bank payment that
     * has a proof on file, regardless of payment status. Callers gate this to Bank payments only; a caller
     * without the required capability never reaches here (render_payment_tab() already denies the whole tab).
     *
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_snapshot
     */
    private static function build_bank_proof_panel_html(array $chada_travel_payment, array $chada_travel_snapshot): string {
        $chada_travel_payment_id = (int) $chada_travel_payment['chada_travel_payment_id'];
        $chada_travel_has_proof  = !empty($chada_travel_payment['chada_travel_proof_attachment_id']);
        $chada_travel_can_verify = current_user_can(self::PAYMENT_CAPABILITY);
        $chada_travel_has_proof_and_can_verify = $chada_travel_has_proof && $chada_travel_can_verify;

        $chada_travel_view_url = '';
        $chada_travel_download_url = '';
        $chada_travel_deposit_slip_preview_html = '';
        if ($chada_travel_has_proof_and_can_verify) {
            $chada_travel_view_action = CHADA_TRAVEL_Admin_Payment_Review::VIEW_PROOF_ACTION;
            $chada_travel_view_url = wp_nonce_url(
                admin_url('admin-post.php?action=' . $chada_travel_view_action . '&payment_id=' . $chada_travel_payment_id),
                $chada_travel_view_action . '_' . $chada_travel_payment_id
            );
            $chada_travel_download_url = wp_nonce_url(
                admin_url(
                    'admin-post.php?action=' . $chada_travel_view_action . '&payment_id=' . $chada_travel_payment_id . '&download=1'
                ),
                $chada_travel_view_action . '_' . $chada_travel_payment_id
            );
            $chada_travel_deposit_slip_preview_html = self::build_deposit_slip_preview_html(
                $chada_travel_payment,
                $chada_travel_snapshot,
                $chada_travel_view_url
            );
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/payment/bank-proof-panel', [
            'chada_travel_has_proof_and_can_verify'  => $chada_travel_has_proof_and_can_verify,
            'chada_travel_view_url'                  => $chada_travel_view_url,
            'chada_travel_download_url'              => $chada_travel_download_url,
            'chada_travel_deposit_slip_preview_html' => $chada_travel_deposit_slip_preview_html,
            'chada_travel_destination_label'         => self::snapshot_bank_destination_label($chada_travel_snapshot),
        ]);
    }

    /**
     * Reads the Bank destination(s) actually shown to the Booker at selection time, supporting both the
     * current normalized list snapshot (`bank_accounts`) and a previous singular snapshot (`bank_account_details`)
     * from a payment attempt created before the Company Bank Accounts migration.
     *
     * @param array<string, mixed> $chada_travel_snapshot
     */
    private static function snapshot_bank_destination_label(array $chada_travel_snapshot): string {
        $chada_travel_accounts = $chada_travel_snapshot['bank_accounts'] ?? null;
        if (is_array($chada_travel_accounts) && $chada_travel_accounts) {
            return implode('; ', array_map(
                static fn(array $chada_travel_account): string => (string) ($chada_travel_account['bank_name'] ?? ''),
                $chada_travel_accounts
            ));
        }
        return (string) ($chada_travel_snapshot['bank_account_details']['bank_name'] ?? '');
    }

    /**
     * Renders the inline protected preview image only when the decoded snapshot has a non-empty proof
     * storage key and the stored MIME type is a supported image type; the <img src> reuses the exact
     * protected, nonce-bearing View URL the View button uses, never a direct uploads/physical/storage-key
     * reference. See render_bank_proof_panel() for the capability/attachment gating around this call.
     *
     * @param array<string, mixed> $chada_travel_payment
     * @param array<string, mixed> $chada_travel_snapshot
     */
    private static function build_deposit_slip_preview_html(
        array $chada_travel_payment,
        array $chada_travel_snapshot,
        string $chada_travel_view_url
    ): string {
        $chada_travel_storage_key = (string) ($chada_travel_snapshot['proof_storage_path'] ?? '');
        $chada_travel_mime_type    = (string) ($chada_travel_snapshot['proof_mime_type'] ?? '');
        if ($chada_travel_storage_key === '' || !in_array($chada_travel_mime_type, ['image/jpeg', 'image/png'], true)) {
            return '';
        }

        $chada_travel_alt = sprintf(
            /* translators: %s: public payment reference (e.g. CHADA_TRAVEL-PAY-B120D11B). */
            __('Deposit Slip preview for payment %s', 'chada-travel'),
            (string) $chada_travel_payment['chada_travel_payment_reference']
        );

        return CHADA_TRAVEL_Template::render('admin/booking-detail/payment/deposit-slip-preview', [
            'chada_travel_view_url' => $chada_travel_view_url,
            'chada_travel_alt_text' => $chada_travel_alt,
        ]);
    }

    /**
     * Bank-only: renders the same Deposit Slip controls/preview as the actionable Awaiting Verification
     * state, for historical Paid/Rejected/Refunded payments - the proof is part of the audit record and
     * must remain inspectable after a decision. The Pro provider contract owns any additional provider state.
     *
     * @param array<string, mixed> $chada_travel_payment
     */
    private static function build_historical_bank_proof_html(array $chada_travel_payment): string {
        if ((string) $chada_travel_payment['chada_travel_payment_method'] !== CHADA_TRAVEL_Payment_Service::METHOD_BANK) {
            return '';
        }
        $chada_travel_snapshot = CHADA_TRAVEL_Payment_Service::decode_snapshot(
            $chada_travel_payment['chada_travel_payment_details_snapshot'] ?? null
        );
        return self::build_bank_proof_panel_html($chada_travel_payment, $chada_travel_snapshot);
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_payment
     */
    private static function build_decision_forms_html(array $chada_travel_order, array $chada_travel_payment): string {
        if (!current_user_can(self::PAYMENT_CAPABILITY)) {
            return '';
        }
        $chada_travel_payment_id  = (int) $chada_travel_payment['chada_travel_payment_id'];
        $chada_travel_redirect_to = self::detail_url((int) $chada_travel_order['chada_travel_order_id'], self::TAB_PAYMENT);

        $chada_travel_confirm_action = CHADA_TRAVEL_Admin_Payment_Review::CONFIRM_ACTION;
        $chada_travel_reject_action  = CHADA_TRAVEL_Admin_Payment_Review::REJECT_ACTION;

        return CHADA_TRAVEL_Template::render('admin/booking-detail/payment/decision-forms', [
            'chada_travel_action_url'  => admin_url('admin-post.php'),
            'chada_travel_payment_id'  => (string) $chada_travel_payment_id,
            'chada_travel_redirect_to' => $chada_travel_redirect_to,
            'chada_travel_confirm_action'     => $chada_travel_confirm_action,
            'chada_travel_confirm_nonce_html' => wp_nonce_field(
                $chada_travel_confirm_action . '_' . $chada_travel_payment_id,
                '_wpnonce',
                true,
                false
            ),
            'chada_travel_reject_action'     => $chada_travel_reject_action,
            'chada_travel_reject_nonce_html' => wp_nonce_field(
                $chada_travel_reject_action . '_' . $chada_travel_payment_id,
                '_wpnonce',
                true,
                false
            ),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_payment */
    private static function render_rejected_payment_state(array $chada_travel_payment): void {
        $chada_travel_grid_html = '<dt>' . esc_html__('Rejection reason', 'chada-travel') . '</dt><dd>'
            . esc_html((string) ($chada_travel_payment['chada_travel_rejection_reason'] ?? '')) . '</dd>'
            . '<dt>' . esc_html__('Rejected at', 'chada-travel') . '</dt><dd>' . esc_html(
                CHADA_TRAVEL_Config::format_utc_datetime(
                    (string) ($chada_travel_payment['chada_travel_verified_at'] ?? ''),
                    CHADA_TRAVEL_Config::get_settings()
                )
            ) . '</dd>';
        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/rejected-state', [
            'chada_travel_historical_proof_html' => self::build_historical_bank_proof_html($chada_travel_payment),
            'chada_travel_grid_html'             => $chada_travel_grid_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_payment */
    private static function render_paid_payment_state(array $chada_travel_payment): void {
        // Uses this specific payment's own immutable snapshot, never current Settings, so a later Settings
        // provider-name change never relabels an already-paid historical record.
        $chada_travel_grid_html = '<dt>' . esc_html__('Method', 'chada-travel') . '</dt><dd>'
            . esc_html(CHADA_TRAVEL_Payment_Service::resolve_method_label($chada_travel_payment)) . '</dd>'
            . '<dt>' . esc_html__('Transaction ID', 'chada-travel') . '</dt><dd>'
            . esc_html((string) ($chada_travel_payment['chada_travel_transaction_id'] ?? '')) . '</dd>'
            . '<dt>' . esc_html__('Paid at', 'chada-travel') . '</dt><dd>' . esc_html(
                CHADA_TRAVEL_Config::format_utc_datetime(
                    (string) ($chada_travel_payment['chada_travel_paid_at'] ?? ''),
                    CHADA_TRAVEL_Config::get_settings()
                )
            ) . '</dd>';
        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/paid-state', [
            'chada_travel_historical_proof_html' => self::build_historical_bank_proof_html($chada_travel_payment),
            'chada_travel_grid_html'             => $chada_travel_grid_html,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_payment */
    private static function render_refunded_payment_state(array $chada_travel_payment): void {
        $chada_travel_sentence = sprintf(
            /* translators: %s: original transaction ID. */
            __('This payment (Transaction ID %s) has been refunded.', 'chada-travel'),
            (string) ($chada_travel_payment['chada_travel_transaction_id'] ?? '')
        );
        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/refunded-state', [
            'chada_travel_historical_proof_html' => self::build_historical_bank_proof_html($chada_travel_payment),
            'chada_travel_sentence'              => $chada_travel_sentence,
        ]);
    }

    /** @param array<string, mixed> $chada_travel_payment */
    private static function render_generic_payment_state(array $chada_travel_payment): void {
        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_sentence = sprintf(
            /* translators: %s: payment status label. */
            __('Status: %s', 'chada-travel'),
            $chada_travel_status_labels[$chada_travel_payment['chada_travel_payment_status']] ?? $chada_travel_payment['chada_travel_payment_status']
        );
        CHADA_TRAVEL_Template::output('admin/booking-detail/payment/generic-state', [
            'chada_travel_sentence' => $chada_travel_sentence,
        ]);
    }

    // ============================================================ APPLICATIONS & DOCUMENTS TAB

    /**
     * @param array<string, mixed>       $chada_travel_order
     * @param list<array<string, mixed>> $chada_travel_applications
     */
    private static function render_documents_tab(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_applications,
        int $chada_travel_selected_application_id
    ): void {
        if (!$chada_travel_applications) {
            CHADA_TRAVEL_Template::output('admin/booking-detail/documents/tab', [
                'chada_travel_has_applications'       => false,
                'chada_travel_tabs'                   => [],
                'chada_travel_panels_html'            => '',
                'chada_travel_upload_link_panel_html' => '',
            ]);
            return;
        }
        $chada_travel_selected_application_id = $chada_travel_selected_application_id
            ?: (int) $chada_travel_applications[0]['chada_travel_application_id'];

        $chada_travel_tabs = [];
        $chada_travel_panels_html = '';
        foreach ($chada_travel_applications as $chada_travel_application) {
            $chada_travel_application_id = (int) $chada_travel_application['chada_travel_application_id'];
            $chada_travel_selected = $chada_travel_application_id === $chada_travel_selected_application_id;
            $chada_travel_href = admin_url(
                'admin.php?page=' . CHADA_TRAVEL_Admin_Bookings::MENU_SLUG . '&view=' . (int) $chada_travel_order['chada_travel_order_id']
                    . '&tab=documents&application=' . $chada_travel_application_id
            );
            $chada_travel_tabs[] = [
                'key'      => (string) $chada_travel_application_id,
                'label'    => trim($chada_travel_application['chada_travel_first_name'] . ' ' . $chada_travel_application['chada_travel_last_name'])
                    . ' &middot; ' . $chada_travel_application['chada_travel_country_name_snapshot'],
                'href'     => $chada_travel_href,
                'selected' => $chada_travel_selected,
            ];
            $chada_travel_panels_html .= '<section class="chada-travel-tabpanel" role="tabpanel" id="chada-travel-apppanel-'
                . esc_attr((string) $chada_travel_application_id) . '" aria-labelledby="chada-travel-apptab-'
                . esc_attr((string) $chada_travel_application_id) . '"' . ($chada_travel_selected ? '' : ' hidden')
                . ' data-chada-travel-tour="booking-documents-requirements">'
                . self::build_application_document_panel_html($chada_travel_wpdb, $chada_travel_order, $chada_travel_application)
                . '</section>';
        }

        CHADA_TRAVEL_Template::output('admin/booking-detail/documents/tab', [
            'chada_travel_has_applications'       => true,
            'chada_travel_tabs'                   => $chada_travel_tabs,
            'chada_travel_panels_html'            => $chada_travel_panels_html,
            'chada_travel_upload_link_panel_html' => self::build_upload_link_panel_html($chada_travel_order),
        ]);
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_application
     */
    private static function build_application_document_panel_html(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_application
    ): string {
        $chada_travel_status_labels  = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_application_id = (int) $chada_travel_application['chada_travel_application_id'];

        $chada_travel_meta_html = '<p class="chada-travel-detail-meta">'
            . esc_html((string) $chada_travel_application['chada_travel_application_reference'])
            . ' &middot; ' . esc_html(sprintf(
                /* translators: %s: checklist version snapshot. */
                __('Checklist version %s', 'chada-travel'),
                (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
            )) . ' &middot; <span class="chada-travel-badge">' . esc_html(
                $chada_travel_status_labels[$chada_travel_application['chada_travel_file_status']]
                    ?? $chada_travel_application['chada_travel_file_status']
            ) . '</span></p>';

        $chada_travel_requirements = CHADA_TRAVEL_Requirement_Repository::get_active_for_country(
            $chada_travel_wpdb,
            (int) $chada_travel_application['chada_travel_country_id'],
            (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
        );
        if (!$chada_travel_requirements) {
            return CHADA_TRAVEL_Template::render('admin/booking-detail/documents/application-document-panel', [
                'chada_travel_meta_html'         => $chada_travel_meta_html,
                'chada_travel_has_requirements'  => false,
                'chada_travel_rows_html'         => '',
            ]);
        }
        $chada_travel_current = CHADA_TRAVEL_Document_Repository::find_current_for_application($chada_travel_wpdb, $chada_travel_application_id);
        $chada_travel_rows_html = '';
        foreach ($chada_travel_requirements as $chada_travel_requirement) {
            $chada_travel_rows_html .= self::build_requirement_row_html(
                $chada_travel_order,
                $chada_travel_application_id,
                $chada_travel_requirement,
                $chada_travel_current[(int) $chada_travel_requirement['chada_travel_requirement_id']] ?? null,
                $chada_travel_status_labels
            );
        }
        return CHADA_TRAVEL_Template::render('admin/booking-detail/documents/application-document-panel', [
            'chada_travel_meta_html'        => $chada_travel_meta_html,
            'chada_travel_has_requirements' => true,
            'chada_travel_rows_html'        => $chada_travel_rows_html,
        ]);
    }

    /**
     * @param array<string, mixed>      $chada_travel_order
     * @param array<string, mixed>      $chada_travel_requirement
     * @param array<string, mixed>|null $chada_travel_document
     * @param array<string, string>     $chada_travel_status_labels
     */
    private static function build_requirement_row_html(
        array $chada_travel_order,
        int $chada_travel_application_id,
        array $chada_travel_requirement,
        ?array $chada_travel_document,
        array $chada_travel_status_labels
    ): string {
        $chada_travel_settings   = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_can_view   = current_user_can(self::DOCUMENT_CAPABILITY);
        $chada_travel_can_review = current_user_can(self::REVIEW_CAPABILITY);
        $chada_travel_mime_types = json_decode((string) $chada_travel_requirement['chada_travel_allowed_mime_types'], true) ?: [];

        $chada_travel_header_html = '<strong>' . esc_html((string) $chada_travel_requirement['chada_travel_requirement_label'])
            . '</strong> ' . (empty($chada_travel_requirement['chada_travel_is_required'])
                ? '<span class="chada-travel-badge">' . esc_html__('Optional', 'chada-travel') . '</span>'
                : '<span class="chada-travel-badge">' . esc_html__('Required', 'chada-travel') . '</span>');
        if ($chada_travel_document) {
            $chada_travel_document_status_label = $chada_travel_status_labels[$chada_travel_document['chada_travel_document_status']]
                ?? $chada_travel_document['chada_travel_document_status'];
            $chada_travel_header_html .= ' <span class="chada-travel-badge">' . esc_html($chada_travel_document_status_label) . '</span>';
        } else {
            $chada_travel_header_html .= ' <span class="chada-travel-badge">' . esc_html__('Missing', 'chada-travel') . '</span>';
        }

        $chada_travel_allowed_text = sprintf(
            /* translators: 1: allowed MIME types, 2: maximum file size in megabytes. */
            __('Allowed: %1$s &middot; Max size: %2$s MB', 'chada-travel'),
            implode(', ', $chada_travel_mime_types),
            number_format((int) $chada_travel_requirement['chada_travel_max_file_bytes'] / 1048576, 1)
        );

        $chada_travel_blank_form_html = '';
        if (!empty($chada_travel_requirement['chada_travel_form_attachment_id']) && function_exists('wp_get_attachment_url')) {
            $chada_travel_form_url = wp_get_attachment_url((int) $chada_travel_requirement['chada_travel_form_attachment_id']);
            if ($chada_travel_form_url) {
                $chada_travel_blank_form_html = '<p><a href="' . esc_url($chada_travel_form_url) . '">'
                    . esc_html__('Download blank form', 'chada-travel') . '</a></p>';
            }
        }

        $chada_travel_file_detail_text   = '';
        $chada_travel_reviewed_text      = '';
        $chada_travel_review_note_text   = '';
        $chada_travel_view_download_html = '';
        $chada_travel_review_form_html   = '';
        if ($chada_travel_document) {
            $chada_travel_file_detail_text = sprintf(
                /* translators: 1: file name, 2: MIME type, 3: size KB, 4: version, 5: upload time. */
                __('%1$s &middot; %2$s &middot; %3$s KB &middot; Version %4$d &middot; Uploaded %5$s', 'chada-travel'),
                (string) $chada_travel_document['chada_travel_original_filename'],
                (string) $chada_travel_document['chada_travel_mime_type'],
                number_format((int) $chada_travel_document['chada_travel_file_size'] / 1024, 1),
                (int) $chada_travel_document['chada_travel_file_version'],
                CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_document['chada_travel_uploaded_at'], $chada_travel_settings)
            );
            if (!empty($chada_travel_document['chada_travel_reviewed_at'])) {
                $chada_travel_reviewed_text = sprintf(
                    /* translators: %s: review timestamp. */
                    __('Reviewed %s', 'chada-travel'),
                    CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_document['chada_travel_reviewed_at'], $chada_travel_settings)
                );
            }
            if (!empty($chada_travel_document['chada_travel_review_note'])) {
                $chada_travel_review_note_text = sprintf(
                    /* translators: %s: administrator review note. */
                    __('Review note: %s', 'chada-travel'),
                    (string) $chada_travel_document['chada_travel_review_note']
                );
            }
            if ($chada_travel_can_view) {
                $chada_travel_document_id = (int) $chada_travel_document['chada_travel_document_id'];
                $chada_travel_view_action_base = 'admin-post.php?action=' . self::VIEW_DOCUMENT_ACTION
                    . '&document_id=' . $chada_travel_document_id;
                $chada_travel_view_url = wp_nonce_url(
                    admin_url($chada_travel_view_action_base),
                    self::VIEW_DOCUMENT_ACTION . '_' . $chada_travel_document_id
                );
                $chada_travel_download_url = wp_nonce_url(
                    admin_url($chada_travel_view_action_base . '&download=1'),
                    self::VIEW_DOCUMENT_ACTION . '_' . $chada_travel_document_id
                );
                $chada_travel_view_download_html = '<p><a class="button button-small" href="'
                    . esc_url($chada_travel_view_url) . '">' . esc_html__('View', 'chada-travel')
                    . '</a> <a class="button button-small" href="' . esc_url($chada_travel_download_url) . '">'
                    . esc_html__('Download', 'chada-travel') . '</a></p>';
            }
            if ($chada_travel_can_review) {
                $chada_travel_review_form_html = self::build_document_review_form_html(
                    (int) $chada_travel_document['chada_travel_document_id'],
                    (int) $chada_travel_order['chada_travel_order_id']
                );
            }
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/documents/requirement-row', [
            'chada_travel_header_html'        => $chada_travel_header_html,
            'chada_travel_description'        => (string) $chada_travel_requirement['chada_travel_requirement_description'],
            'chada_travel_allowed_text'       => $chada_travel_allowed_text,
            'chada_travel_blank_form_html'    => $chada_travel_blank_form_html,
            'chada_travel_has_document'       => (bool) $chada_travel_document,
            'chada_travel_file_detail_text'   => $chada_travel_file_detail_text,
            'chada_travel_reviewed_text'      => $chada_travel_reviewed_text,
            'chada_travel_review_note_text'   => $chada_travel_review_note_text,
            'chada_travel_view_download_html' => $chada_travel_view_download_html,
            'chada_travel_review_form_html'   => $chada_travel_review_form_html,
        ]);
    }

    private static function build_document_review_form_html(int $chada_travel_document_id, int $chada_travel_order_id): string {
        return CHADA_TRAVEL_Template::render('admin/booking-detail/documents/document-review-form', [
            'chada_travel_action_url'    => admin_url('admin-post.php'),
            'chada_travel_review_action' => self::REVIEW_DOCUMENT_ACTION,
            'chada_travel_document_id'   => (string) $chada_travel_document_id,
            'chada_travel_order_id'      => (string) $chada_travel_order_id,
            'chada_travel_nonce_html'    => wp_nonce_field(
                self::REVIEW_DOCUMENT_ACTION . '_' . $chada_travel_document_id,
                '_wpnonce',
                true,
                false
            ),
        ]);
    }

    /** @param array<string, mixed> $chada_travel_order */
    private static function build_upload_link_panel_html(array $chada_travel_order): string {
        $chada_travel_order_id   = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_has_token  = !empty($chada_travel_order['chada_travel_upload_token_hash']);
        $chada_travel_expires_at = (string) ($chada_travel_order['chada_travel_upload_token_expires_at'] ?? '');
        $chada_travel_is_valid   = $chada_travel_has_token && $chada_travel_expires_at !== '' && strtotime($chada_travel_expires_at) > time();
        $chada_travel_can_issue  = (string) $chada_travel_order['chada_travel_payment_status'] === 'chada_travel_paid';
        $chada_travel_can_manage = current_user_can(self::CAPABILITY);

        if ($chada_travel_has_token) {
            $chada_travel_status_text = $chada_travel_is_valid
                ? sprintf(
                    /* translators: %s: link expiry date/time. */
                    __('Active until %s.', 'chada-travel'),
                    CHADA_TRAVEL_Config::format_utc_datetime($chada_travel_expires_at, CHADA_TRAVEL_Config::get_settings())
                )
                : __('Expired.', 'chada-travel');
        } else {
            $chada_travel_status_text = __('No upload link has been issued yet.', 'chada-travel');
        }

        $chada_travel_show_issue  = $chada_travel_can_manage && $chada_travel_can_issue;
        $chada_travel_show_revoke = $chada_travel_can_manage && $chada_travel_has_token;

        $chada_travel_issue_nonce_html = '';
        if ($chada_travel_show_issue) {
            $chada_travel_issue_nonce_html = wp_nonce_field(
                self::ISSUE_UPLOAD_ACTION . '_' . $chada_travel_order_id,
                '_wpnonce',
                true,
                false
            );
        }
        $chada_travel_revoke_nonce_html = '';
        if ($chada_travel_show_revoke) {
            $chada_travel_revoke_nonce_html = wp_nonce_field(
                self::REVOKE_UPLOAD_ACTION . '_' . $chada_travel_order_id,
                '_wpnonce',
                true,
                false
            );
        }

        return CHADA_TRAVEL_Template::render('admin/booking-detail/documents/upload-link-panel', [
            'chada_travel_status_text'       => $chada_travel_status_text,
            'chada_travel_can_manage'        => $chada_travel_can_manage,
            'chada_travel_show_issue'        => $chada_travel_show_issue,
            'chada_travel_action_url'        => admin_url('admin-post.php'),
            'chada_travel_order_id'          => (string) $chada_travel_order_id,
            'chada_travel_issue_action'      => self::ISSUE_UPLOAD_ACTION,
            'chada_travel_issue_nonce_html'  => $chada_travel_issue_nonce_html,
            'chada_travel_issue_label'       => $chada_travel_has_token
                ? __('Reissue Upload Link', 'chada-travel') : __('Issue Upload Link', 'chada-travel'),
            'chada_travel_show_revoke'       => $chada_travel_show_revoke,
            'chada_travel_revoke_action'     => self::REVOKE_UPLOAD_ACTION,
            'chada_travel_revoke_nonce_html' => $chada_travel_revoke_nonce_html,
        ]);
    }

    public static function handle_review_document(): void {
        if (!current_user_can(self::REVIEW_CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to review documents.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs immediately below.
        $chada_travel_document_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('document_id', 0));
        check_admin_referer(self::REVIEW_DOCUMENT_ACTION . '_' . $chada_travel_document_id);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above.
        $chada_travel_order_id    = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('order_id', 0));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $chada_travel_status      = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_post('status'));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $chada_travel_review_note = CHADA_TRAVEL_Config::sanitize_request_post('review_note');

        global $wpdb;
        $chada_travel_document = CHADA_TRAVEL_Document_Repository::find_by_id($wpdb, $chada_travel_document_id);
        $chada_travel_notice   = 'error';
        if ($chada_travel_document) {
            $chada_travel_result = CHADA_TRAVEL_Document_Service::admin_review(
                $wpdb,
                $chada_travel_document,
                $chada_travel_status,
                $chada_travel_review_note,
                get_current_user_id()
            );
            $chada_travel_notice = $chada_travel_result['errors'] ? 'error' : 'saved';
        }
        $chada_travel_redirect = self::detail_url(
            $chada_travel_order_id,
            self::TAB_DOCUMENTS,
            ['chada_travel_document_notice' => $chada_travel_notice]
        );
        wp_safe_redirect($chada_travel_redirect);
        exit;
    }

    /** Streams a protected visa document only after a capability check and a document-specific nonce. */
    public static function handle_view_document(): void {
        if (!current_user_can(self::DOCUMENT_CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view documents.', 'chada-travel'), '', ['response' => 403]);
        }
        $chada_travel_document_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('document_id', 0));
        check_admin_referer(self::VIEW_DOCUMENT_ACTION . '_' . $chada_travel_document_id);

        global $wpdb;
        $chada_travel_document = CHADA_TRAVEL_Document_Repository::find_by_id($wpdb, $chada_travel_document_id);
        if (!$chada_travel_document || empty($chada_travel_document['chada_travel_storage_key'])) {
            wp_die(esc_html__('Document file not found.', 'chada-travel'), '', ['response' => 404]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce already verified above.
        CHADA_TRAVEL_Upload_Service::stream_private_file(
            (string) $chada_travel_document['chada_travel_storage_key'],
            (string) $chada_travel_document['chada_travel_mime_type'],
            (string) $chada_travel_document['chada_travel_original_filename'],
            CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('download')) !== ''
        );
    }

    public static function handle_issue_upload_link(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Bookings.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs immediately below.
        $chada_travel_order_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('order_id', 0));
        check_admin_referer(self::ISSUE_UPLOAD_ACTION . '_' . $chada_travel_order_id);

        global $wpdb;
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, $chada_travel_order_id);
        if ($chada_travel_order) {
            CHADA_TRAVEL_Document_Service::admin_reissue_upload_link(
                $wpdb,
                $chada_travel_order,
                CHADA_TRAVEL_Config::get_settings(),
                get_current_user_id()
            );
        }
        wp_safe_redirect(self::detail_url($chada_travel_order_id, self::TAB_DOCUMENTS, ['chada_travel_upload_notice' => 'issued']));
        exit;
    }

    public static function handle_revoke_upload_link(): void {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage Visa Bookings.', 'chada-travel'));
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() runs immediately below.
        $chada_travel_order_id = CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_post('order_id', 0));
        check_admin_referer(self::REVOKE_UPLOAD_ACTION . '_' . $chada_travel_order_id);

        global $wpdb;
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_id($wpdb, $chada_travel_order_id);
        if ($chada_travel_order) {
            CHADA_TRAVEL_Document_Service::revoke_upload_link($wpdb, $chada_travel_order, get_current_user_id());
        }
        wp_safe_redirect(self::detail_url($chada_travel_order_id, self::TAB_DOCUMENTS, ['chada_travel_upload_notice' => 'revoked']));
        exit;
    }

    // ============================================================ ACTIVITY TAB

    /**
     * @param array<string, mixed>       $chada_travel_order
     * @param list<array<string, mixed>> $chada_travel_applications
     */
    private static function render_activity_tab(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_applications
    ): void {
        $chada_travel_order_id = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_application_ids = array_map(
            static fn(array $chada_travel_application): int => (int) $chada_travel_application['chada_travel_application_id'],
            $chada_travel_applications
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter form.
        $chada_travel_event_type = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('event_type'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_actor_type = CHADA_TRAVEL_Config::sanitize_request_key(CHADA_TRAVEL_Config::sanitize_request_get('actor_type'));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $chada_travel_paged = max(1, CHADA_TRAVEL_Config::sanitize_request_int(CHADA_TRAVEL_Config::sanitize_request_get('activity_page', 1)));

        $chada_travel_available_types = CHADA_TRAVEL_Order_Repository::get_order_event_types($chada_travel_wpdb, $chada_travel_order_id);
        $chada_travel_total = CHADA_TRAVEL_Order_Repository::count_order_events(
            $chada_travel_wpdb,
            $chada_travel_order_id,
            $chada_travel_application_ids,
            $chada_travel_event_type,
            $chada_travel_actor_type
        );
        $chada_travel_events = CHADA_TRAVEL_Order_Repository::search_order_events(
            $chada_travel_wpdb,
            $chada_travel_order_id,
            $chada_travel_application_ids,
            $chada_travel_event_type,
            $chada_travel_actor_type,
            $chada_travel_paged
        );

        CHADA_TRAVEL_Template::output('admin/booking-detail/activity/filter-form', [
            'chada_travel_page_slug'           => CHADA_TRAVEL_Admin_Bookings::MENU_SLUG,
            'chada_travel_order_id'            => (string) $chada_travel_order_id,
            'chada_travel_tab'                 => self::TAB_ACTIVITY,
            'chada_travel_available_types'     => $chada_travel_available_types,
            'chada_travel_selected_event_type' => $chada_travel_event_type,
            'chada_travel_actor_types'         => [
                ['value' => 'booker', 'label' => __('Booker', 'chada-travel')],
                ['value' => 'admin', 'label' => __('Administrator', 'chada-travel')],
                ['value' => 'system', 'label' => __('System', 'chada-travel')],
                ['value' => 'webhook', 'label' => __('Webhook', 'chada-travel')],
                ['value' => 'cron', 'label' => __('Cron', 'chada-travel')],
            ],
            'chada_travel_selected_actor_type' => $chada_travel_actor_type,
        ]);

        if (!$chada_travel_events) {
            CHADA_TRAVEL_Template::output('admin/booking-detail/activity/table', [
                'chada_travel_has_events' => false,
                'chada_travel_rows_html'  => '',
            ]);
            return;
        }

        $chada_travel_status_labels = CHADA_TRAVEL_Workflow::get_status_labels();
        $chada_travel_settings      = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_rows_html = '';
        foreach ($chada_travel_events as $chada_travel_event) {
            $chada_travel_rows_html .= '<tr>'
                . '<td data-label="' . esc_attr__('Time', 'chada-travel') . '">' . esc_html(
                    CHADA_TRAVEL_Config::format_utc_datetime((string) $chada_travel_event['chada_travel_created_at'], $chada_travel_settings)
                ) . '</td>'
                . '<td data-label="' . esc_attr__('Event', 'chada-travel') . '">' . esc_html(self::humanize_event_type(
                    (string) $chada_travel_event['chada_travel_event_type']
                )) . '<br><span class="chada-travel-muted chada-travel-small">' . esc_html((string) $chada_travel_event['chada_travel_event_type'])
                    . '</span></td>';
            $chada_travel_record_label = (string) $chada_travel_event['chada_travel_record_type'] . ' #'
                . (string) ($chada_travel_event['chada_travel_record_id'] ?? '');
            $chada_travel_rows_html .= '<td data-label="' . esc_attr__('Related record', 'chada-travel') . '">'
                . esc_html($chada_travel_record_label) . '</td>';
            $chada_travel_from = $chada_travel_status_labels[$chada_travel_event['chada_travel_from_status']]
                ?? $chada_travel_event['chada_travel_from_status'];
            $chada_travel_to   = $chada_travel_status_labels[$chada_travel_event['chada_travel_to_status']]
                ?? $chada_travel_event['chada_travel_to_status'];
            $chada_travel_status_change = !empty($chada_travel_event['chada_travel_to_status'])
                ? esc_html($chada_travel_from . ' &rarr; ' . $chada_travel_to) : esc_html__('No status change', 'chada-travel');
            $chada_travel_rows_html .= '<td data-label="' . esc_attr__('Status change', 'chada-travel') . '">'
                . $chada_travel_status_change . '</td>';
            $chada_travel_rows_html .= '<td data-label="' . esc_attr__('Actor', 'chada-travel') . '">'
                . esc_html((string) $chada_travel_event['chada_travel_actor_type']) . '</td>';
            $chada_travel_rows_html .= '</tr>';
        }
        CHADA_TRAVEL_Template::output('admin/booking-detail/activity/table', [
            'chada_travel_has_events' => true,
            'chada_travel_rows_html'  => $chada_travel_rows_html,
        ]);

        $chada_travel_pages = (int) ceil($chada_travel_total / CHADA_TRAVEL_Order_Repository::ACTIVITY_PER_PAGE);
        if ($chada_travel_pages > 1) {
            $chada_travel_page_links = [];
            for ($chada_travel_page = 1; $chada_travel_page <= $chada_travel_pages; $chada_travel_page++) {
                $chada_travel_url = add_query_arg([
                    'page' => CHADA_TRAVEL_Admin_Bookings::MENU_SLUG, 'view' => $chada_travel_order_id,
                    'tab' => self::TAB_ACTIVITY, 'event_type' => $chada_travel_event_type,
                    'actor_type' => $chada_travel_actor_type, 'activity_page' => $chada_travel_page,
                ], admin_url('admin.php'));
                $chada_travel_page_links[] = [
                    'page'       => $chada_travel_page,
                    'url'        => $chada_travel_url,
                    'is_current' => $chada_travel_page === $chada_travel_paged,
                ];
            }
            CHADA_TRAVEL_Template::output('admin/booking-detail/activity/pagination', [
                'chada_travel_page_links' => $chada_travel_page_links,
            ]);
        }
    }

    private static function humanize_event_type(string $chada_travel_event_type): string {
        $chada_travel_label = str_replace(['chada_travel_', '_'], ['', ' '], $chada_travel_event_type);
        return ucfirst($chada_travel_label);
    }

    // ============================================================ Shared helpers

    /** @param array<string, string> $chada_travel_extra_query */
    private static function detail_url(int $chada_travel_order_id, string $chada_travel_tab, array $chada_travel_extra_query = []): string {
        return add_query_arg(
            array_merge(
                ['page' => CHADA_TRAVEL_Admin_Bookings::MENU_SLUG, 'view' => $chada_travel_order_id, 'tab' => $chada_travel_tab],
                $chada_travel_extra_query
            ),
            admin_url('admin.php')
        );
    }
}
