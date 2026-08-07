<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_cycles');
    }
};
