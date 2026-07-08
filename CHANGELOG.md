# Changelog

All notable changes to `filament-launchpad` will be documented in this file, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## 1.2.2 - 2026-07-09

### Fixed
- Avoided `Route [filament.{panel}.pages..] not defined` when the Launchpad page uses the root `/` slug and another panel page owns the panel home route.
- The global sub-nav and fallback card URLs now use the current Filament panel root URL instead of the generated Launchpad page route name.
- `Edit Home` now treats legacy `launchpad_spaces.panel_id = null` rows as global fallback rows for the current panel, preventing false "no home page" redirects after upgrading existing installations.
- Page access remains allowed until the corresponding Shield permission row is generated, preventing `403 Forbidden` immediately after installing/updating the plugin.

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
