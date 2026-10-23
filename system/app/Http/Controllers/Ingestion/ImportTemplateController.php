<?php

namespace App\Http\Controllers\Ingestion;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Ingestion\Services\ImportTemplateCatalog;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Ingestion\ImportBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportTemplateController extends Controller
{
    public function schema(string $type, Request $request, CapabilityAuthorizer $authorizer, ImportTemplateCatalog $catalog): JsonResponse
    {
        [$institution, $cycle, $kit, $session] = $this->context($type, $request, $authorizer);

        return response()->json($catalog->schema($type, $institution, $cycle, $kit, $session));
    }

    public function export(string $type, Request $request, CapabilityAuthorizer $authorizer, ImportTemplateCatalog $catalog): JsonResponse|StreamedResponse
    {
        [$institution, $cycle, $kit, $session] = $this->context($type, $request, $authorizer);
        $schema = $catalog->schema($type, $institution, $cycle, $kit, $session);
        if (! is_string($request->query('revision')) || ! hash_equals($schema['revision'], $request->query('revision'))) {
            return response()->json([
                'message' => 'The template contract has changed. Refresh the schema and review your selection.',
                'errors' => ['revision' => ['The selected template contract is stale.']],
            ], 409);
        }
        $headers = $request->query('headers');
        if (! is_array($headers) || ! array_is_list($headers) || $headers === [] || array_filter($headers, fn (mixed $header): bool => ! is_string($header)) !== []) {
            throw ValidationException::withMessages(['headers' => 'Choose one or more valid fields.']);
        }
        if (count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['headers' => 'Choose one or more unique fields.']);
        }
        $fields = collect($schema['fields'])->keyBy('header');
        foreach ($headers as $header) {
            if (! is_string($header) || ! $fields->has($header)) {
                throw ValidationException::withMessages(['headers' => 'One or more selected fields are unknown or unavailable.']);
            }
        }
        foreach ($schema['fields'] as $field) {
            if ($field['required'] && ! in_array($field['header'], $headers, true)) {
                throw ValidationException::withMessages(['headers' => 'Required field '.$field['header'].' cannot be omitted.']);
            }
        }
        $contents = $catalog->csvHeaders($headers);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, $type.'-cycle-'.$cycle->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{Institution, AcademicCycle, ?TestKitVersion, ?ExamSession} */
    private function context(string $type, Request $request, CapabilityAuthorizer $authorizer): array
    {
        if (! in_array($type, [ImportBatch::TYPE_APPLICANTS_ONLY, ImportBatch::TYPE_SCORES_ONLY, ImportBatch::TYPE_ALL_IN_ONE], true)) {
            abort(404);
        }
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $institution = Institution::current();
        $scope = $user->actorScope();
        abort_unless((int) $scope->institutionId === (int) $institution->id, 403);
        $request->validate([
            'academic_cycle_id' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'test_kit_version_id' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'target_exam_session_id' => ['nullable', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ]);
        $cycleId = $request->query('academic_cycle_id');
        $cycleId = $cycleId === null ? null : (int) $cycleId;
        $cycle = $cycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institution->id)->find($cycleId)
            : AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first();
        if (! $cycle instanceof AcademicCycle) {
            throw ValidationException::withMessages(['academic_cycle_id' => 'Select an Academic Year and Semester for this institution.']);
        }
        $authorizationScope = ['institution_id' => (int) $institution->id, 'cycle' => (string) $cycle->code];
        $canApps = $authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT, [...$authorizationScope, 'office' => 'registrar'])->allowed;
        $canScores = $authorizer->decideFresh($user, CapabilityCatalog::GUIDANCE_SCORING_IMPORT, [...$authorizationScope, 'office' => 'guidance'])->allowed;
        abort_unless(match ($type) {
            ImportBatch::TYPE_APPLICANTS_ONLY => $canApps,
            ImportBatch::TYPE_SCORES_ONLY, ImportBatch::TYPE_ALL_IN_ONE => $canScores,
        }, 403);
        if ($type === ImportBatch::TYPE_ALL_IN_ONE && $request->query('target_exam_session_id') !== null) {
            throw ValidationException::withMessages(['target_exam_session_id' => 'All-in-one templates do not bind to an exam session.']);
        }

        $kit = null;
        if ($type !== ImportBatch::TYPE_APPLICANTS_ONLY) {
            $kitId = $request->integer('test_kit_version_id');
            $kit = $kitId > 0
                ? TestKitVersion::query()->where('institution_id', $institution->id)->whereKey($kitId)->whereIn('status', [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_RETIRED])->whereNull('revoked_at')->whereNotNull('test_instrument_version_id')->first()
                : null;
            if (! $kit instanceof TestKitVersion) {
                throw ValidationException::withMessages(['test_kit_version_id' => 'Select an active, non-revoked Test Kit version with a scoring profile.']);
            }
        }
        $session = null;
        if ($request->query('target_exam_session_id') !== null) {
            $sessionId = $request->integer('target_exam_session_id');
            $session = ExamSession::query()->where('academic_cycle_id', $cycle->id)->where('status', ExamSession::STATUS_COMPLETED)->find($sessionId);
            if (! $session instanceof ExamSession || ($kit !== null && $session->test_kit_version_id !== null && (int) $session->test_kit_version_id !== (int) $kit->id)) {
                throw ValidationException::withMessages(['target_exam_session_id' => 'The selected exam session is not available in this Academic Year and Semester and Test Kit context.']);
            }
        }
        if ($kit?->status === TestKitVersion::STATUS_RETIRED
            && ($session === null || (int) $session->test_kit_version_id !== (int) $kit->id)) {
            throw ValidationException::withMessages(['test_kit_version_id' => 'A retired Test Kit version is available only for its exact completed exam session.']);
        }

        return [$institution, $cycle, $kit, $session];
    }
}
