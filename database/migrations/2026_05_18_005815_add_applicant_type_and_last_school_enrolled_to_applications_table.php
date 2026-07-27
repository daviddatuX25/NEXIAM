<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('applicant_type', 20)->default('new')->after('sex')
                ->comment('Applicant classification: new or transferee');
            $table->string('last_school_enrolled', 255)->nullable()->after('applicant_type')
                ->comment('Last school attended / school of origin');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['applicant_type', 'last_school_enrolled']);
        });
    }
};
