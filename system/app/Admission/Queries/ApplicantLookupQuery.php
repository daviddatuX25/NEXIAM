<?php

namespace App\Admission\Queries;

use App\Admission\Support\ApplicantReference;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ApplicantLookupQuery
{
    public function __construct(private readonly ApplicantActionMenuQuery $menu) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function search(User $actor, array $input): array
    {
        $actor = $actor->fresh();
        $scope = $actor->actorScope();
        $cycles = AcademicCycle::query()->where('institution_id', $scope->institutionId)
            ->when($scope->office !== 'registrar', fn (Builder $query) => $query->where('is_active', true)->where('code', $scope->activeCycleCode))
            ->orderByDesc('is_active')->orderByDesc('id')->get();
        $cycle = isset($input['academic_cycle_id'])
            ? $cycles->firstWhere('id', (int) $input['academic_cycle_id'])
            : $cycles->firstWhere('is_active', true);
        $term = trim(preg_replace('/\s+/u', ' ', (string) ($input['q'] ?? '')) ?? '');
        $page = max(1, (int) ($input['page'] ?? 1));
        $payload = [
            'results' => [], 'pagination' => ['current_page' => $page, 'has_more' => false],
            'cycles' => $cycles->map(fn (AcademicCycle $item): array => ['id' => $item->id, 'label' => $item->staffLabel()])->all(),
            'academic_cycle_id' => $cycle?->id,
        ];
        if ($cycle === null || $term === '' || (mb_strlen($term) < 2 && ! ctype_digit($term))) {
            return $payload;
        }

        $references = ApplicantReference::candidateCodes($term, $cycle);
        $tokens = array_slice(preg_split('/\s+/u', mb_strtolower($term), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);
        $query = $this->menu->visibleAttempts($actor)->where('academic_cycle_id', $cycle->id)
            ->where(function (Builder $match) use ($references, $tokens): void {
                $match->whereIn('reference_code', $references)
                    ->orWhereHas('identity', function (Builder $identity) use ($tokens): void {
                        foreach ($tokens as $token) {
                            $prefix = addcslashes($token, '\\%_').'%';
                            $identity->where(function (Builder $part) use ($prefix): void {
                                $part->whereRaw('lower(first_name) like ?', [$prefix])
                                    ->orWhereRaw('lower(last_name) like ?', [$prefix])
                                    ->orWhereRaw('lower(middle_name) like ?', [$prefix]);
                            });
                        }
                    });
            })
            ->with(['identity:id,first_name,middle_name,last_name,suffix', 'academicCycle'])
            ->orderBy('reference_code')->orderBy('id');
        $results = $query->simplePaginate(20, ['id', 'applicant_identity_id', 'academic_cycle_id', 'reference_code'], 'page', $page);
        $payload['results'] = $results->getCollection()->map(fn (AdmissionAttempt $attempt): array => [
            'id' => $attempt->id,
            'name' => $attempt->identity?->fullName() ?? $attempt->reference_code,
            'reference_code' => $attempt->reference_code,
            'cycle_label' => $attempt->academicCycle->staffLabel(),
        ])->all();
        $payload['pagination']['has_more'] = $results->hasMorePages();

        return $payload;
    }
}
