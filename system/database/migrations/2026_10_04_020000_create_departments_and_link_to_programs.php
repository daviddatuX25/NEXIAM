<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 128);
            $table->foreignId('dean_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'is_active']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('institution_id')->constrained('departments')->nullOnDelete();
        });

        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('institution_id')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
