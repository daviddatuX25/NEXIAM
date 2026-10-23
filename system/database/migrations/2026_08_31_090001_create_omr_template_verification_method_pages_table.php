<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_verification_method_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('method_id')->constrained('omr_template_verification_methods')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->boolean('required')->default(true);
            $table->string('page_state', 24)->default('not_started');
            $table->unsignedBigInteger('page_version')->default(0);
            $table->unsignedBigInteger('selected_attempt_id')->nullable();
            $table->timestamps();

            $table->unique(['method_id', 'page_no'], 'omr_verification_method_pages_method_page_unique');
        });

        // The selected pointer is nullable and assigned only by the coordinator
        // after a passing attempt exists. PostgreSQL keeps this FK deferred so a
        // lock-protected transaction can replace a selected pass atomically.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_method_pages
                    ADD CONSTRAINT omr_verification_method_pages_selected_attempt_fk
                    FOREIGN KEY (selected_attempt_id)
                    REFERENCES omr_template_verification_captures (id)
                    ON DELETE NO ACTION
                    DEFERRABLE INITIALLY DEFERRED
            SQL);
        } elseif ($driver === 'sqlite') {
            Schema::table('omr_template_verification_method_pages', function (Blueprint $table): void {
                $table->foreign('selected_attempt_id', 'omr_verification_method_pages_selected_attempt_fk')
                    ->references('id')
                    ->on('omr_template_verification_captures')
                    ->restrictOnDelete();
            });
        } else {
            throw new RuntimeException("OMR verification method pages require pgsql or sqlite; got {$driver}.");
        }

        if ($driver === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_method_pages
                    ADD CONSTRAINT omr_verification_method_pages_page_no_check
                        CHECK (page_no > 0),
                    ADD CONSTRAINT omr_verification_method_pages_state_check
                        CHECK (page_state IN ('not_started', 'awaiting_capture', 'analyzing', 'needs_review', 'passed', 'failed')),
                    ADD CONSTRAINT omr_verification_method_pages_version_check
                        CHECK (page_version >= 0)
            SQL);
        } elseif ($driver === 'sqlite') {
            // SQLite has no PostgreSQL CHECK DDL; model guards provide parity.
        } else {
            throw new RuntimeException("OMR verification method pages require pgsql or sqlite; got {$driver}.");
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_verification_method_pages');
    }
};
