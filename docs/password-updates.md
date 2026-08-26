# Password updates

Retain `update-passwords` in `laranail.authkit.fortify.features` to enable authenticated password updates. `UpdateUserPassword` implements Fortify's `UpdatesUserPasswords` contract, requires the current password for the configured guard, validates and hashes the replacement, clears the remember token, and revokes personal tokens when available.

For the currently authenticated user, the action also asks a compatible guard to log out other devices. Invoke it only from an authenticated, CSRF-protected flow. Removing the feature prevents Auth Kit from enabling this Fortify capability; do not expose a corresponding application route or UI.

---

[← Docs index](../README.md#documentation)
