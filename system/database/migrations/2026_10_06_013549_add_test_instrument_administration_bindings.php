<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->foreignId('test_instrument_version_id')
                ->nullable()
                ->after('academic_cycle_id')
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
        });

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->foreignId('test_instrument_version_id')
                ->nullable()
                ->after('booklet_version_id')
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
        });
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS exam_session_material_snapshots_current_unique');
            Schema::getConnection()->statement('CREATE UNIQUE INDEX exam_session_material_snapshots_current_unique ON exam_session_material_snapshots (exam_session_id) WHERE is_current = 1');
        }

        Schema::create('assessment_administrations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('exam_assignment_id')->constrained('exam_assignments')->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('binding_kind', 32);
            $table->unsignedInteger('binding_revision')->default(1);
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->foreignId('material_snapshot_id')->nullable()->constrained('exam_session_material_snapshots')->restrictOnDelete();
            $table->foreignId('exam_session_seat_id')->nullable()->constrained('exam_session_seats')->restrictOnDelete();
            $table->foreignId('material_set_id')->nullable()->constrained('exam_session_material_sets')->restrictOnDelete();
            $table->foreignId('booklet_set_id')->nullable()->constrained('omr_booklet_sets')->restrictOnDelete();
            $table->json('required_metric_set');
            $table->string('binding_state', 32)->default('ready');
            $table->foreignId('bound_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('bound_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_assignment_id', 'binding_revision'], 'assessment_administrations_assignment_revision_unique');
            $table->index(['exam_assignment_id', 'superseded_at'], 'assessment_administrations_active_idx');
            $table->index(['test_instrument_version_id', 'binding_state'], 'assessment_administrations_version_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_administrations');

        Schema::table('exam_session_material_snapshots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_instrument_version_id');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_instrument_version_id');
        });
    }
};
