<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('artifact_type', 40);            // blank_sheet | result_sheet
            $table->string('scope_type', 40);               // template_version | release | grading_session
            $table->unsignedBigInteger('scope_id');
            $table->string('status', 24)->default('queued'); // queued|rendering|ready|failed
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_path', 512)->nullable();
            $table->char('file_checksum', 64)->nullable();
            $table->string('safe_error', 64)->nullable();
            $table->unsignedInteger('progress_total')->default(0);
            $table->unsignedInteger('progress_done')->default(0);
            $table->json('per_item_manifest')->nullable();
            $table->json('pinned_input_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'print_jobs_status_idx');
            $table->index(['artifact_type', 'scope_type', 'scope_id'], 'print_jobs_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
