# Contacts

## Purpose and Behavior

Implemented from `.prompts/05.Contacts.md`. Each account manages its own contacts from More → Contacts. The list is an alphabetical, two-column grid with an ID tie-breaker. It initially renders 12 contacts and automatically grows by 12 when its end sentinel enters the viewport. A Load more contacts button also permits explicit loading or retrying a failed request. The count reports the contacts currently shown. Loading stops after the last batch. Queries fetch only the visible range plus one record, using Laravel's simple paginator; every update rechecks the current account instead of keeping serialized contact data on the client.

Each card displays a circular photo or a name initial, with the name below. Tapping or using the keyboard on a card toggles three icon-only actions beneath it: Edit, Delete, and View profile. Actions start hidden with Alpine `x-cloak`/`x-show`; only one contact is expanded at a time. Every action has an accessible label. Deletion requires confirmation naming the contact. Cards and dialogs follow the existing dark galaxy indigo neumorphism, centered within the mobile shell at desktop widths.

Add contact opens a native dialog above the list. Edit loads the selected contact into the same dialog. Users can add, retain, replace, or remove an optional photo. Saving refreshes the visible list and shows a status message. Cancel, Close, Escape, and a backdrop click discard unsaved form state. The photo input is recreated when the editor resets, preventing a stale selected file when it reopens. Upload progress is shown and submission is disabled during uploads and requests. The name field initially receives focus, and native modal behavior contains focus. Alpine display bindings tolerate component teardown during Livewire navigation.

Contact profiles intentionally contain no main content yet. They retain the shared navigation and transaction placeholder dialog, and Contacts/More remain active. Transaction models and contact-transaction relationships are deferred.

## Routes

All routes are in `routes/web.php`, use `web` and `auth` middleware, and redirect guests to `login`.

| Method | Path | Name | Behavior |
| --- | --- | --- | --- |
| GET/HEAD | `/contacts` | `contacts` | Contact grid and editor |
| GET/HEAD | `/contacts/{contact}` | `contacts.show` | Owner-only empty profile; other-account IDs return 404 |
| GET/HEAD | `/contacts/{contact}/photo` | `contacts.photo` | Owner-only inline private photo; missing files and other-account IDs return 404 |

Create, edit, save, delete, close, and load-more actions use Livewire's framework update endpoint. Uploads and temporary image previews use Livewire's managed upload endpoints; there are no custom mutation routes.

## Related Source

- Livewire single-file component: `resources/views/components/⚡contact-manager.blade.php`; includes state, list query, validation, ownership checks, file handling, dialog, and cards.
- Views: `resources/views/contacts.blade.php` mounts the component; `resources/views/contacts/show.blade.php` is the empty profile. Both use `resources/views/components/layouts/app.blade.php`.
- Controllers: `app/Http/Controllers/ContactController.php::show` checks profile ownership; `app/Http/Controllers/ContactPhotoController.php` checks photo ownership and streams the private file.
- Models: `app/Models/Contact.php` and `app/Models/User.php`. `User::contacts()` is has-many; `Contact::user()` is its inverse. Contact mass assignment allows only `name` and `profile_photo`, never `user_id`.
- Migration: `database/migrations/2026_11_11_134643_create_contacts_table.php` defines ID, owner foreign key, 100-character name, nullable profile photo path, and timestamps. A composite user/name/ID index supports the scoped ordered list. Account deletion cascades contact rows.
- Factory: `database/factories/ContactFactory.php` creates a named contact without a photo and supplies an owner by default. Use `for($user)` to reuse an account.
- Optional seeder: `database/seeders/ContactSeeder.php` uses `firstOrCreate` for three sample names on existing accounts. It is not called by `DatabaseSeeder` and was not run against application data.
- Styles: `resources/css/app.css` adds contact cards, avatars, icon actions, and the file input, reusing existing dialog, input, button, and empty-state styles.
- Navigation: `resources/views/components/bottom-navigation.blade.php` recognizes `contacts.*` for More's active state; `navigation-link.blade.php` recognizes destination subroutes.
- Services: none required.

## Validation, Persistence, and Security

Names are trimmed, required, and limited to 100 characters. Duplicate names are allowed. Photos must be JPEG, PNG, or WebP images no larger than 2 MB. SVGs, GIFs, documents, and oversized files are rejected. New file previews are shown only after successful image validation.

Photos are stored with generated names under `contacts/{user_id}` on the private `local` disk (`storage/app/private`). They are served through the authenticated photo route with `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`; no public storage link is needed. Replaced or removed photos are deleted after the record saves. A newly stored photo is cleaned up if saving throws. Deleting a contact also deletes its photo. No account deletion feature exists; database cascade deletion alone does not perform file cleanup.

The component rejects unauthenticated updates in `boot()`. Every list, edit, save, and delete query uses the authenticated user's relationship. Save rechecks ownership before storing an upload. Editing IDs, pagination limits, and editor reset keys are locked against client updates. Profile/photo controllers check ownership before responding. Blade escapes names in cards, icon labels, and confirmation messages. Stored file paths are computed server-side, never accepted from form state.

## Verification

- `php artisan test --compact tests/Feature/ContactsTest.php tests/Feature/NavigationMenuTest.php`: 47 passing cases, 233 assertions. Covers successive batches and stopping, account isolation, empty state, creation, editing, validation, private photo delivery, photo retention/replacement/removal, cancellation/reset, deletion/file cleanup, ownership changes while editing, identifier/limit tampering, ended sessions, guest routes, empty profiles, active navigation, missing photos, and escaping.
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: passed, zero errors.
- `vendor/bin/pint --dirty --format agent`: passed.
- Windows `cmd.exe /c npm run build`: passed, with the existing optional font fallback warning.
- `php artisan migrate --no-interaction`: applied the contacts migration locally. No contacts were seeded into the application database.
- Frontend inspector: zero failures at 390, 768, and 1440px. Allowed checks preserve the expressly required indigo palette, neumorphic buttons/cards, and color coverage. The remaining desktop whitespace warning follows the required centered mobile layout. Source lint only flags required shadows.
- Screenshots reviewed at 320, 390, 768, and 1440px: two columns, no horizontal overflow, centered shell no wider than 440px, aligned header/list edges, readable names, and three hidden icon actions revealed beneath the selected card.
- Headless Chrome interaction checks passed: automatic 12 → 24 → 26 scrolling and termination, initially hidden card actions, photo upload/preview/private delivery, create/edit/retain/remove, file-input reset, validation, Escape/backdrop dismissal and reopening, empty profile navigation, cancelled/confirmed deletion, and editor reuse after Livewire navigation. No JavaScript exceptions remained. The preview used an isolated temporary database and temporary private disk with normal runtime configuration.
- Design reference: True-black flat, seed 395495296, adapted to the project-required indigo, neumorphism, two columns, and mobile shell. The screen answers “Who are my contacts?” through its portrait cards. All sample people used for visual checks belong to an isolated temporary preview database; they are not application records.
