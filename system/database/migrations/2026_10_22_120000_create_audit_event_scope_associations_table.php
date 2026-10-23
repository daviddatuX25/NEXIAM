<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_event_scope_associations', function (Blueprint $table) {
            $table->id();
            $table->uuid('audit_event_id');
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('office', 64)->nullable();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->string('resolution_evidence', 64);
            $table->timestamps();

            $table->foreign('audit_event_id')->references('id')->on('audit_events')->cascadeOnDelete();
            $table->index(['institution_id', 'audit_event_id'], 'idx_audit_assoc_lookup');
            $table->index('audit_event_id', 'idx_audit_assoc_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_event_scope_associations');
    }
};
