<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('print_jobs');
    }

    public function down(): void
    {
        // The print_jobs table and async PDF job infrastructure
        // have been intentionally removed. No rollback.
    }
};
