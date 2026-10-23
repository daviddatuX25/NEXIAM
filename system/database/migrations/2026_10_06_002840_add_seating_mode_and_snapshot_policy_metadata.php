<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->string('seating_mode', 32)->nullable();
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->string('seating_mode', 32)->nullable();
            $table->unsignedInteger('capacity_limit')->nullable();
            $table->json('required_set_codes')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE exam_sessions
                ADD CONSTRAINT exam_sessions_seating_mode_check
                    CHECK (seating_mode IS NULL OR seating_mode IN ('simple_capacity', 'assigned_seating')),
                ADD CONSTRAINT exam_sessions_simple_mode_arrangement_check
                    CHECK (seating_mode IS NULL OR seating_mode <> 'simple_capacity' OR seating_arrangement_id IS NULL)
        SQL);

        DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT exam_session_material_snapshots_arrangement_shape_check');
        DB::statement(<<<'SQL'
            ALTER TABLE exam_session_material_snapshots
                ADD CONSTRAINT exam_session_material_snapshots_arrangement_shape_check
                    CHECK (
                        (
                            seating_arrangement_id IS NULL
                            AND arrangement_lock_version IS NULL
                            AND arrangement_rows IS NULL
                            AND arrangement_columns IS NULL
                            AND arrangement_booklet_pattern IS NULL
                            AND (
                                (seating_mode IS NULL AND seat_manifest_hash IS NULL)
                                OR (
                                    seating_mode = 'simple_capacity'
                                    AND seat_manifest_hash IS NOT NULL
                                    AND char_length(seat_manifest_hash) = 64
                                )
                            )
                        )
                        OR
                        (
                            seating_arrangement_id IS NOT NULL
                            AND arrangement_lock_version IS NOT NULL
                            AND arrangement_lock_version >= 1
                            AND arrangement_rows IS NOT NULL
                            AND arrangement_rows >= 1
                            AND arrangement_columns IS NOT NULL
                            AND arrangement_columns >= 1
                            AND arrangement_booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')
                            AND seat_manifest_hash IS NOT NULL
                            AND char_length(seat_manifest_hash) = 64
                            AND (seating_mode IS NULL OR seating_mode = 'assigned_seating')
                        )
                        OR
                        (
                            seating_mode = 'simple_capacity'
                            AND seating_arrangement_id IS NULL
                            AND arrangement_lock_version IS NOT NULL
                            AND arrangement_lock_version >= 1
                            AND arrangement_rows IS NOT NULL
                            AND arrangement_rows >= 1
                            AND arrangement_columns IS NOT NULL
                            AND arrangement_columns >= 1
                            AND arrangement_booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')
                            AND seat_manifest_hash IS NOT NULL
                            AND char_length(seat_manifest_hash) = 64
                        )
                    ),
                ADD CONSTRAINT exam_session_material_snapshots_seating_mode_metadata_check
                    CHECK (
                        (
                            seating_mode IS NULL
                            AND capacity_limit IS NULL
                            AND required_set_codes IS NULL
                        )
                        OR
                        (
                            seating_mode = 'simple_capacity'
                            AND seating_arrangement_id IS NULL
                            AND capacity_limit IS NOT NULL
                            AND capacity_limit >= 1
                            AND required_set_codes IS NOT NULL
                            AND jsonb_typeof(required_set_codes::jsonb) = 'array'
                            AND required_set_codes::jsonb = '["A"]'::jsonb
                        )
                        OR
                        (
                            seating_mode = 'assigned_seating'
                            AND seating_arrangement_id IS NOT NULL
                            AND capacity_limit IS NOT NULL
                            AND capacity_limit >= 1
                            AND required_set_codes IS NOT NULL
                            AND jsonb_typeof(required_set_codes::jsonb) = 'array'
                            AND jsonb_array_length(required_set_codes::jsonb) >= 1
                            AND required_set_codes::jsonb <@ '["A", "B", "C", "D"]'::jsonb
                        )
                    )
        SQL);
    }

    public function down(): void
    {
        throw new LogicException('Seating interpretation metadata is forward-only after publication.');
    }
};
