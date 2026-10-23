<?php

use App\Models\User;
use App\Staffing\Mail\StaffActivationInviteMail;
use App\Staffing\Mail\StaffSetupEmailVerificationMail;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('keeps delivered credential secrets out of persisted queue payloads', function (string $purpose): void {
    $recipient = User::factory()->create(['email' => 'private-recipient@example.test']);
    $secret = '98f35f4e1f7c6e7ea0ccfe5ec225db96';
    $fallback = 'JX4P-73QZ';
    $mail = $purpose === 'activation'
        ? new StaffActivationInviteMail($recipient, url('/staff/activate').'#v=ABCDEFGH23&t='.$secret, 'ABCDEFGH23', $fallback)
        : new StaffSetupEmailVerificationMail($recipient, $secret);
    $mail->to($recipient->email);

    // Queue persistence is isolated from the test's outer rollback transaction.
    $queued = new SendQueuedMailable($mail);
    $queued->beforeCommit();
    Queue::connection('database')->push($queued);

    $persisted = (string) DB::table('jobs')->sole()->payload;
    expect($persisted)->not->toContain($secret)
        ->not->toContain($fallback)
        ->not->toContain($recipient->email);

    $payload = json_decode($persisted, true, flags: JSON_THROW_ON_ERROR);
    $restored = unserialize(app(Encrypter::class)->decrypt($payload['data']['command']));
    expect($restored)->toBeInstanceOf(SendQueuedMailable::class);
    $rendered = html_entity_decode($restored->mailable->render(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    expect($rendered)->toContain($secret);
    if ($purpose === 'activation') {
        expect($rendered)->toContain($fallback)->toContain('ABCDEFGH23');
    }
})->with(['activation', 'email-verification']);
