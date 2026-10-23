<?php

namespace App\Authorization;

final readonly class ActorScope
{
    /**
     * @param  list<string>  $programCodes
     */
    public function __construct(
        public ?int $institutionId,
        public ?string $office,
        public array $programCodes,
        public ?string $activeCycleCode,
        public ?int $departmentId = null,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, [], null, null);
    }

    /**
     * @return array{institution_id: ?int, office: ?string, department_id: ?int, program_codes: list<string>, active_cycle_code: ?string}
     */
    public function toArray(): array
    {
        return [
            'institution_id' => $this->institutionId,
            'office' => $this->office,
            'department_id' => $this->departmentId,
            'program_codes' => $this->programCodes,
            'active_cycle_code' => $this->activeCycleCode,
        ];
    }
}
