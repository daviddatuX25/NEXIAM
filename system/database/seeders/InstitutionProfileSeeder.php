<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class InstitutionProfileSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->firstOrCreate(
            ['code' => 'SYN-INST-001'],
            ['name' => 'Ilocos Sur Polytechnic State College']
        );

        $logoPath = null;
        $logoMime = null;
        $logoByteSize = null;
        $logoSha256 = null;

        $sourceFile = public_path('assets/branding/ispsc/logo/ispsc-seal.webp');
        if (! File::exists($sourceFile)) {
            $sourceFile = public_path('assets/branding/ispsc/logo/ispsc-seal.png');
        }

        if (File::exists($sourceFile)) {
            $extension = pathinfo($sourceFile, PATHINFO_EXTENSION);
            $targetPath = "institutions/{$institution->id}/logo/ispsc-seal.{$extension}";
            $content = File::get($sourceFile);

            Storage::disk('public')->put($targetPath, $content);

            $logoPath = $targetPath;
            $logoMime = $extension === 'webp' ? 'image/webp' : 'image/png';
            $logoByteSize = strlen($content);
            $logoSha256 = hash('sha256', $content);
        }

        $institution->update([
            'name' => 'Ilocos Sur Polytechnic State College',
            'short_name' => 'ISPSC',
            'campus_name' => 'Tagudin Campus',
            'address' => 'Quirino, Tagudin, Ilocos Sur 2714',
            'location' => 'Tagudin, Ilocos Sur',
            'contact_number' => '+63 (77) 674-1474',
            'email' => 'tagudin@ispsc.edu.ph',
            'website' => 'https://ispsc.edu.ph/campuses/tagudin-campus',
            'logo_path' => $logoPath,
            'logo_mime' => $logoMime,
            'logo_byte_size' => $logoByteSize,
            'logo_sha256' => $logoSha256,
        ]);
    }
}
