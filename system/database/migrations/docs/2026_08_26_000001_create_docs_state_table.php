<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dev-only docs workspace state (client brief decisions, task flags).
     * Runs against the separate `docs` SQLite connection:
     *   php artisan migrate --database=docs --path=database/migrations/docs
     * Never runs against the system database.
     */
    public function up(): void
    {
        Schema::connection('docs')->create('docs_state', function (Blueprint $table) {
            $table->string('page', 60);
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->primary(['page', 'key']);
        });
    }

    public function down(): void
    {
        Schema::connection('docs')->dropIfExists('docs_state');
    }
};
