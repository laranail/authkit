# Browser sessions

Enumerating the browsers a user is signed in on, and signing the others out.

## Why it exists

There was previously no way to list a user's sessions, and no "log out other devices" action — it
existed only as a side effect of changing a password. That side effect called Laravel's
`logoutOtherDevices()`, which rotates the remembered password hash and **deletes no rows**. The other
browsers stop working on their next request, but their rows stay in the `sessions` table, so anything
rendering a device list still shows them as active. Someone who changed their password precisely
because a laptop was stolen still saw that laptop signed in.

## Requirements

Both actions read the `sessions` table, so they need `SESSION_DRIVER=database` and the sessions
migration. With the file, cookie or array driver there is no per-user index to read.

`BrowserSessionService::isSupported()` reports which case you are in. Check it before rendering:
"you have no other sessions" and "this application cannot see your sessions" are very different
answers to give someone checking their account security, and returning an empty list for both would
say the first while meaning the second.

## Listing

```php
use Simtabi\Laranail\AuthKit\Contracts\ListBrowserSessionsInterface;

$sessions = app(ListBrowserSessionsInterface::class)->execute(
    user: $request->user(),
    currentSessionId: $request->session()->getId(),
);
```

Each entry carries `id`, `ip_address`, `user_agent`, `last_activity` and `is_current_device`, most
recently active first. The current device is flagged rather than filtered out, because a device list
missing its own entry reads as though something is wrong with it.

## Signing other devices out

```php
use Simtabi\Laranail\AuthKit\Contracts\LogoutOtherBrowserSessionsInterface;

$ended = app(LogoutOtherBrowserSessionsInterface::class)->execute(
    user: $request->user(),
    currentSessionId: $request->session()->getId(),
);
```

Both halves are needed and neither is sufficient on its own. Rotating the remembered password hash is
what actually stops the other browsers on their next request; deleting the rows is what stops them
still being listed as active afterwards. A password change performs the same cleanup.

## Extending

`BrowserSessionService` owns the table name, the driver check and the current-device comparison, so
an application storing sessions elsewhere replaces that one class rather than every action above it.

---

[← Docs index](../README.md#documentation)
