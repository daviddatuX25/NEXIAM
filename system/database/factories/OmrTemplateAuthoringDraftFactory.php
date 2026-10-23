<?php

namespace Database\Factories;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmrTemplateAuthoringDraft>
 */
class OmrTemplateAuthoringDraftFactory extends Factory
{
    protected $model = OmrTemplateAuthoringDraft::class;

    public function definition(): array
    {
        return [
            'institution_id' => fn (): int => Institution::query()->create([
                'code' => 'DRAFT-FACTORY-'.fake()->unique()->numerify('####'),
                'name' => 'Draft Factory Institution',
            ])->id,
            'is_synthetic' => false,
            'code' => fake()->unique()->bothify('DRAFT-####-????'),
            'name' => fake()->words(3, true),
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
            'registration_mode' => OmrRegistrationMode::DocumentBound,
            'qr_mode' => OmrQrMode::Disabled,
            'requested_page_count' => 1,
            'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
            'authoring_json' => [],
            'lock_version' => 0,
            'owner_user_id' => User::factory(),
        ];
    }

    public function withPaperPolicy(
        OmrRegistrationMode $registrationMode,
        OmrQrMode $qrMode,
        int $pageCount,
    ): static {
        return $this->state([
            'registration_mode' => $registrationMode,
            'qr_mode' => $qrMode,
            'requested_page_count' => $pageCount,
        ]);
    }
}
