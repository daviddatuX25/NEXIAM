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
        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->boolean('allow_online_exceptions')->default(false);
            $table->string('normal_method', 32)->default('online')->change();
        });

        DB::table('consultation_policies')
            ->where('online_enabled', true)
            ->update(['normal_method' => 'online']);
        DB::table('consultation_policies')
            ->where('online_enabled', false)
            ->update(['normal_method' => 'f2f']);

        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->dropColumn('online_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->boolean('online_enabled')->default(false);
        });

        DB::table('consultation_policies')
            ->where(function ($query): void {
                $query->where('normal_method', 'online')
                    ->orWhere(function ($query): void {
                        $query->where('normal_method', 'f2f')
                            ->where('allow_online_exceptions', true);
                    });
            })
            ->update(['online_enabled' => true]);

        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->string('normal_method', 32)->default('f2f')->change();
            $table->dropColumn('allow_online_exceptions');
        });
    }
};
