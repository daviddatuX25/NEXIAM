<?php

namespace App\Http\Requests\Demo;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Testing\PracticeScenarioCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PracticePreparationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment(['local', 'testing']) && app(CapabilityAuthorizer::class)->decideFresh($this->user(), CapabilityCatalog::DEMO_STAGE_CONTROL)->allowed;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('get')) {
            $purpose = $this->query('purpose', 'demo');
            $this->merge(['purpose' => $purpose, 'scenario' => $this->query('scenario', $purpose === 'research' ? 'student' : 'application-review')]);
        }
    }

    public function rules(): array
    {
        if ($this->routeIs('demo.preparation.confirm')) {
            return [];
        }
        $rules = [
            'purpose' => ['required', 'string', Rule::in(['demo', 'research'])],
            'scenario' => ['required', 'string', Rule::in(array_column(app(PracticeScenarioCatalog::class)->scenarios($this->input('purpose') === 'research' ? 'research' : 'demo'), 'key'))],
            'set_id' => ['sometimes', 'required', 'uuid'],
            'booklet_version_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
        if ($this->isMethod('post')) {
            $rules += [
                'action' => ['required', 'string', Rule::in(PracticeScenarioCatalog::ACTIONS)],
                'session_id' => ['sometimes', 'required', 'integer', 'min:1'],
                'window' => ['sometimes', 'required', 'string', Rule::in(['72h', '24h', '2h', '1h'])],
                'preset' => ['sometimes', 'required', 'string', Rule::in(['72h-reminder', '24h-reminder', '2h-reminder', '1h-reminder', 'soon', 'door-open', 'live', 'ended'])],
            ];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = $this->routeIs('demo.preparation.confirm') ? [] : ($this->isMethod('get') ? ['purpose', 'scenario', 'set_id', 'booklet_version_id'] : ['purpose', 'scenario', 'action', 'set_id', 'session_id', 'window', 'preset', 'booklet_version_id']);
            if ($this->isMethod('get')) {
                $allowed[] = 'practice_set';
            }
            if (array_diff(array_keys($this->all()), [...$allowed, '_token']) !== []) {
                $validator->errors()->add('operation', 'Unknown inputs are not permitted.');
            }
        }];
    }
}
