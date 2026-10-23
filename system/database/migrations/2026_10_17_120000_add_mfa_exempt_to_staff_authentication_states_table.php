<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_authentication_states') || Schema::hasColumn('staff_authentication_states', 'mfa_exempt')) {
            return;
        }

        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->boolean('mfa_exempt')->default(false)->after('mfa_required');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_authentication_states') || ! Schema::hasColumn('staff_authentication_states', 'mfa_exempt')) {
            return;
        }

        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->dropColumn('mfa_exempt');
        });
    }
};
