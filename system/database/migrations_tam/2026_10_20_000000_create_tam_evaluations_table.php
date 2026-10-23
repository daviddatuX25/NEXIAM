<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'tam';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tam')->create('tam_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('evaluator_role')->default('First-Year College Student');
            $table->boolean('video_watched_confirmed')->default(true);
            $table->date('evaluation_date')->nullable();
            $table->string('evaluator_name')->nullable();
            $table->string('department')->nullable(); // CAS, CBME, CTE
            $table->string('academic_program')->nullable();

            // Perceived Usefulness (STU-PU)
            $table->unsignedTinyInteger('stu_pu_01')->nullable();
            $table->unsignedTinyInteger('stu_pu_02')->nullable();
            $table->unsignedTinyInteger('stu_pu_03')->nullable();
            $table->unsignedTinyInteger('stu_pu_04')->nullable();
            $table->unsignedTinyInteger('stu_pu_05')->nullable();

            // Perceived Ease of Use (STU-PEOU)
            $table->unsignedTinyInteger('stu_peou_01')->nullable();
            $table->unsignedTinyInteger('stu_peou_02')->nullable();
            $table->unsignedTinyInteger('stu_peou_03')->nullable();
            $table->unsignedTinyInteger('stu_peou_04')->nullable();

            // Qualitative Feedback
            $table->text('useful_features')->nullable();
            $table->text('difficulties_encountered')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('evaluator_signature')->nullable();

            // Audit metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tam')->dropIfExists('tam_evaluations');
    }
};
