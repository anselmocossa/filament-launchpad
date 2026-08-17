# Changelog

All notable changes to `filament-launchpad` will be documented in this file, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

## [1.8.2] - 2026-08-17

### Fixed
- **The launchpad rebuilt its whole tree on every call, and asked the database
  about every single item's visibility, one item at a time.** On a real portal
  a single page ran **636 queries — 532 of them identical** — and built the
  spaces **seven times**.

  Three changes, no behaviour difference:
  - `getSpaces()` remembers what it built, per request and per viewer. A page
    that asks four times now builds once. The memory is dropped whenever any
    launchpad model is saved or deleted, so someone editing a space still sees
    their edit in the same request.
  - `visibilityRoles` is eager-loaded down the whole tree, and `isRestricted()`
    / `visibleToRoleIds()` read the loaded relation instead of firing their own
    query per space, page, section and card.
  - The viewer's own role ids are resolved once instead of once per card.

  Three tests cover it, including one that asserts the query count does not
  grow when the number of cards doubles.

## [1.8.1] - 2026-08-17

### Fixed
- **The panel home died with a 500 and an exhausted memory limit, on any panel
  where a card points at a Shield-gated page.** Regression introduced by 1.8.0.

  Building the spaces evaluates every card's `canAccess()`. A card pointing at
  a page whose gate asks Shield for permission labels makes Shield read those
  labels by calling `getTitle()` on every registered page — the launchpad's own
  included. And `getTitle()`, as of 1.8.0, resolved the active space's name,
  which built the spaces again. Round and round until the process ran out of
  memory: a blank 500 in the browser, and nothing in the application log,
  because the process dies before it can write the line.

  Two guards, either of which breaks the cycle:
  - `getTitle()` returns the fallback title straight away when there is no
    active space. An instance that never went through `mount()` — which is
    exactly how Shield builds it — has nothing to resolve, and now does not go
    to the database to find that out.
  - `getSpacesFromDatabase()` refuses to re-enter. Whoever asks for the spaces
    while they are being built is told there are none yet, instead of starting
    a second build.

## [1.8.0] - 2026-08-17

### Changed
- **The browser tab now says where the user is.** `getTitle()` returned the
  brand name on every space and every page, so a user with three tabs open saw
  three tabs reading "Launchpad — Portal" and could not tell them apart. It now
  resolves to the active page's label, falling back to the space's label, and
  only then to the brand name. When page and space share a name (a space with a
  single, eponymous page) it is not repeated — "Cursos", not "Cursos — Cursos".

  This changes the title of an existing page, so it is a behaviour change
  rather than a fix: hosts that relied on the tab reading the brand name should
  call `->title('…')`.

### Added
- **`->title()` on the plugin**, to override what the tab says. Accepts a plain
  string for a fixed title, or a closure receiving the active
  `?LaunchpadSpace` and `?LaunchpadPage`:

  ```php
  LaunchpadPlugin::make()
      ->title(fn (?LaunchpadSpace $space, ?LaunchpadPage $page): string =>
          $page?->getLabel() ?? 'Portal')
  ```

  Passing `null` restores the default. The panel name is still appended by
  Filament itself, so only the specific part belongs here.

## [1.7.1] - 2026-08-14

### Fixed
- **The page polled every ~2 seconds, and eventually threw the Livewire error
  screen at the user.** `wire:poll.keep-alive` carried no interval, so Livewire
  used its ~2s default and every cycle re-rendered the whole page — meaning
  every KPI queried the database again. A refresh slower than the interval let
  the next one start before the previous finished; the requests piled up until
  one exceeded `max_execution_time`, and a failed Livewire request paints the
  error screen over whatever the user was doing. Measured on a real panel:
  60 requests in 8 minutes, one of them 32 seconds long.

  Now `wire:poll.60s.keep-alive`. These are indicators that move over hours or
  days — how many active staff, how many appraisals still open — so a minute is
  frequent enough, and it still keeps the session alive, which is what the
  `.keep-alive` is for.

## [1.7.0] - 2026-08-14

