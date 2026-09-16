<?php
/**
 * Visa-document business rules: upload-token issue/revoke, the Booker-facing requirement checklist, protected
 * uploads/replacements validated against each requirement's own MIME/size rules, and administrator document
 * review. Mirrors CHADA_TRAVEL_Payment_Service's shape: repositories stay pure data access, this class owns the rules.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Document_Service {
    /**
     * @param array<string, mixed> $chada_travel_order Must already be the chada_travel_paid order (caller resolves/authorizes).
     * @param array<string, mixed> $chada_travel_settings
     * @return array{errors: list<string>, order?: array<string, mixed>, token?: string}
     */
    public static function issue_upload_link(object $chada_travel_wpdb, array $chada_travel_order, array $chada_travel_settings): array {
        if ((string) $chada_travel_order['chada_travel_payment_status'] !== 'chada_travel_paid') {
            return ['errors' => ['Visa-document uploads are available only after payment is confirmed.']];
        }
        $chada_travel_hours  = (int) ($chada_travel_settings['chada_travel_upload_token_hours'] ?? CHADA_TRAVEL_Config::DEFAULT_UPLOAD_TOKEN_HOURS);
        $chada_travel_issued = CHADA_TRAVEL_Order_Repository::issue_upload_token($chada_travel_wpdb, $chada_travel_order, $chada_travel_hours);
        return ['errors' => [], 'order' => $chada_travel_issued['order'], 'token' => $chada_travel_issued['token']];
    }

    /**
     * Administrator-initiated reissue: mints a fresh token (overwriting/revoking the previous one via the same
     * single-column mechanism issue_upload_link() already uses), logs a distinct audit event, and emails the
     * new link when the order has a resolvable payment to attach the notification to.
     *
     * @param array<string, mixed> $chada_travel_order
     * @param array<string, mixed> $chada_travel_settings
     * @return array{errors: list<string>, order?: array<string, mixed>, token?: string}
     */
    public static function admin_reissue_upload_link(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        array $chada_travel_settings,
        int $chada_travel_admin_user_id
    ): array {
        $chada_travel_issued = self::issue_upload_link($chada_travel_wpdb, $chada_travel_order, $chada_travel_settings);
        if ($chada_travel_issued['errors']) {
            return $chada_travel_issued;
        }
        CHADA_TRAVEL_Order_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            'chada_travel_upload_link_reissued',
            'admin',
            ['actor_user_id' => $chada_travel_admin_user_id]
        );
        $chada_travel_order_id = (int) $chada_travel_order['chada_travel_order_id'];
        $chada_travel_payment  = CHADA_TRAVEL_Payment_Repository::get_active_payment($chada_travel_wpdb, $chada_travel_order_id);
        if ($chada_travel_payment) {
            CHADA_TRAVEL_Email_Service::queue('upload_link_reissued', (int) $chada_travel_payment['chada_travel_payment_id'], [
                'upload_token' => $chada_travel_issued['token'],
            ]);
        }
        return $chada_travel_issued;
    }

    /**
     * @param array<string, mixed> $chada_travel_order
     * @return array{errors: list<string>, order: array<string, mixed>}
     */
    public static function revoke_upload_link(
        object $chada_travel_wpdb,
        array $chada_travel_order,
        int $chada_travel_admin_user_id
    ): array {
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::revoke_upload_token(
            $chada_travel_wpdb,
            $chada_travel_order,
            'admin',
            $chada_travel_admin_user_id
        );
        return ['errors' => [], 'order' => $chada_travel_order];
    }

    /**
     * Resolves a valid upload token to its order and builds the Booker-facing requirement checklist: every
     * active application, its country's active requirements, and each requirement's current document status.
     *
     * @return array{errors: list<string>, order?: array<string, mixed>, applications?: list<array<string, mixed>>}
     */
    public static function build_checklist(object $chada_travel_wpdb, string $chada_travel_token): array {
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_valid_upload_token($chada_travel_wpdb, $chada_travel_token);
        if (!$chada_travel_order) {
            return ['errors' => ['This upload link is invalid, expired, or has been revoked.']];
        }

        $chada_travel_settings   = CHADA_TRAVEL_Config::get_settings();
        $chada_travel_server_max = CHADA_TRAVEL_Upload_Settings::get_server_max_upload_bytes();
        $chada_travel_applications = CHADA_TRAVEL_Order_Repository::get_applications(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id']
        );
        $chada_travel_result        = [];
        foreach ($chada_travel_applications as $chada_travel_application) {
            $chada_travel_application_id = (int) $chada_travel_application['chada_travel_application_id'];
            $chada_travel_requirements   = CHADA_TRAVEL_Requirement_Repository::get_active_for_country(
                $chada_travel_wpdb,
                (int) $chada_travel_application['chada_travel_country_id'],
                (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
            );
            $chada_travel_current = CHADA_TRAVEL_Document_Repository::find_current_for_application(
                $chada_travel_wpdb,
                $chada_travel_application_id
            );

            $chada_travel_requirement_rows = [];
            foreach ($chada_travel_requirements as $chada_travel_requirement) {
                $chada_travel_requirement_id = (int) $chada_travel_requirement['chada_travel_requirement_id'];
                $chada_travel_document        = $chada_travel_current[$chada_travel_requirement_id] ?? null;
                $chada_travel_effective = CHADA_TRAVEL_Upload_Settings::get_effective_requirement_rules(
                    self::decode_mime_types($chada_travel_requirement['chada_travel_allowed_mime_types']),
                    (int) $chada_travel_requirement['chada_travel_max_file_bytes'],
                    $chada_travel_settings,
                    $chada_travel_server_max
                );
                $chada_travel_requirement_rows[] = [
                    'requirementId'  => $chada_travel_requirement_id,
                    'label'          => $chada_travel_requirement['chada_travel_requirement_label'],
                    'description'    => $chada_travel_requirement['chada_travel_requirement_description'],
                    'isRequired'     => (bool) $chada_travel_requirement['chada_travel_is_required'],
                    'mimeTypes'      => $chada_travel_effective['mime_types'],
                    'maxBytes'       => $chada_travel_effective['max_bytes'],
                    'documentStatus' => $chada_travel_document['chada_travel_document_status'] ?? 'chada_travel_missing',
                    'filename'       => $chada_travel_document['chada_travel_original_filename'] ?? null,
                    'uploadedAt'     => $chada_travel_document['chada_travel_uploaded_at'] ?? null,
                    'reviewNote'     => $chada_travel_document['chada_travel_review_note'] ?? null,
                ];
            }

            $chada_travel_result[] = [
                'applicationId'   => $chada_travel_application_id,
                'countryName'     => $chada_travel_application['chada_travel_country_name_snapshot'],
                'applicantName'   => trim(
                    (string) $chada_travel_application['chada_travel_first_name'] . ' '
                        . (string) $chada_travel_application['chada_travel_last_name']
                ),
                'fileStatus'      => $chada_travel_application['chada_travel_file_status'],
                'requirements'    => $chada_travel_requirement_rows,
            ];
        }

        return ['errors' => [], 'order' => $chada_travel_order, 'applications' => $chada_travel_result];
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,size?:int,error?:int} $chada_travel_file
     * @return array{
     *     errors: list<string>,
     *     document?: array<string, mixed>,
     *     fileStatus?: string
     * }
     */
    public static function upload(
        object $chada_travel_wpdb,
        string $chada_travel_token,
        int $chada_travel_application_id,
        int $chada_travel_requirement_id,
        array $chada_travel_file,
        ?int $chada_travel_uploaded_by_user_id
    ): array {
        $chada_travel_order = CHADA_TRAVEL_Order_Repository::find_by_valid_upload_token($chada_travel_wpdb, $chada_travel_token);
        if (!$chada_travel_order) {
            return ['errors' => ['This upload link is invalid, expired, or has been revoked.']];
        }

        $chada_travel_application = self::find_owned_application(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            $chada_travel_application_id
        );
        if (!$chada_travel_application) {
            return ['errors' => ['That visa application does not belong to this booking.']];
        }

        $chada_travel_requirement = CHADA_TRAVEL_Requirement_Repository::find_by_id($chada_travel_wpdb, $chada_travel_requirement_id);
        if (!$chada_travel_requirement
            || (int) $chada_travel_requirement['chada_travel_country_id'] !== (int) $chada_travel_application['chada_travel_country_id']
            || (string) $chada_travel_requirement['chada_travel_checklist_version']
                !== (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
            || empty($chada_travel_requirement['chada_travel_is_active'])
        ) {
            return ['errors' => ['That requirement does not apply to this visa application.']];
        }

        $chada_travel_rules = CHADA_TRAVEL_Upload_Settings::get_effective_requirement_rules(
            self::decode_mime_types($chada_travel_requirement['chada_travel_allowed_mime_types']),
            (int) $chada_travel_requirement['chada_travel_max_file_bytes'],
            CHADA_TRAVEL_Config::get_settings()
        );
        if (!$chada_travel_rules['mime_types'] || $chada_travel_rules['max_bytes'] <= 0) {
            // Fail closed: no valid positive effective rule exists (e.g. the server upload limit could not be
            // resolved, or Settings/requirement MIME types no longer intersect at all) - never fall through to
            // store_upload(), whose own max_bytes<=0 has an unrelated unbounded-value meaning it must never inherit.
            return ['errors' => ['Visa document uploads are temporarily unavailable. Try again later.']];
        }
        $chada_travel_stored = CHADA_TRAVEL_Upload_Service::store_upload($chada_travel_file, $chada_travel_rules, 'documents');
        if ($chada_travel_stored['errors']) {
            return ['errors' => $chada_travel_stored['errors']];
        }

        $chada_travel_document = CHADA_TRAVEL_Document_Repository::create_version(
            $chada_travel_wpdb,
            $chada_travel_application_id,
            $chada_travel_requirement_id,
            $chada_travel_stored,
            $chada_travel_uploaded_by_user_id
        );
        CHADA_TRAVEL_Document_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_order['chada_travel_order_id'],
            $chada_travel_application_id,
            (int) $chada_travel_document['chada_travel_document_id'],
            'chada_travel_document_uploaded',
            'booker',
            ['requirement_id' => $chada_travel_requirement_id, 'mime_type' => $chada_travel_stored['mime_type']]
        );

        $chada_travel_file_status = self::recalculate_file_status($chada_travel_wpdb, $chada_travel_application);
        CHADA_TRAVEL_Order_Repository::update_application_file_status($chada_travel_wpdb, $chada_travel_application_id, $chada_travel_file_status);

        return ['errors' => [], 'document' => $chada_travel_document, 'fileStatus' => $chada_travel_file_status];
    }

    /**
     * Administrator accept/reject/replacement-required decision. A safe review note is required for a
     * rejection or a replacement request, matching the Bank/Digital Wallet "required reason" pattern.
     *
     * @param array<string, mixed> $chada_travel_document
     * @return array{errors: list<string>, document?: array<string, mixed>, fileStatus?: string}
     */
    public static function admin_review(
        object $chada_travel_wpdb,
        array $chada_travel_document,
        string $chada_travel_status,
        string $chada_travel_review_note,
        int $chada_travel_admin_user_id
    ): array {
        $chada_travel_allowed_statuses = ['chada_travel_accepted', 'chada_travel_rejected', 'chada_travel_replacement_required'];
        if (!in_array($chada_travel_status, $chada_travel_allowed_statuses, true)) {
            return ['errors' => ['Choose a valid document decision.']];
        }
        if (function_exists('sanitize_textarea_field')) {
            $chada_travel_review_note = sanitize_textarea_field($chada_travel_review_note);
        } else {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- dependency-free fallback for tests without WordPress loaded.
            $chada_travel_review_note = trim(strip_tags($chada_travel_review_note));
        }
        if ($chada_travel_status !== 'chada_travel_accepted' && $chada_travel_review_note === '') {
            return ['errors' => ['A review note is required to reject or request a replacement.']];
        }

        $chada_travel_application = CHADA_TRAVEL_Order_Repository::find_application_by_id(
            $chada_travel_wpdb,
            (int) $chada_travel_document['chada_travel_application_id']
        );
        if (!$chada_travel_application) {
            return ['errors' => ['That visa application could not be found.']];
        }

        $chada_travel_document = CHADA_TRAVEL_Document_Repository::update_status(
            $chada_travel_wpdb,
            $chada_travel_document,
            $chada_travel_status,
            $chada_travel_review_note !== '' ? $chada_travel_review_note : null,
            $chada_travel_admin_user_id
        );
        CHADA_TRAVEL_Document_Repository::log_event(
            $chada_travel_wpdb,
            (int) $chada_travel_application['chada_travel_order_id'],
            (int) $chada_travel_application['chada_travel_application_id'],
            (int) $chada_travel_document['chada_travel_document_id'],
            'chada_travel_document_status_updated',
            'admin',
            ['status' => $chada_travel_status],
            $chada_travel_admin_user_id
        );

        $chada_travel_file_status = self::recalculate_file_status($chada_travel_wpdb, $chada_travel_application);
        CHADA_TRAVEL_Order_Repository::update_application_file_status(
            $chada_travel_wpdb,
            (int) $chada_travel_application['chada_travel_application_id'],
            $chada_travel_file_status
        );

        return ['errors' => [], 'document' => $chada_travel_document, 'fileStatus' => $chada_travel_file_status];
    }

    /** @return array<string, mixed>|null */
    private static function find_owned_application(
        object $chada_travel_wpdb,
        int $chada_travel_order_id,
        int $chada_travel_application_id
    ): ?array {
        foreach (CHADA_TRAVEL_Order_Repository::get_applications($chada_travel_wpdb, $chada_travel_order_id) as $chada_travel_application) {
            if ((int) $chada_travel_application['chada_travel_application_id'] === $chada_travel_application_id) {
                return $chada_travel_application;
            }
        }
        return null;
    }

    /** @param array<string, mixed> $chada_travel_application */
    private static function recalculate_file_status(object $chada_travel_wpdb, array $chada_travel_application): string {
        $chada_travel_requirements = CHADA_TRAVEL_Requirement_Repository::get_active_for_country(
            $chada_travel_wpdb,
            (int) $chada_travel_application['chada_travel_country_id'],
            (string) $chada_travel_application['chada_travel_checklist_version_snapshot']
        );
        $chada_travel_required_ids = array_values(array_map(
            static fn(array $chada_travel_requirement): int => (int) $chada_travel_requirement['chada_travel_requirement_id'],
            array_filter($chada_travel_requirements, static fn(array $chada_travel_requirement): bool =>
                !empty($chada_travel_requirement['chada_travel_is_required']))
        ));
        if (!$chada_travel_required_ids) {
            return 'chada_travel_no_submitted_files';
        }

        $chada_travel_current = CHADA_TRAVEL_Document_Repository::find_current_for_application(
            $chada_travel_wpdb,
            (int) $chada_travel_application['chada_travel_application_id']
        );

        $chada_travel_present_count   = 0;
        $chada_travel_all_accepted    = true;
        $chada_travel_action_required = false;
        foreach ($chada_travel_required_ids as $chada_travel_requirement_id) {
            $chada_travel_document = $chada_travel_current[$chada_travel_requirement_id] ?? null;
            if (!$chada_travel_document) {
                $chada_travel_all_accepted = false;
                continue;
            }
            $chada_travel_present_count++;
            $chada_travel_status = (string) $chada_travel_document['chada_travel_document_status'];
            if (in_array($chada_travel_status, ['chada_travel_rejected', 'chada_travel_replacement_required'], true)) {
                $chada_travel_action_required = true;
                $chada_travel_all_accepted    = false;
            } elseif ($chada_travel_status !== 'chada_travel_accepted') {
                $chada_travel_all_accepted = false;
            }
        }

        if ($chada_travel_action_required) {
            return 'chada_travel_action_required';
        }
        if ($chada_travel_all_accepted) {
            return 'chada_travel_complete_files';
        }
        if ($chada_travel_present_count === 0) {
            return 'chada_travel_no_submitted_files';
        }
        if ($chada_travel_present_count < count($chada_travel_required_ids)) {
            return 'chada_travel_partially_submitted_files';
        }
        return 'chada_travel_submitted_for_review';
    }

    /** @return list<string> */
    private static function decode_mime_types(string $chada_travel_json): array {
        $chada_travel_decoded = json_decode($chada_travel_json, true);
        return is_array($chada_travel_decoded) ? array_values(array_map('strval', $chada_travel_decoded)) : [];
    }
}
