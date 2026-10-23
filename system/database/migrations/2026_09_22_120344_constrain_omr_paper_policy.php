<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
                $table->string('registration_mode', 24)->nullable(false)->change();
                $table->string('qr_mode', 24)->nullable(false)->change();
                $table->unsignedSmallInteger('requested_page_count')->nullable(false)->change();
            });
            Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
                $table->string('registration_mode', 24)->nullable(false)->change();
                $table->string('qr_mode', 24)->nullable(false)->change();
                $table->unsignedSmallInteger('page_count')->nullable(false)->change();
            });

            return;
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException("OMR paper-policy constraints require PostgreSQL; got {$driver}.");
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_template_authoring_drafts
                ALTER COLUMN registration_mode SET NOT NULL,
                ALTER COLUMN qr_mode SET NOT NULL,
                ALTER COLUMN requested_page_count SET NOT NULL,
                ADD CONSTRAINT omr_authoring_drafts_registration_mode_check
                    CHECK (registration_mode IN ('fiducial_anchored', 'document_bound')),
                ADD CONSTRAINT omr_authoring_drafts_qr_mode_check
                    CHECK (qr_mode IN ('qr_signed', 'qr_disabled')),
                ADD CONSTRAINT omr_authoring_drafts_requested_page_count_check
                    CHECK (requested_page_count BETWEEN 1 AND 4)
            SQL);
        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_answer_sheet_template_versions
                ALTER COLUMN registration_mode SET NOT NULL,
                ALTER COLUMN qr_mode SET NOT NULL,
                ALTER COLUMN page_count SET NOT NULL,
                ADD CONSTRAINT omr_template_versions_registration_mode_check
                    CHECK (registration_mode IN ('fiducial_anchored', 'document_bound')),
                ADD CONSTRAINT omr_template_versions_qr_mode_check
                    CHECK (qr_mode IN ('qr_signed', 'qr_disabled')),
                ADD CONSTRAINT omr_template_versions_page_count_check
                    CHECK (page_count BETWEEN 1 AND 4)
            SQL);

        Schema::getConnection()->unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION omr_guard_template_version() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status IN ('active', 'retired') THEN
                        RAISE EXCEPTION 'OMR template versions are append-only after activation';
                    END IF;
                    RETURN OLD;
                END IF;

                IF OLD.status = 'retired' THEN
                    RAISE EXCEPTION 'Retired OMR template versions are immutable';
                END IF;

                IF OLD.status = 'active' AND NOT (
                    NEW.status = 'retired'
                    AND NEW.template_id = OLD.template_id
                    AND NEW.version = OLD.version
                    AND NEW.registration_mode = OLD.registration_mode
                    AND NEW.qr_mode = OLD.qr_mode
                    AND NEW.page_count = OLD.page_count
                    AND NEW.page_width = OLD.page_width
                    AND NEW.page_height = OLD.page_height
                    AND NEW.identity_regions_json::jsonb IS NOT DISTINCT FROM OLD.identity_regions_json::jsonb
                    AND NEW.provider_config_json::jsonb IS NOT DISTINCT FROM OLD.provider_config_json::jsonb
                    AND NEW.config_hash = OLD.config_hash
                    AND NEW.native_bundle_version IS NOT DISTINCT FROM OLD.native_bundle_version
                    AND NEW.native_bundle_json::jsonb IS NOT DISTINCT FROM OLD.native_bundle_json::jsonb
                    AND NEW.native_bundle_hash IS NOT DISTINCT FROM OLD.native_bundle_hash
                    AND NEW.engine_name IS NOT DISTINCT FROM OLD.engine_name
                    AND NEW.engine_commit IS NOT DISTINCT FROM OLD.engine_commit
                    AND NEW.patch_identity IS NOT DISTINCT FROM OLD.patch_identity
                    AND NEW.review_manifest_json::jsonb IS NOT DISTINCT FROM OLD.review_manifest_json::jsonb
                    AND NEW.review_manifest_hash IS NOT DISTINCT FROM OLD.review_manifest_hash
                    AND NEW.activated_by_user_id IS NOT DISTINCT FROM OLD.activated_by_user_id
                    AND NEW.activated_at IS NOT DISTINCT FROM OLD.activated_at
                ) THEN
                    RAISE EXCEPTION 'Active OMR template versions permit only retirement';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
                $table->string('registration_mode', 24)->nullable()->change();
                $table->string('qr_mode', 24)->nullable()->change();
                $table->unsignedSmallInteger('requested_page_count')->nullable()->change();
            });
            Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
                $table->string('registration_mode', 24)->nullable()->change();
                $table->string('qr_mode', 24)->nullable()->change();
                $table->unsignedSmallInteger('page_count')->nullable()->change();
            });

            return;
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_template_authoring_drafts
                DROP CONSTRAINT IF EXISTS omr_authoring_drafts_registration_mode_check,
                DROP CONSTRAINT IF EXISTS omr_authoring_drafts_qr_mode_check,
                DROP CONSTRAINT IF EXISTS omr_authoring_drafts_requested_page_count_check,
                ALTER COLUMN registration_mode DROP NOT NULL,
                ALTER COLUMN qr_mode DROP NOT NULL,
                ALTER COLUMN requested_page_count DROP NOT NULL
            SQL);
        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_answer_sheet_template_versions
                DROP CONSTRAINT IF EXISTS omr_template_versions_registration_mode_check,
                DROP CONSTRAINT IF EXISTS omr_template_versions_qr_mode_check,
                DROP CONSTRAINT IF EXISTS omr_template_versions_page_count_check,
                ALTER COLUMN registration_mode DROP NOT NULL,
                ALTER COLUMN qr_mode DROP NOT NULL,
                ALTER COLUMN page_count DROP NOT NULL
            SQL);
    }
};
