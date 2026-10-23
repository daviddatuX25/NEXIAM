<?php

namespace Database\Seeders;

use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use Illuminate\Database\Seeder;

class IntakeRequirementSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first()
            ?? Institution::query()->first()
            ?? Institution::query()->create([
                'name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College'),
                'code' => 'SYN-INST-001',
            ]);

        $requirements = [
            [
                'code' => 'form_138',
                'label' => 'High School Report Card (Form 138 / SF9 - Original)',
                'applicant_track' => IntakeRequirement::TRACK_FRESHMAN,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'psa_secpa',
                'label' => 'PSA Birth Certificate (SECPA Original)',
                'applicant_track' => IntakeRequirement::TRACK_ALL,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'good_moral',
                'label' => 'Certificate of Good Moral Character (Original)',
                'applicant_track' => IntakeRequirement::TRACK_ALL,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'passport_photos',
                'label' => 'Two (2) Identical Passport-size Photos with Nametag',
                'applicant_track' => IntakeRequirement::TRACK_ALL,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'brown_envelope',
                'label' => 'One (1) Long Brown Envelope',
                'applicant_track' => IntakeRequirement::TRACK_ALL,
                'item_kind' => IntakeRequirement::KIND_CONTAINER,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'white_folder',
                'label' => 'One (1) Long White Folder',
                'applicant_track' => IntakeRequirement::TRACK_FRESHMAN,
                'item_kind' => IntakeRequirement::KIND_CONTAINER,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 60,
            ],
            [
                'code' => 'tor',
                'label' => 'Official Transcript of Records (TOR)',
                'applicant_track' => IntakeRequirement::TRACK_TRANSFEREE,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 70,
            ],
            [
                'code' => 'honorable_dismissal',
                'label' => 'Transfer Credential / Honorable Dismissal',
                'applicant_track' => IntakeRequirement::TRACK_TRANSFEREE,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 80,
            ],
            [
                'code' => 'als_cor',
                'label' => 'Certificate of Rating (COR - Original)',
                'applicant_track' => IntakeRequirement::TRACK_ALS,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'is_mandatory' => true,
                'is_active' => true,
                'sort_order' => 90,
            ],
        ];

        foreach ($requirements as $req) {
            IntakeRequirement::query()->updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'code' => $req['code'],
                ],
                $req,
            );
        }
    }
}
