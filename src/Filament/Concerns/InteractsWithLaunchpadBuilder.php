<?php

namespace Filament\Launchpad\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Launchpad\Filament\Resources\Concerns\HasCardForm;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Support\LaunchpadScope;
use Filament\Launchpad\Support\LaunchpadTenant;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The drag&drop "Construtor de Layout" behaviour: a canvas showing every
 * Section of a Page with its Cards as mosaics (left) and a "Biblioteca de
 * Cards" of presets + the reusable card catalog to drag in (right). Drag&drop
 * is implemented with the native HTML5 Drag and Drop API (Alpine-driven, no
 * external JS library), which calls straight back into these Livewire
 * methods. All mutations validate the section belongs to THIS page before
 * touching the database, so a stray id (e.g. from another page) is always a
 * safe no-op.
 *
 * Cards are a reusable catalog (belongsToMany with Section, see
 * Models/Card::sections() / Models/Section::cards()): the same Card can sit
 * in several sections at once. Removing a card from a section's canvas (the
 * "×") only ever DETACHES the pivot row — it never deletes the Card itself.
 * The Card is only ever permanently deleted from /admin/cards
 * (CardResource), which cascades the pivot via its FK.
 *
 * Shared between the Resource-context BuildLayout page (route-bound to any
 * Page record, breadcrumbed through Spaces) and the standalone EditHome page
 * (always the home page, no breadcrumb). Consumers must implement
 * builderPage() to say WHICH Page record the builder operates on — every
 * other method in this trait is oblivious to that distinction.
 */
trait InteractsWithLaunchpadBuilder
{
    use HasCardForm;
    use HasLaunchpadIconOptions;

    /**
     * Live-bound search term for the "Biblioteca de Cards" panel. Filters the
     * preset list and the card catalog by title/subtitle so a large library
     * (hundreds/thousands of presets or cards) stays usable.
     */
    public string $librarySearch = '';

    /**
     * Live-bound search term for the "Cards Existentes" (reusable catalog)
     * panel. Independent from the preset library so each panel filters on its
     * own.
     */
    public string $catalogSearch = '';

    /**
     * Live-bound search term for the "Widgets" panel. Independent from the
     * preset library so each panel filters on its own.
     */
    public string $widgetSearch = '';

    /**
     * The Page record the builder mutates. BuildLayout resolves it from the
     * route (getRecord()); EditHome always resolves the home page.
     */
    abstract protected function builderPage(): PageModel;

    /**
     * Whether the builder runs in PERSONAL mode (the end-user personalising
     * their own home) rather than ADMIN mode (full authoring). Personal mode
     * hides card creation/editing and only lets the user add catalog cards and
     * personal sections to their own layer, reorder and remove their own.
     * EditHome overrides this to true; BuildLayout (admin) keeps it false.
     */
    protected function isPersonalMode(): bool
    {
        return false;
    }

    protected function currentUserId(): int|string|null
    {
        return auth()->id();
    }

    protected function currentUserStorageId(): ?string
    {
        $userId = $this->currentUserId();

        return $userId === null ? null : (string) $userId;
    }

    // ------------------------------------------------------------------
    // Phase H: which overlay layer this builder reads and writes
    // ------------------------------------------------------------------

    /**
     * Whether the personal-mode builder is currently pointed at the tenant's
     * shared layer rather than the individual's own. Overridden by EditHome,
     * which exposes it as a switcher; false everywhere else keeps the
     * pre-Phase H meaning of personal mode ("my own home") intact.
     */
    protected function isTenantLayer(): bool
    {
        return false;
    }

    /**
     * GLOBAL — the parent's template (tenant_id null, user_id null).
     * TENANT — one tenant's shared layout (tenant_id set, user_id null).
     * USER   — one person's own additions (user_id set).
     *
     * Note that the parent authoring "as" a tenant via the /admin selector and
     * the tenant owner editing their own home both land on TENANT: it is a
     * single layer with two doors, not two parallel layers that could drift.
     */
    protected function builderScopeName(): string
    {
        if (! $this->isPersonalMode()) {
            return LaunchpadScope::name($this->builderTenantId(), null);
        }

        return $this->isTenantLayer()
            ? LaunchpadScope::TENANT
            : LaunchpadScope::USER;
    }

