<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('avatar_type')->default('initials')->after('office');
            $table->string('avatar_preset')->nullable()->after('avatar_type');
            $table->string('avatar_path')->nullable()->after('avatar_preset');
            $table->string('phone_extension', 30)->nullable()->after('avatar_path');
            $table->string('room_number', 100)->nullable()->after('phone_extension');
            $table->string('consultation_hours', 255)->nullable()->after('room_number');
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'avatar_type',
                'avatar_preset',
                'avatar_path',
                'phone_extension',
                'room_number',
                'consultation_hours',
            ]);
        });
    }
};
