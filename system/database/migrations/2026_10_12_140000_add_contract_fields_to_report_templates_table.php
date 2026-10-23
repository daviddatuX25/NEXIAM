<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->json('selected_leaves')->nullable();
            $table->integer('field_policy_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('report_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'institution_id',
                'selected_leaves',
                'field_policy_version',
            ]);
        });
    }
};
