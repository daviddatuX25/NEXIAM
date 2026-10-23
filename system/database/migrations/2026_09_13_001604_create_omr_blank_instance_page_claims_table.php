<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_blank_instance_page_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blank_instance_page_id')->constrained('omr_blank_instance_pages')->restrictOnDelete();
            $table->foreignId('capture_sheet_page_id')->unique()->constrained('omr_capture_sheet_pages')->restrictOnDelete();
            $table->foreignId('reproduction_authorization_id')->nullable()->constrained('omr_paper_reproduction_authorizations')->restrictOnDelete();
            $table->string('claim_kind', 24);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['blank_instance_page_id', 'claim_kind'], 'omr_blank_page_claims_page_kind_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_blank_instance_page_claims
                    ADD CONSTRAINT omr_blank_page_claims_kind_check
                        CHECK (claim_kind IN ('original', 'reusable_copy', 'authorized_copy')),
                    ADD CONSTRAINT omr_blank_page_claims_authorization_check
                        CHECK (
                            (claim_kind = 'authorized_copy' AND reproduction_authorization_id IS NOT NULL)
                            OR (claim_kind <> 'authorized_copy' AND reproduction_authorization_id IS NULL)
                        )
            SQL);
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('omr_blank_instance_page_claims');
    }
};
