<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('short_name', 32)->nullable()->after('name');
            $table->string('campus_name', 255)->nullable()->after('short_name');
            $table->text('address')->nullable()->after('campus_name');
            $table->string('location', 255)->nullable()->after('address');
            $table->string('contact_number', 64)->nullable()->after('location');
            $table->string('email', 255)->nullable()->after('contact_number');
            $table->string('website', 255)->nullable()->after('email');
            $table->string('logo_path', 255)->nullable()->after('website');
            $table->string('logo_mime', 64)->nullable()->after('logo_path');
            $table->unsignedInteger('logo_byte_size')->nullable()->after('logo_mime');
            $table->char('logo_sha256', 64)->nullable()->after('logo_byte_size');
        });

        // Seed initial default values for single-institution anchor 'SYN-INST-001'
        $config = config('public-site.institution', []);
        DB::table('institutions')
            ->where('code', 'SYN-INST-001')
            ->update([
                'short_name' => (string) ($config['short_name'] ?? 'ISPSC'),
                'campus_name' => (string) ($config['campus'] ?? 'Tagudin Campus'),
                'address' => 'Quirino, Tagudin, Ilocos Sur',
                'location' => (string) ($config['location'] ?? 'Tagudin, Ilocos Sur'),
                'contact_number' => '+63 (77) 674-1234',
                'email' => 'admissions.tagudin@ispsc.edu.ph',
                'website' => 'https://ispsc.edu.ph',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropColumn([
                'short_name',
                'campus_name',
                'address',
                'location',
                'contact_number',
                'email',
                'website',
                'logo_path',
                'logo_mime',
                'logo_byte_size',
                'logo_sha256',
            ]);
        });
    }
};
