<?php

namespace App\Ingestion\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Models\Ingestion\ImportBatch;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CommitMultiSessionIngestionBatch
{
    public function __construct(private readonly ResumeGuidanceIngestionBatch $resumeGuidance) {}

    /**
     * @param  list<int>  $selectedRowIds
     */
    public function handle(
        ImportBatch $batch,
        array $selectedRowIds,
        User $actor,
        CapabilityAuthorizer $authorizer,
    ): ImportBatch {
        if ($batch->import_type !== ImportBatch::TYPE_MULTI_SESSION) {
            throw new \DomainException('Only multi-session batches can resume through this action.');
        }
        if ($selectedRowIds !== []) {
            throw ValidationException::withMessages(['selected_row_ids' => 'Guidance resumes the full set of Registrar-consented rows.']);
        }

        return $this->resumeGuidance->handle($batch, $actor, $authorizer);
    }
}
