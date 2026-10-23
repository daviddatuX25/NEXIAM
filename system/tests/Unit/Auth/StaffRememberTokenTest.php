<?php

declare(strict_types=1);

use App\Auth\Support\StaffRememberToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

test('n2 token binds session epoch and original interactive login time', function (): void {
    $anchor = CarbonImmutable::parse('2026-10-15T08:30:00Z');
    $factorGeneration = (string) Str::uuid();
    $token = app(StaffRememberToken::class)->issue(42, $factorGeneration, $anchor);
    $claims = app(StaffRememberToken::class)->parse($token);

    expect(str_starts_with($token, 'n2:42:'.$factorGeneration.':'))->toBeTrue()
        ->and($claims)->not->toBeNull()
        ->and($claims['session_version'])->toBe(42)
        ->and($claims['factor_generation'])->toBe($factorGeneration)
        ->and($claims['interactive_authenticated_at']->equalTo($anchor))->toBeTrue()
        ->and(substr_count($token, ':'))->toBe(4);
});

test('legacy and malformed remember tokens cannot be restored', function (string $token): void {
    expect(app(StaffRememberToken::class)->parse($token))->toBeNull();
})->with([
    'legacy n1 token' => 'n1:42:00000000-0000-4000-8000-000000000000:'.str_repeat('a', 40),
    'truncated n2 token' => 'n2:42:00000000-0000-4000-8000-000000000000:1792053000:'.str_repeat('a', 31),
]);

test('remember token issue rejects invalid epoch, generation, and anchor', function (int $epoch, string $generation, CarbonImmutable $anchor): void {
    expect(fn () => app(StaffRememberToken::class)->issue($epoch, $generation, $anchor))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'zero epoch' => [0, '00000000-0000-4000-8000-000000000000', CarbonImmutable::parse('2026-10-15T08:30:00Z')],
    'invalid factor generation' => [42, 'invalid', CarbonImmutable::parse('2026-10-15T08:30:00Z')],
    'unrepresentable anchor' => [42, '00000000-0000-4000-8000-000000000000', CarbonImmutable::createFromTimestampUTC(0)],
]);
