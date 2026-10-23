<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishProgramPublicProfile;
use App\Communications\Actions\SaveProgramPublicProfile;
use App\Communications\ProgramPublicProfileAuthorizer;
use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Communications\ProgramPublicProfileRevision;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ProgramPublicProfileSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::current();

        if ($institution === null) {
            return;
        }

        $this->ensureProgramHeadProfileCapabilities();

        $catalogByCode = array_column(app(TagudinUndergraduateCatalog::class)->programs(), null, 'code');
        $authorizer = app(ProgramPublicProfileAuthorizer::class);

        $programs = Program::query()
            ->with('department')
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($programs as $program) {
            $catalogEntry = $catalogByCode[$program->code] ?? null;
            if ($catalogEntry === null) {
                continue;
            }

            if (ProgramPublicProfile::query()->where('program_id', $program->id)->exists()) {
                continue;
            }

            $shortDescription = trim((string) ($catalogEntry['summary'] ?? ''));
            $description = trim((string) ($catalogEntry['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $actor = $this->resolveAuthorizedActor($program, $authorizer);
            if ($actor !== null) {
                $saveRequestId = $this->deterministicUuid('program-public-profile-save:'.$institution->id.':'.$program->code);
                $publishRequestId = $this->deterministicUuid('program-public-profile-publish:'.$institution->id.':'.$program->code);

                $revision = app(SaveProgramPublicProfile::class)->handle($actor, $program, [
                    'request_id' => $saveRequestId,
                    'expected_revision' => 0,
                    'short_description' => $shortDescription,
                    'description' => $description,
                    'photos' => [],
                ]);

                app(PublishProgramPublicProfile::class)->handle($actor, $program, [
                    'request_id' => $publishRequestId,
                    'expected_revision' => (int) $revision->revision,
                ]);

                continue;
            }

            DB::transaction(function () use ($institution, $program, $shortDescription, $description): void {
                $fallbackActor = ($program->department?->dean_user_id !== null
                    ? User::query()->find($program->department->dean_user_id)
                    : null)
                    ?? User::query()->orderBy('id')->first();

                $profile = ProgramPublicProfile::query()->create([
                    'program_id' => $program->id,
                    'institution_id' => $institution->id,
                    'current_revision' => 1,
                    'published_revision_id' => null,
                ]);

                $revision = ProgramPublicProfileRevision::query()->create([
                    'profile_id' => $profile->id,
                    'revision' => 1,
                    'short_description' => $shortDescription,
                    'description' => $description,
                    'photos' => [],
                    'is_published' => true,
                    'operation' => 'publish',
                    'actor_id' => $fallbackActor?->id,
                    'actor_name' => $fallbackActor?->name ?? 'System Seeder',
                ]);

                $profile->update([
                    'current_revision' => 1,
                    'published_revision_id' => $revision->id,
                ]);
            });
        }
    }

    private function resolveAuthorizedActor(Program $program, ProgramPublicProfileAuthorizer $authorizer): ?User
    {
        $candidates = User::query()
            ->whereHas('staffProfile', fn ($query) => $query
                ->where('institution_id', $program->institution_id)
                ->where('office', 'program')
                ->where('status', 'active')
                ->whereNull('deactivated_at'))
            ->orderBy('id')
            ->get();

        foreach ($candidates as $candidate) {
            if ($authorizer->canPublish($candidate, $program)) {
                return $candidate;
            }
        }

        return null;
    }

    private function ensureProgramHeadProfileCapabilities(): void
    {
        $role = Role::findOrCreate('program_head', 'web');
        foreach ([
            CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE,
            CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_PUBLISH,
        ] as $capability) {
            $permission = Permission::findOrCreate($capability, 'web');
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function deterministicUuid(string $seed): string
    {
        $hash = md5($seed);

        return (string) Str::of(sprintf(
            '%s-%s-4%s-%x%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            8 | (hexdec(substr($hash, 16, 1)) & 0x3),
            substr($hash, 17, 3),
            substr($hash, 20, 12),
        ));
    }
}
