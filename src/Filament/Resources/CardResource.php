<?php

namespace Filament\Launchpad\Filament\Resources;

use Filament\Actions\EditAction;
use Filament\Launchpad\Filament\Resources\CardResource\Pages\ListCards;
use Filament\Launchpad\Filament\Resources\Concerns\HasCardForm;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Pages\Launchpad;
use Filament\Launchpad\Support\LaunchpadVisibility;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Never shown in the sidebar — reached only via a flat index page (from the
 * Spaces list header) or Filament's global search. Searching a Card's
 * title/subtitle surfaces it in the search results, and clicking it
 * navigates to whatever the Card's own target_type/target_value resolves
 * to (mirroring Tile::getUrl()'s semantics), falling back to the Launchpad
 * page itself when the Card has no target (or an invalid one).
 */
class CardResource extends Resource
{
    use HasCardForm;
    use HasLaunchpadIconOptions;

    protected static ?string $model = Card::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('launchpad::launchpad.models.card');
    }

    public static function getPluralModelLabel(): string
    {
        return __('launchpad::launchpad.models.cards');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'subtitle'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Card $record */
        return $record->title;
    }

    /**
     * Shows where the card lives so identically-named cards are told apart
     * (e.g. several "Receita do Mês" across different pages).
     *
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Card $record */
        $section = $record->section;
        $page = $section?->page;
        $space = $page?->space;

        return array_filter([
            __('launchpad::launchpad.models.secao') => $section?->title,
            __('launchpad::launchpad.models.pagina') => $page?->label,
            __('launchpad::launchpad.models.space') => $space?->label,
        ]);
    }

    /**
     * Returns null (meaning: the caller drops this result entirely — see
     * Resource\Concerns\HasGlobalSearch::getGlobalSearchResults(), which
     * filters out any mapped result with a blank url) when the current user
     * cannot see this Card per the role-visibility gate. A restricted card
     * is therefore never exposed via global search to someone without the
     * role, title/details included.
     */
    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        /** @var Card $record */
        if (! LaunchpadVisibility::canView($record)) {
            return null;
        }

        try {
            $url = match ($record->target_type) {
                'url' => filled($record->target_value) ? $record->target_value : null,
                'resource' => static::resolveClassUrl($record->target_value, 'index'),
                'page' => static::resolveClassUrl($record->target_value),
                default => null,
            };
        } catch (Throwable) {
            $url = null;
        }

        if (filled($url)) {
            return $url;
        }

        try {
            return Launchpad::getUrl();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function resolveClassUrl(?string $class, ?string $name = null): ?string
    {
        if (blank($class) || (! class_exists($class)) || (! method_exists($class, 'getUrl'))) {
            return null;
        }

        return $name === null ? $class::getUrl() : $class::getUrl($name);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('section_id')
            ->columns([
                IconColumn::make('icon')
                    ->label(__('launchpad::launchpad.labels.icone'))
                    ->icon(fn (?string $state): ?string => $state)
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('title')
                    ->label(__('launchpad::launchpad.labels.titulo'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('launchpad::launchpad.labels.tipo'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'kpi' ? __('launchpad::launchpad.card_types.kpi') : __('launchpad::launchpad.card_types.atalho'))
                    ->color(fn (string $state): string => $state === 'kpi' ? 'success' : 'gray'),
                TextColumn::make('section.title')
                    ->label(__('launchpad::launchpad.table_columns.secoes'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.page.label')
                    ->label(__('launchpad::launchpad.labels.pagina'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.page.space.label')
                    ->label(__('launchpad::launchpad.models.space'))
                    ->searchable()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->schema(fn (): array => static::cardFormComponents()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCards::route('/'),
        ];
    }
}
