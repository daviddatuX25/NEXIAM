<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrepareResultDocument
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(AdmissionAttempt $attempt, User $actor, string $templateVersion = 'e2-v1'): ResultDocument
    {
        return DB::transaction(function () use ($attempt, $actor, $templateVersion): ResultDocument {
            $safeName = e($attempt->identity->first_name.' '.$attempt->identity->last_name);
            $body = "<html><body><p>Result for {$safeName}</p><p>Ref: ".e($attempt->reference_code).'</p></body></html>';
            $checksum = hash('sha256', $body);
            $path = 'results/'.$attempt->id.'/'.$checksum.'.html';
            Storage::disk('local')->put($path, $body);

            $document = ResultDocument::query()->create([
                'admission_attempt_id' => $attempt->id,
                'template_version' => $templateVersion,
                'checksum' => $checksum,
                'status' => ResultDocument::STATUS_READY,
                'storage_path' => $path,
                'preview_path' => $path,
            ]);

            $this->recordAuditEvent->handle('release.document_prepared', [
                'document_id' => $document->id,
                'attempt_id' => $attempt->id,
                'checksum' => $checksum,
            ], (string) $actor->id);

            return $document;
        });
    }
}
