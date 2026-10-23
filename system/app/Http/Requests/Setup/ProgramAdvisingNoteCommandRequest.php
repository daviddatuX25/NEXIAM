<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

final class ProgramAdvisingNoteCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isSave = $this->routeIs('setup.admission-support.advising-note.save');
        $isRestore = $this->routeIs('setup.admission-support.advising-note.restore');
        $isCopy = $this->routeIs('setup.admission-support.advising-note.copy');
        $isPublication = $this->routeIs(
            'setup.admission-support.advising-note.publish',
            'setup.admission-support.advising-note.retract',
        );
        $isPublish = $this->routeIs('setup.admission-support.advising-note.publish');
        $isRetract = $this->routeIs('setup.admission-support.advising-note.retract');

        return [
            'content' => [$isSave ? 'required' : 'sometimes', 'string', 'max:20000'],
            'note_id' => [$isCopy ? 'prohibited' : ($isPublication || $isRestore ? 'required' : 'sometimes'), ...($isSave ? ['nullable'] : []), 'integer', 'min:1'],
            'source_revision_id' => [$isRestore || $isCopy ? 'required' : 'sometimes', 'integer', 'min:1'],
            'revision_id' => [$isPublish ? 'required' : 'sometimes', 'integer', 'min:1'],
            'publication_id' => [$isRetract ? 'required' : 'sometimes', 'integer', 'min:1'],
            'target_cycle_id' => [$isCopy ? 'required' : 'sometimes', 'integer', 'exists:academic_cycles,id'],
            'audience' => [$isSave ? 'sometimes' : 'prohibited', 'nullable', 'in:program,private'],
            'target_audience' => [$isCopy ? 'sometimes' : 'prohibited', 'nullable', 'in:program,private'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $allowed = ['_token', '_method', 'content', 'note_id', 'source_revision_id', 'revision_id', 'publication_id', 'target_cycle_id', 'audience', 'target_audience', 'expected_version', 'reason', 'idempotency_key'];
            foreach (array_diff(array_keys($this->all()), $allowed) as $unknown) {
                $validator->errors()->add($unknown, 'Unexpected request field.');
            }
        }];
    }
}
