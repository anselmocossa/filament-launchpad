<?php

namespace Filament\Launchpad\Filament\Resources\Concerns;

use Filament\Forms\Components\Select;
use Filament\Launchpad\Support\LaunchpadVisibility;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section as FormSection;

/**
 * Shared "Visível para (papéis)" field, added to every launchpad item's
 * form (Space/Page/Section, and the shared Card form). Only rendered when
 * spatie/laravel-permission is actually installed (SOFT integration) — on a
 * plain install the Section is simply omitted, no error, no empty husk.
 *
 * Uses Filament's native `Select::relationship()` against `visibilityRoles`
 * (a MorphToMany added by HasLaunchpadVisibility on the model): Filament
 * itself takes care of both hydrating the current roles when the form
 * loads and syncing the pivot when the form's schema state is saved
 * (`Schema::saveRelationships()`), for every one of this field's three
 * hosts — Resource Create/Edit pages, RelationManager row actions, and the
 * drag&drop builder's edit-card modal (which explicitly binds `->record()`
 * so the schema knows which Card to sync against; see
 * InteractsWithLaunchpadBuilder::editCardAction()).
 */
trait HasLaunchpadVisibilityField
{
    /**
     * @return array<int, Component>
     */
    protected static function visibilityRolesFormComponents(): array
    {
        if (! LaunchpadVisibility::spatieAvailable()) {
            return [];
        }

        return [
            Select::make('visibilityRoles')
                ->label(__('launchpad::launchpad.labels.permissao'))
                ->relationship('visibilityRoles', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->placeholder(__('launchpad::launchpad.placeholders.todos_podem_ver'))
                ->helperText(__('launchpad::launchpad.helpers.permissao_vazia')),
        ];
    }

    /**
     * The visibility field wrapped in its own plain, headerless Section — for
     * forms (like the Card form) whose fields already live inside Sections, so
     * it sits flush with them instead of floating loose at the schema root.
     * Empty when Spatie isn't installed, so no empty husk is ever rendered.
     *
     * @return array<int, Component>
     */
    protected static function visibilityRolesSection(): array
    {
        $components = static::visibilityRolesFormComponents();

        if ($components === []) {
            return [];
        }

        return [
            FormSection::make()
                ->components($components)
                ->columnSpanFull(),
        ];
    }
}
