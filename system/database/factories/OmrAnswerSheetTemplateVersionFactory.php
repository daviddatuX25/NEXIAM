<?php

namespace Database\Factories;

use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmrAnswerSheetTemplateVersion>
 */
class OmrAnswerSheetTemplateVersionFactory extends Factory
{
    protected $model = OmrAnswerSheetTemplateVersion::class;

    public function definition(): array
    {
        return [
            'template_id' => OmrAnswerSheetTemplate::factory(),
            'version' => 1,
            'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
            'registration_mode' => OmrRegistrationMode::FiducialAnchored,
            'qr_mode' => OmrQrMode::Signed,
            'page_count' => 1,
            'page_width' => 2480,
            'page_height' => 3508,
            'identity_regions_json' => [],
            'provider_config_json' => [],
            'config_hash' => hash('sha256', fake()->uuid()),
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
            'page_count' => $pageCount,
        ]);
    }
}
