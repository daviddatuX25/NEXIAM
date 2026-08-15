<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_records', function (Blueprint $table) {
            $table->string('converted_score', 50)->nullable()->after('score');
        });

        // The encrypted cast (SCV-5) stores AES ciphertext, which cannot live
        // in a decimal column.
        Schema::table('score_records', function (Blueprint $table) {
            $table->string('score', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('score_records', function (Blueprint $table) {
            $table->dropColumn('converted_score');
            $table->decimal('score', 8, 2)->change();
        });
    }
};
