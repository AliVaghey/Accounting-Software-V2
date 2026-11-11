# Tags

## Purpose and Behavior

Implemented from `.prompts/03.Tags.md`. Each authenticated account manages its own tags. The Tags screen shows every owned tag alphabetically by name, with an ID tie-breaker, and no pagination. The visible count uses the same collection as the cards. Accounts without tags see a first-tag empty state.

An Add tag button above the list opens a native dialog for a name and color. Six named preset swatches and a native custom color picker update deferred Livewire state. The same dialog loads existing values for editing. Saving refreshes the cards and shows a status message. Each card uses the exact selected background color; relative luminance determines black or white foreground text. Cards follow the established neumorphic styling within the 440px maximum centered mobile layout. Swatches use two rows on narrow screens to preserve touch targets. Dismissal immediately synchronizes client open state before requesting server cleanup, so a bundled close/reopen still reopens the native dialog. The hex label tolerates Livewire component teardown during navigation.

Each card has labeled Edit and Delete buttons. Delete asks for confirmation naming the tag before removing it. Escape, backdrop click, Cancel, and Close dismiss the editor and discard unsaved values. Opening a fresh editor clears validation and editing state. Native dialog behavior contains focus and initially focuses the name field.

## Routes

- `GET|HEAD /tags`, named `tags`, in `routes/web.php`, uses web and auth middleware. Guests redirect to `login`.
- Create, edit, save, close, and delete use Livewire's framework update endpoint; there are no custom mutation routes.
- [Transactions](transactions.md) uses the existing authenticated `/transactions` route and the shared Add transaction editor. `Tag::transactions()` provides the inverse many-to-many relationship through `tag_transaction`; deleting a tag removes its attachments while preserving transactions.

## Related Source

- Page: `resources/views/tags.blade.php`, inside the existing app layout.
- Livewire component: `resources/views/components/⚡tag-manager.blade.php`. Contains the list query, form state, actions, validation, and dialog/card markup. No separate controller or service is needed.
- Models: `app/Models/Tag.php` and `app/Models/User.php`. `User::tags()` is a has-many relationship; `Tag::user()` is its inverse. `Tag::foregroundColor()` computes the readable card foreground from linearized RGB luminance.
- Migration: `database/migrations/2026_11_11_124214_create_tags_table.php` creates tag ID, owner foreign key, name (60 characters), color (7 characters), and timestamps. A user/name index supports the scoped list; deleting an account cascades to its tags.
- Factory: `database/factories/TagFactory.php` generates a tag and its owner by default; use `for($user)` to reuse an owner.
- Optional seeder: `database/seeders/TagSeeder.php` adds Groceries, Transport, and Bills to existing accounts with `firstOrCreate`. It is not called by `DatabaseSeeder` and was not run against application data.
- Styles: `resources/css/app.css` adds card, action, empty state, swatch, custom picker, and Cancel button styles and reuses existing authentication controls and dialog styling.

## Persistence, Validation, and Access

Names are trimmed, required, and limited to 60 characters. Duplicate names are allowed. Colors must be exactly `#` followed by six hexadecimal digits and are stored in lowercase. Only validated name and color are mass assignable; ownership is set through the authenticated user's relationship.

The component checks authentication on every request. List, edit, save, and delete queries use the current account's relationship. Other-account IDs return 404. The editing identifier is locked against client changes, and saving rechecks ownership instead of trusting the earlier edit lookup. Blade escapes names in card text, accessible labels, and delete confirmation. Color validation prevents CSS injection through the dynamic style values.

## Verification

- `php artisan test --compact tests/Feature/TagsTest.php tests/Feature/NavigationMenuTest.php`: 39 passing cases, 173 assertions. Covers the full 26-tag list, account isolation, creation, editing, invalid inputs, state reset, deletion, ownership changes while editing, identifier tampering, expired sessions, escaping, color contrast, and existing navigation behavior.
- `php vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: zero errors.
- `vendor/bin/pint --dirty --format agent`: passed.
- Windows `cmd.exe /c npm run build`: passed, with the existing optional font fallback warning.
- Headless Chrome: reviewed the list at 320, 390, 768, and 1440px and the editor/validation state at 320px. No horizontal overflow; the shell stays at most 440px wide. Verified create/edit with custom color, initial input focus, validation, Escape, backdrop dismissal, rapid reopening, editor use after Livewire navigation, and both cancelling and confirming deletion. No JavaScript exceptions after the fixes. The preview used only an isolated temporary database.
- Frontend inspector: zero failures at 390, 768, and 1440px, with allowances for required indigo/neumorphic controls and colored cards. Its remaining desktop whitespace warning follows the explicit centered mobile layout requirement. Source lint only flagged the required neumorphic shadows. Screenshots were visually reviewed; color belongs to tags, counts match rendered cards, card edges align, and no chart or invented accounting data appears.
- The new migration has been applied to the local application database. No tags were seeded there.
- Design direction: True-black flat, seed 633943387, adapted to the explicitly required galaxy indigo, neumorphic surfaces, colored cards, and centered mobile frame. These project requirements take precedence over the design skill's neutral palette, shadow, and color-budget defaults.
