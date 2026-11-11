# Contacts

## Purpose and Behavior

Implemented the empty destination requested by `.prompts/04.more-options.md`. The Contacts page has the title `Contacts · Accounting Software`, an intentionally empty main content slot, the shared authenticated bottom navigation, and the existing Add transaction dialog. Reach it through More → Contacts. More and the Contacts link receive the existing active treatment on this page.

Contact lists, forms, persistence, and relationships are deferred. This task does not implement `.prompts/05.Contacts.md`.

## Routes and Source

- `GET/HEAD /contacts`, named `contacts`, is a view route in `routes/web.php` inside the `auth` middleware group. Guests redirect to `login`.
- View: `resources/views/contacts.blade.php`, using `resources/views/components/layouts/app.blade.php`.
- Navigation: `resources/views/components/bottom-navigation.blade.php` contains the More disclosure, with the shared `navigation-link.blade.php` component for Contacts and Statistics. See [Navigation menu](navigation-menu.md) for interaction behavior.
- Styles: `resources/css/app.css`; the existing centered mobile shell, galaxy indigo palette, and neumorphic controls are reused.
- Controllers, dedicated Livewire components, models, services, migrations: none required for this empty page.

## Verification

`tests/Feature/NavigationMenuTest.php` covers guest redirection, authenticated rendering, empty content, the Contacts destination inside More, active link semantics, and shared navigation/modal presence. The navigation and authentication tests passed together: 43 cases, 256 assertions.

Headless Chrome checks passed at 320, 390, 768, and 1440px, including navigation into Contacts and subsequent navigation to Statistics. The frontend inspector passed for Contacts at mobile, tablet, and desktop widths. The empty-page allowance follows the task; the 11px navigation label warning follows the existing mobile navigation convention. See [Navigation menu](navigation-menu.md) for the detailed interaction checks.
