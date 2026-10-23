<?php

declare(strict_types=1);

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class UpdateInstitutionProfile
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     short_name?: ?string,
     *     campus_name?: ?string,
     *     address?: ?string,
     *     location?: ?string,
     *     contact_number?: ?string,
     *     email?: ?string,
     *     website?: ?string,
     * } $data
     */
    public function handle(Institution $institution, array $data, User|string $actor): Institution
    {
        $validated = Validator::validate($data, [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'campus_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        return DB::transaction(function () use ($institution, $validated, $actor): Institution {
            $locked = Institution::query()->lockForUpdate()->findOrFail($institution->id);

            if ($actor instanceof User && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $locked->id,
            ])->allowed) {
                throw new AuthorizationException('Unauthorized to manage institution profile.');
            }

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
                'name' => trim((string) $validated['name']),
                'short_name' => isset($validated['short_name']) && trim((string) $validated['short_name']) !== '' ? trim((string) $validated['short_name']) : null,
                'campus_name' => isset($validated['campus_name']) && trim((string) $validated['campus_name']) !== '' ? trim((string) $validated['campus_name']) : null,
                'address' => isset($validated['address']) && trim((string) $validated['address']) !== '' ? trim((string) $validated['address']) : null,
                'location' => isset($validated['location']) && trim((string) $validated['location']) !== '' ? trim((string) $validated['location']) : null,
                'contact_number' => isset($validated['contact_number']) && trim((string) $validated['contact_number']) !== '' ? trim((string) $validated['contact_number']) : null,
                'email' => isset($validated['email']) && trim((string) $validated['email']) !== '' ? strtolower(trim((string) $validated['email'])) : null,
                'website' => isset($validated['website']) && trim((string) $validated['website']) !== '' ? trim((string) $validated['website']) : null,
            ]);

            $this->audit->handle('setup.institution_profile_updated', [
                'institution_id' => (int) $locked->id,
                'before' => $before,
                'after' => array_intersect_key($locked->toArray(), $before),
            ], is_string($actor) ? $actor : (string) $actor->id);

            return $locked->fresh();
        });
    }
}
