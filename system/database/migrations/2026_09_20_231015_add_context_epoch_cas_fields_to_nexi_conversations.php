<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexi_conversations', function (Blueprint $table): void {
            $table->unsignedBigInteger('epoch_version')->default(1);
            $table->string('browser_session_id', 128)->nullable();
            $table->string('route_name', 128)->nullable();
            $table->string('surface_profile_id', 128)->nullable();
            $table->index(
                ['partition_key', 'status', 'epoch_version'],
                'nexi_conversations_epoch_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::table('nexi_conversations', function (Blueprint $table): void {
            $table->dropIndex('nexi_conversations_epoch_lookup');
            $table->dropColumn([
                'epoch_version',
                'browser_session_id',
                'route_name',
                'surface_profile_id',
            ]);
        });
    }
};
