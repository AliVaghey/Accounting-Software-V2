# Dashboard

## Purpose and Behavior

Implemented from `.prompts/07.Dashboard.md`. The authenticated Dashboard answers “Which tags appear in this Persian month's transactions, and what happened most recently?” Sections appear in order: today's date, a monthly statistics card, and recent transactions. The existing Instrument Sans, Bootstrap Icons, galaxy indigo neumorphism, and centered mobile shell (maximum 440px) are reused.

Today's date uses the Persian calendar, English month names and digits, and Asia/Tehran time, for example `Wednesday, 20 Aban 1405`. PHP's installed `intl` extension and ICU supply Persian dates and month lengths. The current month starts at Tehran midnight on its first day and ends exclusively at the next month's Tehran midnight. Boundaries convert to UTC for querying stored timestamps. This includes the entire Persian month, independently of Gregorian month boundaries or the recent-list limit, and handles 31-day months, 30-day months, leap Esfand, and Nowruz. Global application/storage timezone configuration remains UTC.

The statistics card shows the Persian month and total monthly transaction count. Only owned tags attached to owned transactions within that month appear. Tags sort by usage count descending, then name and ID. Each legend row contains the tag's stored color, escaped name, sum of the full positive amounts of its transactions (both income and expense), and its percentage of all owned monthly transactions, including untagged transactions in the denominator. Amounts use thousands separators; percentages use one decimal. No currency is invented.

The user explicitly chose to count each tag use equally in the pie and show transaction percentages in the legend. Therefore, a transaction with two tags contributes one use to each tag. Pie slices normalize tag-use counts to 100%; they are unrelated to amounts. Legend percentages may sum above 100% when tags overlap. Each tag includes the full amount of every matching transaction. The color-only SVG pie contains no tag-name or percentage labels; its accessible title/caption explain the measure. The legend underneath provides the values. A keyboard-accessible “How these totals are counted” disclosure explains multi-tag counting, combined income/expense amounts, and untagged entries. The pie uses SVG circle strokes rather than a new chart dependency.

An empty month displays a neutral empty pie, zero transactions, explanatory text, and an Add transaction action. A month containing only untagged transactions displays its actual count and explains how to add tags. Unused tags do not appear. No sample accounting figures are rendered or inserted into application data.

Recent transactions use Livewire 4's `defer` parameter, which renders a loading placeholder in the initial response and loads the component after the page is ready, even below the fold. It shows at most the latest 30 owned records across all months, ordered by creation time descending and ID descending. There is no pagination or load-more control; View all opens the existing `transactions` route. Counts describe the rendered records. An empty ledger offers Add transaction. The placeholder and rendered component both use a `section` root.

Recent cards reuse the transaction ledger's shared Blade card: signed green income/red expense amounts, Persian creation timestamps, descriptions, owned contacts/tags, owner name, and disclosure actions. Edit opens the existing shared editor; deletion requires confirmation. Saves refresh both the monthly card and recent list. Deletion refreshes the list, displays a status message, and dispatches `transaction-deleted` to refresh monthly statistics. Re-querying preserves the 30-record cap and fills the gap after a deletion when older records exist.

## Routes

- `GET|HEAD /home`, named `home`, retains its existing `Route::view` definition in `routes/web.php` with web/auth middleware. Guests redirect to `login`.
- `GET|HEAD /transactions`, named `transactions`, is the View all destination.
- Shared add/edit/deletion and component refreshes use Livewire's managed update endpoint. No custom dashboard or mutation routes were added.

## Related Source

- Page view: `resources/views/home.blade.php` mounts `dashboard-overview` followed by `recent-transactions defer` inside the existing app layout.
- Overview SFC: `resources/views/components/⚡dashboard-overview.blade.php` owns Persian date/month boundaries, scoped aggregate queries, pie/legend data, empty states, and save/delete event listeners.
- Recent SFC: `resources/views/components/⚡recent-transactions.blade.php` owns the loading placeholder, scoped/eager-loaded limited query, deletion, save refresh, and recent-list markup.
- Shared Blade card: `resources/views/components/transaction-card.blade.php` is reused by recent transactions and `resources/views/components/⚡transaction-manager.blade.php`. Prefixes keep action IDs and tag keys distinct. The parent provides disclosure state, owner name, and the scoped delete action.
- Existing editor: `resources/views/components/⚡add-transaction-modal.blade.php`; existing layout/navigation mount and open it.
- Models: existing `User::transactions()`, `User::tags()`, `Tag::transactions()`, and `Transaction` contact/tag relationships and `persianCreatedAt()`.
- Styles: the dashboard statistics surface in `resources/css/app.css`; existing transaction and navigation styles are reused. No custom JavaScript asset is needed.
- Controllers/services: none added; business queries remain in Livewire SFCs, consistent with existing management screens. No migrations, dependencies, or model changes were needed.

## Security and Query Behavior

Both SFCs reject unauthenticated rendering/updates in `boot()`. All queries start from the current user's relationships. Monthly aggregate subqueries also restrict transaction ownership, so stale cross-account pivot attachments do not expose foreign amounts. Recent eager loads restrict contact/tag ownership, preventing reassigned related records from leaking names. Deletion uses the current user's transaction relationship and returns 404 for foreign IDs. Client state cannot supply an owner, date range, or list limit. Free text is Blade-escaped; colors come from the existing validated tag color field.

Monthly counts/sums are database aggregates, not a full hydrated transaction list. Recent contact/tag data are eager-loaded for the capped query. Computed properties cache their result within a render; save/delete events clear the relevant values.

## Verification

- `tests/Feature/DashboardTest.php` covers initial placeholder ordering, Persian/Tehran/leap/year boundaries, inclusive/exclusive month filters, whole-month aggregates beyond 30 records, amount-versus-frequency percentages, overlapping tags, empty/untagged states, stale cross-account attachments, stable recent ordering, full card details, save/delete refreshes, foreign deletion refusal, escaping, guest refusal, and expired sessions.
- `php artisan test --compact tests/Feature/DashboardTest.php tests/Feature/TransactionsTest.php tests/Feature/NavigationMenuTest.php tests/Feature/AuthenticationTest.php` passed: 106 cases, 584 assertions. Authentication's former empty-home assertion now checks the dashboard components; navigation retains an empty-content assertion only for Statistics.
- PHPStan passed with zero errors; Pint passed. Windows `cmd.exe /c npm run build` passed with the existing optional font fallback warning.
- Headless Chrome verified the initial placeholder and subsequent deferred loading, three correctly colored/normalized slices, creation/edit updates in both components, cancellation/deletion, refreshing totals after deletion, View all and back navigation, no HTTP/JavaScript errors, no horizontal overflow at 320/390/768/1440px, and the 440px maximum shell. Screenshots were visually reviewed. Browser actions used a separate temporary copy of an existing preview database; application data were untouched.
- Frontend design reference: True-black flat, seed 1495357993, adapted to the explicit indigo, neumorphism, mobile-width shell, and stored tag colors. Its face/signature is the Persian month with the user's own colored tag distribution. Project requirements override the skill's neutral palette/shadow defaults. The final inspector passed with zero failures at 390/768/1440px, permitting required neumorphic effects and content passing behind fixed navigation. Its two warnings concern desktop side space (the required mobile frame) and small metadata text (existing card/nav typography). CSS source lint reports only required neumorphic shadows. No sample values need replacing in production.
