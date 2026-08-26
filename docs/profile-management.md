# Profile management

Auth Kit exposes profile behavior through Laravel Fortify actions and abstract controllers while your application owns routes and responses.

Retain `update-profile-information` in `laranail.authkit.fortify.features` to enable profile changes. `UpdateUserProfileInformation` implements Fortify's `UpdatesUserProfileInformation` contract and accepts a required name plus a unique email address. If a user implements Laravel's `MustVerifyEmail` contract and changes email, it clears `email_verified_at` and sends a new verification notification. Extend or replace the action if the application has additional profile fields or different validation requirements.

Removing the feature prevents Auth Kit from enabling the related Fortify capability; ensure application routes and UI do not expose a disabled flow. For authenticated password changes, see [password updates](password-updates.md).