<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xiam_proposals', function (Blueprint $table): void {
            $table->string('safety_tier', 16)->default('t1');
            $table->string('confirmation_phrase')->nullable();
            $table->boolean('requires_reauth')->default(false);
            $table->unsignedInteger('safety_policy_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('xiam_proposals', function (Blueprint $table): void {
            $table->dropColumn([
                'safety_tier',
                'confirmation_phrase',
                'requires_reauth',
                'safety_policy_version',
            ]);
        });
    }
};
