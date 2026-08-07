<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 20)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->date('birthdate');
            $table->unsignedTinyInteger('age');
            $table->enum('sex', ['male', 'female']);
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('address_line')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->foreignId('course_preference_1')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_preference_2')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('course_preference_3')->constrained('courses')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('last_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
