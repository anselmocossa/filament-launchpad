<?php

namespace Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Launchpad\Filament\Resources\Concerns\HasCardForm;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Manages a Section's `cards` BelongsToMany relationship. A Card here is a
 * REFERENCE into the global catalog (/admin/cards), not an owned child:
 * removing a row from this table only ever DETACHES the pivot (see
 * DetachAction below) — the Card itself, and any other section referencing
 * it, survives. Permanently deleting a Card is only ever done from
 * CardResource's own table.
 */
class CardsRelationManager extends RelationManager
{
    use HasCardForm;
    use HasLaunchpadIconOptions;

    protected static string $relationship = 'cards';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('launchpad::launchpad.models.cards');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(static::cardFormComponents());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                IconColumn::make('icon')
                    ->label(__('launchpad::launchpad.labels.icone'))
                    ->icon(fn (?string $state): ?string => $state)
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('title')
                    ->label(__('launchpad::launchpad.labels.titulo'))
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label(__('launchpad::launchpad.labels.tipo'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'kpi' ? __('launchpad::launchpad.card_types.kpi') : __('launchpad::launchpad.card_types.atalho'))
                    ->color(fn (string $state): string => $state === 'kpi' ? 'success' : 'gray'),
            ])
            ->headerActions([
                // Creates a BRAND NEW Card and attaches it to this section.
                CreateAction::make()
                    ->label(__('launchpad::launchpad.buttons.novo_card'))
                    ->slideOver(),
                // Attaches an EXISTING Card from the catalog to this section,
                // without creating a new record — the same card can therefore
                // end up referenced by several sections.
                AttachAction::make()
                    ->label(__('launchpad::launchpad.buttons.anexar_card'))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver(),
                // REMOVES the card from THIS section only (detaches the pivot
                // row). The Card itself is never deleted here.
                DetachAction::make()
                    ->label(__('launchpad::launchpad.buttons.remover_da_seccao')),
            ]);
    }
}
