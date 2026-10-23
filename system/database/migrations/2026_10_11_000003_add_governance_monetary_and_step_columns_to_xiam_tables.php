<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xiam_usage_reservations', function (Blueprint $table): void {
            $table->string('office', 32)->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('extension_reason', 255)->nullable();
            $table->index(['institution_id', 'office', 'created_at']);
        });

        Schema::table('xiam_institution_policies', function (Blueprint $table): void {
            $table->json('admin_locked_tools')->nullable();
            $table->boolean('applicant_inquiry_enabled')->default(true);
            $table->boolean('applicant_self_booking_enabled')->default(true);
            $table->integer('daily_institution_ask_ceiling')->default(1000);
            $table->integer('daily_applicant_pool_asks')->default(300);
            $table->json('office_ask_allocations')->nullable();
            $table->bigInteger('daily_institution_budget_micro')->default(50000000);
            $table->json('office_budget_allocations_micro')->nullable();
            $table->integer('per_staff_daily_ask_limit')->default(200);
            $table->integer('per_applicant_daily_ask_limit')->default(15);
        });

        Schema::table('xiam_run_steps', function (Blueprint $table): void {
            $table->string('provider_driver', 32)->nullable();
            $table->string('model_id', 128)->nullable();
            $table->unsignedBigInteger('tariff_id')->nullable();
            $table->json('rate_snapshot_json')->nullable();
            $table->integer('cache_read_tokens')->default(0);
            $table->integer('cache_write_tokens')->default(0);
            $table->integer('reasoning_tokens')->default(0);
            $table->bigInteger('step_cost_micro')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('xiam_run_steps', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_driver',
                'model_id',
                'tariff_id',
                'rate_snapshot_json',
                'cache_read_tokens',
                'cache_write_tokens',
                'reasoning_tokens',
                'step_cost_micro',
            ]);
        });

        Schema::table('xiam_institution_policies', function (Blueprint $table): void {
            $table->dropColumn([
                'admin_locked_tools',
                'applicant_inquiry_enabled',
                'applicant_self_booking_enabled',
                'daily_institution_ask_ceiling',
                'daily_applicant_pool_asks',
                'office_ask_allocations',
                'daily_institution_budget_micro',
                'office_budget_allocations_micro',
                'per_staff_daily_ask_limit',
                'per_applicant_daily_ask_limit',
            ]);
        });

        Schema::table('xiam_usage_reservations', function (Blueprint $table): void {
            $table->dropIndex(['institution_id', 'office', 'created_at']);
            $table->dropConstrainedForeignId('granted_by_user_id');
            $table->dropColumn(['office', 'extension_reason']);
        });
    }
};
