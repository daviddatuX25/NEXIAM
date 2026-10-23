<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('space_kind', 32)->default('room')->after('name');
            $table->index(['institution_id', 'space_kind', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropIndex(['institution_id', 'space_kind', 'is_active']);
            $table->dropColumn('space_kind');
        });
    }
};
