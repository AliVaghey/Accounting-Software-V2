# Authentication

## Purpose and Behavior

Implemented from `.prompts/01.Authentication.md`. Visitors can register with a name, email, password, and password confirmation, or log in using email and password. Registration immediately authenticates the new user. Both successful flows regenerate the session and redirect to the empty home page. Authenticated visitors cannot reopen or submit login and registration forms.

Authentication pages use English labels, Bootstrap Icons, the existing locally bundled Instrument Sans font, dark galaxy indigo colors, and raised buttons with inset fields for neumorphism. The layout fills mobile screens and remains at most 440px wide, centered on desktop. Labels, autocomplete, visible keyboard focus, escaped old input, and associated validation errors are included. Password values are never repopulated.

## Routes

| Method | Path | Name | Access and behavior |
| --- | --- | --- | --- |
| GET/HEAD | `/` | None | Requires authentication; redirects to `home`; guests redirect to `login` |
| GET/HEAD | `/login` | `login` | Guest login page; authenticated users redirect to `home` |
| POST | `/login` | `login.store` | Guest credentials validation and authentication |
| GET/HEAD | `/register` | `register` | Guest registration page; authenticated users redirect to `home` |
| POST | `/register` | `register.store` | Guest account creation; five requests per minute per IP |
| GET/HEAD | `/home` | `home` | Requires authentication; empty page, reserved for future accounting features |
| POST | `/logout` | `logout` | Requires authentication; logs out, invalidates the session, regenerates the CSRF token, and redirects to `login` |

All routes are in `routes/web.php` and use Laravel web middleware, including CSRF protection. Framework health and package infrastructure routes retain their normal behavior.

## Related Source

- Controllers: `app/Http/Controllers/Auth/AuthenticatedSessionController.php` serves login, starts sessions, and logs out. `app/Http/Controllers/Auth/RegisteredUserController.php` serves registration, creates users, dispatches `Registered`, and authenticates them.
- Requests: `app/Http/Requests/Auth/LoginRequest.php` and `RegisterRequest.php` validate input and normalize string emails to trimmed lowercase. LoginRequest also authenticates and tracks failed attempts.
- Model: existing `app/Models/User.php`, with fillable name/email/password and a hashed password cast. Existing `database/factories/UserFactory.php` supports test users.
- Views: `resources/views/auth/login.blade.php`, `resources/views/auth/register.blade.php`, and the empty `resources/views/home.blade.php`.
- Components: `resources/views/components/layouts/app.blade.php`, `layouts/auth.blade.php`, and `auth-input.blade.php`. App layout uses `@fonts` and Vite entry points.
- Styling: `resources/css/app.css` defines galaxy theme tokens and shared authentication controls. `resources/js/app.js` remains the existing empty entry point.
- Services: no custom services; Laravel web authentication, sessions, hashing, validation, events, and rate limiter provide the behavior.

## Persistence and Security

No schema changes or dependencies were needed. Existing users and sessions infrastructure is reused. Only validated name, email, and password are persisted. Name and email have a 255-character limit; email must be unique after normalization. Passwords must match confirmation, satisfy the existing `Password::defaults()` policy, and have at most 72 characters. The provider's existing policy is eight characters outside production and at least twelve with mixed case, letters, numbers, symbols, and an uncompromised check in production.

Login optionally sets Laravel's remember cookie. Failed credentials return the same generic error for an unknown email or wrong password. Five failures for an email/IP pair cause a 60-second lockout, with remaining time displayed inline; successful authentication clears that pair's counter. Login returns to `home` directly, as required by the prompt. Logout is implemented as a POST endpoint; no logout control is rendered because the home page must remain empty.

Password reset and email verification flows are outside this task. The home page is now the Dashboard destination: its main content is empty, with the shared authenticated [navigation menu](navigation-menu.md) and transaction dialog available.

## Verification

- The empty-home assertion now checks the empty main content slot while allowing shared navigation and Livewire assets. `php artisan test --compact tests/Feature/NavigationMenuTest.php tests/Feature/AuthenticationTest.php`: 41 passing cases, including the existing authentication coverage and new navigation behavior.
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: passed with zero errors.
- `vendor/bin/pint --dirty --format agent`: formatter for changed PHP and Blade files.
- Windows `cmd.exe /c npm run build`: frontend build passed. The existing font plugin emitted a nonblocking warning about optional optimized fallbacks.
- Real Chrome rendering inspected at widths 1440, 768, and 390 for both authentication screens. Instrument Sans loaded. Shadow and indigo allowances follow explicit project requirements; desktop whitespace follows the centered mobile layout requirement. No mock account or sample transaction data is rendered.
- A real browser POST with an invalid email followed the redirect, displayed the associated inline error, and left the password blank. Registration navigation rendered all four fields without horizontal overflow at 390px.
