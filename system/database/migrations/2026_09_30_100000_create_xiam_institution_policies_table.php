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
        Schema::create('xiam_institution_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained('institutions')->cascadeOnDelete();
            $table->boolean('emergency_stop')->default(false);
            $table->string('emergency_stop_reason')->nullable();
            $table->timestamp('emergency_stopped_at')->nullable();
            $table->json('disabled_tools')->nullable();
            $table->integer('policy_version')->default(1);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xiam_institution_policies');
    }
};
