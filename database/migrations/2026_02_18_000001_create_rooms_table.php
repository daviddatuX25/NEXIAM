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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('e.g., "Room 101"');
            $table->string('building', 100)->comment('e.g., "ITBR"');
            $table->string('floor', 20)->nullable()->comment('e.g., "2nd Floor"');
            $table->unsignedSmallInteger('capacity')->comment('Max examinees');
            $table->json('facilities')->nullable()->comment('e.g., {"projector": true, "ac": true}');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['building', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
