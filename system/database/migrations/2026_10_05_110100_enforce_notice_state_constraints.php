<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('content_schema_version', 32)->default('notice-content-1')->change();
                $table->string('email_renderer_version', 32)->default('legacy')->change();
            });
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communication_template_drafts ADD CONSTRAINT notice_draft_generations CHECK (lock_version >= 1 AND stage BETWEEN 1 AND 3)');
            DB::statement('ALTER TABLE communication_templates ADD CONSTRAINT notice_family_generation CHECK (family_revision >= 1)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communication_template_drafts DROP CONSTRAINT notice_draft_generations');
            DB::statement('ALTER TABLE communication_templates DROP CONSTRAINT notice_family_generation');
        }
    }
};