    protected function builderTenantId(): ?string
    {
        return LaunchpadTenant::id();
    }

    /**
     * The user whose layer is being written — only ever set in USER scope, so
     * a tenant-layer edit can never be mistaken for one person's private one.
     */
    protected function builderUserId(): ?string
    {
        return $this->builderScopeName() === LaunchpadScope::USER
            ? $this->currentUserStorageId()
            : null;
    }

    /**
     * Ownership columns to stamp on rows created by this builder.
     *
     * @return array<string, mixed>
     */
    protected function builderOwnAttributes(): array
    {
        return [
            'tenant_id' => $this->builderTenantId(),
            'user_id' => $this->builderUserId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function builderOverlayAttributes(): array
    {
        return LaunchpadScope::attributes($this->builderTenantId(), $this->builderUserId());
    }

    /**
     * True when writes land in the overlay table rather than the parent's own
     * section_card pivot.
     */
    protected function writesOverlay(): bool
    {
        return $this->builderScopeName() !== LaunchpadScope::GLOBAL;
    }

    /**
     * Only the tenant layer may tombstone a parent card. The personal layer
     * stays purely additive, exactly as before Phase H — letting one employee
     * hide a card their manager pinned was never asked for and would silently
     * change published behaviour.
     */
    protected function mayHideInheritedCards(): bool
    {
        return $this->builderScopeName() === LaunchpadScope::TENANT;
    }

    /**
     * Sections this builder OWNS — the only ones it may rename, reorder or
     * delete.
     */
    protected function applyOwnedSectionScope($query)
    {
        $tenantId = $this->builderTenantId();
        $userId = $this->builderUserId();

        return $query
            ->when($this->sectionsAreTenantAware(), fn ($q) => $q->where(
                fn ($q) => blank($tenantId) ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId),
            ))
            ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId));
    }

    /**
     * Sections this builder can SEE — everything it owns plus everything
     * inherited from the layers beneath it, because a card may be added into a
     * section the parent defined.
     */
    protected function applyVisibleSectionScope($query)
    {
        $tenantId = $this->builderTenantId();
        $userId = $this->builderUserId();

        return $query
            ->when($this->sectionsAreTenantAware(), fn ($q) => $q->forTenant($tenantId))
            ->where(function ($query) use ($userId): void {
                $query->whereNull('user_id')
                    ->when($userId !== null, fn ($query) => $query->orWhere('user_id', $userId));
            });
    }

    protected function sectionsAreTenantAware(): bool
    {
        return Schema::hasColumn('launchpad_sections', 'tenant_id');
    }

    /**
     * Overlay rows belonging to the layer this builder writes — the single
     * gate every mutation goes through, so no code path can reach across into
     * another tenant's or another person's rows.
     */
    protected function overlayQuery()
    {
        return UserCard::query()->forScope($this->builderTenantId(), $this->builderUserId());
    }

    /**
     * Hides a card inherited from the parent's template in THIS layer only, by
     * writing a tombstone. The parent's pivot row is deliberately left intact:
     * that is what lets the parent keep pushing later template changes into
     * every slot nobody has overridden.
     */
    protected function hideInheritedCard(int|string $sectionId, int|string $cardId): void
    {
        UserCard::query()->updateOrCreate(
            [
                ...$this->builderOverlayAttributes(),
                'section_id' => $sectionId,
                'card_id' => $cardId,
                'is_hidden' => true,
            ],
            ['sort' => 0],
        );
    }

    /**
     * Drops every deviation this layer has accumulated for the current page and
     * falls back to whatever the layer beneath provides — the "Repor template"
     * action. Scoped to this builder's layer, so restoring one tenant never
     * touches the template or any other tenant.
     */
    public function restoreParentTemplate(): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $sectionIds = Section::query()
            ->where('page_id', $this->builderPage()->id)
            ->pluck('id');

        $this->overlayQuery()->whereIn('section_id', $sectionIds)->delete();

        $this->applyOwnedSectionScope(
            Section::query()->where('page_id', $this->builderPage()->id),
        )->delete();

        Notification::make()
            ->title(__('launchpad::launchpad.messages.template_reposto'))
            ->success()
            ->send();
    }

    public function restoreParentTemplateAction(): Action
    {
        return Action::make('restoreParentTemplate')
            ->label(__('launchpad::launchpad.buttons.repor_template'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('launchpad::launchpad.messages.repor_template_aviso'))
            ->visible(fn (): bool => $this->writesOverlay())
            ->action(fn () => $this->restoreParentTemplate());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $page = $this->getPageModel();

        return [
            'page' => $page,
            'mode' => $this->isPersonalMode() ? 'user' : 'admin',
            'builderSections' => $this->builderSections($page),
            'library' => $this->isPersonalMode() ? [] : $this->getLibrary(),
            'cardCatalog' => $this->getCardCatalog(),
            'widgetLibrary' => $this->isPersonalMode() ? [] : $this->getWidgetLibrary(),
        ];
    }

    /**
     * Uniform per-section view-model shared by both modes. Each card carries
     * `pinned` (admin fixed), `locked` (user cannot remove/reorder it) and
     * `origin` (admin|user). In ADMIN mode every card is an editable admin
     * card; in PERSONAL mode the list is the admin's pinned cards (locked)
     * followed by the current user's own added cards (editable by them).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function builderSections(PageModel $page): array
    {
        $personal = $this->isPersonalMode();
        $scope = $this->builderScopeName();
        $ownsSection = fn (Section $section): bool => $this->sectionBelongsToBuilderLayer($section);

        // In the tenant layer an inherited card is removable (it tombstones), so
        // it must NOT come back marked locked the way the personal layer needs.
        $inheritedLocked = ! $this->mayHideInheritedCards();

        return $page->sections->map(function (Section $section) use ($personal, $scope, $ownsSection, $inheritedLocked): array {
            $cards = [];
            $owner = $this->sectionOwnerLabel($section);
            $overlay = $this->sectionOverlayRows($section);
            $hidden = $overlay
                ->filter(fn ($row): bool => (bool) ($row->is_hidden ?? false))
                ->map(fn ($row) => (int) $row->card_id)
                ->all();

            if ($personal || $scope === LaunchpadScope::TENANT) {
                if (! $ownsSection($section)) {
                    foreach ($section->cards as $card) {
                        if (! (bool) ($card->pivot->is_pinned ?? true)) {
                            continue; // available/catalog card — not shown until it is added
                        }
                        if (in_array((int) $card->getKey(), $hidden, true)) {
                            continue; // tombstoned by this layer
                        }
                        $cards[] = $this->builderCardData($card, pinned: true, locked: $inheritedLocked, origin: 'admin');
                    }
                }

                foreach ($overlay->reject(fn ($row): bool => (bool) ($row->is_hidden ?? false))->sortBy('sort') as $row) {
                    if ($row->card) {
                        $cards[] = $this->builderCardData($row->card, pinned: false, locked: false, origin: 'user');
                    }
                }
            } else {
                foreach ($section->cards as $card) {
                    $cards[] = $this->builderCardData(
                        $card,
                        pinned: (bool) ($card->pivot->is_pinned ?? true),
                        locked: false,
                        origin: 'admin',
                    );
                }
            }

            return [
                'id' => $section->id,
                'title' => $section->title,
                'cards' => $cards,
                'owner' => $owner,
                'locked' => $this->writesOverlay() && ! $ownsSection($section),
            ];
        })->all();
    }

    /**
     * Whether a section was authored by the layer this builder is writing —
     * i.e. whether it can be renamed, reordered or deleted here, as opposed to
     * being inherited from a layer below.
     */
    protected function sectionBelongsToBuilderLayer(Section $section): bool
    {
        $sectionTenant = $this->sectionsAreTenantAware() ? ($section->tenant_id ?? null) : null;

        return (string) ($section->user_id ?? '') === (string) ($this->builderUserId() ?? '')
            && (string) ($sectionTenant ?? '') === (string) ($this->builderTenantId() ?? '');
    }

    protected function sectionOwnerLabel(Section $section): string
    {
        return $this->sectionBelongsToBuilderLayer($section) && $this->writesOverlay()
            ? 'user'
            : ($section->user_id === null ? 'admin' : 'user');
    }

    /**
     * This layer's overlay rows for one section — additions and tombstones
     * alike, already narrowed to the current scope.
     */
    protected function sectionOverlayRows(Section $section): Collection
    {
        return $this->writesOverlay()
            ? $this->overlayQuery()->where('section_id', $section->id)->with('card')->get()
            : new Collection;
    }

    /**
     * @return array<string, mixed>
     */
    protected function builderCardData(Card $card, bool $pinned, bool $locked, string $origin): array
    {
        return [
            'id' => $card->id,
            'title' => $card->title,
            'subtitle' => $card->subtitle,
            'icon' => $card->icon,
            'type' => $card->type,
            'kpi_value' => $card->kpi_value,
            'unit' => $card->unit,
            'trend' => $card->trend,
            'badge' => $card->badge,
            'pinned' => $pinned,
            'locked' => $locked,
            'origin' => $origin,
        ];
    }

    /**
     * The registered widgets, narrowed by the same live search term as the
     * card library (matched against label). Shown as a separate "Widgets"
     * group in the Builder's sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getWidgetLibrary(): array
    {
        $search = trim($this->widgetSearch);

        return collect(LaunchpadPlugin::get()->getWidgets())
            ->when($search !== '', fn ($widgets) => $widgets->filter(function (array $widget) use ($search): bool {
                $haystack = Str::lower($widget['label'] ?? '');

                return Str::contains($haystack, Str::lower($search));
            }))
            ->values()
            ->all();
    }

    /**
     * The card PRESET library, narrowed by the live search term (matched
     * against title + subtitle). Presets are REUSABLE: the same preset can be
     * dropped into as many sections as wanted — the design places e.g.
     * "Vendas Hoje" several times — so nothing is ever hidden after use.
     * Dropping a preset always CREATES a brand new Card (see
     * addCardFromLibrary()); dropping an existing catalog card (see
     * getCardCatalog()) instead attaches the SAME Card record.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getLibrary(): array
    {
        $search = trim($this->librarySearch);

        return collect(LaunchpadPlugin::get()->getCardLibrary())
            ->when($search !== '', fn ($presets) => $presets->filter(function (array $preset) use ($search): bool {
                $haystack = Str::lower(($preset['title'] ?? '').' '.($preset['subtitle'] ?? ''));

                return Str::contains($haystack, Str::lower($search));
            }))
            ->values()
            ->all();
    }

    /**
     * The full reusable Card catalog (every Card that exists, regardless of
     * which section(s) it is already attached to), narrowed by the same live
     * search term. Shown in the Builder's sidebar alongside the presets and
     * widgets so an existing card can be dragged into another section
     * instead of recreated. Dropping one calls attachCardFromCatalog(), which
     * attaches the SAME Card record to the target section (a card can live in
     * several sections at once).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCardCatalog(): array
    {
        $search = trim($this->catalogSearch);

        $query = Card::query()
            // Only the template's cards plus this layer's own — never another
            // tenant's, which would leak its private cards into this catalog.
            ->when(
                Schema::hasColumn('launchpad_cards', 'tenant_id'),
                fn ($query) => $query->forTenant($this->builderTenantId()),
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%");
                });
            });

        if ($this->isPersonalMode()) {
            // Personal mode reuses existing cards/widgets globally. Users cannot
            // create new catalog items here; they only add their own references.
            $sectionIds = $this->getPageModel()->sections->pluck('id');
            $userId = $this->currentUserStorageId();

            $query->whereNotExists(function ($sub) use ($sectionIds) {
                $sub->selectRaw('1')
                    ->from('launchpad_section_card')
                    ->whereColumn('launchpad_section_card.card_id', 'launchpad_cards.id')
                    ->whereIn('launchpad_section_card.section_id', $sectionIds)
                    ->where('launchpad_section_card.is_pinned', true);
            });

            if ($userId !== null) {
                $query->whereNotExists(function ($sub) use ($sectionIds, $userId) {
                    $sub->selectRaw('1')
                        ->from('launchpad_user_cards')
                        ->whereColumn('launchpad_user_cards.card_id', 'launchpad_cards.id')
                        ->whereIn('launchpad_user_cards.section_id', $sectionIds)
                        ->where('launchpad_user_cards.user_id', $userId);
                });
            }
        }

        return $query
            ->orderBy('title')
            ->get()
            ->map(fn (Card $card): array => [
                'id' => $card->id,
                'title' => $card->title,
                'subtitle' => $card->subtitle,
                'icon' => $card->icon,
                'type' => $card->type,
            ])
            ->all();
    }

    protected function getPageModel(): PageModel
    {
        return $this->builderPage()->load(['sections' => function ($query) {
            $this->applyVisibleSectionScope($query)
                ->orderByRaw($this->sectionsAreTenantAware()
                    ? 'case when tenant_id is null and user_id is null then 0 when user_id is null then 1 else 2 end'
                    : 'case when user_id is null then 0 else 1 end')
                ->orderBy('sort')
                ->with(['cards' => fn ($q) => $q->orderByPivot('sort')]);
        }]);
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    public function addSection(): void
    {
        // A personal-layer section still requires an authenticated user; the
        // tenant and template layers do not, since they belong to nobody.
        if ($this->builderScopeName() === LaunchpadScope::USER && $this->currentUserStorageId() === null) {
            return;
        }

        $own = $this->sectionsAreTenantAware()
            ? $this->builderOwnAttributes()
            : ['user_id' => $this->builderUserId()];

        $nextSort = ((int) $this->applyOwnedSectionScope(
            Section::query()->where('page_id', $this->builderPage()->id),
        )->max('sort')) + 1;

        Section::query()->create([
            'page_id' => $this->builderPage()->id,
            'title' => __('launchpad::launchpad.buttons.nova_secao'),
            'sort' => $nextSort,
            ...$own,
        ]);
    }

    public function renameSection(int|string $sectionId, string $title): void
    {
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $title = trim($title);

        if ($title === '') {
            return;
        }

        $section->update(['title' => $title]);
    }

    public function deleteSection(int|string $sectionId): void
    {
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        // A Section owns no Cards anymore (they are global catalog items) —
        // deleting it only detaches the pivot rows (via the FK cascade on
        // launchpad_section_card), the Cards themselves survive untouched.
        $section->delete();

        $this->reindexSections();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderSections(array $orderedIds): void
    {
        $ownedIds = Section::query()
            ->where('page_id', $this->builderPage()->id)
            ->tap(fn ($query) => $this->applyOwnedSectionScope($query))
            ->pluck('id')
            ->all();

        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), $ownedIds));

        foreach ($orderedIds as $index => $id) {
            Section::query()->whereKey($id)->update(['sort' => $index]);
        }
    }

    protected function reindexSections(): void
    {
        Section::query()
            ->where('page_id', $this->builderPage()->id)
            ->tap(fn ($query) => $this->applyOwnedSectionScope($query))
            ->orderBy('sort')
            ->pluck('id')
            ->each(function ($id, int $index) {
                Section::query()->whereKey($id)->update(['sort' => $index]);
            });
    }

    protected function ownedSection(int|string $sectionId): ?Section
    {
        return Section::query()
            ->where('id', $sectionId)
            ->where('page_id', $this->builderPage()->id)
            ->tap(fn ($query) => $this->applyOwnedSectionScope($query))
            ->first();
    }

    protected function visibleSection(int|string $sectionId): ?Section
    {
        return Section::query()
            ->where('id', $sectionId)
            ->where('page_id', $this->builderPage()->id)
            ->tap(fn ($query) => $this->applyVisibleSectionScope($query))
            ->first();
    }

    /**
     * Mountable confirmation action for deleting a Section, replacing the
     * previous native wire:confirm (which cannot be styled/translated as a
     * proper modal). Dispatched from the Blade view via
     * mountAction('deleteSection', { section: <id> }).
     */
    public function deleteSectionAction(): Action
    {
        return Action::make('deleteSection')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(__('launchpad::launchpad.builder.confirm_delete_section_heading'))
            ->modalDescription(__('launchpad::launchpad.builder.confirm_delete_section_body'))
            ->action(function (array $arguments): void {
                $this->deleteSection($arguments['section']);
            });
    }

    // ------------------------------------------------------------------
    // Cards
    // ------------------------------------------------------------------

    /**
     * Drops a preset from the "Biblioteca de Cards" onto a Section: creates a
     * brand new Card seeded from the preset's fields, then attaches it to the
     * section's pivot at the given index.
     */
    public function addCardFromLibrary(int|string $sectionId, string $presetKey, ?int $index = null): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $preset = Arr::first(
            LaunchpadPlugin::get()->getCardLibrary(),
            fn (array $preset): bool => ($preset['key'] ?? null) === $presetKey,
        );

        if (! $preset) {
            return;
        }

        $card = Card::query()->create([
            // A card a tenant creates belongs to that tenant; the parent's stay null.
            'tenant_id' => $this->builderTenantId(),
            'library_key' => $preset['key'] ?? null,
            'title' => $preset['title'] ?? 'Novo Card',
            'subtitle' => $preset['subtitle'] ?? null,
            'icon' => $preset['icon'] ?? null,
            'type' => $preset['type'] ?? 'kpi',
            'kpi_value' => $preset['kpi_value'] ?? null,
            'unit' => $preset['unit'] ?? null,
            'trend' => $preset['trend'] ?? null,
            'trend_color' => $preset['trend_color'] ?? null,
            'badge' => $preset['badge'] ?? null,
            'target_type' => $preset['target_type'] ?? 'none',
            'target_value' => $preset['target_value'] ?? null,
        ]);

        $this->attachCardAt($section->id, $card->id, $index);
    }

    /**
     * Drops an EXISTING card from the Builder's reusable catalog onto a
     * Section: attaches the SAME Card record (no new row created) — this is
     * how the same card ends up referenced by several sections. A no-op when
     * the section is not owned by this page or the card does not exist.
     */
    public function attachCardFromCatalog(int|string $sectionId, int|string $cardId, ?int $index = null): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        if (! Card::query()->whereKey($cardId)->exists()) {
            return;
        }

        $this->attachCardAt($section->id, $cardId, $index);
    }

    /**
     * Drops a registered widget from the Builder's "Widgets" library group
     * into a Section, creating a Card of type=widget carrying only the
     * widget's `key` (never its class — the class is resolved from the
     * developer's widgets() registration at render time). A no-op when the
     * section does not belong to this page, or the key is not registered.
     */
    public function addWidgetFromLibrary(int|string $sectionId, string $widgetKey, ?int $index = null): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $widget = LaunchpadPlugin::get()->getWidget($widgetKey);

        if (! $widget) {
            return;
        }

        $card = Card::query()->create([
            'tenant_id' => $this->builderTenantId(),
            'widget_key' => $widgetKey,
            'widget_column_span' => (string) ($widget['columnSpan'] ?? 'full'),
            'title' => $widget['label'] ?? 'Widget',
            'icon' => $widget['icon'] ?? null,
            'type' => 'widget',
            'target_type' => 'none',
        ]);

        $this->attachCardAt($section->id, $card->id, $index);
    }

    /**
     * Moves a card TILE from one section to another on the canvas. Because
     * cards are a shared catalog, "moving" the instance rendered under
     * $fromSectionId means: detach the pivot row that placed it there, and
     * attach (or re-place) it under $toSectionId at the given index. If the
     * card also happens to be referenced by other sections, those pivot rows
     * are untouched. When both sections are the same, this is just a
     * same-section reorder.
     */
    public function moveCard(int|string $cardId, int|string $fromSectionId, int|string $toSectionId, ?int $index = null): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $fromSection = $this->ownedSection($fromSectionId);
        $toSection = $this->ownedSection($toSectionId);

        if (! $fromSection || ! $toSection) {
            return;
        }

        if ((int) $fromSection->id === (int) $toSection->id) {
            $this->attachCardAt($toSection->id, $cardId, $index);

            return;
        }

        $fromSection->cards()->detach($cardId);
        $this->reindexCardsInSection($fromSection->id);

        $this->attachCardAt($toSection->id, $cardId, $index);
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderCards(int|string $sectionId, array $orderedIds): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $ownedIds = $section->cards()->pluck('launchpad_cards.id')->all();
        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), $ownedIds));

        foreach ($orderedIds as $index => $id) {
            $section->cards()->updateExistingPivot($id, ['sort' => $index]);
        }
    }

    /**
     * The × on a card tile: REMOVES the card from THIS section only (detach
     * from the pivot). Non-destructive, so no confirmation is asked — the
     * Card itself, and any other section referencing it, is untouched. The
     * Card is only ever permanently deleted from /admin/cards.
     *
     * Phase H: in the tenant layer the pivot row belongs to the parent and must
     * not be touched, so the same × writes a tombstone instead — the card
     * disappears for that tenant and stays for everyone else. The personal layer
     * still cannot remove an inherited card at all (see mayHideInheritedCards).
     */
    public function removeCard(int|string $sectionId, int|string $cardId): void
    {
        if ($this->writesOverlay()) {
            if (! $this->mayHideInheritedCards()) {
                return;
            }

            $section = $this->visibleSection($sectionId);

            if (! $section) {
                return;
            }

            // A card this layer added itself is simply deleted; only a card
            // inherited from below needs a tombstone.
            $own = $this->overlayQuery()
                ->where('section_id', $section->id)
                ->where('card_id', $cardId)
                ->where('is_hidden', false)
                ->first();

            if ($own) {
                $own->delete();
                $this->reindexUserCards($section->id);

                return;
            }

            $this->hideInheritedCard($section->id, $cardId);

            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $section->cards()->detach($cardId);

        $this->reindexCardsInSection($section->id);
    }

    // ------------------------------------------------------------------
    // Admin: pin / unpin a card in a section
    // ------------------------------------------------------------------

    /**
     * Toggles whether a card is PINNED (injected for every user, fixed) or
     * AVAILABLE (only in the catalog for users to add themselves). Admin-only
     * — a no-op in personal mode or when the section is not owned by this page.
     */
    public function togglePinned(int|string $sectionId, int|string $cardId): void
    {
        if ($this->isPersonalMode()) {
            return;
        }

        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $current = $section->cards()->whereKey($cardId)->first();

        if (! $current) {
            return;
        }

        $section->cards()->updateExistingPivot($cardId, [
            'is_pinned' => ! (bool) ($current->pivot->is_pinned ?? true),
        ]);
    }

    // ------------------------------------------------------------------
    // Personal mode: the user's own card layer (launchpad_user_cards)
    // ------------------------------------------------------------------

    /**
     * Adds an existing catalog card to the current user's own layer for a
     * section, at $index. Personal mode only. It never creates catalog cards,
     * and is idempotent (a card the user already added is just re-placed).
     */
    public function addUserCard(int|string $sectionId, int|string $cardId, ?int $index = null): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $section = $this->visibleSection($sectionId);

        if (! $section) {
            return;
        }

        if (! Card::query()->whereKey($cardId)->exists()) {
            return;
        }

        $fixedInSection = $section->cards()
            ->whereKey($cardId)
            ->wherePivot('is_pinned', true)
            ->exists();

        if ($fixedInSection) {
            return;
        }

        UserCard::query()->firstOrCreate(
            [
                ...$this->builderOverlayAttributes(),
                'section_id' => $section->id,
                'card_id' => $cardId,
                'is_hidden' => false,
            ],
            ['sort' => 0],
        );

        $this->reorderUserCardsAt($section->id, $cardId, $index);
    }

    /**
     * Removes a card the user themselves added (their own row only). Personal
     * mode only; never touches pinned admin cards.
     */
    public function removeUserCard(int|string $sectionId, int|string $cardId): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $this->overlayQuery()
            ->where('section_id', $sectionId)
            ->where('card_id', $cardId)
            ->delete();

        $this->reindexUserCards($sectionId);
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderUserCards(int|string $sectionId, array $orderedIds): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $ownIds = $this->overlayQuery()
            ->where('section_id', $sectionId)
            ->pluck('card_id')
            ->all();

        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), array_map('intval', $ownIds)));

        foreach ($orderedIds as $position => $cardId) {
            $this->overlayQuery()
                ->where('section_id', $sectionId)
                ->where('card_id', $cardId)
                ->update(['sort' => $position]);
        }
    }

    /**
     * Repositions a card the user owns within its section to $index (drag to
     * reorder). Personal mode only; a no-op if the user does not own that row.
     */
    public function moveUserCard(int|string $sectionId, int|string $cardId, ?int $index = null): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $owns = $this->overlayQuery()
            ->where('section_id', $sectionId)
            ->where('card_id', $cardId)
            ->exists();

        if (! $owns) {
            return;
        }

        $this->reorderUserCardsAt($sectionId, $cardId, $index);
    }

    protected function reorderUserCardsAt(int|string $sectionId, int|string $cardId, ?int $index): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $ids = $this->overlayQuery()
            ->where('section_id', $sectionId)
            ->orderBy('sort')
            ->pluck('card_id')
            ->reject(fn ($id) => (int) $id === (int) $cardId)
            ->values()
            ->all();

        $index = $index === null ? count($ids) : max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$cardId]);

        foreach ($ids as $position => $id) {
            $this->overlayQuery()
                ->where('section_id', $sectionId)
                ->where('card_id', $id)
                ->update(['sort' => $position]);
        }
    }

    protected function reindexUserCards(int|string $sectionId): void
    {
        if (! $this->writesOverlay()) {
            return;
        }

        $this->overlayQuery()
            ->where('section_id', $sectionId)
            ->orderBy('sort')
            ->pluck('card_id')
            ->each(function ($cardId, int $index) use ($sectionId) {
                $this->overlayQuery()
                    ->where('section_id', $sectionId)
                    ->where('card_id', $cardId)
                    ->update(['sort' => $index]);
            });
    }

    /**
     * Any Card referenced by a Section of this page — used to guard the
     * edit-by-click modal (editCardAction()) so a stray id from another page
     * cannot be edited through this builder instance.
     */
    protected function cardOnThisPage(int|string $cardId): ?Card
    {
        $card = Card::query()->find($cardId);

        if (! $card) {
            return null;
        }

        $belongsHere = $card->sections()
            ->where('page_id', $this->builderPage()->id)
            ->exists();

        return $belongsHere ? $card : null;
    }

    /**
     * Attaches $cardId to $sectionId's pivot at $index (or the end, when
     * null), shifting the sort of every other card already in the section.
     * A no-op when the section is not owned by this page.
     */
    protected function attachCardAt(int|string $sectionId, int|string $cardId, ?int $index): void
    {
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        /** @var Collection<int, int> $ids */
        $ids = $section->cards()->pluck('launchpad_cards.id');
        $ids = $ids->reject(fn ($id) => (int) $id === (int) $cardId)->values()->all();

        $index = $index === null ? count($ids) : max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$cardId]);

        $sync = [];

        foreach ($ids as $position => $id) {
            $sync[$id] = ['sort' => $position];
        }

        // syncWithoutDetaching both creates the new pivot row (with its
        // `sort`) and updates the `sort` of every already-attached row that
        // shifted position — a single statement, no separate update loop.
        $section->cards()->syncWithoutDetaching($sync);
    }

    protected function reindexCardsInSection(int|string $sectionId): void
    {
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $section->cards()->orderByPivot('sort')->pluck('launchpad_cards.id')
            ->each(function ($id, int $index) use ($section) {
                $section->cards()->updateExistingPivot($id, ['sort' => $index]);
            });
    }

    // ------------------------------------------------------------------
    // Edit card by click (shared Card form, modal action)
    // ------------------------------------------------------------------

    public function editCardAction(): Action
    {
        return Action::make('editCard')
            ->label(__('launchpad::launchpad.buttons.editar_card'))
            ->modalHeading(__('launchpad::launchpad.buttons.editar_card'))
            // Binds the mounted schema to this specific Card, so that a
            // `visibilityRoles` relationship field (added by
            // HasCardForm/HasLaunchpadVisibilityField) hydrates from and
            // syncs back to the RIGHT record — Filament's
            // Schema::saveRelationships() (invoked automatically on every
            // mounted action, not just Resource Create/EditRecord pages)
            // resolves its model from Action::getRecord(), which reads
            // this ->record() binding.
            ->record(fn (array $arguments): ?Card => $this->cardOnThisPage($arguments['card'] ?? null))
            ->schema(fn (): array => static::cardFormComponents())
            ->fillForm(function (array $arguments): array {
                $card = $this->cardOnThisPage($arguments['card'] ?? null);

                return $card?->toArray() ?? [];
            })
            ->action(function (array $data, array $arguments): void {
                $card = $this->cardOnThisPage($arguments['card'] ?? null);

                if (! $card) {
                    return;
                }

                $card->update($data);

                Notification::make()
                    ->title(__('launchpad::launchpad.messages.card_actualizado'))
                    ->success()
                    ->send();
            });
    }
}
