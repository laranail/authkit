# Registration

Auth Kit is headless: it supplies actions and abstract controllers, while your application owns routes, views, and response behavior.

Use `CreateNewUser` for registration. It implements Fortify's `CreatesNewUsers` contract and validates a required name, a unique email, and a confirmed password using Laravel's default password rules. It lowercases the stored email and applies the configured Turnstile rule when Turnstile is enabled. `CheckEmailExists` is available when a multi-step registration flow needs to check an address before creating an account; do not expose its result in a way that enables broad account enumeration.

Extend `AbstractRegisterController` to connect registration to an application route. Override `registered()` when the default JSON response does not fit the application.

For session authentication and logout, see [login](login.md). Configure bot protection separately in [security](security.md).

---

[← Docs index](../README.md#documentation)
