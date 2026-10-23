<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->resolveDuplicateAttemptNumbers();
        Schema::table('omr_template_verification_methods', function (Blueprint $table): void {
            $table->index(
                ['run_id', 'method', 'method_state'],
                'omr_verification_methods_run_method_state_idx',
            );
        });

        Schema::table('omr_template_verification_method_pages', function (Blueprint $table): void {
            $table->index(
                ['method_id', 'page_no', 'page_state'],
                'omr_verification_method_pages_method_page_state_idx',
            );
            $table->index(
                ['selected_attempt_id'],
                'omr_verification_method_pages_selected_attempt_idx',
            );
        });

        Schema::table('omr_template_verification_captures', function (Blueprint $table): void {
            $table->unique(
                ['run_id', 'method', 'page_no', 'attempt_no'],
                'omr_verification_captures_run_method_page_attempt_unique',
            );
            $table->index(
                ['run_id', 'method', 'attempt_state', 'created_at', 'id'],
                'omr_verification_captures_run_method_state_created_idx',
            );
        });

        Schema::table('omr_template_verification_item_evidence', function (Blueprint $table): void {
            $table->unique(
                ['capture_id', 'item_id'],
                'omr_verification_item_evidence_capture_item_unique',
            );
            $table->index(
                ['capture_id', 'group_type', 'area_position', 'question_no', 'item_id'],
                'omr_verification_item_evidence_capture_group_order_idx',
            );
            $table->index(
                ['capture_id', 'state', 'area_position', 'question_no', 'item_id'],
                'omr_verification_item_evidence_capture_state_order_idx',
            );
            $table->index(
                ['capture_id', 'evidence_version', 'evidence_hash'],
                'omr_verification_item_evidence_capture_hash_idx',
            );
        });

        Schema::table('omr_template_assets', function (Blueprint $table): void {
            $table->index(['sha256'], 'omr_template_assets_sha256_idx');
        });
    }

    private function resolveDuplicateAttemptNumbers(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException("OMR verification indexes require pgsql or sqlite; got {$driver}.");
        }

        $nextByGroup = [];
        foreach (DB::table('omr_template_verification_captures')
            ->whereNotNull('method')
            ->where('page_no', '>', 0)
            ->orderBy('id')
            ->cursor() as $capture) {
            if (! in_array((string) $capture->method, ['pre_shaded', 'hand_shaded'], true)) {
                continue;
            }
            $group = implode(':', [(int) $capture->run_id, (string) $capture->method, (int) $capture->page_no]);
            $attemptNo = ($nextByGroup[$group] ?? 0) + 1;
            $nextByGroup[$group] = $attemptNo;
            if ((int) $capture->attempt_no === $attemptNo) {
                continue;
            }
            DB::table('omr_template_verification_captures')
                ->where('id', $capture->id)
                ->update(['attempt_no' => $attemptNo]);
        }
    }

    public function down(): void
    {
        Schema::table('omr_template_assets', function (Blueprint $table): void {
            $table->dropIndex('omr_template_assets_sha256_idx');
        });

        Schema::table('omr_template_verification_item_evidence', function (Blueprint $table): void {
            $table->dropIndex('omr_verification_item_evidence_capture_hash_idx');
            $table->dropIndex('omr_verification_item_evidence_capture_state_order_idx');
            $table->dropIndex('omr_verification_item_evidence_capture_group_order_idx');
            $table->dropUnique('omr_verification_item_evidence_capture_item_unique');
        });

        Schema::table('omr_template_verification_captures', function (Blueprint $table): void {
            $table->dropIndex('omr_verification_captures_run_method_state_created_idx');
            $table->dropUnique('omr_verification_captures_run_method_page_attempt_unique');
        });

        Schema::table('omr_template_verification_method_pages', function (Blueprint $table): void {
            $table->dropIndex('omr_verification_method_pages_selected_attempt_idx');
            $table->dropIndex('omr_verification_method_pages_method_page_state_idx');
        });

        Schema::table('omr_template_verification_methods', function (Blueprint $table): void {
            $table->dropIndex('omr_verification_methods_run_method_state_idx');
        });
    }
};