### Added
- **A narrow widget can now share a row with the tiles before it.** Until now a
  widget always closed the tile row and started its own, so a layout of "two
  indicators and a banner beside them" was impossible — the banner took a full
  row of its own and pushed everything down. A widget whose `columnSpan` is
  under 12 now sits in the same row as the preceding tiles, provided it fits:
  the row is measured in the widgets' 12-column grid, where each tile counts as
  2. It falls back to its own row when there is no space, and everything
  collapses to full width on mobile.

  Extracted the tile markup to `pages/partials/tile.blade.php` so the mixed row
  and the tiles-only row share it instead of carrying sixty duplicated lines.

## [1.6.6] - 2026-08-14

### Fixed
- **Clicking "Home" landed the user on `/?space=3&page=5`.** The first space and
  its first page are exactly what the launchpad picks when the URL carries no
  parameters at all, so spelling them out turned the panel's canonical address
  into a deep link. Worse, it is a deep link built on database ids: the URL a
  user copies or bookmarks pins today's space and page, and a reordered
  launchpad — or a restored database with different ids — sends them somewhere
  else, or nowhere. Home now redirects to the bare panel root. The parameters
  still appear for anywhere that is not the default, where they actually carry
  information.

## [1.6.5] - 2026-08-14

### Fixed
- **The card catalog offered cards the user cannot reach, and adding one broke
  the page.** Cards are a single global catalog, but resources and pages are
  registered PER PANEL and gated per user. The "available cards" sidebar showed
  all of them regardless, so a user on a staff panel was offered admin-only
  entries: adding one either 403'd or — when the resource is not registered in
  that panel at all — threw `RouteNotFoundException` and replaced their home
  page with a stack trace. The catalog now only offers cards whose target is
  registered in the current panel AND passes `canViewAny()` / `canAccess()`.
  Cards with no class target (plain URLs, actions, widgets) are untouched.

- **`Tile::getUrl()` no longer lets an unresolvable target take the page down.**
  It degrades to no target, so the tile renders inert — the same treatment a
  throwing KPI closure already got. The catalog filter above should stop such a
  card being offered in the first place, but one that was seeded, imported, or
  left behind by a resource that has since moved panels must not be able to
  break the render.

## [1.6.4] - 2026-08-14

### Fixed
- **"Edit Home" was treated as a management page, so `shield:generate` took
  personalisation away from every end user.** `EditHome` drives the same
  builder as the management tree, and 1.6.3 classified it accordingly —
  strict. But it is an end-user destination: this person customizing THEIR OWN
  home (the `user` layer), reached from the user menu, not the shared
  configuration. Once `View:EditHome` was generated and granted only to
  `super_admin`, every other user got a 403 on `/edit-home` and lost the
  ability to arrange their own tiles. It now tolerates an unconfigured
  permission, exactly like the home page it belongs to.

  The management path — `PageResource` / `BuildLayout`, and the
  Space/Page/Section/Card abilities — stays strict. The distinction is who the
  page is *for*, not which builder it happens to render.

## [1.6.3] - 2026-08-14

### Fixed
- **`shield:generate` locked every user out of the panel's home page.** The
  command creates the `View:Launchpad` permission row and grants it to
  `super_admin` in the same run — so the instant it is executed, the permission
  exists (which is what the gate keyed on) while every other role holds nothing.
  The result was a 403 at the front door for the entire host app: not a hidden
  tile, but no way in at all, and no clue as to why. The home page now treats a
  permission that nobody (bar the catch-all `super_admin`) has been given as
  still unconfigured, and stays open until someone actually decides who should
  hold it. The moment it is granted to any role or user, the gate is live again.

  The tolerance is opt-in per call (`LaunchpadPermission::check(...,
  tolerateUnconfigured: true)`) and only the home page opts in. Management
  abilities — Space/Page/Section/Card and `EditHome` — stay strict on purpose:
  there, "nobody was granted it" has to keep meaning "nobody gets in", or
  regenerating permissions would hand the launchpad's own configuration to
  every authenticated user.

## [1.6.2] - 2026-08-13

