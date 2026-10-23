<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('report_templates', 'grain')) {
                $table->string('grain', 40)->nullable()->after('surface_key');
            }

            if (! Schema::hasColumn('report_templates', 'mounted_slots')) {
                $table->json('mounted_slots')->nullable()->after('grain');
            }

            if (! Schema::hasColumn('report_templates', 'custom_group')) {
                $table->string('custom_group', 120)->nullable()->after('mounted_slots');
            }
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            $columns = array_values(array_filter(
                ['grain', 'mounted_slots', 'custom_group'],
                static fn (string $column): bool => Schema::hasColumn('report_templates', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
