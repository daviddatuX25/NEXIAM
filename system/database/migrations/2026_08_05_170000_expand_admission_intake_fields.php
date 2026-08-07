<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promote full public intake fields from legacy Apply form (REWRITE columns,
 * not opaque payload-only). Course prefs map to Program codes (stable), not legacy course ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table) {
            $table->string('last_school_enrolled')->nullable()->after('applicant_type');
            $table->string('strand')->nullable()->after('last_school_enrolled');
            $table->decimal('gwa', 5, 2)->nullable()->after('strand');
            $table->string('address_line')->nullable()->after('contact_phone');
            $table->string('city', 120)->nullable()->after('address_line');
            $table->string('province', 120)->nullable()->after('city');
            $table->string('zip_code', 32)->nullable()->after('province');
            $table->string('program_preference_1_code', 32)->nullable()->after('preferred_program_code');
            $table->string('program_preference_2_code', 32)->nullable()->after('program_preference_1_code');
            $table->string('program_preference_3_code', 32)->nullable()->after('program_preference_2_code');
        });
    }

    public function down(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'last_school_enrolled',
                'strand',
                'gwa',
                'address_line',
                'city',
                'province',
                'zip_code',
                'program_preference_1_code',
                'program_preference_2_code',
                'program_preference_3_code',
            ]);
        });
    }
};
