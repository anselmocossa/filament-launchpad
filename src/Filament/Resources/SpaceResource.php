<?php

namespace Filament\Launchpad\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadIconOptions;
use Filament\Launchpad\Filament\Resources\Concerns\HasLaunchpadVisibilityField;
use Filament\Launchpad\Filament\Resources\Concerns\ScopesToLaunchpadTenant;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\CreateSpace;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\EditSpace;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\ListSpaces;
use Filament\Launchpad\Filament\Resources\SpaceResource\RelationManagers\PagesRelationManager;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadOverride;
use Filament\Launchpad\Support\LaunchpadPanel;
use Filament\Launchpad\Support\LaunchpadTenant;
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
    use ScopesToLaunchpadTenant;

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
                        ->options(fn (?string $state): array => static::launchpadIconOptionsWith($state))
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
        // Drag-reorder writes `sort` straight onto the listed rows. In a tenant
        // context those rows include shared-template ones, so a drag would
        // change the template for every tenant. Enabled only in the primary
        // context; a tenant reorders through the per-space "Ordem" field, which
        // forks a private copy.
        if (blank(LaunchpadTenant::id())) {
            $table->reorderable('sort');
        }

        return $table
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
                TextColumn::make('tenant_id')
                    ->label('')
                    ->badge()
                    ->color('gray')
                    ->state(fn (Space $record): ?string => static::launchpadRecordIsInherited($record)
                        ? __('launchpad::launchpad.messages.badge_template')
                        : null),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Space $record): bool => static::launchpadRecordEditableByCurrentTenant($record)),
                DeleteAction::make()
                    ->hidden(fn (Space $record): bool => $record->is_default
                        || ! static::launchpadRecordEditableByCurrentTenant($record))
                    // Deleting an inherited space HIDES it for this tenant only;
                    // it never destroys the shared template (which would remove
                    // it for every tenant).
                    ->using(fn (Space $record) => LaunchpadOverride::deleteOrHide($record)),
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

        // The parent may step out of its own panel to author another one's
        // template — that is the only way to reach the tenant panel's launchpad
        // from /admin. A panel that resolves a tenant of its own never gets
        // this door (LaunchpadTenant::resolved() is filled there), so a tenant
        // can never list another panel's spaces.
        $browsingPanel = LaunchpadPanel::browsing();

        if (SchemaFacade::hasColumn('launchpad_spaces', 'panel_id') && filled($browsingPanel)) {
            $query->forPanel($browsingPanel);
        }

        // Phase H.3: the tenant's effective set under copy-on-write — the
        // template it hasn't diverged, plus its own overrides and new spaces.
        // Never another tenant's rows.
        if (SchemaFacade::hasColumn('launchpad_spaces', 'tenant_id')) {
            $query->effectiveForTenant(LaunchpadTenant::id());
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
