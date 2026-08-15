<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff-authored booklet questions carry no template link until
        // pair activation projects the geometry and binds them.
        Schema::table('omr_question_booklet_questions', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_question_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('omr_question_booklet_questions', function (Blueprint $table): void {
            $table->unsignedBigInteger('template_question_id')->nullable(false)->change();
        });
    }
};
