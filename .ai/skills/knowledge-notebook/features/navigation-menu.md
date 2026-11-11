# Navigation Menu

## Purpose and Behavior

Implemented from `.prompts/02.Navigation-Menu.md`. The shared app layout renders fixed bottom navigation for authenticated users. Login and registration omit the navigation and transaction modal. The navigation follows the 440px maximum mobile layout, centered on desktop, and stays at the viewport bottom when scrolling. Main content reserves bottom space for the bar and mobile safe area.

The five items appear in this order: Dashboard, Tags, an icon-only Add transaction button, Transactions, and Statistics. All four links use named routes and `wire:navigate.hover`; the current destination has `aria-current="page"` and an inset active treatment. Labels sit beneath Bootstrap Icons. The plus button uses the existing indigo accent and raised neumorphic treatment. Narrow phones use reduced horizontal spacing so labels fit.

Add transaction dispatches `open-transaction-modal` to a Livewire 4 single-file component. Its authenticated `open()` action sets `isOpen`; `close()` resets it. Alpine synchronizes that state with a native dialog using `showModal()` and `close()`. Native modal behavior provides focus containment and focus restoration. A close button, Escape, and a backdrop click dismiss the dialog. Its body intentionally contains no transaction fields or save action. Nothing is persisted.

## Routes

All destinations use web and auth middleware in `routes/web.php`. Guests redirect to `login`.

| Method | Path | Name | View and behavior |
| --- | --- | --- | --- |
| GET/HEAD | `/home` | `home` | `home`; Dashboard tab, preserving existing authentication redirects |
| GET/HEAD | `/tags` | `tags` | `tags`; [tag management](tags.md) |
| GET/HEAD | `/transactions` | `transactions` | `transactions`; empty Transactions page |
| GET/HEAD | `/statistics` | `statistics` | `statistics`; empty Statistics page |

The Dashboard uses the existing empty `resources/views/home.blade.php`; there is no separate `/dashboard` endpoint. Dashboard, Transactions, and Statistics content slots intentionally remain empty. Tags now mounts the `tag-manager` Livewire component. The transaction modal has no dedicated page route; it uses Livewire's framework update endpoint and requires authentication to open.

## Related Source

- Layout: `resources/views/components/layouts/app.blade.php` includes Livewire styles/scripts, reserves navigation space for authenticated users, and mounts the navigation and modal.
- Blade components: `resources/views/components/bottom-navigation.blade.php` and `resources/views/components/navigation-link.blade.php` render the five controls and active link semantics.
- Livewire component: `resources/views/components/⚡add-transaction-modal.blade.php` contains server state/actions and dialog markup. There is no separate controller or class file.
- Page views: `resources/views/home.blade.php`, `tags.blade.php`, `transactions.blade.php`, and `statistics.blade.php`.
- Styles: `resources/css/app.css` reuses galaxy tokens, neumorphic shadows, keyboard focus outlines, and safe area padding. `resources/js/app.js` remains empty; Alpine comes from Livewire.
- Models/services: none added. Authentication uses the existing user/session infrastructure. No migrations or dependency changes.

## Verification and Limits

- `php artisan test --compact tests/Feature/NavigationMenuTest.php tests/Feature/AuthenticationTest.php`: 41 passing cases, 221 assertions. Covers protected destinations, menu order and links, exactly one active item, hover directives, empty content, authentication page exclusions, modal open/close/reopen, and guest refusal.
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: passed with zero errors.
- `vendor/bin/pint --dirty --format agent`: passed.
- Windows `cmd.exe /c npm run build`: passed; existing optional font fallback warning remains.
- Headless Chrome checks passed at 320, 390, 768, and 1440px: full labels fit, no horizontal overflow, bar aligned with the centered mobile shell. Verified modal focus, close button, Escape, backdrop dismissal, client-side navigation and active links, modal reuse after navigation, fixed positioning after scrolling, and no JavaScript exceptions. The temporary preview used an unsaved factory user; no account records were created.
- Frontend design inspector passed with explicit allowances for required indigo/neumorphic controls, small navigation labels, and intentionally empty page content. Screenshots were visually reviewed. Project requirements take precedence over the design skill's neutral color and populated-page defaults.
- Tags page content and persistence are now implemented; see [Tags](tags.md). Dashboard, Transactions, Statistics, and transaction creation remain deferred. The navigation test retains empty-content assertions for those three pages while checking the shared navigation on all four destinations.
