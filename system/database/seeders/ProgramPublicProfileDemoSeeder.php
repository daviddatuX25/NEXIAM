<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Communications\Actions\PublishProgramPublicProfile;
use App\Communications\Actions\SaveProgramPublicProfile;
use App\Communications\Actions\StageProgramPhoto;
use App\Models\Communications\ProgramPublicProfile;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class ProgramPublicProfileDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Program public-profile demo data is restricted to local/testing environments.');
        }

        $institution = Institution::current();
        $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
        $staffProfile = $head->staffProfile()->first();
        $scope = $head->actorScope();
        if ($staffProfile === null
            || ! $staffProfile->isActive()
            || $staffProfile->office !== 'program'
            || (int) $staffProfile->institution_id !== (int) $institution->getKey()
            || $scope->institutionId !== (int) $institution->getKey()) {
            throw new RuntimeException('The existing synthetic program-head account no longer has an active program-office profile in the current institution; no account or profile was changed.');
        }

        $preferredCodes = array_values(array_intersect(['BSIT'], $scope->programCodes));
        $ownedPrograms = Program::query()
            ->where('institution_id', $institution->getKey())
            ->where('is_active', true)
            ->whereIn('code', $preferredCodes)
            ->get()
            ->keyBy('code');
        $program = null;
        foreach ($preferredCodes as $code) {
            if ($ownedPrograms->has($code)) {
                $program = $ownedPrograms->get($code);
                break;
            }
        }
        if (! $program instanceof Program) {
            throw new RuntimeException('The existing synthetic program-head account owns no active BSIT demo program; no account or profile was changed.');
        }
        if (ProgramPublicProfile::query()->where('program_id', $program->getKey())->exists()) {
            $this->command?->info('Skipped the synthetic '.$program->code.' program-profile fixture because a profile already exists; no profile or account was changed.');

            return;
        }

        $this->ensureProgramHeadProfileCapabilities();
        $temporaryPath = tempnam(sys_get_temp_dir(), 'nexiam-profile-demo-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create the temporary synthetic demo raster.');
        }

        try {
            $raster = $this->syntheticRaster();
            if (file_put_contents($temporaryPath, $raster, LOCK_EX) !== strlen($raster)) {
                throw new RuntimeException('Could not write the temporary synthetic demo raster.');
            }

            $photo = app(StageProgramPhoto::class)->handle(
                $head,
                $program,
                'fe150015-0150-4150-8150-000000000015',
                new UploadedFile($temporaryPath, 'synthetic-program-profile-demo.png', 'image/png', UPLOAD_ERR_OK, true),
            );
            $revision = app(SaveProgramPublicProfile::class)->handle($head, $program, [
                'request_id' => 'fe150015-0150-4150-8150-000000000022',
                'expected_revision' => 0,
                'short_description' => 'Synthetic '.$program->code.' demo profile. Not an official program statement.',
                'description' => 'DEMO CONTENT — this visibly synthetic profile exists only to exercise the private draft and explicit publication workflow. It is not verified program information, a curriculum summary, or a campus photograph.',
                'photos' => [[
                    'id' => (int) $photo->getKey(),
                    'alt_text' => 'Generated synthetic green campus-style illustration, not a campus photograph.',
                    'caption' => 'Generated demo raster; no real campus image or program claim.',
                ]],
            ]);
            app(PublishProgramPublicProfile::class)->handle($head, $program, [
                'request_id' => 'fe150015-0150-4150-8150-000000000023',
                'expected_revision' => (int) $revision->revision,
            ]);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        $this->command?->info('Created one visibly synthetic '.$program->code.' public-profile demo using a generated, sanitized raster. Existing users and pre-existing program profiles were not changed.');
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

    private function syntheticRaster(): string
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagepng')) {
            throw new RuntimeException('GD PNG generation is unavailable for the synthetic profile fixture.');
        }

        $image = imagecreatetruecolor(640, 360);
        $forest = imagecolorallocate($image, 24, 78, 57);
        $leaf = imagecolorallocate($image, 89, 151, 100);
        $paper = imagecolorallocate($image, 244, 239, 221);
        $dark = imagecolorallocate($image, 20, 42, 34);
        imagefilledrectangle($image, 0, 0, 639, 359, $forest);
        imagefilledrectangle($image, 0, 235, 639, 359, $leaf);
        imagefilledrectangle($image, 158, 112, 482, 282, $paper);
        imagefilledrectangle($image, 186, 78, 454, 132, $paper);
        imagefilledrectangle($image, 204, 165, 250, 217, $forest);
        imagefilledrectangle($image, 286, 165, 332, 217, $forest);
        imagefilledrectangle($image, 368, 165, 414, 217, $forest);
        imagefilledrectangle($image, 294, 225, 346, 282, $dark);
        imagefilledrectangle($image, 118, 278, 522, 290, $dark);
        imagestring($image, 5, 24, 24, 'SYNTHETIC DEMO', $paper);
        imagestring($image, 3, 24, 48, 'NOT A CAMPUS PHOTOGRAPH', $paper);

        ob_start();
        imagepng($image, null, 6);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Could not encode the generated synthetic demo raster.');
        }

        return $bytes;
    }
}
