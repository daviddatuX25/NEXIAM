<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_domains');
    }

    public function down(): void
    {
        // Recreated by existing create_exam_domains_table migration on rollback
    }
};
