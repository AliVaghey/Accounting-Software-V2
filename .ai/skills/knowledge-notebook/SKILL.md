---
name: knowledge-notebook
description: Consult and maintain Accounting Software's project knowledge before working on the application and after each task, including implemented behavior, routes, and related source files.
---

# Knowledge Notebook

This notebook describes the application as implemented. Read it before taking action, then read the feature notes relevant to the task. Confirm details against source code when they may have changed.

## Project Context

Accounting Software is a minimal personal transaction tracking application. Project requirements live in [project-information.md](../../guidelines/project-information.md): English, Asia/Tehran timezone, neumorphism, dark galaxy indigo colors, Bootstrap Icons, and a mobile size layout centered on desktop. Authentication, authenticated navigation, and tag management implement the visual requirements; transaction functionality is not implemented yet.

## Feature Index

- [Project foundation](features/project-foundation.md): Installed stack, framework health endpoint (`GET /up`), infrastructure, and configuration differences. The starter welcome page has been removed.
- [Authentication](features/authentication.md): Session login, registration, remember me, logout, validation, throttling, guest redirects, and the protected Dashboard (`GET /home`, named `home`). Authentication forms use a centered mobile layout in dark indigo neumorphism.
- [Navigation menu](features/navigation-menu.md): Fixed bottom navigation on authenticated pages, active links with hover prefetching, a More drop-up linking to Statistics and Contacts, and an empty Livewire Add transaction dialog. The Tags destination contains tag management; the other destination content remains empty.
- [Tags](features/tags.md): Account-owned tags at `GET /tags` (named `tags`), an unpaginated alphabetical list of color cards, and Livewire create/edit dialogs with preset and custom colors, validation, and confirmed deletion. No transaction models or relationships are implemented.
- [Contacts](features/contacts.md): Protected empty Contacts page at `GET /contacts` (named `contacts`), reached from the More drop-up. Contact management is deferred.

## Maintaining the Notebook

After each task, update this index and the affected feature notes to reflect the verified result. For a new feature, create `features/{feature_name}.md` and add a brief description and relative link here. Keep one maintained explanation per feature rather than a chronological task log.

Each feature note must explain:

- Purpose, implementation status, and observable behavior.
- Related routes: HTTP methods, paths, route names, and access restrictions when applicable.
- Related controllers or Livewire components, views, models, and services using repository relative paths. Explicitly mark layers that do not apply.
- Relevant persistence, validation, authorization, integrations, and configuration when implemented.
- Existing verification coverage and known limitations relevant to later work. Distinguish checks actually run from tests merely present.

Record implemented facts separately from requirements or planned behavior. Update renamed or removed routes and source references. Do not store secrets, personal transaction data, or environment credentials.
