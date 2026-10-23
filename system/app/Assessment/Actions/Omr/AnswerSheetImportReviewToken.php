<?php

namespace App\Assessment\Actions\Omr;

use App\Models\Assessment\AnswerSheetImportOperation;

final class AnswerSheetImportReviewToken
{
    public function forOperation(AnswerSheetImportOperation $operation): string
    {
        return hash('sha256', json_encode([
            'operation_id' => (string) $operation->id,
            'institution_id' => (int) $operation->institution_id,
            'owner_user_id' => (int) $operation->owner_user_id,
            'archive_sha256' => (string) $operation->archive_sha256,
            'ledger_sha256' => $operation->ledger_sha256 === null ? null : (string) $operation->ledger_sha256,
            'report_sha256' => (string) $operation->report_sha256,
            'review_revision' => (int) $operation->review_revision,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
