<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Actions;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\User;
use App\Notifications\Templates\CommunicationTemplateMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DiscardCommunicationTemplateDraft
{
    public function __construct(private readonly CommunicationTemplateMutation $mutation, private readonly StoreCommunicationTemplateDocument $documents) {}

    public function discard(CommunicationTemplate $template, array $data, User $actor): array
    {
        return $this->mutation->run($template, $data, $actor, 'discard', 200,
            function (CommunicationTemplate $locked, ?CommunicationTemplateDraft $draft) use ($data): array {
                abort_unless($draft !== null, 422, 'A saved draft is required.');
                Validator::make(['data' => $data], ['data' => ['required',
                    'array:base_version,family_revision,lock_version,idempotency_key,draft_id']])->validate();
                $draft->delete();
                DB::afterCommit(fn () => $this->documents->pruneUnreferenced($locked));

                return ['discarded' => true, 'return_url' => route('setup.notifications.'.$locked->domain.'.templates.studio', ['slug' => $locked->slug])];
            });
    }
}
