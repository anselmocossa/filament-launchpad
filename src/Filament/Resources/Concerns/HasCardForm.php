<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Components\Utilities\Get;
use Throwable;

/**
 * Card form schema (Conteúdo / Indicador KPI condicional / Ação), shared
 * between the CardsRelationManager (native table CRUD) and the drag&drop
 * BuildLayout page (edit-by-click), so the two entry points never drift.
 * Requires HasLaunchpadIconOptions on the consuming class for the icon list.
 *
 * Cards are a reusable catalog (belongsToMany with Section): this schema
 * never includes a Section picker — a Card is created/edited on its own, and
 * placed into section(s) via the drag&drop Builder or the
 * CardsRelationManager's Attach action.
 */
trait HasCardForm
{
    use HasLaunchpadVisibilityField;

    /**
     * @return array<int, Component>
     */
    public static function cardFormComponents(): array
    {
        return [
            FormSection::make(__('launchpad::launchpad.sections.conteudo'))
                ->description(__('launchpad::launchpad.descriptions.conteudo'))
                ->components([
                    TextInput::make('title')
                        ->label(__('launchpad::launchpad.labels.titulo'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('subtitle')
                        ->label(__('launchpad::launchpad.labels.subtitulo'))
                        ->maxLength(255),
                    Select::make('icon')
                        ->label(__('launchpad::launchpad.labels.icone'))
                        ->options(static::launchpadIconOptions())
                        ->searchable(),
                    ToggleButtons::make('type')
                        ->label(__('launchpad::launchpad.labels.tipo'))
                        ->options([
                            'kpi' => __('launchpad::launchpad.card_types.kpi'),
                            'shortcut' => __('launchpad::launchpad.card_types.atalho'),
                            'widget' => __('launchpad::launchpad.card_types.widget'),
                        ])
                        ->default('kpi')
                        ->inline()
                        ->required()
                        ->live(),
                ])
                ->columns(2)
                ->columnSpanFull(),

            FormSection::make(__('launchpad::launchpad.sections.widget'))
                ->description(__('launchpad::launchpad.descriptions.widget'))
                ->visible(fn (Get $get): bool => $get('type') === 'widget')
                ->components([
                    Select::make('widget_key')
                        ->label(__('launchpad::launchpad.labels.widget'))
                        ->options(function () {
                            try {
                                return collect(LaunchpadPlugin::get()->getWidgets())
                                    ->mapWithKeys(fn (array $widget): array => [$widget['key'] => $widget['label'] ?? $widget['key']])
                                    ->all();
                            } catch (Throwable $e) {
                                return [];
                            }
                        })
                        ->searchable()
                        ->required(fn (Get $get): bool => $get('type') === 'widget'),
                    Select::make('widget_column_span')
                        ->label(__('launchpad::launchpad.labels.largura'))
                        ->options([
                            'full' => __('launchpad::launchpad.labels.largura_total'),
                            '6' => '1/2',
                            '4' => '1/3',
                            '3' => '1/4',
                        ])
                        ->default('full')
                        ->required(fn (Get $get): bool => $get('type') === 'widget'),
                ])
                ->columns(2)
                ->columnSpanFull(),

            FormSection::make(__('launchpad::launchpad.sections.indicador_kpi'))
                ->description(__('launchpad::launchpad.descriptions.indicador_kpi'))
                ->visible(fn (Get $get): bool => $get('type') === 'kpi')
                ->components([
                    Select::make('kpi_source')
                        ->label(__('launchpad::launchpad.labels.fonte_ao_vivo'))
                        ->options(function () {
                            try {
                                return LaunchpadPlugin::get()->getKpiSourceOptions();
                            } catch (Throwable $e) {
                                return [];
                            }
                        })
                        ->searchable()
                        ->nullable()
                        ->placeholder(__('launchpad::launchpad.placeholders.valor_fixo'))
                        ->helperText(__('launchpad::launchpad.helpers.fonte_ou_valor')),
                    TextInput::make('kpi_value')
                        ->label(__('launchpad::launchpad.labels.valor_fixo'))
                        ->helperText(__('launchpad::launchpad.helpers.valor_sem_fonte'))
                        ->maxLength(255),
                    TextInput::make('unit')
                        ->label(__('launchpad::launchpad.labels.unidade'))
                        ->maxLength(255),
                    TextInput::make('trend')
                        ->label(__('launchpad::launchpad.labels.tendencia'))
                        ->maxLength(255),
                    ToggleButtons::make('trend_color')
                        ->label(__('launchpad::launchpad.labels.cor_tendencia'))
                        ->options([
                            'success' => __('launchpad::launchpad.trend_colors.success'),
                            'danger' => __('launchpad::launchpad.trend_colors.danger'),
                            'warning' => __('launchpad::launchpad.trend_colors.warning'),
                            'gray' => __('launchpad::launchpad.trend_colors.gray'),
                        ])
                        ->colors([
                            'success' => 'success',
                            'danger' => 'danger',
                            'warning' => 'warning',
                            'gray' => 'gray',
                        ])
                        ->inline(),
                    TextInput::make('badge')
                        ->label(__('launchpad::launchpad.labels.badge'))
                        ->placeholder(__('launchpad::launchpad.placeholders.badge_exemplo'))
                        ->maxLength(255),
                ])
                ->columns(2)
                ->columnSpanFull(),

            FormSection::make(__('launchpad::launchpad.sections.acao_ao_clicar'))
                ->description(__('launchpad::launchpad.descriptions.acao_ao_clicar'))
                ->visible(fn (Get $get): bool => $get('type') !== 'widget')
                ->components([
                    ToggleButtons::make('target_type')
                        ->label(__('launchpad::launchpad.labels.alvo'))
                        ->options([
                            'none' => __('launchpad::launchpad.card_types.nenhuma'),
                            'url' => __('launchpad::launchpad.labels.url'),
                            'resource' => __('launchpad::launchpad.labels.recurso'),
                            'page' => __('launchpad::launchpad.labels.pagina'),
                        ])
                        ->default('none')
                        ->inline()
                        ->required()
                        ->live(),

                    TextInput::make('target_value')
                        ->label(__('launchpad::launchpad.labels.url'))
                        ->url()
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('target_type') === 'url')
                        ->dehydrated(fn (Get $get): bool => $get('target_type') === 'url'),

                    Select::make('target_value')
                        ->label(__('launchpad::launchpad.labels.recurso'))
                        ->options(fn (): array => static::panelResourceOptions())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('target_type') === 'resource')
                        ->dehydrated(fn (Get $get): bool => $get('target_type') === 'resource'),

                    Select::make('target_value')
                        ->label(__('launchpad::launchpad.labels.pagina'))
                        ->options(fn (): array => static::panelPageOptions())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('target_type') === 'page')
                        ->dehydrated(fn (Get $get): bool => $get('target_type') === 'page'),
                ])
                ->columnSpanFull(),

            ...static::visibilityRolesSection(),
        ];
    }

    /**
     * Targets the CURRENT user may actually open.
     *
     * The rendered tile was already gated by the target's own canAccess(), but
     * the authoring dropdown was not — so on a multi-tenant panel a retail
     * shopkeeper browsing card targets could read the label of every module the
     * engine ships (Housekeeping, Hotel Reservations, Chart of Accounts…). That
     * is a product leak rather than a privilege one, but the fix is the same
     * gate, applied one step earlier.
     *
     * A target whose canAccess() throws is omitted: an authoring dropdown is
     * not the place to surface someone else's broken authorization logic.
     *
     * @return array<string, string>
     */
    protected static function panelResourceOptions(): array
    {
        $options = [];

        foreach (Filament::getResources() as $resource) {
            if (! static::targetIsAccessible($resource)) {
                continue;
            }

            $options[$resource] = $resource::getLabel() ?? class_basename($resource);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected static function panelPageOptions(): array
    {
        $options = [];

        foreach (Filament::getPages() as $page) {
            if (! static::targetIsAccessible($page)) {
                continue;
            }

            $options[$page] = $page::getNavigationLabel();
        }

        return $options;
    }

    /**
     * A target with no canAccess() at all stays visible — that is Filament's
     * own default for an ungated resource/page, and hiding it would be a
     * regression rather than a tightening.
     */
    protected static function targetIsAccessible(string $target): bool
    {
        if (! method_exists($target, 'canAccess')) {
            return true;
        }

        try {
            return (bool) $target::canAccess();
        } catch (Throwable) {
            return false;
        }
    }
}
