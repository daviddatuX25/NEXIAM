<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allow applicants to submit with only one course preference (2nd and 3rd optional).
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['course_preference_2']);
            $table->dropForeign(['course_preference_3']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE applications MODIFY course_preference_2 BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE applications MODIFY course_preference_3 BIGINT UNSIGNED NULL');
        } else {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedBigInteger('course_preference_2')->nullable()->change();
                $table->unsignedBigInteger('course_preference_3')->nullable()->change();
            });
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('course_preference_2')->references('id')->on('courses')->nullOnDelete();
            $table->foreign('course_preference_3')->references('id')->on('courses')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['course_preference_2']);
            $table->dropForeign(['course_preference_3']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE applications MODIFY course_preference_2 BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE applications MODIFY course_preference_3 BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedBigInteger('course_preference_2')->nullable(false)->change();
                $table->unsignedBigInteger('course_preference_3')->nullable(false)->change();
            });
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->foreign('course_preference_2')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('course_preference_3')->references('id')->on('courses')->cascadeOnDelete();
        });
    }
};
