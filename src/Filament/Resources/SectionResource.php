<?php

namespace Filament\Launchpad\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadVisibilityField;
use Filament\Launchpad\Filament\Resources\SectionResource\Pages\CreateSection;
use Filament\Launchpad\Filament\Resources\SectionResource\Pages\EditSection;
use Filament\Launchpad\Filament\Resources\SectionResource\Pages\ListSections;
use Filament\Launchpad\Filament\Resources\SectionResource\RelationManagers\CardsRelationManager;
use Filament\Launchpad\Models\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Never shown in the sidebar — reached only via PageResource's Sections
 * relation manager. Its Edit page hosts the Cards relation manager, closing
 * the Space → Página → Secção → Card navigation chain.
 */
class SectionResource extends Resource
{
    use HasLaunchpadVisibilityField;

    protected static ?string $model = Section::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('launchpad::launchpad.models.secao');
    }

    public static function getPluralModelLabel(): string
    {
        return __('launchpad::launchpad.models.secoes');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FormSection::make()
                ->components([
                    TextInput::make('title')
                        ->label(__('launchpad::launchpad.labels.titulo'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('sort')
                        ->label(__('launchpad::launchpad.labels.ordem'))
                        ->numeric()
                        ->default(0)
                        ->required(),
                    ...static::visibilityRolesFormComponents(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('title')
                    ->label(__('launchpad::launchpad.labels.titulo'))
                    ->weight('medium'),
                TextColumn::make('page.label')
                    ->label(__('launchpad::launchpad.labels.pagina')),
                TextColumn::make('cards_count')
                    ->label(__('launchpad::launchpad.table_columns.cards'))
                    ->counts('cards')
                    ->badge(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CardsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSections::route('/'),
            'create' => CreateSection::route('/create'),
            'edit' => EditSection::route('/{record}/edit'),
        ];
    }
}
