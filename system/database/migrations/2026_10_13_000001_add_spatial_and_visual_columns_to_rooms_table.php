<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', static function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('capacity');
            $table->decimal('longitude', 11, 7)->nullable()->after('latitude');
            $table->string('building_name', 100)->nullable()->after('longitude');
            $table->string('floor_level', 32)->nullable()->after('building_name');
            $table->string('photo_path', 255)->nullable()->after('floor_level');
            $table->text('directions_hint')->nullable()->after('photo_path');
            $table->index(['institution_id', 'building_name']);
        });
    }

    public function down(): void
    {
        Schema::table('rooms', static function (Blueprint $table): void {
            $table->dropIndex('rooms_institution_id_building_name_index');
            $table->dropColumn([
                'latitude',
                'longitude',
                'building_name',
                'floor_level',
                'photo_path',
                'directions_hint',
            ]);
        });
    }
};
