<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\DocumentTemplate\Import\DocxEmailImporter;
use App\DocumentTemplate\Import\DocxPackageInspectionException;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateState;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReviewCommunicationTemplateImport
{
    public function __construct(
        private readonly CommunicationTemplateMutation $mutation,
        private readonly CommunicationTemplateState $state,
        private readonly StoreCommunicationTemplateDocument $store,
    ) {}

    public function handle(CommunicationTemplate $template, User $actor, array $data): array
    {
        return $this->mutation->run($template, $data, $actor, 'import_reviewed', 200, function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($actor, $data): array {
            abort_unless($draft !== null && is_array($draft->import_provenance), 422, 'No current import report to review.');
            $provenance = $draft->import_provenance;
            foreach (['report_id', 'report_sha256'] as $key) {
                abort_unless(is_string($data[$key] ?? null) && hash_equals((string) $provenance[$key], $data[$key]), 422, 'Import report binding is stale.');
            }
            $source = $this->store->verified($locked, $provenance['source_asset_id'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            $report = $this->store->verified($locked, $provenance['report_id'], 'application/json');
            abort_unless(hash_equals($source->checksum, $provenance['source_sha256']) && hash_equals($report->checksum, $provenance['report_sha256']), 422, 'Import source or report changed.');
            $storedReport = json_decode(Storage::disk('local')->get($report->storage_path), true, 64, JSON_THROW_ON_ERROR);
            abort_unless(is_array($storedReport) && ($storedReport['source_sha256'] ?? '') === $source->checksum
                && ($storedReport['converter_version'] ?? '') === $provenance['converter_version']
                && CommunicationTemplatePayload::hash($storedReport['findings'] ?? null) === CommunicationTemplatePayload::hash($provenance['findings']), 422, 'Persisted report provenance is inconsistent.');
            $emailDigest = CommunicationTemplateState::emailDigest($draft->email_blueprint ?? []);
            abort_unless(is_string($data['email_blueprint_sha256'] ?? null) && hash_equals($emailDigest, $data['email_blueprint_sha256']), 422, 'Email review binding is stale.');
            try {
                DocxEmailImporter::assertTokens(DocxEmailImporter::authoredText($draft->email_blueprint ?? []), CommunicationTemplateState::tokens($locked), ImportCommunicationTemplateDocument::requiredTokens($locked));
            } catch (DocxPackageInspectionException $exception) {
                throw ValidationException::withMessages(['email_blueprint' => $exception->getMessage()]);
            }
            $this->state->validate($locked, CommunicationTemplateState::state($draft));
            $resolutions = $data['resolutions'] ?? [];
            abort_unless(is_array($resolutions), 422, 'Finding resolutions must be structured.');
            self::assertResolutions($provenance, $draft->email_blueprint, $resolutions);
            $provenance['review_acknowledgment'] = ['actor_id' => (string) $actor->id, 'acknowledged_at' => now()->toIso8601String(),
                'report_sha256' => $report->checksum, 'email_blueprint_sha256' => $emailDigest, 'resolutions' => $resolutions];
            $draft = $this->state->persist($locked, $draft, CommunicationTemplateState::state($draft), $draft->stage, $actor, $provenance);

            return ['draft' => CommunicationTemplateState::draft($draft), 'content_sha256' => $draft->content_sha256];
        });
    }

    public static function assertResolutions(array $provenance, array $blueprint, array $resolutions): void
    {
        $findingIds = array_column($provenance['findings'], 'id');
        abort_unless(array_diff(array_keys($resolutions), $findingIds) === [] && array_diff($findingIds, array_keys($resolutions)) === [], 422, 'Resolve exactly the current report findings.');
        foreach ($provenance['findings'] as $finding) {
            abort_if(in_array($finding['severity'], ['blocker', 'security', 'blocking'], true)
                || in_array($finding['kind'], ['security', 'security_violation', 'unknown_token', 'malformed_token', 'missing_required_token'], true), 422, 'Blocking findings cannot be acknowledged.');
            $resolution = $resolutions[$finding['id']];
            abort_unless(is_array($resolution), 422, 'Caller labels cannot resolve import findings.');
            if ($finding['disposition'] === 'requires_correction' || $finding['severity'] === 'critical') {
                abort_unless(($resolution['disposition'] ?? '') === 'corrected' && is_array($resolution['block_paths'] ?? null)
                    && $resolution['block_paths'] !== [] && array_diff(array_keys($resolution), ['disposition', 'block_paths']) === [], 422, 'Critical omissions require a corrected email block.');
                $correctedText = '';
                foreach ($resolution['block_paths'] as $path) {
                    $block = self::block($blueprint, $path);
                    $prior = self::block($provenance['candidate_email_blueprint'] ?? [], $path, false);
                    $text = DocxEmailImporter::authoredText($block);
                    abort_unless(trim($text) !== '' && CommunicationTemplateState::emailDigest($block) !== CommunicationTemplateState::emailDigest($prior), 422, 'Correction must reference changed meaningful email content.');
                    $correctedText .= $text;
                }
                $sourceText = self::normalize($finding['source_text'] ?? '');
                abort_unless($sourceText === '' || str_contains(self::normalize($correctedText), $sourceText), 422, 'Corrected blocks must restore omitted source information.');
            } else {
                abort_unless($resolution === ['disposition' => 'acknowledged'], 422, 'Benign adaptation requires explicit acknowledgment.');
            }
        }
    }

    private static function block(array $blueprint, mixed $path, bool $required = true): array
    {
        abort_unless(is_string($path) && preg_match('/^blocks\.(0|[1-9][0-9]*)$/', $path, $match), 422, 'Correction must identify a top-level email block.');
        $block = $blueprint['blocks'][(int) $match[1]] ?? null;
        abort_if($required && ! is_array($block), 422, 'Correction references a missing email block.');

        return is_array($block) ? $block : [];
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
