<?php

declare(strict_types=1);

namespace App\Communications;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class AnnouncementSourceAuthorizer
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return list<array{key: string, label: string, requires_program: bool}> */
    public function publishers(User $actor): array
    {
        $fresh = $this->freshActor($actor);
        $scope = $fresh->actorScope();
        if ($scope->institutionId === null) {
            return [];
        }

        $publishers = [];
        $labels = ['admin' => 'Admin Office', 'registrar' => 'Registrar Office', 'guidance' => 'Guidance Office'];
        if (isset($labels[$scope->office])) {
            $source = new AnnouncementSource('office:'.$scope->office, $labels[$scope->office], $scope->institutionId, $scope->office, null);
            if ($this->canManage($fresh, $source)) {
                $publishers[] = ['key' => $source->key, 'label' => $source->label, 'requires_program' => false];
            }
        }

        if ($scope->office === 'program') {
            $programs = Program::query()->active()
                ->where('institution_id', $scope->institutionId)
                ->whereIn('code', $scope->programCodes)
                ->orderBy('name')
                ->get(['code', 'name']);

            foreach ($programs as $program) {
                $source = new AnnouncementSource('program:'.$program->code, $program->name, $scope->institutionId, 'program', $program->code);
                if ($this->canManage($fresh, $source)) {
                    $publishers[] = ['key' => $source->key, 'label' => $source->label, 'requires_program' => true];
                }
            }
        }

        return $publishers;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function targetPrograms(User $actor): array
    {
        $fresh = $this->freshActor($actor);
        $scope = $fresh->actorScope();
        if ($scope->institutionId === null || ! in_array($scope->office, ['admin', 'registrar', 'guidance', 'program'], true)) {
        }

        $programs = Program::query()->active()->where('institution_id', $scope->institutionId);
        if ($scope->office === 'program') {
            $codes = collect($this->publishers($fresh))
                ->pluck('key')
                ->map(fn (string $key): ?string => str_starts_with($key, 'program:') ? substr($key, 8) : null)
                ->filter()
                ->all();
            $programs->whereIn('code', $codes);
        }

        return $programs->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn (Program $program): array => ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name])
            ->all();
    }

    public function assertCanTarget(User $actor, AnnouncementSource $source, ?int $programId): ?Program
    {
        $fresh = $this->freshActor($actor);
        if ($source->office === 'program') {
            $program = $programId === null ? null : Program::query()->active()
                ->whereKey($programId)
                ->where('institution_id', $source->institutionId)
                ->where('code', $source->program)
                ->first();
            if ($program === null || ! in_array($program->code, $fresh->actorScope()->programCodes, true)) {
                throw new AuthorizationException('Academic announcements must target an owned active program.');
            }

            return $program;
        }

        if (! in_array($source->office, ['admin', 'registrar', 'guidance'], true)) {
        }
        if ($programId === null) {
            return null;
        }

        $program = Program::query()->active()
            ->whereKey($programId)
            ->where('institution_id', $source->institutionId)
            ->first();
        if ($program === null) {
            throw new AuthorizationException('The announcement target must be an active program in this institution.');
        }

        return $program;
    }

    public function assertCanManage(User $actor, int $institutionId, string $publisherKey): string
    {
        return $this->assertFor($actor, $institutionId, $publisherKey, false);
    }

    public function assertCanPublish(User $actor, int $institutionId, string $publisherKey): string
    {
        return $this->assertFor($actor, $institutionId, $publisherKey, true);
    }

    public function canManage(User $actor, AnnouncementSource $source): bool
    {
        return $this->decision($actor, $source, CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE);
    }

    public function canPublish(User $actor, AnnouncementSource $source): bool
    {
        return $this->decision($actor, $source, CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH);
    }

    public function assertCanSee(User $actor, int $institutionId, string $publisherKey): AnnouncementSource
    {
        $source = $this->resolve($this->freshActor($actor), $institutionId, $publisherKey);
        if ($source === null || ! $this->canManage($actor, $source)) {
            throw new AuthorizationException('You cannot access this announcement source.');
        }

        return $source;
    }

    public function resolve(User $actor, int $institutionId, string $publisherKey): ?AnnouncementSource
    {
        $scope = $actor->actorScope();
        if ($scope->institutionId !== $institutionId || $institutionId <= 0) {
            return null;
        }

        if (str_starts_with($publisherKey, 'office:')) {
            $office = substr($publisherKey, 7);
            $labels = ['admin' => 'Admin Office', 'registrar' => 'Registrar Office', 'guidance' => 'Guidance Office'];
            if (! isset($labels[$office]) || $scope->office !== $office) {
                return null;
            }

            return new AnnouncementSource($publisherKey, $labels[$office], $institutionId, $office, null);
        }

        if (str_starts_with($publisherKey, 'program:') && $scope->office === 'program') {
            $code = substr($publisherKey, 8);
            if (! in_array($code, $scope->programCodes, true)) {
                return null;
            }

            $program = Program::query()->active()->where('institution_id', $institutionId)->where('code', $code)->first();
            if ($program === null) {
                return null;
            }

            return new AnnouncementSource($publisherKey, $program->name, $institutionId, 'program', $code);
        }

        return null;
    }

    private function assertFor(User $actor, int $institutionId, string $publisherKey, bool $publish): string
    {
        $fresh = $this->freshActor($actor);
        $source = $this->resolve($fresh, $institutionId, $publisherKey);
        $capability = $publish ? CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH : CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE;
        if ($source === null || ! $this->decision($fresh, $source, $capability)) {
            throw new AuthorizationException('You cannot manage this announcement source.');
        }

        return $source->label;
    }

    private function decision(User $actor, AnnouncementSource $source, string $capability): bool
    {
        $scope = ['institution_id' => $source->institutionId];
        if ($source->office !== null) {
            $scope['office'] = $source->office;
        }
        if ($source->program !== null) {
            $scope['program'] = $source->program;
        }

        return $this->authorizer->decideFresh($actor, $capability, $scope)->allowed;
    }

    private function freshActor(User $actor): User
    {
        return User::query()->findOrFail($actor->getKey());
    }
}
