<?php

namespace Filament\Launchpad\Filament\Resources\PageResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Models\Section;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('launchpad::launchpad.models.secoes');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('launchpad::launchpad.labels.titulo'))
                ->required()
                ->maxLength(255),
            TextInput::make('sort')
                ->label(__('launchpad::launchpad.labels.ordem'))
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')
                    ->label(__('launchpad::launchpad.labels.titulo'))
                    ->weight('medium'),
                TextColumn::make('cards_count')
                    ->label(__('launchpad::launchpad.table_columns.cards'))
                    ->counts('cards')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('launchpad::launchpad.buttons.nova_secao')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('launchpad::launchpad.buttons.editar'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Section $record): string => SectionResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
