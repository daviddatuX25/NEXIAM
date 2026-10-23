<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_attempt_requirement_internal_notes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('requirement_item_id')->constrained('admission_attempt_requirement_items')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users');
            $table->timestamp('recorded_at');
            $table->text('internal_remarks')->nullable();
            $table->foreignId('predecessor_id')->nullable()->constrained('admission_attempt_requirement_internal_notes')->nullOnDelete();
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->index(['admission_attempt_id', 'requirement_item_id', 'id'], 'adm_req_internal_notes_attempt_item_idx');
            $table->unique(['admission_attempt_id', 'idempotency_key'], 'adm_req_internal_notes_attempt_idemp_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_attempt_requirement_internal_notes');
    }
};
