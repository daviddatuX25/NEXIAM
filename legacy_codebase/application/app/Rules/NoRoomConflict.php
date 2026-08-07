<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ExamSession;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class NoRoomConflict implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function __construct(
        protected ?int $excludeSessionId = null,
        protected ?ExamSession $existingSession = null,
    ) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $roomId = (int) ($this->data['room_id'] ?? $this->existingSession?->room_id);
        $date = $this->data['date'] ?? $this->existingSession?->date?->format('Y-m-d');
        $startTime = $this->data['start_time'] ?? $this->existingSession?->start_time;
        $endTime = array_key_exists('end_time', $this->data)
            ? $this->data['end_time']
            : $this->existingSession?->end_time;

        $type = $this->data['type'] ?? $this->existingSession?->type ?? 'scheduled';

        if ($type !== 'scheduled' || ! $roomId || ! $date || ! $startTime) {
            return;
        }

        if (ExamSession::hasRoomConflict($roomId, $date, $startTime, $endTime, $this->excludeSessionId)) {
            $fail('This room is already booked for the selected date and time.');
        }
    }
}
