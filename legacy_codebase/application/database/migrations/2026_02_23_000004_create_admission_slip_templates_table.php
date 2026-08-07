<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_slip_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('mode', 10)->default('html');
            $table->string('paper_size', 20)->default('a4');
            $table->string('orientation', 10)->default('portrait');
            $table->string('logical_unit', 20)->default('full');
            $table->longText('content');
            $table->string('docx_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_slip_templates');
    }
};
