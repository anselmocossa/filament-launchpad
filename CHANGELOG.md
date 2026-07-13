# Changelog

All notable changes to `filament-launchpad` will be documented in this file, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
