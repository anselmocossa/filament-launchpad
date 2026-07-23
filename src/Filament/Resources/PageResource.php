<?php

namespace Filament\Launchpad\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadVisibilityField;
use Filament\Launchpad\Filament\Resources\Concerns\ScopesToLaunchpadTenant;
use Filament\Launchpad\Filament\Resources\PageResource\Pages\BuildLayout;
use Filament\Launchpad\Filament\Resources\PageResource\Pages\CreatePage;
use Filament\Launchpad\Filament\Resources\PageResource\Pages\EditPage;
use Filament\Launchpad\Filament\Resources\PageResource\Pages\ListPages;
use Filament\Launchpad\Filament\Resources\PageResource\RelationManagers\SectionsRelationManager;
use Filament\Launchpad\Models\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Never shown in the sidebar — reached only via SpaceResource's Pages
 * relation manager. Kept as a full Resource (not a modal form) so its Edit
 * page can host the Sections relation manager, per the Space → Página →
 * Secção → Card navigation chain.
 */
class PageResource extends Resource
{
    use HasLaunchpadIconOptions;
    use HasLaunchpadVisibilityField;
    use ScopesToLaunchpadTenant;

    protected static ?string $model = Page::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('launchpad::launchpad.models.pagina');
    }

    public static function getPluralModelLabel(): string
    {
        return __('launchpad::launchpad.models.paginas');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->components([
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
                TextColumn::make('label')
                    ->label(__('launchpad::launchpad.labels.nome'))
                    ->weight('medium'),
                TextColumn::make('space.label')
                    ->label(__('launchpad::launchpad.models.space')),
                TextColumn::make('sections_count')
                    ->label(__('launchpad::launchpad.table_columns.secoes'))
                    ->counts('sections')
                    ->badge(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->visible(fn (Page $record): bool => static::launchpadRecordEditableByCurrentTenant($record)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
            'build' => BuildLayout::route('/{record}/build'),
        ];
    }
}
