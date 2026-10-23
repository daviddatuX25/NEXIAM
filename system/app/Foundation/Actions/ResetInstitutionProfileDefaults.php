<?php

declare(strict_types=1);

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ResetInstitutionProfileDefaults
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Institution $institution, User|string $actor): Institution
    {
        return DB::transaction(function () use ($institution, $actor): Institution {
            $locked = Institution::query()->lockForUpdate()->findOrFail($institution->id);

            if ($actor instanceof User && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $locked->id,
            ])->allowed) {
                throw new AuthorizationException('Unauthorized to reset institution defaults.');
            }

            $config = config('public-site.institution', []);
            $before = [
                'name' => $locked->name,
                'short_name' => $locked->short_name,
                'campus_name' => $locked->campus_name,
                'address' => $locked->address,
                'location' => $locked->location,
                'contact_number' => $locked->contact_number,
                'email' => $locked->email,
                'website' => $locked->website,
            ];

            $locked->update([
                'name' => (string) ($config['name'] ?? 'Ilocos Sur Polytechnic State College'),
                'short_name' => (string) ($config['short_name'] ?? 'ISPSC'),
                'campus_name' => (string) ($config['campus'] ?? 'Tagudin Campus'),
                'address' => 'Quirino, Tagudin, Ilocos Sur',
                'location' => (string) ($config['location'] ?? 'Tagudin, Ilocos Sur'),
                'contact_number' => '+63 (77) 674-1234',
                'email' => 'admissions.tagudin@ispsc.edu.ph',
                'website' => 'https://ispsc.edu.ph',
            ]);

            $this->audit->handle('setup.institution_profile_reset', [
                'institution_id' => (int) $locked->id,
                'before' => $before,
                'after' => array_intersect_key($locked->fresh()->toArray(), $before),
            ], is_string($actor) ? $actor : (string) $actor->id);

            return $locked->fresh();
        });
    }
}
