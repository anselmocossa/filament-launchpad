<?php

use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Panel;

function registerPluginOnPanel(LaunchpadPlugin $plugin, string $id): Panel
{
    $panel = Panel::make()->id($id)->path($id);

    $plugin->register($panel);

    return $panel;
}

it('registers all resources (including CardResource) when auto-register is on', function () {
    $panel = registerPluginOnPanel(LaunchpadPlugin::make(), 'lp-all');

    expect($panel->getResources())
        ->toContain(CardResource::class)
        ->toContain(SpaceResource::class);
});

it('keeps the search-only CardResource when management resources are disabled', function () {
    $panel = registerPluginOnPanel(
        LaunchpadPlugin::make()->autoRegisterResources(false),
        'lp-search-only',
    );

    expect($panel->getResources())
        ->toContain(CardResource::class)
        ->not->toContain(SpaceResource::class);
});

it('can turn card global search off explicitly', function () {
    $panel = registerPluginOnPanel(
        LaunchpadPlugin::make()->autoRegisterResources(false)->cardGlobalSearch(false),
        'lp-no-search',
    );

    expect($panel->getResources())->not->toContain(CardResource::class);
});
