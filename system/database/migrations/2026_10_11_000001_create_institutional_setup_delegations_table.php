<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institutional_setup_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_cycle_id')->nullable()->constrained('academic_cycles')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->index(['institution_id', 'revoked_at']);
            $table->index(['recipient_id', 'revoked_at']);
        });

        DB::statement('CREATE UNIQUE INDEX uq_active_setup_delegation_recipient ON institutional_setup_delegations (recipient_id) WHERE revoked_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_setup_delegations');
    }
};
