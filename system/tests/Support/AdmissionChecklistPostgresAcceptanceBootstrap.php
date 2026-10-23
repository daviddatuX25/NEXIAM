<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$expectedEnvironment = [
    'APP_ENV' => 'testing',
    'NEXIAM_TEST_PIN_SQLITE' => '0',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'nexiam_messaging_acceptance',
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_USERNAME' => 'nexiam',
    'DB_PASSWORD' => 'nexiam-local-only',
    'DB_URL' => '',
];

foreach ($expectedEnvironment as $name => $expectedValue) {
    $actualValue = getenv($name);

    if ($actualValue !== $expectedValue) {
        throw new RuntimeException(sprintf(
            'Refusing admission checklist PostgreSQL acceptance: %s must be exactly %s; got %s.',
            $name,
            var_export($expectedValue, true),
            var_export($actualValue, true),
        ));
    }
}

foreach ($expectedEnvironment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$schema = 'nexiam_admission_checklist_'.bin2hex(random_bytes(8));
$configCachePath = sys_get_temp_dir().'/'.$schema.'-config.php';
$storageRoot = sys_get_temp_dir().'/'.$schema.'-storage';
if (is_file($configCachePath) || file_exists($storageRoot)) {
    throw new RuntimeException('Refusing admission checklist acceptance because a generated isolated path already exists.');
}
foreach (['APP_CONFIG_CACHE' => $configCachePath, 'NEXIAM_ADMISSION_TEST_SCHEMA' => $schema] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
Env::enablePutenv();

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'nexiam_messaging_acceptance') {
    throw new RuntimeException('Refusing admission checklist acceptance outside the guarded disposable PostgreSQL database.');
}
if (! mkdir($storageRoot, 0700)) {
    throw new RuntimeException('Could not create the private admission checklist fixture storage root.');
}
register_shutdown_function(static function () use ($schema, $configCachePath, $storageRoot): void {
    try {
        $pdo = new PDO(
            'pgsql:host=postgres;port=5432;dbname=nexiam_messaging_acceptance',
            'nexiam',
            'nexiam-local-only',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->exec('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
    } catch (Throwable) {
        // This schema is unique to this process; never touch public or another test schema.
    }

    if (is_dir($storageRoot)) {
        (new Filesystem)->remove($storageRoot);
    }

    if (is_file($configCachePath)) {
        unlink($configCachePath);
    }
});
foreach (['private', 'public', 'sensitive'] as $directory) {
    if (! mkdir($storageRoot.'/'.$directory, 0700)) {
        throw new RuntimeException('Could not create an isolated admission checklist fixture disk.');
    }
}
$connection->getPdo()->exec('CREATE SCHEMA "'.$schema.'"');

config([
    'database.connections.pgsql.search_path' => $schema,
    'filesystems.disks.local.root' => $storageRoot.'/private',
    'filesystems.disks.public.root' => $storageRoot.'/public',
    'filesystems.disks.sensitive.root' => $storageRoot.'/sensitive',
    'omr.canonical.storage.disk' => 'local',
]);
foreach (['local', 'public', 'sensitive'] as $disk) {
    Storage::purge($disk);
}
DB::purge('pgsql');
$connection = DB::connection();

$resolvedSchemas = json_decode(
    (string) $connection->scalar('SELECT array_to_json(current_schemas(false))::text'),
    true,
    512,
    JSON_THROW_ON_ERROR,
);
if ((string) $connection->scalar('SELECT current_schema()') !== $schema || $resolvedSchemas !== [$schema]) {
    throw new RuntimeException('Refusing admission checklist migrations because the PostgreSQL connection did not resolve only to its unique schema.');
}
$configCache = '<?php return '.var_export($app['config']->all(), true).';'.PHP_EOL;
if (file_put_contents($configCachePath, $configCache, LOCK_EX) === false) {
    throw new RuntimeException('Could not write the isolated admission checklist PostgreSQL config cache.');
}

// Frozen real-migration superset for checklist fixtures. The incomplete TestKit
// owner/tuple migration and malformed seal migration are omitted; this is not full TestKit acceptance.
$migrationFiles = [
    '0001_01_01_000000_create_users_table.php',
    '0001_01_01_000001_create_cache_table.php',
    '0001_01_01_000002_create_jobs_table.php',
    '2026_08_04_045658_create_institutions_table.php',
    '2026_08_04_045700_create_academic_cycles_table.php',
    '2026_08_04_045702_create_operations_table.php',
    '2026_08_04_045703_create_audit_events_table.php',
    '2026_08_04_103123_create_permission_tables.php',
    '2026_08_04_104531_create_staff_profiles_table.php',
    '2026_08_04_110405_create_programs_table.php',
    '2026_08_04_110406_create_rooms_table.php',
    '2026_08_04_110407_create_aptitude_areas_table.php',
    '2026_08_04_110408_create_application_windows_table.php',
    '2026_08_05_160000_create_e2_connected_slice_tables.php',
    '2026_08_05_170000_expand_admission_intake_fields.php',
    '2026_08_05_181000_add_privacy_policies_and_intake_grades.php',
    '2026_08_05_185000_add_status_to_staff_profiles_table.php',
    '2026_08_06_060000_create_program_quotas_tables.php',
    '2026_08_06_064000_add_school_year_and_semester_to_academic_cycles.php',
    '2026_08_06_070000_add_is_active_to_academic_cycles.php',
    '2026_08_07_210000_create_exam_slot_scheduling_tables.php',
    '2026_08_10_130000_create_nexi_run_governance_tables.php',
    '2026_08_10_150000_create_nexi_knowledge_governance_tables.php',
    '2026_08_11_000001_add_auditors_to_grading_sessions_table.php',
    '2026_08_11_140000_create_notification_deliveries_table.php',
    '2026_08_11_150000_create_score_conversion_rows_table.php',
    '2026_08_11_150001_add_scoring_to_aptitude_areas_table.php',
    '2026_08_11_150002_add_converted_score_to_score_records_table.php',
    '2026_08_11_170000_create_omr_capture_batches_table.php',
    '2026_08_11_170001_create_omr_capture_sheets_table.php',
    '2026_08_11_170002_add_omr_provenance_to_score_records_table.php',
    '2026_08_12_080000_omr_cutover_preflight.php',
    '2026_08_12_080100_create_omr_configuration_tables.php',
    '2026_08_12_080200_forward_fix_omr_capture_tables.php',
    '2026_08_12_080300_create_omr_workflow_tables.php',
    '2026_08_12_080400_add_omr_invariant_triggers.php',
    '2026_08_12_080500_fix_omr_json_trigger.php',
    '2026_08_12_080600_widen_encrypted_score_columns.php',
    '2026_08_12_080700_add_omr_native_bundle_identity.php',
    '2026_08_12_080800_enforce_omr_native_identity.php',
    '2026_08_13_090000_harden_omr_processing_runs.php',
    '2026_08_13_100000_harden_omr_binding_and_recovery.php',
    '2026_08_13_120000_add_omr_multipage.php',
    '2026_08_13_130000_relax_omr_sheet_assets.php',
    '2026_08_13_140000_create_print_jobs_table.php',
    '2026_08_19_090000_create_print_job_items_table.php',
    '2026_08_19_091000_add_document_outputs_to_print_jobs.php',
    '2026_08_13_150000_nullable_booklet_question_link.php',
    '2026_08_13_151000_add_template_json_hash.php',
    '2026_08_14_090000_add_print_overlay_to_template_versions.php',
    '2026_08_14_093000_add_detected_birthdate_to_sheets.php',
    '2026_08_14_100000_create_consultation_scheduling_tables.php',
    '2026_08_14_200000_add_mode_to_result_releases.php',
    '2026_08_15_100000_add_omr_layout_blueprint.php',
    '2026_08_15_110000_widen_layout_solver_version.php',
    '2026_08_15_120000_create_admission_support_tables.php',
    '2026_08_18_210000_create_result_sheet_templates_table.php',
    '2026_08_20_090001_create_omr_template_authoring_drafts_table.php',
    '2026_08_20_090002_create_omr_template_assets_table.php',
    '2026_08_20_090003_add_omr_authoring_manifest_to_template_versions.php',
    '2026_08_20_090004_create_omr_blank_instances_table.php',
    '2026_08_20_090005_create_omr_blank_instance_pages_table.php',
    '2026_08_20_090000_create_omr_marker_profiles_table.php',
    '2026_08_22_120001_expand_omr_template_asset_kinds.php',
    '2026_08_22_123000_add_builder_state_to_result_sheet_templates.php',
    '2026_08_26_090001_create_omr_physical_challenges_table.php',
    '2026_08_31_090000_create_omr_template_verification_methods_table.php',
    '2026_08_26_090002_create_omr_template_verification_captures_table.php',
    '2026_08_26_090003_add_omr_verification_variant_unique_index.php',
    '2026_08_26_090000_create_omr_template_verification_runs_table.php',
    '2026_08_28_100000_add_guide_json_to_omr_physical_challenges_table.php',
    '2026_08_31_090001_create_omr_template_verification_method_pages_table.php',
    '2026_08_31_090002_add_omr_physical_assurance_columns.php',
    '2026_08_31_090003_create_omr_template_verification_item_evidence_table.php',
    '2026_08_31_090004_backfill_omr_physical_assurance_legacy.php',
    '2026_08_31_090005_add_omr_physical_assurance_constraints.php',
    '2026_08_31_090006_add_omr_physical_assurance_indexes.php',
    '2026_09_01_090000_harden_omr_physical_assurance_capture_trigger.php',
    '2026_09_02_090000_allow_omr_false_accept_actual.php',
    '2026_09_05_090000_create_omr_item_review_revisions_table.php',
    '2026_09_06_090000_add_omr_diagnostic_persistence_to_physical_challenges_table.php',
    '2026_09_06_134147_backfill_omr_evidence_asset_dimensions.php',
    '2026_09_09_092849_create_room_seating_profiles_table.php',
    '2026_09_10_144957_create_seating_arrangements_table.php',
    '2026_09_10_145003_add_material_snapshot_revisions_and_seat_frozen_fields.php',
    '2026_09_10_145007_create_exam_session_seats_and_assignment_allocations.php',
    '2026_09_10_144959_backfill_seating_arrangements_from_profiles.php',
    '2026_09_10_145011_add_seating_persistence_constraints.php',
    '2026_09_10_145010_backfill_published_seating_manifests_and_allocations.php',
    '2026_09_10_120000_create_secure_booklet_authoring_tables.php',
    '2026_09_11_104706_drop_legacy_material_columns_from_exam_assignments.php',
    '2026_09_12_231613_create_result_sheet_template_drafts_table.php',
    '2026_09_13_001603_create_omr_paper_reproduction_authorizations_table.php',
    '2026_09_13_001604_create_omr_blank_instance_page_claims_table.php',
    '2026_09_13_001605_backfill_omr_blank_instance_page_claims.php',
    '2026_09_13_001602_add_copy_policy_to_omr_blank_instances.php',
    '2026_09_14_000100_create_omr_pending_captures_table.php',
    '2026_09_14_000101_add_omr_blank_instance_scope.php',
    '2026_09_14_000102_add_unique_omr_pending_adoption.php',
    '2026_09_14_000200_create_omr_diagnostic_runs_table.php',
    '2026_09_14_000201_create_omr_diagnostic_captures_table.php',
    '2026_09_14_000202_create_omr_diagnostic_item_evidence_table.php',
    '2026_09_14_000203_create_omr_diagnostic_review_revisions_table.php',
    '2026_09_15_060521_restore_partial_exam_assignment_uniqueness.php',
    '2026_09_16_100000_add_claim_fencing_to_print_jobs_and_items.php',
    '2026_09_20_100000_add_space_kind_to_rooms_table.php',
    '2026_09_20_100001_create_intake_requirements_table.php',
    '2026_09_20_231015_add_context_epoch_cas_fields_to_nexi_conversations.php',
    '2026_09_20_231315_reset_nexi_ai_tables_to_xiam.php',
    '2026_09_20_233926_create_xiam_run_steps_table.php',
    '2026_09_21_120000_create_intake_field_policies_table.php',
    '2026_09_21_120100_create_import_batches_and_rows_tables.php',
    '2026_09_22_114814_add_paper_policy_to_omr_templates.php',
    '2026_09_22_114815_backfill_and_constrain_omr_paper_policy.php',
    '2026_09_22_120344_constrain_omr_paper_policy.php',
    '2026_09_22_223742_add_auto_distribution_fields_to_scheduling_tables.php',
    '2026_09_23_083000_extend_omr_qr_disabled_issuance_lifecycle.php',
    '2026_09_23_090000_add_client_quad_to_omr_capture_sheet_pages.php',
    '2026_09_23_090000_add_knowledge_office_faq_governance.php',
    '2026_09_23_215750_create_omr_pending_capture_sets_table.php',
    '2026_09_23_230502_create_omr_pending_capture_page_evidence_table.php',
    '2026_09_23_231605_protect_omr_pending_capture_page_evidence.php',
    '2026_09_23_232355_add_pairing_code_and_create_omr_pending_capture_page_analyses_table.php',
    '2026_09_24_040000_add_client_quad_to_omr_pending_capture_page_evidence.php',
    '2026_09_24_033701_add_omr_qr_disabled_page_claims_and_capture_set_adoption.php',
    '2026_09_26_000200_add_superseded_at_to_result_releases_table.php',
    '2026_09_26_000100_create_omr_supplier_stock_custody_tables.php',
    '2026_09_27_010000_add_seating_policy_to_institutions_table.php',
    '2026_09_27_024253_create_examination_action_execution_receipts_table.php',
    '2026_09_30_100000_create_admission_benchmark_tables.php',
    '2026_09_30_120000_create_report_templates_table.php',
    '2026_09_30_100000_create_xiam_institution_policies_table.php',
    '2026_09_30_140000_add_builder_fields_to_report_templates_table.php',
    '2026_10_01_100000_allow_sandbox_purge_on_omr_verification_triggers.php',
    '2026_10_01_110000_add_allow_guidance_all_in_one_intake_to_academic_cycles_table.php',
    '2026_10_01_110100_add_label_and_make_room_id_nullable_on_exam_sessions_table.php',
    '2026_10_01_110200_expand_score_records_and_revisions_source_constraints_to_include_import.php',
    '2026_10_01_110000_add_allow_multiple_sets_to_omr_question_booklet_versions.php',
    '2026_10_01_120000_permit_booklet_version_unarchive_transition.php',
    '2026_10_01_130000_create_staff_authentication_tables.php',
    '2026_10_02_000001_add_revoke_reason_to_staff_authentication_attempts.php',
    '2026_10_02_050613_add_delivery_lease_and_success_timestamps_to_notification_deliveries_table.php',
    '2026_10_02_054917_add_notification_policies_to_academic_cycles_and_exam_sessions_tables.php',
    '2026_10_02_140000_create_staff_email_changes_table.php',
    '2026_10_02_150000_add_avatar_and_directory_to_staff_profiles_table.php',
    '2026_10_03_000001_add_proposal_view_to_xiam_proposals.php',
    '2026_10_03_000002_add_safety_tier_to_xiam_proposals.php',
    '2026_10_03_010000_add_guidance_status_to_import_batch_rows.php',
    '2026_10_03_100000_create_admission_support_snapshots_table.php',
    '2026_10_03_190000_add_communication_master_controls_to_academic_cycles.php',
    '2026_10_04_010000_create_communication_templates_tables.php',
    '2026_10_04_030000_add_builder_fields_to_communication_templates.php',
    '2026_10_04_020000_create_departments_and_link_to_programs.php',
    '2026_10_04_120000_add_snapshot_and_manifest_to_result_documents.php',
    '2026_10_04_120200_add_channel_to_result_releases_table.php',
    '2026_10_04_120100_create_result_document_print_receipts_table.php',
    '2026_10_04_120300_create_consultation_policy_and_exceptions_tables.php',
    '2026_10_04_120400_create_consultation_encounters_table.php',
    '2026_10_04_120500_reconcile_legacy_print_and_release_facts.php',
    '2026_10_04_120600_add_result_document_id_to_print_job_items.php',
    '2026_10_04_231000_create_admission_benchmark_governance_tables.php',
    '2026_10_04_232000_create_program_advising_note_tables.php',
    '2026_10_04_233000_create_program_governance_discussion_tables.php',
    '2026_10_04_234000_create_registrar_quota_governance_grants_table.php',
    '2026_10_05_000000_add_is_admission_offered_to_programs_table.php',
    '2026_10_05_010000_pin_benchmark_revision_bases.php',
    '2026_10_05_020000_create_admission_benchmark_recoveries.php',
    '2026_10_05_022223_add_activation_snapshot_to_admission_benchmark_recoveries.php',
    '2026_10_05_032806_create_public_communications_tables.php',
    '2026_10_05_100000_update_communication_templates_to_v2.php',
    '2026_10_05_110000_repair_notice_content_discriminators.php',
    '2026_10_05_110100_enforce_notice_state_constraints.php',
    '2026_10_05_110200_widen_communication_template_document_mime_type.php',
    '2026_10_05_120000_rename_consultation_online_policy_toggle.php',
    '2026_10_05_120100_add_access_window_to_consultation_encounters.php',
    '2026_10_05_120200_pin_online_consultation_start_context.php',
    '2026_10_05_120300_add_media_host_joined_at_to_consultation_encounters.php',
    '2026_10_05_120000_add_sequence_and_read_markers_to_record_threads.php',
    '2026_10_05_150000_add_source_binding_to_notice_attempt_artifacts.php',
    '2026_10_05_160000_create_applicant_office_inquiry_tables.php',
    '2026_10_05_161610_create_practice_preparation_tables.php',
    '2026_10_05_170000_replace_consultation_online_enabled_with_mode_policy.php',
    '2026_10_05_180000_allow_multiple_program_advising_notes.php',
    '2026_10_05_180000_create_admission_benchmark_recovery_program_reviews.php',
    '2026_10_05_181000_backfill_governance_for_full_ta_designations.php',
    '2026_10_05_200000_add_program_placement_to_announcements.php',
    '2026_10_05_200001_backfill_announcement_program_placement.php',
    '2026_10_05_210000_create_public_policy_tables.php',
    '2026_10_05_230503_create_test_instrument_profile_tables.php',
    '2026_10_05_232420_add_exam_feature_policy_version_to_institutions_table.php',
    '2026_10_05_232423_make_multiple_booklet_sets_opt_in_by_default.php',
    '2026_10_05_114446_create_program_public_profile_tables.php',
    '2026_10_06_002840_add_seating_mode_and_snapshot_policy_metadata.php',
    '2026_10_06_010000_add_admission_benchmark_schema_bindings.php',
    '2026_10_06_013549_add_test_instrument_administration_bindings.php',
    '2026_10_06_015845_create_omr_template_item_mappings_table.php',
    '2026_10_06_023755_add_test_instrument_item_identity_to_omr_booklet_items.php',
    '2026_10_06_072724_create_assessment_scoring_runs_and_raw_revisions_tables.php',
    '2026_10_06_120000_remove_academic_benchmark_publication_grants.php',
    '2026_10_06_122120_create_assessment_norm_packages_and_conversions_tables.php',
    '2026_10_06_124715_create_assessment_result_sets_table.php',
    '2026_10_06_130000_create_notice_document_lifecycle.php',
    '2026_10_06_140000_add_notice_message_document_revision.php',
    '2026_10_06_140000_add_notice_document_version_pin.php',
    '2026_10_06_140000_create_knowledge_source_reconciliations_table.php',
    '2026_10_07_090000_copy_legacy_notice_document_pins_to_revision.php',
    '2026_10_07_100001_create_admission_clearance_receipts_table.php',
    '2026_10_07_100002_create_enrollment_sheet_handoff_receipts_table.php',
    '2026_10_07_100003_create_admission_attempt_requirement_ledgers_table.php',
    '2026_10_07_100004_add_exam_session_id_to_assessment_result_sets_table.php',
    '2026_10_07_120000_add_kind_and_intake_designation_to_public_policies_table.php',
    '2026_10_08_010000_create_versioned_test_kit_tables.php',
    '2026_10_08_010010_add_test_instrument_material_applicability.php',
    '2026_10_08_010020_add_omr_material_ownership.php',
    '2026_10_08_010030_add_test_kit_production_pins.php',
    '2026_10_08_010040_add_test_kit_diagnostic_contracts.php',
    '2026_10_08_010070_separate_booklet_catalog_archival.php',
    '2026_10_08_010080_add_exam_session_test_kit_intent.php',
    '2026_10_08_020000_add_public_policy_id_to_admission_attempts_table.php',
    '2026_10_08_024400_add_kind_to_public_policy_revisions_and_intake_designation_index.php',
    '2026_10_08_140000_create_admission_document_correction_request_tables.php',
    '2026_10_08_140100_harden_admission_document_checklist_ledgers.php',
    '2026_10_08_140110_add_clearance_receipt_predecessor.php',
    '2026_10_17_150000_add_pre_application_support_to_applicant_office_inquiries.php',
];
sort($migrationFiles, SORT_STRING);
$migrationCommand = [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'];
foreach ($migrationFiles as $migrationFile) {
    $migrationPath = 'database/migrations/'.$migrationFile;
    if (! is_file(dirname(__DIR__, 2).'/'.$migrationPath)) {
        throw new RuntimeException('Admission checklist migration manifest is missing '.$migrationPath.'.');
    }
    $migrationCommand[] = '--path='.$migrationPath;
}
$migration = new Process($migrationCommand, dirname(__DIR__, 2));
$migration->setTimeout(900);
$migration->run(static function (string $type, string $buffer): void {
    fwrite(STDERR, $buffer);
});
if (! $migration->isSuccessful()) {
    throw new RuntimeException('Isolated admission checklist schema migration failed: '.$migration->getErrorOutput());
}

// This bootstrap app only configures the schema. Restore PHPUnit's error-handler baseline
// before TestCase boots its own fresh Laravel application.
HandleExceptions::flushState();
