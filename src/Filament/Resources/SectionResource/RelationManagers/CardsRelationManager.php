<?php

namespace Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Launchpad\Filament\Resources\Concerns\HasCardForm;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                CreateAction::make()
                    ->label(__('launchpad::launchpad.buttons.novo_card'))
                    ->slideOver(),
            ])
            ->recordActions([
                EditAction::make()
                    ->slideOver(),
                DeleteAction::make(),
            ]);
    }
}
