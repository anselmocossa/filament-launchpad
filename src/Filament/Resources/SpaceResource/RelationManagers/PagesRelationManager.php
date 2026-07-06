<?php

namespace Filament\Launchpad\Filament\Resources\SpaceResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Models\Page;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PagesRelationManager extends RelationManager
{
    use HasLaunchpadIconOptions;

    protected static string $relationship = 'pages';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('launchpad::launchpad.models.paginas');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label(__('launchpad::launchpad.labels.nome'))
                ->required()
                ->maxLength(255),
            Select::make('icon')
                ->label(__('launchpad::launchpad.labels.icone'))
                ->options(static::launchpadIconOptions())
                ->searchable(),
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
            ->recordTitleAttribute('label')
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('label')
                    ->label(__('launchpad::launchpad.labels.nome'))
                    ->weight('medium'),
                TextColumn::make('sections_count')
                    ->label(__('launchpad::launchpad.table_columns.secoes'))
                    ->counts('sections')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('launchpad::launchpad.buttons.nova_pagina')),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(__('launchpad::launchpad.buttons.editar'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Page $record): string => PageResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
