<?php

use Filament\Launchpad\Launchpad\LaunchpadSpace;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Space;
use Illuminate\Support\Facades\Schema;

it('returns spaces built from the database when no config spaces are set', function () {
    Space::create(['label' => 'Vendas', 'sort' => 1]);
    Space::create(['label' => 'Clientes', 'sort' => 2]);

    $plugin = LaunchpadPlugin::make();

    $spaces = $plugin->getSpaces();

    expect($spaces)->toHaveCount(2)
        ->and($spaces[0])->toBeInstanceOf(LaunchpadSpace::class)
        ->and($spaces[0]->getLabel())->toBe('Vendas')
        ->and($spaces[1]->getLabel())->toBe('Clientes');
});

it('returns the configured spaces() array instead of the database when both are present', function () {
    Space::create(['label' => 'Vendas (BD)', 'sort' => 1]);

    $configured = [LaunchpadSpace::make('Configurado')];

    $plugin = LaunchpadPlugin::make()->spaces($configured);

    expect($plugin->getSpaces())->toBe($configured);
});

it('returns an empty array without crashing when the database is empty', function () {
    $plugin = LaunchpadPlugin::make();

    expect($plugin->getSpaces())->toBe([]);
});

it('returns an empty array without crashing when the launchpad tables do not exist', function () {
    Schema::dropIfExists('launchpad_cards');
    Schema::dropIfExists('launchpad_sections');
    Schema::dropIfExists('launchpad_pages');
    Schema::dropIfExists('launchpad_spaces');

    $plugin = LaunchpadPlugin::make();

    expect($plugin->getSpaces())->toBe([]);
});
