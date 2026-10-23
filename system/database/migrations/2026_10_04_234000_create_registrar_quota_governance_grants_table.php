<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrar_quota_governance_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('predecessor_id')->nullable()->constrained('registrar_quota_governance_grants')->restrictOnDelete();
            $table->foreignUuid('operation_id')->constrained('operations')->restrictOnDelete();
            $table->foreignUuid('audit_event_id')->nullable()->constrained('audit_events')->restrictOnDelete();
            $table->json('receipt');
            $table->timestamps();
            $table->unique(['recipient_user_id', 'program_id', 'academic_cycle_id', 'version'], 'rqgg_scope_version_unique');
            $table->index(['recipient_user_id', 'institution_id', 'program_id', 'academic_cycle_id', 'version'], 'rqgg_scope_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrar_quota_governance_grants');
    }
};
