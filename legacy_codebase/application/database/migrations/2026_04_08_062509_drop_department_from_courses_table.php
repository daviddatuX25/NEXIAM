<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE courses_temp AS SELECT id, name, code, is_active, created_at, updated_at FROM courses');
            DB::statement('DROP TABLE courses');
            DB::statement('CREATE TABLE courses (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                "name" VARCHAR(255) NOT NULL,
                "code" VARCHAR(20) NOT NULL UNIQUE,
                "is_active" INTEGER NOT NULL DEFAULT 1,
                "created_at" TEXT,
                "updated_at" TEXT
            )');
            DB::statement('INSERT INTO courses ("id", "name", "code", "is_active", "created_at", "updated_at") SELECT "id", "name", "code", "is_active", "created_at", "updated_at" FROM courses_temp');
            DB::statement('DROP TABLE courses_temp');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropForeign('courses_department_id_foreign');
                $table->dropColumn('department_id');
            });
        }

        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained('departments');
        });
    }
};
