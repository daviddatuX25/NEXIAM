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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->dropSqliteTriggers();
        }

        Schema::table('applicant_office_inquiries', function (Blueprint $table): void {
            $table->unsignedBigInteger('admission_attempt_id')->nullable()->change();
            $table->unsignedBigInteger('applicant_identity_id')->nullable()->change();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            $table->string('public_reference_code', 24)->nullable()->unique('office_inquiries_public_ref_unique');
            $table->string('guest_token_hash', 64)->nullable();
            $table->unsignedBigInteger('guest_last_read_sequence')->default(0);

            $table->index(
                ['institution_id', 'academic_cycle_id', 'guest_token_hash', 'id'],
                'office_inquiries_guest_token_idx',
            );
        });

        Schema::table('applicant_office_inquiry_messages', function (Blueprint $table): void {
            $table->unsignedBigInteger('sender_user_id')->nullable()->change();
            $table->string('sender_role', 16)->default('applicant');
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE applicant_office_inquiries ADD CONSTRAINT office_inquiries_guest_read_nonnegative CHECK (guest_last_read_sequence >= 0)');
            DB::statement("ALTER TABLE applicant_office_inquiry_messages ADD CONSTRAINT office_inquiry_messages_sender_role_check CHECK (sender_role IN ('applicant', 'staff'))");
        } elseif ($driver === 'sqlite') {
            $this->installSqliteTriggers(includePreApplicationChecks: true);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE applicant_office_inquiries DROP CONSTRAINT IF EXISTS office_inquiries_guest_read_nonnegative');
            DB::statement('ALTER TABLE applicant_office_inquiry_messages DROP CONSTRAINT IF EXISTS office_inquiry_messages_sender_role_check');
        } elseif ($driver === 'sqlite') {
            $this->dropSqliteTriggers();
        }

        Schema::table('applicant_office_inquiry_messages', function (Blueprint $table): void {
            $table->dropColumn('sender_role');
            $table->unsignedBigInteger('sender_user_id')->nullable(false)->change();
        });

        Schema::table('applicant_office_inquiries', function (Blueprint $table): void {
            $table->dropIndex('office_inquiries_guest_token_idx');
            $table->dropUnique('office_inquiries_public_ref_unique');
            $table->dropColumn(['public_reference_code', 'guest_token_hash', 'guest_last_read_sequence']);
            $table->unsignedBigInteger('admission_attempt_id')->nullable(false)->change();
            $table->unsignedBigInteger('applicant_identity_id')->nullable(false)->change();
            $table->unsignedBigInteger('created_by_user_id')->nullable(false)->change();
        });

        if ($driver === 'sqlite') {
            $this->installSqliteTriggers(includePreApplicationChecks: false);
        }
    }

    private function dropSqliteTriggers(): void
    {
        $names = [
            'office_inquiries_sequence_nonnegative',
            'office_inquiries_subject_length',
            'office_inquiries_guest_read_nonnegative',
            'office_inquiry_messages_sequence_positive',
            'office_inquiry_messages_sender_role_check',
        ];

        foreach ($names as $name) {
            foreach (['insert', 'update'] as $event) {
                DB::statement("DROP TRIGGER IF EXISTS {$name}_{$event}");
            }
        }
    }

    private function installSqliteTriggers(bool $includePreApplicationChecks): void
    {
        $checks = [
            ['applicant_office_inquiries', 'office_inquiries_sequence_nonnegative', 'NEW.latest_message_sequence >= 0'],
            ['applicant_office_inquiries', 'office_inquiries_subject_length', 'length(NEW.subject) <= 120'],
            ['applicant_office_inquiry_messages', 'office_inquiry_messages_sequence_positive', 'NEW.sequence > 0'],
        ];

        if ($includePreApplicationChecks) {
            $checks[] = ['applicant_office_inquiries', 'office_inquiries_guest_read_nonnegative', 'NEW.guest_last_read_sequence >= 0'];
            $checks[] = ['applicant_office_inquiry_messages', 'office_inquiry_messages_sender_role_check', "NEW.sender_role IN ('applicant', 'staff')"];
        }

        foreach ($checks as [$table, $name, $sqliteExpression]) {
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
};
