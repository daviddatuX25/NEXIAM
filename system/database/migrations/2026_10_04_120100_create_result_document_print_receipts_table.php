<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_document_print_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('result_document_id')->constrained('result_documents')->cascadeOnDelete();
            $table->foreignId('confirmed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('confirmed_at');
            $table->foreignId('job_item_id')->nullable()->constrained('print_job_items')->nullOnDelete();
            $table->string('operation', 32);
            $table->text('correction_reason')->nullable();
            $table->foreignId('correction_predecessor_id')
                ->nullable()
                ->constrained('result_document_print_receipts')
                ->nullOnDelete();
            $table->unsignedInteger('evidence_revision')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['admission_attempt_id', 'is_current']);
            $table->index(['result_document_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_document_print_receipts');
    }
};
