<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Reads and prunes the rows behind a user's signed-in browsers.
 *
 * Everything here is only meaningful when sessions are stored in the database. With the file,
 * cookie or array driver there is no per-user index to read, so a device list cannot be built and
 * other devices cannot be signed out by deleting rows -- `isSupported()` reports that rather than
 * silently returning an empty list, because "you have no other sessions" and "this application
 * cannot see your sessions" are very different answers to give someone checking their account
 * security.
 *
 * This sits between the actions and the database on purpose: the table name, the driver check and
 * the current-device comparison are infrastructure details, and an action that reached for them
 * directly would have to be rewritten by anyone storing sessions elsewhere.
 */
class BrowserSessionService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Config $config,
    ) {}

    /** Whether sessions are stored somewhere this service can actually read. */
    public function isSupported(): bool
    {
        return $this->config->get('session.driver') === 'database';
    }

    /**
     * Every session row belonging to a user, most recently active first.
     *
     * The current device is flagged rather than filtered out, because a device list whose own
     * entry is missing reads as though something is wrong with it.
     *
     * @return Collection<int, object{id: string, ip_address: ?string, user_agent: ?string, last_activity: int, is_current_device: bool}>
     */
    public function forUser(Authenticatable $user, ?string $currentSessionId = null): Collection
    {
        if (! $this->isSupported()) {
            return new Collection;
        }

        return $this->query()
            ->where('user_id', $user->getAuthIdentifier())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): object => (object) [
                'id'                => $session->id,
                'ip_address'        => $session->ip_address ?? null,
                'user_agent'        => $session->user_agent ?? null,
                'last_activity'     => (int) $session->last_activity,
                'is_current_device' => $currentSessionId !== null && $session->id === $currentSessionId,
            ]);
    }

    /**
     * Delete every session row for a user except the one they are using.
     *
     * Laravel's own `logoutOtherDevices()` only rotates the password hash held in each session,
     * which invalidates the other browsers on their next request but leaves their rows in place.
     * Those rows keep the devices showing as active in any list built from the table, so someone
     * who has just signed out a stolen laptop still sees it there. Deleting the rows is what makes
     * the action match what it says it does.
     *
     * @return int the number of rows removed
     */
    public function deleteOthersFor(Authenticatable $user, ?string $currentSessionId = null): int
    {
        if (! $this->isSupported()) {
            return 0;
        }

        $query = $this->query()->where('user_id', $user->getAuthIdentifier());

        if ($currentSessionId !== null) {
            $query->where('id', '!=', $currentSessionId);
        }

        return $query->delete();
    }

    private function query(): Builder
    {
        return $this->db
            ->connection($this->config->get('session.connection'))
            ->table($this->config->get('session.table', 'sessions'));
    }
}
