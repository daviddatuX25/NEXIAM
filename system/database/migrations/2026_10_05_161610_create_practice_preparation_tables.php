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
        Schema::create('practice_fixture_sets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->foreignId('institution_id')->constrained();
            $table->foreignId('academic_cycle_id')->constrained();
            $table->string('scenario');
            $table->string('purpose', 16);
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->json('actor_bindings');
            $table->json('material_snapshot');
            $table->text('credentials');
            $table->foreignUuid('predecessor_set_id')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'academic_cycle_id', 'purpose', 'status'], 'practice_sets_context_index');
        });
        Schema::table('practice_fixture_sets', function (Blueprint $table): void {
            $table->foreign('predecessor_set_id')->references('id')->on('practice_fixture_sets');
        });

        Schema::create('practice_preparation_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->foreignUuid('practice_fixture_set_id')->nullable()->constrained('practice_fixture_sets');
            $table->string('purpose', 16);
            $table->string('scenario');
            $table->string('action', 16);
            $table->json('parameters');
            $table->json('prerequisite_snapshot');
            $table->string('prerequisite_digest', 64);
            $table->unsignedInteger('expected_revision')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('status', 16)->default('preview');
            $table->json('result')->nullable();
            $table->timestamps();
            $table->index(['actor_user_id', 'status', 'expires_at'], 'practice_operations_actor_index');
        });

        foreach (['applicant_identities', 'admission_attempts', 'exam_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignUuid('practice_fixture_set_id')->nullable()->index()->constrained('practice_fixture_sets');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['exam_sessions', 'admission_attempts', 'applicant_identities'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('practice_fixture_set_id');
            });
        }
        Schema::dropIfExists('practice_preparation_operations');
        Schema::dropIfExists('practice_fixture_sets');
    }
};
