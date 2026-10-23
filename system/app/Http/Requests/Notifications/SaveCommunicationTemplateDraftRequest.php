<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Templates\CommunicationTemplateState;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Closure;
use Illuminate\Validation\Rule;

class SaveCommunicationTemplateDraftRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        $guards = $this->guards($this->isMethod('PUT'));
        if ($this->isMethod('POST') && ! $this->isReminderMessage()) {
            $guards['lock_version'] = ['present', 'nullable', 'prohibited'];
        }
        if ($this->isReminderMessage()) {
            return [...$guards,
                'source_version' => ['sometimes', 'integer', 'min:1'],
                'state' => ['required', 'array:subject,body,action_label,action_route,email_blueprint,document_revision_id'],
                'state.document_revision_id' => ['present', 'nullable', 'integer', 'min:1'],
                'state.subject' => ['present', 'nullable', 'string', 'max:255'],
                'state.body' => ['present', 'nullable', 'string', 'max:500'],
                'state.action_label' => ['present', 'nullable', 'string', 'max:64'],
                'state.action_route' => ['present', 'nullable', 'string', 'max:128'],
                'state.email_blueprint' => ['present', 'nullable', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && NoticeEmailSchema::diagnostics($value) !== []) {
                        $fail('The email blueprint violates the bounded semantic schema.');
                    }
                }]];
        }

        return [...$guards,
            'source_version' => ['sometimes', 'integer', 'min:1'],
            'stage' => ['required', 'integer', 'between:1,3'],
            'state' => ['required', 'array:'.implode(',', CommunicationTemplateState::FIELDS)],
            'state.subject' => ['present', 'nullable', 'string', 'max:255'],
            'state.body' => ['present', 'nullable', 'string', 'max:500'],
            'state.action_label' => ['present', 'nullable', 'string', 'max:64'],
            'state.action_route' => ['present', 'nullable', 'string', 'max:128'],
            'state.email_blueprint' => ['present', 'nullable', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && NoticeEmailSchema::diagnostics($value) !== []) {
                    $fail('The email blueprint violates the bounded semantic schema.');
                }
            }],
            'state.document_mode' => ['required', Rule::in(['none', 'docx', 'builder'])],
            'state.document_asset_id' => ['present', 'nullable', 'uuid'],
            'state.builder_json' => ['present', 'nullable', 'array'],
            'state.paper_size' => ['present', 'nullable', Rule::in(['a4', 'letter', 'legal'])],
            'state.orientation' => ['present', 'nullable', Rule::in(['portrait', 'landscape'])],
            'state.document_source_drafts' => ['present', 'nullable', 'array:docx,builder'],
            'state.import_provenance' => ['present', 'nullable', 'array'],
        ];
    }

    private function isReminderMessage(): bool
    {
        return NoticeMessageComposerQuery::supports((string) $this->route('domain'), (string) $this->route('slug'));
    }
}
