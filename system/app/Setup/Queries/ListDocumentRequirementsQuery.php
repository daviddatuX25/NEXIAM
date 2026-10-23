<?php

namespace App\Setup\Queries;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListDocumentRequirementsQuery
{
    /**
     * @param  array{search?: ?string, track?: ?string, kind?: ?string, mandatory?: ?string, status?: ?string, page?: int|string|null}  $filters
     * @return array{page_state: string, requirements: LengthAwarePaginator, filters: array{search: string, track: string, kind: string, mandatory: string, status: string}, tracks: array<string, string>, kinds: array<string, string>}
     */
    public function handle(array $filters): array
    {
        $institutionId = Institution::current()->id;
        $totalUnfiltered = IntakeRequirement::query()
            ->where('institution_id', $institutionId)
            ->count();

        $search = $filters['search'] ?? null;
        $track = $filters['track'] ?? null;
        $kind = $filters['kind'] ?? null;
        $mandatory = $filters['mandatory'] ?? null;
        $status = $filters['status'] ?? null;

        $requirements = IntakeRequirement::query()
            ->where('institution_id', $institutionId)
            ->when($search !== null, function (Builder $query) use ($search): void {
                $escapedSearch = addcslashes($search, '%_\\');
                $pattern = '%'.mb_strtolower($escapedSearch).'%';

                $query->where(function (Builder $query) use ($pattern): void {
                    $query->whereRaw("LOWER(code) LIKE ? ESCAPE '\\'", [$pattern])
                        ->orWhereRaw("LOWER(label) LIKE ? ESCAPE '\\'", [$pattern]);
                });
            })
            ->when($track !== null, fn (Builder $query): Builder => $query->where('applicant_track', $track))
            ->when($kind !== null, fn (Builder $query): Builder => $query->where('item_kind', $kind))
            ->when($mandatory !== null, fn (Builder $query): Builder => $query->where('is_mandatory', $mandatory === 'mandatory'))
            ->when($status !== null, fn (Builder $query): Builder => $query->where('is_active', $status === 'active'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (IntakeRequirement $requirement): array => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'label' => $requirement->label,
                'applicant_track' => $requirement->applicant_track,
                'item_kind' => $requirement->item_kind,
                'is_mandatory' => $requirement->is_mandatory,
                'is_active' => $requirement->is_active,
                'sort_order' => $requirement->sort_order,
                'version' => $requirement->version,
            ]);

        return [
            'page_state' => $totalUnfiltered === 0 ? 'empty' : 'ready',
            'requirements' => $requirements,
            'filters' => [
                'search' => $search ?? '',
                'track' => $track ?? '',
                'kind' => $kind ?? '',
                'mandatory' => $mandatory ?? '',
                'status' => $status ?? '',
            ],
            'tracks' => IntakeRequirement::trackOptions(),
            'kinds' => IntakeRequirement::kindOptions(),
        ];
    }
}
