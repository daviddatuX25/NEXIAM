<?php

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Contracts\AiGateway;
use App\Ai\Privacy\PiiTokenVault;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

test('PII token vault caches stable candidate mappings and preserves domain references', function (): void {
    $vault = app(PiiTokenVault::class);
    $conversationId = (string) Str::uuid();
    $epoch = (string) Str::uuid();
    $cacheKey = "xiam:pii_vault:{$conversationId}:{$epoch}";
    $input = 'Review Althea Garcia and Mika Santos for APP-2026-00042 on EX-2026-00017.';

    $tokenized = $vault->tokenize(
        $input,
        'staff-actor',
        $conversationId,
        $epoch,
        ['Althea Garcia', 'Mika Santos'],
    );

    expect($tokenized)->toBe('Review [CANDIDATE_1] and [CANDIDATE_2] for APP-2026-00042 on EX-2026-00017.')
        ->and(Cache::get($cacheKey))->toBe([
            '[CANDIDATE_1]' => 'Althea Garcia',
            '[CANDIDATE_2]' => 'Mika Santos',
        ])
        ->and($vault->tokenize(
            'Follow up with Althea Garcia about APP-2026-00042.',
            'staff-actor',
            $conversationId,
            $epoch,
            ['Althea Garcia', 'Mika Santos'],
        ))->toBe('Follow up with [CANDIDATE_1] about APP-2026-00042.')
        ->and($vault->detokenize('[CANDIDATE_1] and [CANDIDATE_2]', $conversationId, $epoch))
        ->toBe('Althea Garcia and Mika Santos');

    $vault->clear($conversationId, $epoch);
});

test('clearing the PII vault removes mappings only from the cleared epoch', function (): void {
    $vault = app(PiiTokenVault::class);
    $conversationId = (string) Str::uuid();
    $clearedEpoch = (string) Str::uuid();
    $activeEpoch = (string) Str::uuid();

    $vault->tokenize('Mika Santos', 'staff-actor', $conversationId, $clearedEpoch, ['Mika Santos']);
    $vault->tokenize('Althea Garcia', 'staff-actor', $conversationId, $activeEpoch, ['Althea Garcia']);

    $vault->clear($conversationId, $clearedEpoch);

    expect(Cache::has("xiam:pii_vault:{$conversationId}:{$clearedEpoch}"))->toBeFalse()
        ->and($vault->detokenize('[CANDIDATE_1]', $conversationId, $clearedEpoch))->toBe('[CANDIDATE_1]')
        ->and($vault->detokenize('[CANDIDATE_1]', $conversationId, $activeEpoch))->toBe('Althea Garcia');

    $vault->clear($conversationId, $activeEpoch);
});

test('staff applicant names are tokenized before provider input and restored in stream and snapshot', function (): void {
    seedE2Foundation();
    seedE2JourneyFoundation();

    config(['xiam.ai.gateway' => 'laravel', 'xiam.process_inline' => true]);
    app()->instance(AiGateway::class, new LaravelAiGateway);

    $staff = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $applicant = ApplicantIdentity::query()
        ->where('institution_id', $staff->actorScope()->institutionId)
        ->where('first_name', 'Demo')
        ->where('last_name', 'Accepted')
        ->firstOrFail();
    $realName = $applicant->fullName();
    $providerPrompt = '';
    $providerResponse = 'I found [CANDIDATE_1] in the applicant records.';

    XiamAgent::fake(static function (string $prompt) use (&$providerPrompt, $providerResponse): string {
        $providerPrompt = $prompt;

        return $providerResponse;
    });

    $accepted = $this->actingAs($staff)
        ->postJson(route('xiam.turns.store'), [
            'message' => "Please review {$realName}'s application APP-2026-00042.",
            'idempotency_key' => 'pii-vault-staff-applicant-turn',
        ])
        ->assertAccepted()
        ->assertJsonStructure(['conversation_id', 'turn_id', 'run_id', 'context_epoch', 'cursor'])
        ->json();

    $turn = XiamTurn::query()->findOrFail($accepted['turn_id']);
    $run = XiamRun::query()->findOrFail($accepted['run_id']);

    expect($run->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($turn->input_text)->toBe("Please review [CANDIDATE_1]'s application APP-2026-00042.")
        ->and($turn->input_text)->not->toContain($realName)
        ->and($providerPrompt)->toContain('[CANDIDATE_1]')
        ->and($providerPrompt)->not->toContain($realName);

    $snapshot = $this->getJson(route('xiam.conversations.snapshot', $accepted['conversation_id']))
        ->assertOk()
        ->json();
    $stream = $this->get(route('xiam.conversations.stream', $accepted['conversation_id']))
        ->assertOk()
        ->streamedContent();

    expect($snapshot['messages'][0]['content'])->toBe("Please review {$realName}'s application APP-2026-00042.")
        ->and($snapshot['messages'][1]['content'])->toBe("I found {$realName} in the applicant records.")
        ->and($stream)->toContain('"type":"text-delta"')
        ->and($stream)->toContain('I found '.$realName.' in the applicant records.')
        ->and($stream)->not->toContain('[CANDIDATE_1]');
});
