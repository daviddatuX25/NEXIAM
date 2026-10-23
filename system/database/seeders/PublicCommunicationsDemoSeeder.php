<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishAnnouncement;
use App\Communications\Actions\SaveAnnouncement;
use App\Models\Communications\AnnouncementCommandReceipt;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PublicCommunicationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Public communications demo data is restricted to local/testing environments.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
        $bsitProgram = Program::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'BSIT')
            ->where('is_active', true)
            ->firstOrFail();
        foreach ([
            CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
            CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
        ] as $capability) {
            Permission::findOrCreate($capability, 'web');
        }
        foreach (CapabilityCatalog::roleHelperGrants() as $roleName => $capabilities) {
            $communicationsCapabilities = array_values(array_intersect($capabilities, [
                CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE,
                CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH,
            ]));
            if ($communicationsCapabilities !== []) {
                Role::findOrCreate($roleName, 'web')->givePermissionTo($communicationsCapabilities);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $fixtures = [
            [
                'actor' => $registrar,
                'publisher_key' => 'office:registrar',
                'program_id' => null,
                'title' => 'Demo: Registrar enrollment desk hours',
                'summary' => 'Synthetic demo notice for the Registrar Office.',
                'body' => 'DEMO CONTENT — synthetic Registrar Office information for local workflow demonstrations only.',
                'featured' => true,
                'start' => null,
                'end' => null,
                'save_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1001',
                'publish_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1002',
                'publish' => true,
            ],
            [
                'actor' => $guidance,
                'publisher_key' => 'office:guidance',
                'program_id' => null,
                'title' => 'Demo: Guidance counseling orientation',
                'summary' => 'Synthetic demo notice for the Guidance Office.',
                'body' => 'DEMO CONTENT — synthetic Guidance Office information for local workflow demonstrations only.',
                'featured' => false,
                'start' => null,
                'end' => null,
                'save_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1003',
                'publish_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1004',
                'publish' => true,
            ],
            [
                'actor' => $programHead,
                'publisher_key' => 'program:BSIT',
                'program_id' => $bsitProgram->id,
                'title' => 'Demo: BSIT program information session',
                'summary' => 'Synthetic demo notice for the BSN program.',
                'body' => 'DEMO CONTENT — synthetic BSN program information for local workflow demonstrations only.',
                'featured' => true,
                'start' => null,
                'end' => null,
                'save_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1005',
                'publish_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1006',
                'publish' => true,
            ],
            [
                'actor' => $registrar,
                'publisher_key' => 'office:registrar',
                'program_id' => null,
                'title' => 'Demo: Registrar draft workflow example',
                'summary' => 'Synthetic draft used to demonstrate save and publish controls.',
                'body' => 'DEMO CONTENT — this draft is intentionally not public until explicitly published.',
                'featured' => false,
                'start' => null,
                'end' => null,
                'save_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1007',
                'publish_id' => null,
                'publish' => false,
            ],
            [
                'actor' => $guidance,
                'publisher_key' => 'office:guidance',
                'program_id' => $bsitProgram->id,
                'title' => 'Demo: Guidance BSIT orientation',
                'summary' => 'Synthetic Guidance notice attached only to the BSIT program.',
                'body' => 'DEMO CONTENT — synthetic Guidance information for the BSIT program only.',
                'featured' => false,
                'start' => null,
                'end' => null,
                'save_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1009',
                'publish_id' => '2e7da7c4-a5fb-4c49-9c1d-9d82c28a1010',
                'publish' => true,
            ],
        ];

        foreach ($fixtures as $fixture) {
            if (AnnouncementCommandReceipt::query()->where('actor_id', $fixture['actor']->id)
                ->where('request_id', $fixture['save_id'])->exists()) {
                continue;
            }
            $payload = [
                'publisher_key' => $fixture['publisher_key'],
                'program_id' => $fixture['program_id'],
                'request_id' => $fixture['save_id'],
                'title' => $fixture['title'],
                'summary' => $fixture['summary'],
                'body' => $fixture['body'],
                'priority' => 'normal',
                'is_featured' => $fixture['featured'],
                'starts_at' => $fixture['start']?->toISOString(),
                'ends_at' => $fixture['end']?->toISOString(),
            ];
            $announcement = app(SaveAnnouncement::class)->handle($fixture['actor'], $payload);
            if ($fixture['publish'] && $announcement->status === 'draft') {
                $announcement = app(PublishAnnouncement::class)->handle($fixture['actor'], $announcement, [
                    'request_id' => $fixture['publish_id'],
                    'expected_revision' => $announcement->revision,
                ]);
            }
        }

        $this->command?->info('Synthetic Public Communications demo notices are ready; no existing accounts, passwords, policies, or non-demo announcements were changed.');
    }
}