### Fixed
- **A worded badge printed over the tile title.** The badge was
  `position:absolute` in the tile corner while the title reserved a fixed
  `padding-right:26px` — room for a two-character badge such as `24` and nothing
  more. Anything longer ("3 waiting on HR") ran straight across the title. Title
  and badge now share a flex row: the title takes the remaining space and
  truncates at two lines, the badge keeps its natural width up to 60% of the
  tile. In the layout builder, where the tile corner is already taken by the
  remove and lock controls, the badge stays absolute but is capped and
  ellipsised instead.

## [1.6.1] - 2026-08-13

### Fixed
- **Clicking a tile with no target raised a placeholder notification.** A tile
  with neither an action nor a URL popped up *"Abrir «title»"* — a development
  placeholder that shipped by accident. It told the user their click had
  registered while nothing happened, which reads as a broken link rather than as
  a tile that is deliberately inert. Such a tile is now silent; a tile that
  should navigate is fixed by giving it a target, not by announcing it has none.

## [1.6.0] - 2026-08-13

### Fixed
- **Multi-panel apps: the launchpad took down the other panels.** Both render
  hooks were registered globally rather than scoped to the panel that booted the
  plugin, so they also fired on panels where the launchpad was never registered.
  Their views call `LaunchpadPlugin::get()`, which threw *"Plugin [launchpad] is
  not registered for panel [...]"* and returned a 500 on every page of those
  panels. They now render only inside the panel that registered the plugin.
- **SQL Server: the launchpad could not render at all.** Every query that
  re-ordered an already-ordered relation ended up with the same column twice in
  the `ORDER BY`, which MySQL and Postgres tolerate but SQL Server rejects with
  *"A column has been specified more than once in the order by list"*. The home
  page, the page builder and the Sections, Pages and Cards relation managers all
  hit it. They now call `reorder()` before applying their own ordering, which
  clears the ordering inherited from `Page::sections()`, `Space::pages()` and
  `Section::cards()`.

### Added
- **Optional or mandatory Permission field.** `->visibilityRolesRequired()` lets
  a host decide whether an item may be saved with nobody chosen. The field stays
  optional by default — an empty field means "everyone can see", which is right
  where the launchpad is a convenience over a panel people already reached. It
  is wrong where the launchpad IS the way in and every item belongs to somebody:
  there, saving with the field blank quietly publishes the item to the whole
  installation, and nothing on screen says so.

  ```php
  LaunchpadPlugin::make()
      ->visibilityRolesRequired()

  // or per form — mandatory inside a tenant panel, optional in the primary one
  LaunchpadPlugin::make()
      ->visibilityRolesRequired(fn (): bool => tenancy()->tenant() !== null)
  ```

  The switch reaches every item that shares the field (Space, Page, Section,
  Card), and the placeholder and hint change with it, so the form stops
  promising that an empty field lets everyone see. Left unset, nothing changes
  on upgrade.

- **Installation-level gate for the management resources.** `->resourceAccess()`
  lets a host application decide whether Launchpad's own Space/Page/Section/Card
  resources are reachable at all. The plugin's policies already answer "may this
  person manage spaces?"; they cannot answer "does this installation include
  Launchpad?" — a licence tier, a feature flag, an activated module. A host that
  sells its panel in parts had no way to express that without editing files in
  `vendor/`. The callback receives the resource's class name, so one closure
  answers for all four:

  ```php
  LaunchpadPlugin::make()
      ->resourceAccess(fn (string $resource): bool => $tenant->hasModule('core'))
  ```

  The predicate runs **in addition to** the resource's policy, never instead of
  it — it can hide a resource, never expose one the policy would have refused.
  Left unset (the default), nothing changes on upgrade. Soft like every other
  gate in this plugin: a predicate that throws degrades to "allowed" rather than
  taking the panel down.

- **Configurable visibility-role scoping.** `->visibilityRolesQuery()` lets a
  host application constrain the roles offered by Launchpad's Permission field
  with its own Eloquent query. This supports tenant, organisation, guard,
  status, or other domain-specific isolation without coupling the public
  plugin to any one tenancy implementation. The callback applies to Spaces,
  Pages, Sections, Cards, and the drag-and-drop card editor; leaving it unset
  preserves the existing unrestricted role list.

