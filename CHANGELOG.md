# Changelog

All notable changes to `filament-launchpad` will be documented in this file, following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

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
