<?php

namespace Filament\Launchpad\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadVisibilityField;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\CreateSpace;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\EditSpace;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\ListSpaces;
use Filament\Launchpad\Filament\Resources\SpaceResource\RelationManagers\PagesRelationManager;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SpaceResource extends Resource
{
    use HasLaunchpadIconOptions;
    use HasLaunchpadVisibilityField;

    protected static ?string $model = Space::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'Launchpad';

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return __('launchpad::launchpad.models.space');
    }

    public static function getPluralModelLabel(): string
    {
        return __('launchpad::launchpad.models.spaces');
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
                IconColumn::make('icon')
                    ->label(__('launchpad::launchpad.labels.icone'))
                    ->icon(fn (?string $state): ?string => $state)
                    ->color('gray')
                    ->grow(false),
                TextColumn::make('label')
                    ->label(__('launchpad::launchpad.labels.nome'))
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('pages_count')
                    ->label(__('launchpad::launchpad.table_columns.paginas'))
                    ->counts('pages')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Space $record): bool => $record->is_default),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PagesRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (SchemaFacade::hasColumn('launchpad_spaces', 'panel_id') && filled($panelId = LaunchpadPanel::id())) {
            $query->forPanel($panelId);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaces::route('/'),
            'create' => CreateSpace::route('/create'),
            'edit' => EditSpace::route('/{record}/edit'),
        ];
    }
}