## 1.5.2 - 2026-07-23

### Fixed
- **Removed store-specific wording from a public string.** The `spaces_intro`
  subheading said "your store's…" / "da sua loja" in every locale; a generic,
  domain-agnostic plugin must not assume the host is a store. Reworded to
  "Your spaces, pages and cards…" (and the pt/pt_PT/pt_BR equivalents). Apps
  that want domain-specific wording can override the translation key.

## 1.5.1 - 2026-07-23

### Fixed
- **Tenant selector crashed the page with `SvgNotFound`.** The store selector
  action referenced a non-existent heroicon (`heroicon-o-building-tenant`),
  throwing a 500 on any resource page where the selector renders (e.g. the
  Spaces list). Swapped for the valid `heroicon-o-building-office-2`.

## 1.5.0 - 2026-07-23

Multi-tenant launchpad. A single install can now serve many tenants: each one
inherits a shared template and customises it in isolation, like a Windows
profile over the system defaults. Fully opt-in — an install that never wires a
tenant resolver behaves exactly as before.

### Added
- **Per-tenant management of the whole tree.** With `->autoRegisterResources()`
  on, a tenant manages its own Spaces/Pages/Sections/Cards, scoped so it only
  ever sees the shared template plus its own records — never another tenant's.
- **Injectable, host-owned configuration** (the plugin never learns what a
  tenant *is*): `->tenantResolver(fn () => …)` (current tenant id),
  `->tenants(fn () => [id => label])` (the list the parent may author for),
  `->primaryManager(fn () => bool)` (who may author the shared template), and
  `->tenantInheritance('fork' | 'readonly' | 'shared')`.
- **Copy-on-write inheritance (`fork`, default).** Editing an inherited record
  inside a tenant forks a private, deep-copied working subtree; deleting it
  hides it for that tenant alone (a tombstone). The shared template and every
  other tenant stay untouched.
- **Parent tooling in the admin panel**: a store selector to author a given
  tenant's launchpad in place (with a per-tenant change count) and a panel
  selector to reach another panel's template.

### Changed
- Card target dropdowns are filtered by each target's `canAccess()`, so an
  authoring dropdown no longer leaks the labels of modules the user cannot open.
- The icon picker tolerates a value outside its curated list (a seeded icon no
  longer blocks every save with an "invalid icon" error).
- Domain-neutral wording throughout — no assumptions about the host's domain.

### Fixed
- Deleting an inherited record from any path (table, header, relation manager,
  bulk) hides it per-tenant instead of destroying the shared row for everyone —
  enforced centrally at the model layer.
- Forking copies strictly real table columns, so a record loaded with a
  query-time aggregate (e.g. `pages_count`) no longer breaks the insert.

## 1.4.2 - 2026-07-13

### Fixed
- **Resource-page URL cards are gated by the resource, not the page**: a `url` card pointing at a Filament resource index (e.g. `/store/payroll-runs`) resolves to the resource *page* class, whose inherited `canAccess()` is permissive (always `true`) — so the tile showed and clicking it hit a 403. Resource-page targets now must also clear the underlying resource's own `canAccess()`, matching its real authorization (plan/module + policy). Plain `resource`/`page` targets are unchanged.

## 1.4.1 - 2026-07-13

### Fixed
- **Permission-aware cards**: cards whose target Resource/Page the user cannot access are now hidden from the launchpad; `url` cards are likewise gated by resolving the route's page/controller `canAccess()`.
- **Auto-provisioned home**: `Edit Home` now creates a default Space + Page + Section when none exists (instead of failing); the default-home seeder is skipped while running unit tests.
- **Card deletion cleanup**: deleting a `Card` now removes its personal `UserCard` rows (the `card_id` FK was dropped, so cleanup lives in the model).

## 1.4.0 - 2026-07-09

