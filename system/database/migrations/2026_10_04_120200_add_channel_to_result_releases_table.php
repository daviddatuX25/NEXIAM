<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_releases', function (Blueprint $table): void {
            $table->string('channel', 32)->nullable()->after('mode');
            $table->foreignId('handover_counselor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('physical_handover_at')->nullable();
            $table->foreignId('consultation_decision_id')->nullable()->constrained('consultation_decisions')->nullOnDelete();
            $table->foreignId('print_receipt_id')->nullable()->constrained('result_document_print_receipts')->nullOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX result_releases_current_channel_unique ON result_releases (admission_attempt_id, result_document_id, channel) WHERE is_current = true AND channel IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS result_releases_current_channel_unique');

        Schema::table('result_releases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('handover_counselor_user_id');
            $table->dropConstrainedForeignId('consultation_decision_id');
            $table->dropConstrainedForeignId('print_receipt_id');
            $table->dropColumn(['channel', 'physical_handover_at']);
        });
    }
};
