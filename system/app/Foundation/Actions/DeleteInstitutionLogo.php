<?php

declare(strict_types=1);

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Support\Media\MediaStorageLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DeleteInstitutionLogo
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly MediaStorageLifecycle $lifecycle,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Institution $institution, User|string $actor): Institution
    {
        $result = DB::transaction(function () use ($institution, $actor): array {
            $locked = Institution::query()->lockForUpdate()->findOrFail($institution->id);

            if ($actor instanceof User && ! $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $locked->id,
            ])->allowed) {
                throw new AuthorizationException('Unauthorized to manage institution logo.');
            }

            $oldPath = $locked->logo_path;
            $locked->update([
                'logo_path' => null,
                'logo_mime' => null,
                'logo_byte_size' => null,
                'logo_sha256' => null,
            ]);

            $this->audit->handle('setup.institution_logo_removed', [
                'institution_id' => (int) $locked->id,
                'removed_path' => $oldPath,
            ], is_string($actor) ? $actor : (string) $actor->id);

            return [
                'old_path' => $oldPath,
                'institution' => $locked->fresh(),
            ];
        });

        if (! empty($result['old_path'])) {
            $this->lifecycle->deleteQuietly('public', $result['old_path']);
        }

        return $result['institution'];
    }
}
