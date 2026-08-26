# Password reset

Auth Kit delegates reset behavior to Laravel Fortify while exposing actions and abstract controllers for a headless application.

Enable password resets by retaining `reset-passwords` in `laranail.authkit.fortify.features`. Use `AbstractPasswordResetLinkController` to request a reset link and `AbstractNewPasswordController` to accept a reset token and new password. The application must configure mail delivery so users can receive reset links.

`ResetUserPassword` implements Fortify's `ResetsUserPasswords` contract. It validates a confirmed password with Laravel's default password rules, hashes it, clears the remember token, and revokes personal access tokens when the model exposes a `tokens()` relation. Use it through Fortify's reset-token flow or when an application-owned endpoint has already validated the reset token; do not accept a raw reset request without Laravel's broker validation.

Retaining or removing `reset-passwords` in `laranail.authkit.fortify.features` enables or disables this Fortify capability. Ensure application routes and UI do not expose a flow that has been disabled.