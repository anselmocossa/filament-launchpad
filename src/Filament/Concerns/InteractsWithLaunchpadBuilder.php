<?php

namespace Filament\Launchpad\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Launchpad\Filament\Resources\Concerns\HasCardForm;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page as PageModel;
use Filament\Launchpad\Models\Section;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The drag&drop "Construtor de Layout" behaviour: a canvas showing every
 * Section of a Page with its Cards as mosaics (left) and a "Biblioteca de
 * Cards" of presets to drag in (right). Drag&drop is implemented with the
 * native HTML5 Drag and Drop API (Alpine-driven, no external JS library),
 * which calls straight back into these Livewire methods. All mutations
 * validate the section/card belongs to THIS page before touching the
 * database, so a stray id (e.g. from another page) is always a safe no-op.
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
     * preset list by title/subtitle so a large library (hundreds/thousands of
     * presets) stays usable.
     */
    public string $librarySearch = '';

    /**
     * The Page record the builder mutates. BuildLayout resolves it from the
     * route (getRecord()); EditHome always resolves the home page.
     */
    abstract protected function builderPage(): PageModel;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $page = $this->getPageModel();

        return [
            'page' => $page,
            'library' => $this->getLibrary($page),
            'widgetLibrary' => $this->getWidgetLibrary(),
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
        $search = trim($this->librarySearch);

        return collect(LaunchpadPlugin::get()->getWidgets())
            ->when($search !== '', fn ($widgets) => $widgets->filter(function (array $widget) use ($search): bool {
                $haystack = Str::lower($widget['label'] ?? '');

                return Str::contains($haystack, Str::lower($search));
            }))
            ->values()
            ->all();
    }

    /**
     * The card library, narrowed by the live search term (matched against
     * title + subtitle). Presets are REUSABLE: the same preset can be dropped
     * into as many sections as wanted — the design places e.g. "Vendas Hoje"
     * several times — so nothing is ever hidden after use. (library_key is
     * still recorded on each card as provenance, just not used to hide presets.)
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getLibrary(PageModel $page): array
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

    protected function getPageModel(): PageModel
    {
        return $this->builderPage()->load(['sections' => function ($query) {
            $query->orderBy('sort')->with(['cards' => fn ($q) => $q->orderBy('sort')]);
        }]);
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    public function addSection(): void
    {
        $nextSort = ((int) Section::query()->where('page_id', $this->builderPage()->id)->max('sort')) + 1;

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

    public function addCardFromLibrary(int|string $sectionId, string $presetKey, ?int $index = null): void
    {
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
            'section_id' => $section->id,
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
            'sort' => 0,
        ]);

        $this->insertCardAt($section->id, $card->id, $index);
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
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $widget = LaunchpadPlugin::get()->getWidget($widgetKey);

        if (! $widget) {
            return;
        }

        $card = Card::query()->create([
            'section_id' => $section->id,
            'widget_key' => $widgetKey,
            'widget_column_span' => (string) ($widget['columnSpan'] ?? 'full'),
            'title' => $widget['label'] ?? 'Widget',
            'icon' => $widget['icon'] ?? null,
            'type' => 'widget',
            'target_type' => 'none',
            'sort' => 0,
        ]);

        $this->insertCardAt($section->id, $card->id, $index);
    }

    public function moveCard(int|string $cardId, int|string $toSectionId, ?int $index = null): void
    {
        $card = $this->ownedCard($cardId);
        $toSection = $this->ownedSection($toSectionId);

        if (! $card || ! $toSection) {
            return;
        }

        $fromSectionId = $card->section_id;

        $card->update(['section_id' => $toSection->id]);

        $this->insertCardAt($toSection->id, $card->id, $index);

        if ((int) $fromSectionId !== (int) $toSection->id) {
            $this->reindexCardsInSection($fromSectionId);
        }
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorderCards(int|string $sectionId, array $orderedIds): void
    {
        $section = $this->ownedSection($sectionId);

        if (! $section) {
            return;
        }

        $ownedIds = Card::query()->where('section_id', $section->id)->pluck('id')->all();
        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), $ownedIds));

        foreach ($orderedIds as $index => $id) {
            Card::query()->whereKey($id)->update(['sort' => $index]);
        }
    }

    public function removeCard(int|string $cardId): void
    {
        $card = $this->ownedCard($cardId);

        if (! $card) {
            return;
        }

        $sectionId = $card->section_id;
        $card->delete();

        $this->reindexCardsInSection($sectionId);
    }

    protected function ownedCard(int|string $cardId): ?Card
    {
        $card = Card::query()->with('section')->find($cardId);

        if (! $card || ! $card->section || $card->section->page_id !== $this->builderPage()->id) {
            return null;
        }

        return $card;
    }

    protected function insertCardAt(int|string $sectionId, int|string $cardId, ?int $index): void
    {
        /** @var Collection<int, int> $ids */
        $ids = Card::query()->where('section_id', $sectionId)->orderBy('sort')->pluck('id');
        $ids = $ids->reject(fn ($id) => (int) $id === (int) $cardId)->values()->all();

        $index = $index === null ? count($ids) : max(0, min($index, count($ids)));
        array_splice($ids, $index, 0, [$cardId]);

        foreach ($ids as $position => $id) {
            Card::query()->whereKey($id)->update(['sort' => $position]);
        }
    }

    protected function reindexCardsInSection(int|string $sectionId): void
    {
        Card::query()
            ->where('section_id', $sectionId)
            ->orderBy('sort')
            ->pluck('id')
            ->each(function ($id, int $index) {
                Card::query()->whereKey($id)->update(['sort' => $index]);
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
            ->record(fn (array $arguments): ?Card => $this->ownedCard($arguments['card'] ?? null))
            ->schema(fn (): array => static::cardFormComponents())
            ->fillForm(function (array $arguments): array {
                $card = $this->ownedCard($arguments['card'] ?? null);

                return $card?->toArray() ?? [];
            })
            ->action(function (array $data, array $arguments): void {
                $card = $this->ownedCard($arguments['card'] ?? null);

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
