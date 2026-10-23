<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_office_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_identity_id')->constrained()->restrictOnDelete();
            $table->enum('office', ['registrar', 'guidance']);
            $table->string('subject', 120);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedBigInteger('latest_message_sequence')->default(0);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['applicant_identity_id', 'id'], 'office_inquiries_identity_id_idx');
            $table->index(['institution_id', 'office', 'academic_cycle_id', 'id'], 'office_inquiries_scope_id_idx');
            $table->index('admission_attempt_id', 'office_inquiries_attempt_idx');
        });

        Schema::create('applicant_office_inquiry_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_office_inquiry_id')
                ->constrained('applicant_office_inquiries', indexName: 'office_inquiry_messages_inquiry_fk')
                ->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['applicant_office_inquiry_id', 'sequence'], 'office_inquiry_messages_sequence_unique');
        });

        Schema::create('applicant_office_inquiry_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_office_inquiry_id')
                ->constrained('applicant_office_inquiries', indexName: 'office_inquiry_reads_inquiry_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_sequence')->default(0);
            $table->timestamps();

            $table->unique(['applicant_office_inquiry_id', 'user_id'], 'office_inquiry_reads_user_unique');
        });

        Schema::create('applicant_office_inquiry_demo_actors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->enum('actor_type', ['applicant', 'staff']);
            $table->enum('namespace', ['applicant-office-inquiry-demo'])->default('applicant-office-inquiry-demo');
            $table->timestamps();

            $table->unique('user_id', 'office_inquiry_demo_actors_user_unique');
            $table->index(['institution_id', 'actor_type', 'user_id'], 'office_inquiry_demo_actors_scope_idx');
        });

        $this->installChecks();
    }

    private function installChecks(): void
    {
        $driver = DB::connection()->getDriverName();
        $checks = [
            ['applicant_office_inquiries', 'office_inquiries_sequence_nonnegative', 'latest_message_sequence >= 0', 'NEW.latest_message_sequence >= 0'],
            ['applicant_office_inquiries', 'office_inquiries_subject_length', 'length(subject) <= 120', 'length(NEW.subject) <= 120'],
            ['applicant_office_inquiry_messages', 'office_inquiry_messages_sequence_positive', 'sequence > 0', 'NEW.sequence > 0'],
            ['applicant_office_inquiry_reads', 'office_inquiry_reads_sequence_nonnegative', 'last_read_sequence >= 0', 'NEW.last_read_sequence >= 0'],
        ];

        foreach ($checks as [$table, $name, $expression, $sqliteExpression]) {
            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            } elseif ($driver === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    $trigger = $name.'_'.strtolower($event);
                    DB::statement(
                        "CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} "
                        ."WHEN NOT ({$sqliteExpression}) "
                        ."BEGIN SELECT RAISE(ABORT, '{$name}'); END",
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_office_inquiry_demo_actors');
        Schema::dropIfExists('applicant_office_inquiry_reads');
        Schema::dropIfExists('applicant_office_inquiry_messages');
        Schema::dropIfExists('applicant_office_inquiries');
    }
};
