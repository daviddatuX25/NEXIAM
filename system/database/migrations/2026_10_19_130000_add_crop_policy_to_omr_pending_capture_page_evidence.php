<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_pending_capture_page_evidence', function (Blueprint $table): void {
            $table->string('crop_policy', 32)->default('auto_document_quad');
        });
    }

    public function down(): void
    {
        // Historical capture policy is immutable; deploy a forward fix to retire it.
    }
};
