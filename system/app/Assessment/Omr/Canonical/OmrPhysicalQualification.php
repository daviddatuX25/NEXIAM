<?php

namespace App\Assessment\Omr\Canonical;

final readonly class OmrPhysicalQualification
{
    /**
     * @param  array{0:float|int,1:float|int}  $measuredMarkerCenterMm
     * @param  array{0:float|int,1:float|int}  $expectedMarkerCenterMm
     * @param  array<string, string>  $outcomes
     */
    public function __construct(
        public string $rendererVersion,
        public string $printer,
        public string $device,
        public string $paper,
        public array $measuredMarkerCenterMm,
        public array $expectedMarkerCenterMm,
        public array $outcomes,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'renderer_version' => $this->rendererVersion,
            'printer' => $this->printer,
            'device' => $this->device,
            'paper' => $this->paper,
            'measured_marker_center_mm' => $this->measuredMarkerCenterMm,
            'expected_marker_center_mm' => $this->expectedMarkerCenterMm,
            'outcomes' => $this->outcomes,
        ];
    }
}
