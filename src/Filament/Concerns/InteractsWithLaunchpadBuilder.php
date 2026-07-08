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
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
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
        $userId = $this->currentUserId();

        return $page->sections->map(function (Section $section) use ($personal, $userId): array {
            $cards = [];
            $owner = $section->user_id === null ? 'admin' : 'user';

            if ($personal) {
                if ($section->user_id === null) {
                    foreach ($section->cards as $card) {
                        if (! (bool) ($card->pivot->is_pinned ?? true)) {
                            continue; // available/catalog card — not shown until the user adds it
                        }
                        $cards[] = $this->builderCardData($card, pinned: true, locked: true, origin: 'admin');
                    }
                }

                if ($userId !== null) {
                    foreach ($section->userCards()->where('user_id', $userId)->with('card')->get() as $userCard) {
                        if ($userCard->card) {
                            $cards[] = $this->builderCardData($userCard->card, pinned: false, locked: false, origin: 'user');
                        }
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
                'locked' => $personal && $owner === 'admin',
            ];
        })->all();
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
            $userId = $this->currentUserId();

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
        $userId = $this->currentUserId();
        $personal = $this->isPersonalMode();

        return $this->builderPage()->load(['sections' => function ($query) use ($personal, $userId) {
            $query->when(
                $personal,
                fn ($query) => $query->where(function ($query) use ($userId) {
                    $query->whereNull('user_id')
                        ->when($userId !== null, fn ($query) => $query->orWhere('user_id', $userId));
                }),
                fn ($query) => $query->whereNull('user_id'),
            )
                ->orderByRaw('case when user_id is null then 0 else 1 end')
                ->orderBy('sort')
                ->with(['cards' => fn ($q) => $q->orderByPivot('sort')]);
        }]);
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    public function addSection(): void
    {
        if ($this->isPersonalMode()) {
            $userId = $this->currentUserId();

            if ($userId === null) {
                return;
            }

            $nextSort = ((int) Section::query()
                ->where('page_id', $this->builderPage()->id)
                ->where('user_id', $userId)
                ->max('sort')) + 1;

            Section::query()->create([
                'page_id' => $this->builderPage()->id,
                'user_id' => $userId,
                'title' => __('launchpad::launchpad.buttons.nova_secao'),
                'sort' => $nextSort,
            ]);

            return;
        }

        $nextSort = ((int) Section::query()
            ->where('page_id', $this->builderPage()->id)
            ->whereNull('user_id')
            ->max('sort')) + 1;

        Section::query()->create([
            'page_id' => $this->builderPage()->id,
            'title' => __('launchpad::launchpad.buttons.nova_secao'),
            'sort' => $nextSort,
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
            ->when(
                $this->isPersonalMode(),
                fn ($query) => $query->where('user_id', $this->currentUserId()),
                fn ($query) => $query->whereNull('user_id'),
            )
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
            ->when(
                $this->isPersonalMode(),
                fn ($query) => $query->where('user_id', $this->currentUserId()),
                fn ($query) => $query->whereNull('user_id'),
            )
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
            ->when(
                $this->isPersonalMode(),
                fn ($query) => $query->where('user_id', $this->currentUserId()),
                fn ($query) => $query->whereNull('user_id'),
            )
            ->first();
    }

    protected function visibleSection(int|string $sectionId): ?Section
    {
        return Section::query()
            ->where('id', $sectionId)
            ->where('page_id', $this->builderPage()->id)
            ->when(
                $this->isPersonalMode(),
                fn ($query) => $query->where(function ($query) {
                    $query->whereNull('user_id')
                        ->when($this->currentUserId() !== null, fn ($query) => $query->orWhere('user_id', $this->currentUserId()));
                }),
                fn ($query) => $query->whereNull('user_id'),
            )
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
     */
    public function removeCard(int|string $sectionId, int|string $cardId): void
    {
        if ($this->isPersonalMode()) {
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
        $userId = $this->currentUserId();

        if (! $this->isPersonalMode() || $userId === null) {
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
            ['user_id' => $userId, 'section_id' => $section->id, 'card_id' => $cardId],
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
        $userId = $this->currentUserId();

        if (! $this->isPersonalMode() || $userId === null) {
            return;
        }

        UserCard::query()
            ->where('user_id', $userId)
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
        $userId = $this->currentUserId();

        if (! $this->isPersonalMode() || $userId === null) {
            return;
        }

        $ownIds = UserCard::query()
            ->where('user_id', $userId)
            ->where('section_id', $sectionId)
            ->pluck('card_id')
            ->all();

        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), array_map('intval', $ownIds)));

        foreach ($orderedIds as $position => $cardId) {
            UserCard::query()
                ->where('user_id', $userId)
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
        $userId = $this->currentUserId();

        if (! $this->isPersonalMode() || $userId === null) {
            return;
        }

        $owns = UserCard::query()
            ->where('user_id', $userId)
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
        $userId = $this->currentUserId();

        if ($userId === null) {
            return;
        }

        $ids = UserCard::query()
            ->where('user_id', $userId)
            ->where('section_id', $sectionId)
            ->orderBy('sort')
            ->pluck('card_id')
            ->reject(fn ($id) => (int) $id === (int) $cardId)
            ->values()
            ->all();

        $index = $index === null ? count($ids) : max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$cardId]);

        foreach ($ids as $position => $id) {
            UserCard::query()
                ->where('user_id', $userId)
                ->where('section_id', $sectionId)
                ->where('card_id', $id)
                ->update(['sort' => $position]);
        }
    }

    protected function reindexUserCards(int|string $sectionId): void
    {
        $userId = $this->currentUserId();

        if ($userId === null) {
            return;
        }

        UserCard::query()
            ->where('user_id', $userId)
            ->where('section_id', $sectionId)
            ->orderBy('sort')
            ->pluck('card_id')
            ->each(function ($cardId, int $index) use ($userId, $sectionId) {
                UserCard::query()
                    ->where('user_id', $userId)
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
