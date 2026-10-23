<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_template_authoring_draft_identity_guard() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                            RAISE EXCEPTION 'New OMR authoring drafts require an institution owner, user owner and provenance.';
                        END IF;
                    ELSIF OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL THEN
                        IF OLD.compiled_version_id IS NULL AND OLD.template_id IS NULL
                            AND NEW.institution_id IS NOT NULL AND NEW.is_synthetic IS NOT NULL AND NEW.owner_user_id IS NOT NULL THEN
                            -- Permit initial classification of uncompiled in-progress drafts
                        ELSE
                            RAISE EXCEPTION 'Unclassified legacy OMR authoring drafts cannot be updated.';
                        END IF;
                    ELSIF NEW.institution_id IS DISTINCT FROM OLD.institution_id
                        OR NEW.is_synthetic IS DISTINCT FROM OLD.is_synthetic
                        OR NEW.owner_user_id IS DISTINCT FROM OLD.owner_user_id THEN
                        RAISE EXCEPTION 'OMR authoring draft ownership and provenance are immutable.';
                    END IF;

                    IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                        RAISE EXCEPTION 'OMR authoring drafts require classified ownership and provenance.';
                    END IF;
                    IF NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1
                        FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ) THEN
                        RAISE EXCEPTION 'OMR authoring draft template must match its institution ownership and provenance.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_update_guard;
                CREATE TRIGGER omr_template_authoring_drafts_identity_update_guard
                BEFORE UPDATE ON omr_template_authoring_drafts
                WHEN (OLD.compiled_version_id IS NOT NULL OR OLD.template_id IS NOT NULL)
                    AND (OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL)
                BEGIN
                    SELECT RAISE(ABORT, 'Unclassified legacy OMR authoring drafts cannot be updated.');
                END;
            SQL);
        }

        $institution = DB::table('institutions')->where('id', 1)->first()
            ?? DB::table('institutions')->where('code', 'SYN-INST-001')->first();
        $institutionId = $institution ? (int) $institution->id : 1;

        DB::table('omr_template_authoring_drafts')
            ->where('id', 7)
            ->whereNull('compiled_version_id')
            ->whereNull('template_id')
            ->whereNull('institution_id')
            ->update([
                'institution_id' => $institutionId,
                'is_synthetic' => false,
                'owner_user_id' => 5,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('omr_template_authoring_drafts')
            ->where('id', 7)
            ->whereNull('compiled_version_id')
            ->update([
                'institution_id' => null,
                'is_synthetic' => null,
            ]);

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_template_authoring_draft_identity_guard() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                            RAISE EXCEPTION 'New OMR authoring drafts require an institution owner, user owner and provenance.';
                        END IF;
                    ELSIF OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL THEN
                        RAISE EXCEPTION 'Unclassified legacy OMR authoring drafts cannot be updated.';
                    ELSIF NEW.institution_id IS DISTINCT FROM OLD.institution_id
                        OR NEW.is_synthetic IS DISTINCT FROM OLD.is_synthetic
                        OR NEW.owner_user_id IS DISTINCT FROM OLD.owner_user_id THEN
                        RAISE EXCEPTION 'OMR authoring draft ownership and provenance are immutable.';
                    END IF;

                    IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                        RAISE EXCEPTION 'OMR authoring drafts require classified ownership and provenance.';
                    END IF;
                    IF NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1
                        FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ) THEN
                        RAISE EXCEPTION 'OMR authoring draft template must match its institution ownership and provenance.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_update_guard;
                CREATE TRIGGER omr_template_authoring_drafts_identity_update_guard
                BEFORE UPDATE ON omr_template_authoring_drafts
                WHEN OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL
                    OR NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL
                    OR NEW.institution_id IS NOT OLD.institution_id
                    OR NEW.is_synthetic IS NOT OLD.is_synthetic
                    OR NEW.owner_user_id IS NOT OLD.owner_user_id
                    OR (NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ))
                BEGIN
                    SELECT RAISE(ABORT, 'OMR authoring draft ownership and provenance are immutable; unclassified legacy drafts cannot be updated.');
                END;
            SQL);
        }
    }
};
