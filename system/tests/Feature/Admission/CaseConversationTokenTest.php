<?php

use App\Admission\Support\CaseConversationToken;
use App\Models\User;
use RuntimeException;

function startCaseConversationTokenSession(string $id): void
{
    session()->setId($id);
    session()->start();
}

beforeEach(function (): void {
    startCaseConversationTokenSession('case-token-session-a');
});

test('presentation context is stable within a session and separated by user and session without leaking secrets', function (): void {
    $actor = User::factory()->create();
    $context = CaseConversationToken::presentationContext($actor);

    expect($context)->toStartWith('messaging-client-v1.')
        ->and(CaseConversationToken::presentationContext($actor))->toBe($context)
        ->and(CaseConversationToken::presentationContext(User::factory()->create()))->not->toBe($context)
        ->and($context)->not->toContain(session()->getId())
        ->and($context)->not->toContain((string) config('app.key'));

    startCaseConversationTokenSession('case-token-session-presentation-b');

    expect(CaseConversationToken::presentationContext($actor))->not->toBe($context)
        ->and(CaseConversationToken::presentationContext($actor))
        ->toBe(CaseConversationToken::presentationContext($actor));
});

test('observation token is signed, bound to principal session thread and sequence without exposing credentials', function (): void {
    $actor = User::factory()->create();
    $token = CaseConversationToken::issue($actor, 'thread-17', '23');
    [$encodedPayload, $signature] = explode('.', $token);
    $payload = json_decode(base64_decode(strtr($encodedPayload, '-_', '+/').str_repeat('=', (4 - strlen($encodedPayload) % 4) % 4)), true);

    expect(CaseConversationToken::verify($actor, 'thread-17', '23', $token))->toBeTrue()
        ->and(CaseConversationToken::verify(User::factory()->create(), 'thread-17', '23', $token))->toBeFalse()
        ->and(CaseConversationToken::verify($actor, 'thread-18', '23', $token))->toBeFalse()
        ->and(CaseConversationToken::verify($actor, 'thread-17', '24', $token))->toBeFalse()
        ->and($signature)->toMatch('/^[a-f0-9]{64}$/')
        ->and($payload['session'])->toBe(hash('sha256', session()->getId()))
        ->and($token)->not->toContain(session()->getId())
        ->and($token)->not->toContain((string) config('app.key'));
});

test('observation token is rejected in a different configured session for the same user', function (): void {
    $actor = User::factory()->create();
    $token = CaseConversationToken::issue($actor, 17, '23');

    startCaseConversationTokenSession('case-token-session-b');

    expect(CaseConversationToken::verify($actor, 17, '23', $token))->toBeFalse();
});

test('observation token expires exactly five minutes after issuance using the Laravel clock', function (): void {
    $this->travelTo(now()->setMicrosecond(0));
    $actor = User::factory()->create();
    $token = CaseConversationToken::issue($actor, 17, '23');

    $this->travelTo(now()->addSeconds(299));
    expect(CaseConversationToken::verify($actor, 17, '23', $token))->toBeTrue();

    $this->travelTo(now()->addSecond());
    expect(CaseConversationToken::verify($actor, 17, '23', $token))->toBeFalse();
});

test('observation token rejects tampering malformed data and oversized input', function (): void {
    $actor = User::factory()->create();
    $token = CaseConversationToken::issue($actor, 17, '23');
    [$payload, $signature] = explode('.', $token);
    $tamperedPayload = ($payload[0] === 'A' ? 'B' : 'A').substr($payload, 1);

    expect(CaseConversationToken::verify($actor, 17, '23', $payload.'.'.str_repeat('0', 64)))->toBeFalse()
        ->and(CaseConversationToken::verify($actor, 17, '23', $tamperedPayload.'.'.$signature))->toBeFalse()
        ->and(CaseConversationToken::verify($actor, 17, '23', 'not-a-token'))->toBeFalse()
        ->and(CaseConversationToken::verify($actor, 17, '23', str_repeat('x', 1025)))->toBeFalse()
        ->and(fn () => CaseConversationToken::issue($actor, 17, str_repeat('9', 129)))
        ->toThrow(InvalidArgumentException::class);
});

test('observation token fails closed when the session is not started or the application key is empty', function (): void {
    $actor = User::factory()->create();
    $token = CaseConversationToken::issue($actor, 17, '23');

    session()->save();
    expect(CaseConversationToken::verify($actor, 17, '23', $token))->toBeFalse()
        ->and(fn () => CaseConversationToken::issue($actor, 17, '23'))
        ->toThrow(RuntimeException::class);

    startCaseConversationTokenSession('case-token-session-a');
    config(['app.key' => '']);
    expect(CaseConversationToken::verify($actor, 17, '23', $token))->toBeFalse()
        ->and(fn () => CaseConversationToken::issue($actor, 17, '23'))
        ->toThrow(RuntimeException::class);
});
