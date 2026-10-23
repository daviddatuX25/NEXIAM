<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\QueueConsultationPortalCopy;
use Illuminate\Console\Command;

final class ReconcileConsultationPortalCopy extends Command
{
    protected $signature = 'results:reconcile-portal-copy {attempt} {--actor= : Authorized Guidance staff user ID}';

    protected $description = 'Queue a bounded repair for one current, confirmed F2F portal result copy';

    public function handle(CapabilityAuthorizer $authorizer, QueueConsultationPortalCopy $queuePortalCopy): int
    {
        $attemptId = filter_var($this->argument('attempt'), FILTER_VALIDATE_INT);
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT);
        if ($attemptId === false || $attemptId < 1 || $actorId === false || $actorId < 1) {
            $this->error('A positive attempt ID and --actor ID are required.');

            return self::FAILURE;
        }

        $attempt = AdmissionAttempt::query()->find($attemptId);
        $actor = User::query()->find($actorId);
        if ($attempt === null || $actor === null) {
            $this->error('The admission attempt or staff actor was not found.');

            return self::FAILURE;
        }
        if (! $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
            'institution_id' => $attempt->institution_id,
            'office' => 'guidance',
        ])->allowed) {
            $this->error('The actor is not authorized for result release at this institution.');

            return self::FAILURE;
        }

        $decision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        if ($decision === null || ! in_array($decision->decision_type, ['approve', 'modify', 'override'], true)) {
            $this->error('The latest consultation decision is not an approved outcome.');

            return self::FAILURE;
        }

        $document = $attempt->current_result_document_id === null
            ? null
            : ResultDocument::query()->find($attempt->current_result_document_id);
        if ($document === null || $document->status !== ResultDocument::STATUS_READY) {
            $this->error('The attempt has no current ready result document.');

            return self::FAILURE;
        }

        $encounter = ConsultationEncounter::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('institution_id', $attempt->institution_id)
            ->where('counselor_user_id', $decision->counselor_user_id)
            ->where('medium', ConsultationEncounter::MEDIUM_F2F)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
            ])
            ->whereNotNull('closed_at')
            ->where('idempotency_key', 'consultation-decision-encounter-'.$decision->idempotency_key)
            ->first();
        if ($encounter === null) {
            $this->error('No authoritative F2F encounter link exists for the latest decision; automatic repair was not guessed.');

            return self::FAILURE;
        }

        $outcome = $queuePortalCopy->handle($attempt, $decision, $encounter, $document, $actor);
        if ($outcome === 'queued') {
            $this->info('Queued the portal-copy issuance or frozen PDF preparation job.');

            return self::SUCCESS;
        }
        if ($outcome === 'already-issued') {
            $this->info('The current result document already has a portal issuance.');

            return self::SUCCESS;
        }
        if ($outcome === 'requires-reprepare') {
            $this->error('The historical result has no verifiable frozen PDF source; re-prepare the result under an authorized workflow before repair.');

            return self::FAILURE;
        }

        $this->error('The current decision, encounter, document, or actor is no longer eligible.');

        return self::FAILURE;
    }
}
