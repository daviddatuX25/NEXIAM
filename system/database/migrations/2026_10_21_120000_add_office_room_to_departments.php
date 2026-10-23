<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('office_room_id')->nullable()->after('dean_user_id')
                ->constrained('rooms')->nullOnDelete();
            $table->index(['institution_id', 'office_room_id'], 'departments_institution_office_room_index');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropIndex('departments_institution_office_room_index');
            $table->dropConstrainedForeignId('office_room_id');
        });
    }
};
