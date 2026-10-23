<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['communication_template_versions', 'communication_template_drafts'] as $table) {
            DB::table($table)->whereNull('email_blueprint')->where('content_schema_version', 'notice-content-2')
                ->update(['content_schema_version' => 'notice-content-1', 'email_renderer_version' => 'legacy']);
        }
    }

    public function down(): void
    {
        // Historical content cannot truthfully be relabelled as a semantic blueprint.
    }
};
