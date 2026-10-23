<?php

declare(strict_types=1);

namespace App\Communications;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class PublicPolicySourceAuthorizer
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return list<array{key: string, label: string, requires_program: bool}> */
    public function publishers(User $actor): array
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $scope = $fresh->actorScope();
        if ($scope->institutionId === null) {
            return [];
        }

        $publishers = [];
        if (in_array($scope->office, ['admin', 'registrar', 'guidance'], true)) {
            $source = ['key' => 'office:'.$scope->office, 'label' => $this->officeLabel($scope->office), 'requires_program' => false];
            if ($this->canManage($fresh, $source['key'])) {
                $publishers[] = $source;
            }
        } elseif ($scope->office === 'program') {
            $programs = Program::query()->where('institution_id', $scope->institutionId)
                ->where('is_active', true)->whereIn('code', $scope->programCodes)->orderBy('code')->get(['id', 'code', 'name']);
            foreach ($programs as $program) {
                $key = 'program:'.$program->code;
                if ($this->canManage($fresh, $key)) {
                    $publishers[] = ['key' => $key, 'label' => $program->name, 'requires_program' => true];
                }
            }
        }

        return $publishers;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    public function targetPrograms(User $actor): array
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $scope = $fresh->actorScope();
        if ($scope->institutionId === null) {
            return [];
        }

        return Program::query()->where('institution_id', $scope->institutionId)->where('is_active', true)
            ->when($scope->office === 'program', fn ($query) => $query->whereIn('code', $scope->programCodes))
            ->when(! in_array($scope->office, ['program', 'admin', 'registrar', 'guidance'], true), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Program $program): array => [
                'id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name,
            ])->all();
    }

    public function assertCanTarget(User $actor, string $publisherKey, ?int $programId, bool $isDesignatedIntake = false): ?Program
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $scope = $fresh->actorScope();
        if ($isDesignatedIntake && in_array($scope->office, ['admin', 'registrar'], true) && $programId === null) {
            return null;
        }
        $source = $this->resolve($fresh, $publisherKey);
        if ($source === null) {
            throw new AuthorizationException('You cannot access this public-policy source.');
        }
        $scope = $fresh->actorScope();
        if ($source['office'] === 'program') {
            if ($programId === null) {
                throw new AuthorizationException('Academic public policies must target an owned active program.');
            }
            $program = Program::query()->whereKey($programId)->where('institution_id', $scope->institutionId)
                ->where('is_active', true)->where('code', $source['program_code'])
                ->whereIn('code', $scope->programCodes)->first();
            if ($program === null) {
                throw new AuthorizationException('The program must be currently owned and active.');
            }

            return $program;
        }
        if (! in_array($source['office'], ['admin', 'registrar', 'guidance'], true)) {
            throw new AuthorizationException('This source cannot publish public policies.');
        }
        if ($programId === null) {
            return null;
        }
        $program = Program::query()->whereKey($programId)->where('institution_id', $scope->institutionId)
            ->where('is_active', true)->first();
        if ($program === null) {
            throw new AuthorizationException('The target must be active in this institution.');
        }

        return $program;
    }

    public function assertCanManage(User $actor, int $institutionId, string $publisherKey): string
    {
        return $this->assertCapability($actor, $institutionId, $publisherKey, CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE);
    }

    public function assertCanPublish(User $actor, int $institutionId, string $publisherKey): string
    {
        return $this->assertCapability($actor, $institutionId, $publisherKey, CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH);
    }

    public function canManage(User $actor, string $publisherKey): bool
    {
        return $this->can($actor, $publisherKey, CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE);
    }

    public function canPublish(User $actor, string $publisherKey): bool
    {
        return $this->can($actor, $publisherKey, CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH);
    }

    public function canManagePolicy(User $actor, PublicPolicy $policy): bool
    {
        if ($policy->is_designated_intake) {
            $fresh = User::query()->find($actor->getKey());
            if ($fresh === null) {
                return false;
            }
            $scope = $fresh->actorScope();

            $isAllowedOffice = in_array($scope->office, ['admin', 'registrar'], true)
                || ($policy->publisher_key === 'office:'.$scope->office);

            return (int) $policy->institution_id === (int) $scope->institutionId
                && $isAllowedOffice
                && $this->authorizer->decideFresh($fresh, CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE, [
                    'institution_id' => $scope->institutionId,
                    'office' => $scope->office,
                ])->allowed;
        }

        return $this->canManage($actor, $policy->publisher_key);
    }

    public function canPublishPolicy(User $actor, PublicPolicy $policy): bool
    {
        if ($policy->is_designated_intake) {
            $fresh = User::query()->find($actor->getKey());
            if ($fresh === null) {
                return false;
            }
            $scope = $fresh->actorScope();

            $isAllowedOffice = in_array($scope->office, ['admin', 'registrar'], true)
                || ($policy->publisher_key === 'office:'.$scope->office);

            return (int) $policy->institution_id === (int) $scope->institutionId
                && $isAllowedOffice
                && $this->authorizer->decideFresh($fresh, CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH, [
                    'institution_id' => $scope->institutionId,
                    'office' => $scope->office,
                ])->allowed;
        }

        return $this->canPublish($actor, $policy->publisher_key);
    }

    public function assertCanManagePolicy(User $actor, PublicPolicy $policy): string
    {
        if ($policy->is_designated_intake) {
            $fresh = User::query()->findOrFail($actor->getKey());
            $scope = $fresh->actorScope();
            $isAllowedOffice = in_array($scope->office, ['admin', 'registrar'], true)
                || ($policy->publisher_key === 'office:'.$scope->office);
            if ((int) $policy->institution_id !== (int) $scope->institutionId
                || ! $isAllowedOffice
                || ! $this->authorizer->decideFresh($fresh, CapabilityCatalog::COMMUNICATIONS_POLICIES_MANAGE, [
                    'institution_id' => $scope->institutionId,
                    'office' => $scope->office,
                ])->allowed) {
                throw new AuthorizationException('You are not authorized to manage the Application Privacy Policy.');
            }

            return $this->officeLabel($scope->office);
        }

        return $this->assertCanManage($actor, (int) $policy->institution_id, $policy->publisher_key);
    }

    public function assertCanPublishPolicy(User $actor, PublicPolicy $policy): string
    {
        if ($policy->is_designated_intake) {
            $fresh = User::query()->findOrFail($actor->getKey());
            $scope = $fresh->actorScope();
            $isAllowedOffice = in_array($scope->office, ['admin', 'registrar'], true)
                || ($policy->publisher_key === 'office:'.$scope->office);
            if ((int) $policy->institution_id !== (int) $scope->institutionId
                || ! $isAllowedOffice
                || ! $this->authorizer->decideFresh($fresh, CapabilityCatalog::COMMUNICATIONS_POLICIES_PUBLISH, [
                    'institution_id' => $scope->institutionId,
                    'office' => $scope->office,
                ])->allowed) {
                throw new AuthorizationException('You are not authorized to publish the Application Privacy Policy.');
            }

            return $this->officeLabel($scope->office);
        }

        return $this->assertCanPublish($actor, (int) $policy->institution_id, $policy->publisher_key);
    }

    /** @return array{office: string, program_code: ?string}|null */
    public function resolve(User $actor, string $publisherKey): ?array
    {
        $scope = $actor->actorScope();
        if ($scope->institutionId === null) {
            return null;
        }
        if (str_starts_with($publisherKey, 'office:')) {
            $office = substr($publisherKey, 7);

            return in_array($office, ['admin', 'registrar', 'guidance'], true) && $scope->office === $office
                ? ['office' => $office, 'program_code' => null] : null;
        }
        if (str_starts_with($publisherKey, 'program:') && $scope->office === 'program') {
            $code = substr($publisherKey, 8);

            return $code !== '' && in_array($code, $scope->programCodes, true)
                && Program::query()->where('institution_id', $scope->institutionId)->where('code', $code)->where('is_active', true)->exists()
                ? ['office' => 'program', 'program_code' => $code] : null;
        }

        return null;
    }

    private function assertCapability(User $actor, int $institutionId, string $publisherKey, string $capability): string
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $source = $this->resolve($fresh, $publisherKey);
        if ($institutionId !== $fresh->actorScope()->institutionId || $source === null || ! $this->decision($fresh, $source, $capability)) {
            throw new AuthorizationException('You are not authorized for this public-policy source.');
        }

        return $source['program_code'] === null
            ? $this->officeLabel($source['office'])
            : (string) Program::query()->where('institution_id', $institutionId)->where('code', $source['program_code'])->value('name');
    }

    public function officeLabel(string $office): string
    {
        return match ($office) {
            'admin' => 'Admin Office',
            'registrar' => 'Registrar Office',
            'guidance' => 'Guidance Office',
            default => ucfirst($office),
        };
    }

    private function can(User $actor, string $publisherKey, string $capability): bool
    {
        $fresh = User::query()->find($actor->getKey());
        $source = $fresh === null ? null : $this->resolve($fresh, $publisherKey);

        return $source !== null && $this->decision($fresh, $source, $capability);
    }

    /** @param array{office: string, program_code: ?string} $source */
    private function decision(User $actor, array $source, string $capability): bool
    {
        $scope = ['institution_id' => $actor->actorScope()->institutionId];
        if ($source['office'] === 'program') {
            $scope['program'] = $source['program_code'];
        } else {
            $scope['office'] = $source['office'];
        }

        return $this->authorizer->decideFresh($actor, $capability, $scope)->allowed;
    }
}
