<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insert([
            ['key' => 'admission_slip_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->where('key', 'admission_slip_enabled')->delete();
        DB::table('system_settings')->where('key', 'admission_slip_html_template')->delete();
    }
};
