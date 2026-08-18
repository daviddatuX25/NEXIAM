<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versioned result-sheet template authority (legacy parity: html/docx modes,
 * paper/orientation/logical unit, watermark, single-active). Uploaded Word/ODT
 * documents live on private disk; only the checksummed path is stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_sheet_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('mode', 16)->default('html'); // html | docx
            $table->string('paper_size', 16)->default('a4'); // a4 | legal | letter
            $table->string('orientation', 16)->default('portrait'); // portrait | landscape
            $table->string('logical_unit', 16)->default('full'); // full | half_a4 | half_legal | half_letter
            $table->longText('content')->nullable(); // HTML template body
            $table->string('document_path')->nullable(); // private disk, result-sheet-templates/{id}.{ext}
            $table->string('document_checksum', 64)->nullable(); // sha256 of uploaded document
            $table->string('document_extension', 8)->nullable(); // docx | odt
            $table->string('watermark_text', 50)->nullable();
            $table->unsignedBigInteger('rating_scale_id')->nullable(); // reserved (ratings come from converted_score)
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('result_releases', function (Blueprint $table) {
            $table->timestamp('result_printed_at')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->dropColumn('result_printed_at');
        });

        Schema::dropIfExists('result_sheet_templates');
    }
};
