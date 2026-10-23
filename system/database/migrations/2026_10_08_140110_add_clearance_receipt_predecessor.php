<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_clearance_receipts', function (Blueprint $table): void {
            $table->foreignId('predecessor_receipt_id')
                ->nullable()
                ->constrained('admission_clearance_receipts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_clearance_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('predecessor_receipt_id');
        });
    }
};
