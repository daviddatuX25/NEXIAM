<?php

namespace App\AdmissionEngine\DTOs;

final readonly class ProgramCompatibilityItem
{
    /**
     * @param  list<string>  $drivers
     * @param  array<string, mixed>|null  $radarComparison
     */
    public function __construct(
        public int $rank,
        public string $programCode,
        public string $programName,
        public float $compatibilityIndex,
        public string $band,
        public bool $passedCutoff,
        public array $drivers,
        public ?array $radarComparison = null,
    ) {}

    /** @return array{rank:int, program_code:string, program_name:string, compatibility_index:float, band:string, passed_cutoff:bool, drivers:list<string>, radar_comparison:array<string,mixed>|null} */
    public function toArray(): array
    {
        return [
            'rank' => $this->rank,
            'program_code' => $this->programCode,
            'program_name' => $this->programName,
            'compatibility_index' => $this->compatibilityIndex,
            'band' => $this->band,
            'passed_cutoff' => $this->passedCutoff,
            'drivers' => $this->drivers,
            'radar_comparison' => $this->radarComparison,
        ];
    }
}
