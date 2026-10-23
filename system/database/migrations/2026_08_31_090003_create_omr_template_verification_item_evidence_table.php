<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_verification_item_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('capture_id')->constrained('omr_template_verification_captures')->restrictOnDelete();
            $table->string('group_type', 24)->default('aptitude_area');
            $table->string('area_key', 64)->nullable();
            $table->unsignedSmallInteger('area_position')->nullable();
            $table->unsignedSmallInteger('question_no')->nullable();
            $table->string('item_id', 128);
            $table->json('options')->nullable();
            $table->string('expected', 32)->nullable();
            $table->string('detected', 32)->nullable();
            $table->string('state', 32)->nullable();
            $table->json('means')->nullable();
            $table->decimal('delta', 10, 6)->nullable();
            $table->string('status', 32)->nullable();
            $table->foreignId('crop_asset_id')->nullable()->constrained('omr_template_assets')->restrictOnDelete();
            $table->char('crop_asset_hash', 64)->nullable();
            $table->json('crop_rect')->nullable();
            $table->string('evidence_version', 16)->default('v1');
            $table->char('evidence_hash', 64)->nullable();
            $table->unsignedBigInteger('row_version')->default(0);
            $table->timestamps();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_item_evidence
                    ADD CONSTRAINT omr_verification_item_evidence_group_check
                        CHECK (group_type IN ('aptitude_area', 'identity', 'calibration')),
                    ADD CONSTRAINT omr_verification_item_evidence_area_position_check
                        CHECK (area_position IS NULL OR area_position > 0),
                    ADD CONSTRAINT omr_verification_item_evidence_question_no_check
                        CHECK (question_no IS NULL OR question_no > 0),
                    ADD CONSTRAINT omr_verification_item_evidence_row_version_check
                        CHECK (row_version >= 0)
            SQL);
        } elseif ($driver === 'sqlite') {
            // SQLite has no PostgreSQL CHECK DDL; model guards provide parity.
        } else {
            throw new RuntimeException("OMR item evidence requires pgsql or sqlite; got {$driver}.");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_verification_item_evidence');
    }
};
