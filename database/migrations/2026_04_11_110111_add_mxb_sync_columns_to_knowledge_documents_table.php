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
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->string('mxb_file_id')->nullable()->after('is_active');
            $table->enum('mxb_sync_status', ['pending', 'indexed', 'failed'])->default('pending')->after('mxb_file_id');
            $table->timestamp('mxb_synced_at')->nullable()->after('mxb_sync_status');
            $table->index('mxb_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropIndex(['mxb_sync_status']);
            $table->dropColumn(['mxb_file_id', 'mxb_sync_status', 'mxb_synced_at']);
        });
    }
};
