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
        Schema::create('admission_attempt_requirement_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->string('applicant_track', 32);
            $table->unsignedInteger('catalog_version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->foreignId('initialized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('initialized_at')->useCurrent();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'is_current']);
        });

        Schema::create('admission_attempt_requirement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_set_id')->constrained('admission_attempt_requirement_sets')->cascadeOnDelete();
            $table->foreignId('intake_requirement_id')->nullable()->constrained('intake_requirements')->nullOnDelete();
            $table->string('code', 64);
            $table->string('label', 255);
            $table->string('item_kind', 32)->default('document');
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['requirement_set_id', 'sort_order']);
        });

        Schema::create('admission_attempt_document_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('requirement_item_id')->constrained('admission_attempt_requirement_items')->cascadeOnDelete();
            $table->string('status', 32);
            $table->foreignId('verified_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at');
            $table->text('remarks')->nullable();
            $table->foreignId('predecessor_id')->nullable()->constrained('admission_attempt_document_verifications')->nullOnDelete();
            $table->string('idempotency_key', 128)->nullable();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'requirement_item_id']);
            $table->index(['idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_attempt_document_verifications');
        Schema::dropIfExists('admission_attempt_requirement_items');
        Schema::dropIfExists('admission_attempt_requirement_sets');
    }
};
