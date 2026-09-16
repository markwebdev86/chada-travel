<?php
/**
 * Deterministic MVP schema, controlled seeds, roles, and capabilities.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

require_once __DIR__ . '/CHADA_TRAVEL_Extension_Manager.php';

final class CHADA_TRAVEL_Installer {
    /** Installs or upgrades the plugin without overwriting administrator-managed settings or business records. */
    public static function activate(bool $chada_travel_network_wide = false): void {
        global $wpdb;

        CHADA_TRAVEL_Runtime_Limits::apply();
        $chada_travel_previous_db_version = function_exists('get_option') ? get_option('chada_travel_db_version', '') : '';
        CHADA_TRAVEL_Tour_Slug_Service::prepare_existing_schema($wpdb);
        self::apply_schema($wpdb);
        CHADA_TRAVEL_Tour_Slug_Service::finalize_schema($wpdb);
        self::migrate_previous_digital_wallet_payments($wpdb);
        CHADA_TRAVEL_Seeder::seed_options();
        CHADA_TRAVEL_Seeder::seed_country_catalog($wpdb);
        CHADA_TRAVEL_Demo_Media_Provisioner::provision($wpdb);
        CHADA_TRAVEL_Seeder::seed_tour_terms($wpdb);
        CHADA_TRAVEL_Settings_Repository::maybe_migrate($wpdb);
        CHADA_TRAVEL_Page_Provisioner::provision_tour_search_results_page();
        CHADA_TRAVEL_Page_Provisioner::provision_missing_pages();
        self::register_roles_and_capabilities();
        update_option('chada_travel_plugin_version', CHADA_TRAVEL_VERSION, false);
        update_option('chada_travel_db_version', CHADA_TRAVEL_DB_VERSION, false);
        update_option('chada_travel_split_architecture', 'free_core', false);

        if ($chada_travel_previous_db_version !== CHADA_TRAVEL_DB_VERSION) {
            self::record_schema_event($wpdb, (string) $chada_travel_previous_db_version);
        }
    }

    /**
     * Re-runs the idempotent installer when the code schema changes, or when only the settings-data version
     * changes (e.g. a normalized-option migration with no table/column changes). The two versions are tracked
     * independently so an option-format-only release never needs a CHADA_TRAVEL_DB_VERSION bump; activate() itself is
     * fully idempotent (dbDelta() and seed_options()/maybe_migrate() are all safe to re-run).
     */
    public static function maybe_upgrade(): void {
        $chada_travel_installed_db_version = (string) get_option('chada_travel_db_version', '');
        $chada_travel_installed_settings_version = (int) get_option('chada_travel_settings_version', 0);
        $chada_travel_settings_current = $chada_travel_installed_settings_version >= CHADA_TRAVEL_Settings_Repository::CURRENT_VERSION;
        if ($chada_travel_installed_db_version !== CHADA_TRAVEL_DB_VERSION || !$chada_travel_settings_current) {
            self::activate();
        }
    }

    /** Deactivation intentionally preserves all business records and options. */
    public static function deactivate(): void {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('chada_travel_email_queue_worker');
        }
    }

    /**
     * Returns the current installer-managed table names.
     *
     * @return list<string>
     */
    public static function get_table_names(string $chada_travel_wordpress_prefix): array {
        return array_map(static fn(string $chada_travel_name): string => $chada_travel_wordpress_prefix . 'chada_travel_' . $chada_travel_name, [
            'orders', 'applicants', 'applications', 'countries', 'requirements', 'payments', 'documents', 'tours',
            'tour_dates', 'tour_files', 'tour_terms', 'tour_term_relationships', 'events', 'email_queue',
        ]);
    }

    /**
     * Returns dbDelta-compatible SQL in deterministic dependency order.
     *
     * @return list<string>
     */
    public static function get_schema_sql(string $chada_travel_wordpress_prefix, string $chada_travel_charset_collate): array {
        $chada_travel_tables = array_combine([
            'orders', 'applicants', 'applications', 'countries', 'requirements', 'payments', 'documents', 'tours',
            'tour_dates', 'tour_files', 'tour_terms', 'tour_term_relationships', 'events', 'email_queue',
        ], array_map(static fn(string $chada_travel_name): string => $chada_travel_wordpress_prefix . 'chada_travel_' . $chada_travel_name, [
            'orders', 'applicants', 'applications', 'countries', 'requirements', 'payments', 'documents', 'tours',
            'tour_dates', 'tour_files', 'tour_terms', 'tour_term_relationships', 'events', 'email_queue',
        ]));
        $chada_travel_suffix = 'ENGINE=InnoDB ' . trim($chada_travel_charset_collate) . ';';
        // phpcs:ignore Generic.Files.LineLength.TooLong, Generic.Files.LineLength.MaxExceeded -- dbDelta requires an index definition on one physical SQL line.
        $chada_travel_document_composite_index = 'KEY chada_travel_application_requirement_status_version (chada_travel_application_id,chada_travel_requirement_id,chada_travel_document_status,chada_travel_file_version)';

        $chada_travel_schema = [
            "CREATE TABLE {$chada_travel_tables['orders']} (
                chada_travel_order_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_booking_id varchar(32) DEFAULT NULL,
                chada_travel_wp_user_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_booker_first_name varchar(100) NOT NULL,
                chada_travel_booker_last_name varchar(100) NOT NULL,
                chada_travel_booker_email varchar(190) NOT NULL,
                chada_travel_booker_mobile varchar(40) NOT NULL,
                chada_travel_booker_full_address text NULL,
                chada_travel_privacy_accepted_at datetime NOT NULL,
                chada_travel_terms_accepted_at datetime NOT NULL,
                chada_travel_cancellation_refund_accepted_at datetime DEFAULT NULL,
                chada_travel_policy_version varchar(40) NOT NULL,
                chada_travel_policy_snapshot longtext DEFAULT NULL,
                chada_travel_currency char(3) NOT NULL DEFAULT 'PHP',
                chada_travel_subtotal decimal(12,2) NOT NULL DEFAULT 0.00,
                chada_travel_total decimal(12,2) NOT NULL DEFAULT 0.00,
                chada_travel_order_status varchar(50) NOT NULL DEFAULT 'chada_travel_draft',
                chada_travel_payment_status varchar(50) NOT NULL DEFAULT 'chada_travel_unpaid',
                chada_travel_checkout_stage tinyint(3) unsigned NOT NULL DEFAULT 1,
                chada_travel_draft_token_hash char(64) DEFAULT NULL,
                chada_travel_upload_token_hash char(64) DEFAULT NULL,
                chada_travel_upload_token_expires_at datetime DEFAULT NULL,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                chada_travel_archived_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_order_id),
                UNIQUE KEY chada_travel_booking_id (chada_travel_booking_id),
                UNIQUE KEY chada_travel_draft_token_hash (chada_travel_draft_token_hash),
                UNIQUE KEY chada_travel_upload_token_hash (chada_travel_upload_token_hash),
                KEY chada_travel_wp_user_id (chada_travel_wp_user_id),
                KEY chada_travel_booker_email (chada_travel_booker_email),
                KEY chada_travel_order_status (chada_travel_order_status),
                KEY chada_travel_payment_status (chada_travel_payment_status),
                KEY chada_travel_checkout_stage (chada_travel_checkout_stage),
                KEY chada_travel_created_at (chada_travel_created_at)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['applicants']} (
                chada_travel_applicant_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_order_id bigint(20) unsigned NOT NULL,
                chada_travel_applicant_reference varchar(32) NOT NULL,
                chada_travel_first_name varchar(100) NOT NULL,
                chada_travel_last_name varchar(100) NOT NULL,
                chada_travel_date_of_birth date DEFAULT NULL,
                chada_travel_is_booker tinyint(1) NOT NULL DEFAULT 0,
                chada_travel_record_status varchar(50) NOT NULL DEFAULT 'chada_travel_active',
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                chada_travel_archived_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_applicant_id),
                UNIQUE KEY chada_travel_applicant_reference (chada_travel_applicant_reference),
                KEY chada_travel_order_id (chada_travel_order_id),
                KEY chada_travel_record_status (chada_travel_record_status)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['applications']} (
                chada_travel_application_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_application_reference varchar(32) NOT NULL,
                chada_travel_order_id bigint(20) unsigned NOT NULL,
                chada_travel_applicant_id bigint(20) unsigned NOT NULL,
                chada_travel_country_id bigint(20) unsigned NOT NULL,
                chada_travel_country_code_snapshot char(2) NOT NULL,
                chada_travel_country_name_snapshot varchar(120) NOT NULL,
                chada_travel_processing_fee_snapshot decimal(12,2) NOT NULL,
                chada_travel_currency_snapshot char(3) NOT NULL,
                chada_travel_checklist_version_snapshot varchar(40) NOT NULL,
                chada_travel_target_travel_date date DEFAULT NULL,
                chada_travel_application_status varchar(50) NOT NULL DEFAULT 'chada_travel_active',
                chada_travel_file_status varchar(60) NOT NULL DEFAULT 'chada_travel_no_submitted_files',
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                chada_travel_archived_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_application_id),
                UNIQUE KEY chada_travel_application_reference (chada_travel_application_reference),
                KEY chada_travel_order_id (chada_travel_order_id),
                KEY chada_travel_applicant_id (chada_travel_applicant_id),
                KEY chada_travel_country_id (chada_travel_country_id),
                KEY chada_travel_application_status (chada_travel_application_status),
                KEY chada_travel_order_file_status (chada_travel_order_id,chada_travel_file_status)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['countries']} (
                chada_travel_country_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_country_code char(2) NOT NULL,
                chada_travel_country_name varchar(120) NOT NULL,
                chada_travel_processing_fee decimal(12,2) NOT NULL,
                chada_travel_currency char(3) NOT NULL DEFAULT 'PHP',
                chada_travel_full_details longtext NULL,
                chada_travel_guide_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_checklist_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_checklist_version varchar(40) NOT NULL,
                chada_travel_is_active tinyint(1) NOT NULL DEFAULT 1,
                chada_travel_sort_order int(10) unsigned NOT NULL DEFAULT 0,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_country_id),
                UNIQUE KEY chada_travel_country_code (chada_travel_country_code),
                KEY chada_travel_is_active (chada_travel_is_active),
                KEY chada_travel_sort_order (chada_travel_sort_order)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['requirements']} (
                chada_travel_requirement_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_country_id bigint(20) unsigned NOT NULL,
                chada_travel_checklist_version varchar(40) NOT NULL,
                chada_travel_requirement_label varchar(190) NOT NULL,
                chada_travel_requirement_description text NULL,
                chada_travel_form_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_is_required tinyint(1) NOT NULL DEFAULT 1,
                chada_travel_allowed_mime_types text NOT NULL,
                chada_travel_max_file_bytes bigint(20) unsigned NOT NULL,
                chada_travel_sort_order int(10) unsigned NOT NULL DEFAULT 0,
                chada_travel_is_active tinyint(1) NOT NULL DEFAULT 1,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_requirement_id),
                KEY chada_travel_country_id (chada_travel_country_id),
                KEY chada_travel_checklist_version (chada_travel_checklist_version),
                KEY chada_travel_country_version (chada_travel_country_id,chada_travel_checklist_version),
                KEY chada_travel_is_active (chada_travel_is_active)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['payments']} (
                chada_travel_payment_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_payment_reference varchar(64) NOT NULL,
                chada_travel_transaction_id varchar(64) DEFAULT NULL,
                chada_travel_order_id bigint(20) unsigned NOT NULL,
                chada_travel_payment_method varchar(40) NOT NULL,
                chada_travel_payment_provider varchar(80) DEFAULT NULL,
                chada_travel_provider_transaction_id varchar(190) DEFAULT NULL,
                chada_travel_provider_event_id varchar(190) DEFAULT NULL,
                chada_travel_digital_wallet_reference_no varchar(190) DEFAULT NULL,
                chada_travel_amount decimal(12,2) NOT NULL,
                chada_travel_currency char(3) NOT NULL,
                chada_travel_payment_status varchar(60) NOT NULL,
                chada_travel_proof_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_proof_token_hash char(64) DEFAULT NULL,
                chada_travel_proof_token_expires_at datetime DEFAULT NULL,
                chada_travel_payment_details_snapshot longtext NULL,
                chada_travel_submitted_at datetime DEFAULT NULL,
                chada_travel_transaction_id_generated_at datetime DEFAULT NULL,
                chada_travel_verified_by_user_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_verified_at datetime DEFAULT NULL,
                chada_travel_rejection_reason text NULL,
                chada_travel_failure_code varchar(100) DEFAULT NULL,
                chada_travel_failure_message text NULL,
                chada_travel_paid_at datetime DEFAULT NULL,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_payment_id),
                UNIQUE KEY chada_travel_payment_reference (chada_travel_payment_reference),
                UNIQUE KEY chada_travel_transaction_id (chada_travel_transaction_id),
                UNIQUE KEY chada_travel_provider_event_id (chada_travel_provider_event_id),
                UNIQUE KEY chada_travel_proof_token_hash (chada_travel_proof_token_hash),
                KEY chada_travel_order_status (chada_travel_order_id,chada_travel_payment_status),
                KEY chada_travel_payment_method (chada_travel_payment_method),
                KEY chada_travel_provider_transaction_id (chada_travel_provider_transaction_id),
                KEY chada_travel_digital_wallet_reference_no (chada_travel_digital_wallet_reference_no),
                KEY chada_travel_created_at (chada_travel_created_at)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['documents']} (
                chada_travel_document_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_application_id bigint(20) unsigned NOT NULL,
                chada_travel_requirement_id bigint(20) unsigned NOT NULL,
                chada_travel_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_storage_key varchar(255) DEFAULT NULL,
                chada_travel_original_filename varchar(255) NOT NULL,
                chada_travel_mime_type varchar(120) NOT NULL,
                chada_travel_file_size bigint(20) unsigned NOT NULL,
                chada_travel_file_version int(10) unsigned NOT NULL DEFAULT 1,
                chada_travel_document_status varchar(60) NOT NULL DEFAULT 'chada_travel_uploaded',
                chada_travel_booker_note text NULL,
                chada_travel_review_note text NULL,
                chada_travel_uploaded_by_user_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_reviewed_by_user_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_uploaded_at datetime NOT NULL,
                chada_travel_reviewed_at datetime DEFAULT NULL,
                chada_travel_superseded_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_document_id),
                KEY chada_travel_application_id (chada_travel_application_id),
                KEY chada_travel_requirement_id (chada_travel_requirement_id),
                KEY chada_travel_document_status (chada_travel_document_status),
                $chada_travel_document_composite_index
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['tours']} (
                chada_travel_tour_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_feature_image_attachment_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_is_featured tinyint(1) NOT NULL DEFAULT 0,
                chada_travel_tour_name varchar(100) NOT NULL,
                chada_travel_tour_slug varchar(200) NOT NULL,
                chada_travel_description longtext NULL,
                chada_travel_trip_includes longtext NULL,
                chada_travel_trip_excludes longtext NULL,
                chada_travel_basic_visa_requirements longtext NULL,
                chada_travel_itinerary longtext NULL,
                chada_travel_booking_conditions longtext NULL,
                chada_travel_tour_code varchar(20) NOT NULL,
                chada_travel_currency char(3) NOT NULL DEFAULT 'PHP',
                chada_travel_price decimal(12,2) NOT NULL DEFAULT 0.00,
                chada_travel_is_active tinyint(1) NOT NULL DEFAULT 1,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                chada_travel_archived_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_tour_id),
                UNIQUE KEY chada_travel_tour_code (chada_travel_tour_code),
                UNIQUE KEY chada_travel_tour_slug (chada_travel_tour_slug),
                KEY chada_travel_tour_name (chada_travel_tour_name),
                KEY chada_travel_is_active (chada_travel_is_active),
                KEY chada_travel_is_featured (chada_travel_is_featured),
                KEY chada_travel_created_at (chada_travel_created_at)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['tour_dates']} (
                chada_travel_tour_date_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_tour_id bigint(20) unsigned NOT NULL,
                chada_travel_start_date date NOT NULL,
                chada_travel_end_date date NOT NULL,
                chada_travel_sort_order int(10) unsigned NOT NULL DEFAULT 0,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_tour_date_id),
                UNIQUE KEY chada_travel_tour_date_range (chada_travel_tour_id,chada_travel_start_date,chada_travel_end_date),
                KEY chada_travel_tour_id (chada_travel_tour_id),
                KEY chada_travel_tour_start_date (chada_travel_tour_id,chada_travel_start_date)
              ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['tour_files']} (
                chada_travel_tour_file_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_tour_id bigint(20) unsigned NOT NULL,
                chada_travel_attachment_id bigint(20) unsigned NOT NULL,
                chada_travel_sort_order int(10) unsigned NOT NULL DEFAULT 0,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_tour_file_id),
                UNIQUE KEY chada_travel_tour_attachment (chada_travel_tour_id,chada_travel_attachment_id),
                KEY chada_travel_tour_id (chada_travel_tour_id),
                KEY chada_travel_tour_sort_order (chada_travel_tour_id,chada_travel_sort_order)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['tour_terms']} (
                chada_travel_tour_term_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_taxonomy varchar(30) NOT NULL,
                chada_travel_term_name varchar(100) NOT NULL,
                chada_travel_term_slug varchar(120) NOT NULL,
                chada_travel_is_active tinyint(1) NOT NULL DEFAULT 1,
                chada_travel_is_default tinyint(1) NOT NULL DEFAULT 0,
                chada_travel_sort_order int(10) unsigned NOT NULL DEFAULT 0,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                chada_travel_archived_at datetime DEFAULT NULL,
                PRIMARY KEY  (chada_travel_tour_term_id),
                UNIQUE KEY chada_travel_taxonomy_slug (chada_travel_taxonomy,chada_travel_term_slug),
                KEY chada_travel_taxonomy_active (chada_travel_taxonomy,chada_travel_is_active),
                KEY chada_travel_term_name (chada_travel_term_name)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['tour_term_relationships']} (
                chada_travel_tour_term_relationship_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_tour_id bigint(20) unsigned NOT NULL,
                chada_travel_tour_term_id bigint(20) unsigned NOT NULL,
                chada_travel_created_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_tour_term_relationship_id),
                UNIQUE KEY chada_travel_tour_term (chada_travel_tour_id,chada_travel_tour_term_id),
                KEY chada_travel_tour_id (chada_travel_tour_id),
                KEY chada_travel_tour_term_id (chada_travel_tour_term_id)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['events']} (
                chada_travel_event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_order_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_application_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_record_type varchar(30) NOT NULL,
                chada_travel_record_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_event_type varchar(80) NOT NULL,
                chada_travel_from_status varchar(60) DEFAULT NULL,
                chada_travel_to_status varchar(60) DEFAULT NULL,
                chada_travel_event_message text NULL,
                chada_travel_event_meta longtext NULL,
                chada_travel_actor_user_id bigint(20) unsigned DEFAULT NULL,
                chada_travel_actor_type varchar(30) NOT NULL,
                chada_travel_created_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_event_id),
                KEY chada_travel_order_id (chada_travel_order_id),
                KEY chada_travel_application_id (chada_travel_application_id),
                KEY chada_travel_record (chada_travel_record_type,chada_travel_record_id),
                KEY chada_travel_event_type (chada_travel_event_type),
                KEY chada_travel_created_at (chada_travel_created_at)
            ) $chada_travel_suffix",
            "CREATE TABLE {$chada_travel_tables['email_queue']} (
                chada_travel_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                chada_travel_event varchar(80) NOT NULL,
                chada_travel_payment_id bigint(20) unsigned NOT NULL,
                chada_travel_audience varchar(30) NOT NULL,
                chada_travel_country_id bigint(20) unsigned NOT NULL DEFAULT 0,
                chada_travel_context longtext NOT NULL,
                chada_travel_status varchar(20) NOT NULL DEFAULT 'pending',
                chada_travel_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
                chada_travel_available_at datetime NOT NULL,
                chada_travel_claimed_at datetime DEFAULT NULL,
                chada_travel_last_error_code varchar(30) DEFAULT NULL,
                chada_travel_created_at datetime NOT NULL,
                chada_travel_updated_at datetime NOT NULL,
                PRIMARY KEY  (chada_travel_id),
                KEY chada_travel_queue_due (chada_travel_status,chada_travel_available_at),
                KEY chada_travel_queue_lease (chada_travel_status,chada_travel_claimed_at),
                KEY chada_travel_payment_audience (chada_travel_payment_id,chada_travel_audience),
                KEY chada_travel_created_at (chada_travel_created_at)
            ) $chada_travel_suffix",
        ];
        return $chada_travel_schema;
    }

    /**
     * Returns all capabilities assigned to the minimum project manager role.
     *
     * @return list<string>
     */
    public static function get_capabilities(): array {
        return [
            'manage_chada_travel_visa_applications', 'verify_chada_travel_payments', 'view_chada_travel_visa_documents',
            'review_chada_travel_visa_documents', 'manage_chada_travel_visa_settings', 'manage_chada_travel_tours',
        ];
    }

    /**
     * Copies every non-null previous `chada_travel_gcash_reference_no` value into the new canonical
     * `chada_travel_digital_wallet_reference_no` column by primary key, and converts
     * `chada_travel_payments.chada_travel_payment_method = 'chada_travel_gcash'` to `chada_travel_digital_wallet` - never touching any other
     * column, so every payment's primary key, order relationship, amount, status, transaction id, proof state,
     * timestamps, and audit linkage survive untouched. Both statements only ever affect rows not yet migrated
     * (idempotent, safe to call unconditionally on every activation, matching apply_schema()/seed_options()'s
     * own always-idempotent pattern) and never write or clear the previous `chada_travel_gcash_reference_no` column
     * itself - it remains rollback-safe, unused prior data on an upgraded installation. A no-op on a genuinely
     * fresh install or any post-2.3.0 database, since the previous `chada_travel_gcash_reference_no` column itself no
     * longer exists there (checked first, so activation never logs a spurious "Unknown column" DB error), and for
     * any lightweight test double that does not implement a real query() method (real behavior is proven by
     * tests/integration/payment-runtime.php against the canonical payment contract).
     */
    private static function migrate_previous_digital_wallet_payments(object $chada_travel_database): void {
        if (!method_exists($chada_travel_database, 'query') || !method_exists($chada_travel_database, 'get_var')) {
            return;
        }
        $chada_travel_database_properties = get_object_vars($chada_travel_database);
        $chada_travel_prefix = (string) ($chada_travel_database_properties['prefix'] ?? '');
        $chada_travel_table = $chada_travel_prefix . 'chada_travel_payments';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- one-time column-existence probe; code-controlled table name (no user input).
        $chada_travel_previous_column_exists = (bool) $chada_travel_database->get_var(
            "SHOW COLUMNS FROM {$chada_travel_table} LIKE 'chada_travel_gcash_reference_no'"
        );
        if (!$chada_travel_previous_column_exists) {
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- one-time, idempotent previous-column backfill; code-controlled table name (no user input), touches only rows not yet migrated.
        $chada_travel_database->query(
            "UPDATE {$chada_travel_table} SET chada_travel_digital_wallet_reference_no = chada_travel_gcash_reference_no "
                . 'WHERE chada_travel_gcash_reference_no IS NOT NULL AND chada_travel_digital_wallet_reference_no IS NULL'
        );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- one-time, idempotent payment-method value conversion; code-controlled table name (no user input), preserves every other column untouched.
        $chada_travel_database->query(
            "UPDATE {$chada_travel_table} SET chada_travel_payment_method = 'chada_travel_digital_wallet' "
                . "WHERE chada_travel_payment_method = 'chada_travel_gcash'"
        );
    }

    private static function apply_schema(object $chada_travel_database): void {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        $chada_travel_schema_statements = self::get_schema_sql(
            $chada_travel_database->prefix,
            $chada_travel_database->get_charset_collate()
        );
        foreach ($chada_travel_schema_statements as $chada_travel_schema_sql) {
            dbDelta($chada_travel_schema_sql);
        }
    }

    private static function register_roles_and_capabilities(): void {
        $chada_travel_capabilities = array_fill_keys(self::get_capabilities(), true);
        $chada_travel_role = add_role(
            'chada_travel_manager',
            'Travel Agency Manager',
            array_merge(['read' => true], $chada_travel_capabilities)
        );
        if (!$chada_travel_role) {
            $chada_travel_role = get_role('chada_travel_manager');
        }
        $chada_travel_administrator = get_role('administrator');
        foreach (self::get_capabilities() as $chada_travel_capability) {
            if ($chada_travel_role) {
                $chada_travel_role->add_cap($chada_travel_capability);
            }
            if ($chada_travel_administrator) {
                $chada_travel_administrator->add_cap($chada_travel_capability);
            }
        }
    }

    private static function record_schema_event(object $chada_travel_database, string $chada_travel_from_version): void {
        $chada_travel_table_name = $chada_travel_database->prefix . 'chada_travel_events';
        $chada_travel_database->insert($chada_travel_table_name, [
            'chada_travel_record_type'  => 'system',
            'chada_travel_event_type'   => 'chada_travel_schema_migrated',
            'chada_travel_event_message'=> CHADA_TRAVEL_PROJECT_NAME . ' schema migrated.',
            'chada_travel_event_meta'   => function_exists('wp_json_encode') ? wp_json_encode([
                'from' => $chada_travel_from_version, 'to' => CHADA_TRAVEL_DB_VERSION,
            ]) : '{}',
            'chada_travel_actor_type'   => 'system',
            'chada_travel_created_at'   => gmdate('Y-m-d H:i:s'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s']);
    }
}
