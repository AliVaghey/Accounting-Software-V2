# Project Foundation

## Status and Purpose

Initialized on October 24, 2026. This is a fresh Laravel blank Livewire starter kit for Accounting Software, intended to track personal transactions. No transaction management, accounting dashboard, or authentication screens have been implemented.

## Installed Stack

Verified using Laravel Boost application information and `composer show --direct` during notebook initialization:

- PHP 8.4 and Laravel 13.38.0.
- Livewire 4.4.8 and Blaze 1.0.19; no application Livewire components yet.
- Bootstrap Icons 1.13.2.
- Pest 5.3.3 with the Laravel plugin 5.0.1.
- Default database connection: SQLite. Database contents and migration execution were not inspected.

`package.json` declares Tailwind CSS 4, Vite 8, and Vite Plus 0.3.0. Use the Windows installation of npm for frontend commands, per project instructions.

## Routes and Behavior

| Method | Path | Name | Behavior | Source |
| --- | --- | --- | --- | --- |
| GET/HEAD | `/` | `home` | Public starter welcome page | `routes/web.php` |
| GET/HEAD | `/up` | Framework managed | Framework health endpoint | `bootstrap/app.php` |

The welcome route was verified with `php artisan route:list --except-vendor --no-interaction`. The health endpoint is configured by `withRouting(health: '/up')` in `bootstrap/app.php`. Package routes are outside this feature inventory.

## Related Implementation

- Controller: no application controller for either endpoint; the welcome page uses `Route::view`. `app/Http/Controllers/Controller.php` is the base controller scaffold.
- Views: `resources/views/welcome.blade.php` renders the starter welcome page.
- Frontend assets: `resources/css/app.css` and `resources/js/app.js` are the existing entry points.
- Models: `app/Models/User.php` is the starter user model, with its factory in `database/factories/UserFactory.php`. Neither endpoint requires user records.
- Services: no feature service classes. `app/Providers/AppServiceProvider.php` contains application defaults and the Boost agent registration.
- Persistence: starter migrations in `database/migrations/` define users, password reset tokens, sessions, cache, and jobs infrastructure. There are no accounting models or migrations.
- Access: neither endpoint has an application authentication requirement; there is no implemented login or registration flow.

## Requirements and Configuration

Read `.ai/guidelines/project-information.md` for the product requirements. The intended name is Accounting Software; `.env.example` and the fallback in `config/app.php` still use Laravel. The intended timezone is Asia/Tehran; `config/app.php` currently sets UTC. English is the configured locale fallback. The required mobile layout and neumorphism design remain to be implemented.

## Verification

`tests/Feature/ExampleTest.php` checks that `GET /` returns a successful response. `tests/Unit/ExampleTest.php` is the starter boolean assertion. These tests were inspected but not run for this documentation task. Skill validation applies to the notebook itself; no application code or routes were changed by initialization.
