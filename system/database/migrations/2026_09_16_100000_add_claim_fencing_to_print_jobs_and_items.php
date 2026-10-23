<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->uuid('claim_token')->nullable()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('claim_token');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
            $table->unsignedInteger('attempt_count')->default(0)->after('claim_expires_at');

            $table->index(['status', 'claim_expires_at'], 'print_jobs_claim_idx');
        });

        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->uuid('claim_token')->nullable()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('claim_token');
            $table->timestamp('claim_expires_at')->nullable()->after('claimed_at');
            $table->unsignedInteger('attempt_count')->default(0)->after('claim_expires_at');

            $table->index(['status', 'claim_expires_at'], 'print_job_items_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->dropIndex('print_job_items_claim_idx');
            $table->dropColumn(['claim_token', 'claimed_at', 'claim_expires_at', 'attempt_count']);
        });

        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->dropIndex('print_jobs_claim_idx');
            $table->dropColumn(['claim_token', 'claimed_at', 'claim_expires_at', 'attempt_count']);
        });
    }
};
