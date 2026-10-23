<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentCommandReceipt;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveTestInstrumentDraft
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly TestInstrumentProfileValidator $validator,
        private readonly TestInstrumentDraftWriter $draftWriter,
        private readonly TestInstrumentCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
        private readonly SaveAssessmentNormDraft $saveNormDraft,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(
        User $actor,
        ?int $instrumentId,
        ?int $versionId,
        ?int $expectedRevision,
        string $requestId,
        array $input,
        bool $deferAudit = false,
    ): TestInstrumentVersion {

        try {
            return DB::transaction(function () use ($actor, $instrumentId, $versionId, $expectedRevision, $requestId, $input, $deferAudit): TestInstrumentVersion {
                $institutionId = $this->access->institutionId($actor);
                $normalized = $this->validator->normalize($input);
                if (array_key_exists('tables', $input) && is_array($input['tables'])) {
                    $normalized['tables'] = $this->normalizeTables($input['tables']);
                }
                $freshActor = User::query()->findOrFail($actor->getKey());
                $existingInstrument = null;
                $draft = null;

                if ($instrumentId !== null) {
                    $existingInstrument = TestInstrument::query()
                        ->where('institution_id', $institutionId)
                        ->whereKey($instrumentId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($versionId === null) {
                        throw ValidationException::withMessages(['version' => 'Choose the exact draft version to update.']);
                    }

                    $draft = TestInstrumentVersion::query()
                        ->where('test_instrument_id', $existingInstrument->id)
                        ->whereKey($versionId)
                        ->lockForUpdate()
                        ->firstOrFail();
                } elseif ($versionId !== null || $expectedRevision !== null) {
                    throw ValidationException::withMessages(['version' => 'A new instrument cannot include an existing version identity.']);
                }

                $aggregate = $instrumentId === null
                    ? 'institution:'.$institutionId.':test-instrument:create'
                    : 'test-instrument:'.$instrumentId.':version:'.$versionId;
                $receipt = $this->receipts->claim(
                    $freshActor,
                    'assessment.test-instrument.save-draft',
                    $aggregate,
                    [
                        'institution_id' => $institutionId,
                        'instrument_id' => $instrumentId,
                        'version_id' => $versionId,
                        'expected_revision' => $expectedRevision,
                        'input' => $normalized,
                    ],
                    $requestId,
                );

                $replay = $this->receipts->result($receipt);
                if ($replay !== null) {
                    return $this->versionForInstitution($institutionId, $replay['instrument_id'], $replay['version_id']);
                }

                if ($instrumentId === null) {
                    $instrument = new TestInstrument;
                    $instrument->institution_id = $institutionId;
                    $instrument->code = $normalized['profile']['code'];
                    $instrument->is_synthetic = false;
                    $instrument->save();

                    $draft = new TestInstrumentVersion;
                    $draft->test_instrument_id = $instrument->id;
                    $draft->version_number = 1;
                    $draft->status = TestInstrumentVersion::STATUS_DRAFT;
                    $draft->lock_version = 1;
                    $draft->created_by_user_id = $freshActor->id;
                } else {
                    if ($draft === null || $draft->status !== TestInstrumentVersion::STATUS_DRAFT) {
                        throw ValidationException::withMessages(['version' => 'Approved versions are immutable. Create a successor draft before editing.']);
                    }
                    if ($expectedRevision === null || (int) $draft->lock_version !== $expectedRevision) {
                        throw ValidationException::withMessages(['expected_revision' => 'This draft changed. Reload it and review the current version before saving.']);
                    }
                    if (strtoupper($normalized['profile']['code']) !== $existingInstrument?->code) {
                        throw ValidationException::withMessages(['profile.code' => 'An instrument code is immutable after creation.']);
                    }
                    $draft->lock_version++;
                }

                $this->draftWriter->save($draft, $normalized);
                if (array_key_exists('tables', $normalized)) {
                    $this->saveNormTables(
                        $freshActor,
                        $institutionId,
                        (int) $draft->test_instrument_id,
                        $normalized['profile'],
                        $normalized['tables'],
                        $requestId,
                        $deferAudit,
                    );
                }
                $version = $draft->fresh(['instrument']) ?? $draft;
                $this->receipts->remember($receipt, $version);
                if (! $deferAudit) {
                    $this->audit->handle('assessment.test_instrument_draft_saved', [
                        'instrument_id' => (int) $version->test_instrument_id,
                        'version_id' => (int) $version->id,
                        'version_number' => (int) $version->version_number,
                        'content_sha256' => $this->validator->digest($normalized),
                    ], (string) $freshActor->id);
                }

                return $version;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505'
                && str_contains($exception->getMessage(), 'test_instruments_institution_code_unique')) {
                throw ValidationException::withMessages(['profile.code' => 'An instrument with this code already exists in this institution.']);
            }

            throw $exception;
        }
    }

    private function versionForInstitution(int $institutionId, int $instrumentId, int $versionId): TestInstrumentVersion
    {
        return TestInstrumentVersion::query()
            ->whereKey($versionId)
            ->whereHas('instrument', static fn ($query) => $query
                ->whereKey($instrumentId)
                ->where('institution_id', $institutionId))
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<array<string, mixed>>  $tables
     */
    private function saveNormTables(
        User $actor,
        int $institutionId,
        int $instrumentId,
        array $profile,
        array $tables,
        string $requestId,
        bool $deferAudit,
    ): void {
        $packageCode = 'INSTRUMENT_'.$instrumentId;
        $package = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->where('code', $packageCode)
            ->with('draftVersion')
            ->first();
        $draft = $package?->draftVersion;

        $this->saveNormDraft->handle(
            $actor,
            $package,
            $draft,
            $draft === null ? null : (int) $draft->lock_version,
            $requestId,
            [
                'package' => [
                    'code' => $packageCode,
                    'display_name' => $profile['display_name'],
                    'publisher_name' => $profile['publisher_name'],
                    'form_code' => $profile['form_code'],
                    'edition' => $profile['edition'],
                    'language' => $profile['language'],
                    'source_reference' => $profile['source_reference'],
                    'permitted_use_reference' => $profile['permitted_use_reference'],
                ],
                'tables' => $tables,
            ],
            $deferAudit,
        );
    }

    /**
     * @param  array<mixed>  $tables
     * @return list<array{metric_code: string, output_scale: string, intervals: array<mixed>}>
     */
    private function normalizeTables(array $tables): array
    {
        $normalized = [];

        if (array_is_list($tables)) {
            foreach ($tables as $table) {
                if (! is_array($table)) {
                    continue;
                }

                $normalized[] = [
                    'metric_code' => (string) ($table['metric_code'] ?? ''),
                    'output_scale' => (string) ($table['output_scale'] ?? 'percentile_rank'),
                    'intervals' => (array) ($table['intervals'] ?? []),
                ];
            }

            return $normalized;
        }

        foreach ($tables as $metricCode => $intervals) {
            if (! is_array($intervals)) {
                continue;
            }

            $normalized[] = [
                'metric_code' => (string) $metricCode,
                'output_scale' => (string) ($intervals['output_scale'] ?? 'percentile_rank'),
                'intervals' => (array) ($intervals['intervals'] ?? $intervals),
            ];
        }

        return $normalized;
    }
}
