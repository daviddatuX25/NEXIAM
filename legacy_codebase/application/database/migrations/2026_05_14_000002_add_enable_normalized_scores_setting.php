<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insert([
            'key' => 'enable_normalized_scores',
            'value' => '0',
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'enable_normalized_scores')->delete();
    }
};
