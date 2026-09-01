<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\AuthKit\Contracts\ListBrowserSessionsInterface;
use Simtabi\Laranail\AuthKit\Contracts\LogoutOtherBrowserSessionsInterface;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;
use Workbench\App\Models\User;

function seedSession(string $id, ?int $userId, string $agent = 'Other/1.0'): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '10.0.0.1',
        'user_agent' => $agent,
        'payload' => '',
        'last_activity' => time(),
    ]);
}

it('lists a user’s sessions, flagging the current device', function (): void {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    seedSession('current-device', $user->getKey());
    seedSession('other-device', $user->getKey());

    $sessions = app(ListBrowserSessionsInterface::class)->execute($user, 'current-device');

    expect($sessions)->toHaveCount(2)
        ->and($sessions->firstWhere('id', 'current-device')->is_current_device)->toBeTrue()
        ->and($sessions->firstWhere('id', 'other-device')->is_current_device)->toBeFalse();
});

it('does not leak another user’s sessions', function (): void {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    $other = User::factory()->create();
    seedSession('mine', $user->getKey());
    seedSession('theirs', $other->getKey());

    expect(app(ListBrowserSessionsInterface::class)->execute($user)->pluck('id')->all())
        ->toBe(['mine']);
});

it('deletes the other devices’ rows, not just their password hash', function (): void {
    config()->set('session.driver', 'database');
    $user = User::factory()->create();
    seedSession('current-device', $user->getKey());
    seedSession('other-device', $user->getKey());

    $ended = app(LogoutOtherBrowserSessionsInterface::class)->execute($user, 'current-device');

    expect($ended)->toBe(1)
        ->and(DB::table('sessions')->where('id', 'other-device')->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', 'current-device')->count())->toBe(1);
});

it('reports that it cannot see sessions on a non-database driver', function (): void {
    // "You have no other sessions" and "this application cannot see your sessions" are very
    // different answers to give someone checking their account security, so the service says
    // which it is rather than returning an empty list either way.
    config()->set('session.driver', 'file');

    expect(app(BrowserSessionService::class)->isSupported())->toBeFalse()
        ->and(app(ListBrowserSessionsInterface::class)->execute(User::factory()->create()))->toBeEmpty();
});
