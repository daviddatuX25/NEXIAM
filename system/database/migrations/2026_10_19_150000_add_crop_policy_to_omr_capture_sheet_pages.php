<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->string('crop_policy', 32)->default('auto_document_quad')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('The persisted OMR crop policy is immutable; this migration is forward-only.');
    }
};
