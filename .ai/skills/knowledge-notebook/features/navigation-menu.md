# Navigation Menu

## Purpose and Behavior

Implemented from `.prompts/02.Navigation-Menu.md` and extended by `.prompts/04.more-options.md`. The shared app layout renders fixed bottom navigation for authenticated users. Login and registration omit the navigation and transaction modal. The navigation follows the 440px maximum mobile layout, centered on desktop, and stays at the viewport bottom when scrolling. Main content reserves bottom space for the bar and mobile safe area.

The five controls appear in this order: Dashboard, Tags, an icon-only Add transaction button, Transactions, and More. More toggles a drop-up containing Statistics followed by Contacts. All five destination links use named routes and `wire:navigate.hover`; the current destination has `aria-current="page"` and an inset active treatment. More also receives the active treatment on Statistics, Contacts, and contact profiles. Destination links match their named subroutes, so Contacts remains active on a profile. Labels sit beneath Bootstrap Icons in the bar and beside icons in the drop-up. The plus button uses the existing indigo accent and raised neumorphic treatment. Narrow phones use reduced horizontal spacing so labels fit.

The More disclosure uses local Alpine state supplied by Livewire, with no server request to toggle it. It starts closed, uses `x-cloak` to prevent a flash before Alpine initializes, and exposes its state through `aria-expanded` and `aria-controls`. Click, Enter, or Space toggles it; Tab reaches the two links. Outside clicks, focus leaving the disclosure, and Escape dismiss it; Escape restores focus to More. The menu is positioned above its trigger within the centered mobile shell and is recreated closed after navigation.

Add transaction dispatches `open-transaction-modal` to a Livewire 4 single-file component. Its authenticated `open()` action sets `isOpen`; `close()` resets it. Alpine synchronizes that state with a native dialog using `showModal()` and `close()`. Native modal behavior provides focus containment and focus restoration. A close button, Escape, and a backdrop click dismiss the dialog. Its body intentionally contains no transaction fields or save action. Nothing is persisted.

## Routes

All destinations use web and auth middleware in `routes/web.php`. Guests redirect to `login`.

| Method | Path | Name | View and behavior |
| --- | --- | --- | --- |
| GET/HEAD | `/home` | `home` | `home`; Dashboard tab, preserving existing authentication redirects |
| GET/HEAD | `/tags` | `tags` | `tags`; [tag management](tags.md) |
| GET/HEAD | `/transactions` | `transactions` | `transactions`; empty Transactions page |
| GET/HEAD | `/statistics` | `statistics` | `statistics`; empty Statistics page |
| GET/HEAD | `/contacts` | `contacts` | `contacts`; [contact management](contacts.md) |

The Dashboard uses the existing empty `resources/views/home.blade.php`; there is no separate `/dashboard` endpoint. Dashboard, Transactions, and Statistics content slots intentionally remain empty. Tags mounts `tag-manager` and Contacts mounts `contact-manager`. Contact profiles at `/contacts/{contact}` intentionally keep their main content empty. The transaction modal has no dedicated page route; it uses Livewire's framework update endpoint and requires authentication to open.

## Related Source

- Layout: `resources/views/components/layouts/app.blade.php` includes Livewire styles/scripts, reserves navigation space for authenticated users, and mounts the navigation and modal.
- Blade components: `resources/views/components/bottom-navigation.blade.php` and `resources/views/components/navigation-link.blade.php` render the five controls and active link semantics.
- Livewire component: `resources/views/components/⚡add-transaction-modal.blade.php` contains server state/actions and dialog markup. There is no separate controller or class file.
- Page views: `resources/views/home.blade.php`, `tags.blade.php`, `transactions.blade.php`, `statistics.blade.php`, and `contacts.blade.php`.
- Styles: `resources/css/app.css` reuses galaxy tokens, neumorphic shadows, keyboard focus outlines, and safe area padding. `resources/js/app.js` remains empty; Alpine comes from Livewire.
- Models/services: none added. Authentication uses the existing user/session infrastructure. No migrations or dependency changes.

## Verification and Limits

- `php artisan test --compact tests/Feature/NavigationMenuTest.php tests/Feature/AuthenticationTest.php`: 43 passing cases, 256 assertions after adding Contacts and the More disclosure. Covers protected destinations, menu order and nested links, exactly one active destination link, More's active treatment, hover directives, empty content, authentication page exclusions, modal open/close/reopen, and guest refusal. Interactive Alpine behavior is checked separately in Chrome.
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: passed with zero errors.
- `vendor/bin/pint --dirty --format agent`: passed.
- Windows `cmd.exe /c npm run build`: passed; existing optional font fallback warning remains.
- Headless Chrome checks passed at 320, 390, 768, and 1440px: full labels fit, no horizontal overflow, bar aligned with the centered mobile shell. Verified modal focus, close button, Escape, backdrop dismissal, client-side navigation and active links, modal reuse after navigation, fixed positioning after scrolling, and no JavaScript exceptions. The temporary preview used an unsaved factory user; no account records were created.
- Frontend design inspector passed with explicit allowances for required indigo/neumorphic controls, small navigation labels, and intentionally empty page content. Screenshots were visually reviewed. Project requirements take precedence over the design skill's neutral color and populated-page defaults.
- More menu checks in headless Chrome passed at 320, 390, 768, and 1440px: upward positioning within the mobile shell, no horizontal overflow, click and Enter toggling, Tab order, Escape focus restoration, outside click and focus dismissal, both destination links, correct active state, and menu reuse after Livewire navigation. No JavaScript exceptions occurred. The isolated preview used an unsaved factory user and array sessions/cache; it created no account records. Open-menu screenshots were reviewed at all four widths.
- The Contacts frontend inspector passed at 390, 768, and 1440px with zero failures. Allowances preserve the requested empty page and existing indigo/neumorphic design. Its remaining small-text warning is the existing 11px bottom-navigation labels; the drop-up labels are 14px.
- Tags and Contacts content and persistence are implemented; see [Tags](tags.md) and [Contacts](contacts.md). Dashboard, Transactions, Statistics, and transaction creation remain deferred. The navigation test retains empty-content assertions for those three destinations and checks the shared navigation on all five destinations. `ContactsTest` covers the active Contacts destination on an empty contact profile. Contact and navigation tests pass together: 47 cases, 233 assertions.
