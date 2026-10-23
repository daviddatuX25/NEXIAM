<?php

namespace App\Admission\Actions;

use App\Admission\Support\RecordThreadService;
use App\Models\Admission\RecordThread;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * D-092: update the participant read watermark; denied when the actor no
 * longer passes current scope (revoked/expired-duty/cross-program).
 */
class MarkAdmissionSupportMessageRead
{
    public function __construct(private readonly RecordThreadService $threads) {}

    public function handle(
        RecordThread $thread,
        User $actor,
        string $observedSequence,
        string $observationToken,
    ): void {
        $input = Validator::make([
            'observed_sequence' => $observedSequence,
            'observation_token' => $observationToken,
        ], [
            'observed_sequence' => [
                'required',
                'string',
                'regex:/^(0|[1-9][0-9]{0,18})$/',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) === 19 && strcmp($value, '9223372036854775807') > 0) {
                        $fail('The observed sequence is outside the supported range.');
                    }
                },
            ],
            'observation_token' => ['required', 'string', 'max:1024'],
        ])->validate();

        if (! $this->threads->canRead($thread, $actor)) {
            throw new RuntimeException('You are not authorized to read this thread.');
        }

        $this->threads->markRead($thread, $actor, $input['observed_sequence'], $input['observation_token']);
    }
}
