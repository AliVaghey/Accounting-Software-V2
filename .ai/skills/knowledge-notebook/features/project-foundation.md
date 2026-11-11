# Project Foundation

## Status and Purpose

Initialized on October 24, 2026 from the Laravel blank Livewire starter kit. Accounting Software tracks personal transactions. [Authentication](authentication.md), tags, contacts, [transaction management](transactions.md), and the [Dashboard](dashboard.md) are implemented; Statistics remains deferred.

## Installed Stack

Verified using Laravel Boost application information and `composer show --direct` during notebook initialization:

- PHP 8.4 and Laravel 13.38.0.
- Livewire 4.4.8 and Blaze 1.0.19; [navigation](navigation-menu.md) mounts the shared transaction dialog, extended into the [transaction editor](transactions.md).
- Bootstrap Icons 1.13.2.
- Pest 5.3.3 with the Laravel plugin 5.0.1.
- Default database connection: SQLite. Tag, contact, transaction, and tag-attachment migrations have been applied locally; see their feature notes.

`package.json` declares Tailwind CSS 4, Vite 8, and Vite Plus 0.3.0. Use the Windows installation of npm for frontend commands, per project instructions.

## Routes and Behavior

| Method | Path | Name | Behavior | Source |
| --- | --- | --- | --- | --- |
| GET/HEAD | `/` | None | Redirects guests to login and authenticated users to `/home` | `routes/web.php` |
| GET/HEAD | `/home` | `home` | Persian date, monthly tag statistics, deferred latest transactions, and shared navigation | `routes/web.php` |
| GET/HEAD | `/tags` | `tags` | [Account-owned tag management](tags.md) with shared navigation | `routes/web.php` |
| GET/HEAD | `/transactions` | `transactions` | [Private transaction ledger and editor](transactions.md) with shared navigation | `routes/web.php` |
| GET/HEAD | `/statistics` | `statistics` | Empty authenticated Statistics page with shared navigation | `routes/web.php` |
| GET/HEAD | `/contacts` | `contacts` | [Private contact management](contacts.md) with shared navigation | `routes/web.php` |
| GET/HEAD | `/contacts/{contact}` | `contacts.show` | Protected empty contact profile | `ContactController::show` |
| GET/HEAD | `/contacts/{contact}/photo` | `contacts.photo` | Owner-only private profile photo response | `ContactPhotoController` |
| GET/HEAD | `/up` | Framework managed | Framework health endpoint | `bootstrap/app.php` |

The welcome page has been removed. Application routes are verified with `php artisan route:list --except-vendor --no-interaction`; see [authentication](authentication.md) for authentication endpoints. The health endpoint is configured by `withRouting(health: '/up')` in `bootstrap/app.php`. Package routes are outside this feature inventory.

## Related Implementation

- Controller: root redirect and the home view use route definitions directly. Authentication has two controllers; see its feature note. `app/Http/Controllers/Controller.php` is the base controller scaffold.
- Views: `resources/views/home.blade.php` mounts the Dashboard's Livewire components, using `resources/views/components/layouts/app.blade.php`.
- Frontend assets: `resources/css/app.css` and `resources/js/app.js` are the existing entry points.
- Models: `app/Models/User.php` is the starter user model, with its factory in `database/factories/UserFactory.php`. Neither endpoint requires user records.
- Services: no feature service classes. `app/Providers/AppServiceProvider.php` contains application defaults and the Boost agent registration.
- Persistence: starter migrations in `database/migrations/` define users, password reset tokens, sessions, cache, and jobs infrastructure. Tags, contacts, and transactions have account-owned models and migrations; transactions attach multiple tags through an indexed pivot.
- Access: `/`, `/home`, `/tags`, `/transactions`, `/statistics`, `/contacts`, and contact profile/photo routes require authentication. `/up` remains a framework health endpoint.

## Requirements and Configuration

Read `.ai/guidelines/project-information.md` for the product requirements. The UI names the product Accounting Software; `.env.example` and the fallback in `config/app.php` still use Laravel. The intended timezone is Asia/Tehran; `config/app.php` currently sets UTC. English is the configured locale fallback. Authentication implements the required mobile layout, dark indigo palette, and neumorphism.

## Verification

`tests/Feature/ExampleTest.php` now checks that guests at `GET /` are redirected to login. `tests/Feature/AuthenticationTest.php` covers authentication behavior; see its feature note for verification. `tests/Unit/ExampleTest.php` remains the starter boolean assertion.
