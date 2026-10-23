<?php

namespace App\Release\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;

final class ReleasedResultDocumentQuery
{
    /**
     * Select the exact currently released ready document for a staff projection.
     * Portal copy/artifact availability is intentionally not an authorization gate here.
     *
     * @return array{release: ResultRelease|null, document: ResultDocument|null}
     */
    public function forAttempt(AdmissionAttempt $attempt): array
    {
        if ($attempt->current_result_document_id === null) {
            return ['release' => null, 'document' => null];
        }

        $release = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $attempt->current_result_document_id)
            ->where('is_current', true)
            ->whereNotNull('released_at')
            ->whereNull('unreleased_at')
            ->whereNull('superseded_at')
            ->with('resultDocument')
            ->latest('id')
            ->first();
        $document = $release?->resultDocument;

        if ($release === null || $document === null
            || (int) $document->admission_attempt_id !== (int) $attempt->id
            || (int) $document->id !== (int) $attempt->current_result_document_id
            || $document->status !== ResultDocument::STATUS_READY
            || ($release->channel === 'physical' && $release->physical_handover_at === null)) {
            return ['release' => null, 'document' => null];
        }

        return ['release' => $release, 'document' => $document];
    }
}
