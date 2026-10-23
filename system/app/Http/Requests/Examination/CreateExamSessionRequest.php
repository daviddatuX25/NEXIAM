<?php

namespace App\Http\Requests\Examination;

use App\Models\Examination\ExamSession;
use App\Notifications\Policies\ExamNotificationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('seating_mode')) {
            $this->merge(['seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY]);
        }

        if ($this->filled(['date', 'start_time', 'end_time']) && ! $this->filled('starts_at')) {
            $this->merge([
                'starts_at' => trim($this->string('date')->toString().' '.$this->string('start_time')->toString()),
                'effective_end_at' => trim($this->string('date')->toString().' '.$this->string('end_time')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'code' => ['required', 'string', 'max:64'],
            'starts_at' => ['required', 'date'],
            'effective_end_at' => ['required', 'date', 'after:starts_at'],
            'seating_mode' => ['required', 'string', Rule::in(ExamSession::SEATING_MODES)],
            'test_kit_version_id' => ['nullable', 'integer', 'min:1', 'exists:test_kit_versions,id'],
            'booklet_version_id' => ['prohibited'],
            'seating_arrangement_id' => ['nullable', 'integer', 'exists:seating_arrangements,id'],
            'notification_policy' => ['nullable', 'array'],
            'notification_policy.publication_email_enabled' => ['nullable', 'boolean'],
            'notification_policy.reminder_offsets_minutes' => ['nullable', 'array', 'max:4'],
            'notification_policy.reminder_offsets_minutes.*' => ['integer', Rule::in(ExamNotificationPolicy::ALLOWED_REMINDER_OFFSETS_MINUTES), 'distinct'],
            'notification_policy.reminder_email_enabled' => ['nullable', 'boolean'],
            'auto_assign_enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function notificationPolicy(): ?array
    {
        $raw = $this->input('notification_policy');

        if (! is_array($raw) || empty($raw) || ($this->boolean('use_cycle_notification_defaults', true) && ! $this->filled('notification_policy.reminder_offsets_minutes'))) {
            return null;
        }

        $offsets = $raw['reminder_offsets_minutes'] ?? null;
        if (is_array($offsets)) {
            $offsets = array_values(array_unique(array_map('intval', $offsets)));
            rsort($offsets, SORT_NUMERIC);
        }

        return [
            'schema_version' => ExamNotificationPolicy::SCHEMA_VERSION,
            'publication_email_enabled' => (bool) ($raw['publication_email_enabled'] ?? true),
            'reminder_offsets_minutes' => $offsets ?? ExamNotificationPolicy::DEFAULT_REMINDER_OFFSETS_MINUTES,
            'reminder_email_enabled' => (bool) ($raw['reminder_email_enabled'] ?? true),
        ];
    }
}
