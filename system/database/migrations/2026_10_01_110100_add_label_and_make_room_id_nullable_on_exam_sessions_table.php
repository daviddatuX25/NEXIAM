<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('code');
            $table->foreignId('room_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropColumn('label');
            $table->foreignId('room_id')->nullable(false)->change();
        });
    }
};