### Added
- **Class-based card presets**: the builder's "Card Library" presets can now be `CardPreset` classes (auto-discovered under `app/Filament/Launchpad`) instead of one big `cardLibrary([...])` array — register with `->cards([...])` / `->discoverCards(in:, for:)`, toggle with `->autoDiscoverCards()`. `BaseCardPreset` derives the `key()` from the class name. The legacy `cardLibrary([...])` array still works and is merged with the class-based presets (class presets win on key collision).
- **`make:launchpad-card`**: scaffolds a `CardPreset` class (auto `Card` suffix, optional `--model=` subfolder).

## 1.3.1 - 2026-07-09

### Fixed
- **Cross-tenant KPI cache leak**: cached KPI values are now keyed by the source's `cacheKey()` (default = the source key) instead of the bare key. Tenant- or context-scoped sources can override `cacheKey()` to append the tenant id so a cached value is never served to a different tenant within the TTL window.

## 1.3.0 - 2026-07-09

### Added
- **Live KPI engine**: KPI tile values are provided by discoverable `KpiSource` classes returning a rich `KpiResult` (value + unit + trend + badge), resolved lazily with per-request memoization and an optional per-source cache TTL via `cacheFor()`. `BaseKpiSource` derives `key()`/`label()` from the class name (stripping the `Kpi` suffix).
- **KPI registration**: register sources explicitly with `->kpis([...])`, or let the plugin auto-discover every `KpiSource` under `app/Filament/Launchpad` (recursive). Auto-discovery disables itself once you register manually and can be toggled with `->autoDiscoverKpis()`. The legacy `->kpiSources(['name' => fn () => ...])` closures keep working unchanged.
- **Per-panel KPI scoping**: a `KpiSource` may implement `panels(): array` to limit itself to specific panels (empty = all panels).
- **Generator commands**: `make:launchpad-kpi` and `make:launchpad-widget` scaffold classes into `app/Filament/Launchpad` (or a `--model=` subfolder), enforcing the `Kpi`/`Widget` class-name suffix (à la Filament's `...Resource`/`...Exporter`). The generated KPI stub is self-documenting.
- **`cardGlobalSearch()`**: global "search by card" now registers independently of `autoRegisterResources()`, so hiding the management resources no longer disables card search (on by default).
- **`autoRegisterResources()`**: toggle to skip registering the management resources (Spaces/Pages/Sections/Cards) in a panel.

### Fixed
- The topbar back button (`‹`) now falls back to the browser history when there is nothing left to walk up to (the launchpad root, or a resource opened from a tile), instead of being a dead no-op.
- The card form's live-source `Select` now lists sources by their human `label()` instead of the raw key.

## 1.2.2 - 2026-07-09

### Fixed
- Avoided `Route [filament.{panel}.pages..] not defined` when the Launchpad page uses the root `/` slug and another panel page owns the panel home route.
- The global sub-nav and fallback card URLs now use the current Filament panel root URL instead of the generated Launchpad page route name.
- `Edit Home` now treats legacy `launchpad_spaces.panel_id = null` rows as global fallback rows for the current panel, preventing false "no home page" redirects after upgrading existing installations.
- Page access remains allowed until the corresponding Shield permission row is generated, preventing `403 Forbidden` immediately after installing/updating the plugin.
- Personal Launchpad `user_id` values now support UUID/string user IDs instead of requiring integer user IDs.
- Personal Launchpad ownership queries now normalize user IDs to strings, keeping UUID support without breaking applications that still use normal integer user IDs.

## 1.2.1 - 2026-07-08

### Changed
- The launchpad sub-nav now renders across the whole Filament panel, so Spaces and Pages remain available after users open resources or custom pages.
- Selecting a Space/Page from outside the Launchpad page now redirects back to the Launchpad with the selected `space` and `page` query parameters.

### Fixed
- The Launchpad page now restores the selected Space/Page from query parameters when opened through the global sub-nav.
- The installer no longer publishes duplicate migration files when package migrations are already auto-loaded by the service provider.

## 1.2.0 - 2026-07-08

### Changed
- **Fixed tile grid layout**: `tileSizing('fixed')` now uses `repeat(6, 1fr)` — always 6 equal columns, each card exactly 1/6 of row width, no empty space, no stretching.
- **Fluid tile layout**: `tileSizing('fluid')` uses `repeat(auto-fit, minmax(176px, 1fr))` — tiles stretch equally to fill the row.
- Both modes use `auto-fit` so empty grid tracks collapse when fewer tiles than columns.

## 1.1.2 - 2026-07-07

### Fixed
- A lone half-width widget in a widgets row now stretches to fill the full row width, instead of leaving the other half empty.

## 1.1.1 - 2026-07-07

### Changed
- The builder's library panels (Card Library, Existing cards, Widgets) now cap their height at ~5 items with an internal scroll, revealing more in batches as you scroll (infinite-scroll style) so a large card catalog no longer stretches the sidebar.

## 1.1.0 - 2026-07-07

### Changed
- **Cards are now a reusable catalog (many-to-many).** A card is global (it lives in the Cards resource) and can be placed in several sections at once, through the new `launchpad_section_card` pivot. Removing a card from a section now *detaches* it — the card survives — and a card is only ever permanently deleted from the Cards list. A bundled migration backfills existing single-section cards into the pivot automatically.

### Added
- **Card catalog in the drag-and-drop builder**: an "Existing cards" library lets you drag any existing card into a section, next to the KPI/Shortcut presets.
- **Attach / Detach** on a section's cards relation manager — reference an existing catalog card, or remove a reference without deleting the card.
- Empty sections (no visible cards) are hidden from the rendered launchpad while staying editable in the builder.

### Fixed
- The builder's card "×" now removes the card from that section only, instead of permanently deleting it.

## 1.0.0 - 2026-07-07

### Added
- **Fiori-style launchpad home page** rendered inside the native Filament panel shell (topbar, sidebar, breadcrumbs and dark mode untouched), with a full-width sub-nav glued directly under the topbar.
- **Space → Page → Section → Card hierarchy**, each level manageable through dedicated Filament Resources (Spaces, Pages, Sections, Cards) and nested relation managers.
- **Sub-nav navigation**: a "☰ All Spaces" menu, a per-space Pages dropdown, and an automatic "More ▾" overflow (priority-nav) that never lets the tab bar scroll.
- **Three card types**: KPI (live value via a registered, closure-based KPI source), Shortcut (link to a Resource, Page or URL), and Widget (renders a native Filament `StatsOverviewWidget`/`ChartWidget` in place of the card).
- **Safe KPI sources**: `LaunchpadPlugin::kpiSources(['key' => fn () => ...])` — developer-registered callables only, no `eval`, no user-controlled code. A throwing source degrades the tile to `—` instead of breaking the page.
- **Card library**: `LaunchpadPlugin::cardLibrary([...])` — reusable, draggable presets available in the layout builder.
- **Native widget library**: widgets already registered on the panel (via `widgets()`/`discoverWidgets()`) are auto-discovered for the builder; `LaunchpadPlugin::widgets([...])` lets you override label/icon/column span or add widgets not registered on the panel.
- **Drag-and-drop layout builder** per Page (HTML5 Drag and Drop, Alpine-driven, no external JS dependency), with a searchable Card Library and Widgets panel, reachable from the Pages Resource (`Build`) or from the standalone "Edit Home" entry in the user/account menu.
- **Management UI**: Spaces/Pages/Sections/Cards Resources, "Pages"/"Cards" shortcut buttons on the Spaces list header, a flat Cards index, and Cards are searchable from Filament's global search.
- **Role-based visibility (Fiori-style permissions)**: every Space/Page/Section/Card has a "Permission" (roles) field. Softly integrated with `bezhansalleh/filament-shield` and `spatie/laravel-permission` — without them everything is visible to everyone; with them, visibility is filtered by role and the Launchpad/Edit Home pages are gated by policies. The Shield `super_admin` role always sees everything.
- **Localization**: English (base), European Portuguese (`pt_PT`), Brazilian Portuguese (`pt_BR`) and generic Portuguese (`pt`) translation catalogs, fully overridable via Laravel's standard translation publishing.
- Full Pest test suite covering the plugin, resources, policies, visibility, KPI sources, the layout builder and widget rendering.
