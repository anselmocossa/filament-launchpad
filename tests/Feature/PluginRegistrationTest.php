<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\LaunchpadServiceProvider;
use Illuminate\Support\Facades\Config;

it('resolves the plugin id', function () {
    expect(LaunchpadPlugin::make()->getId())->toBe('launchpad');
});

it('registers the service provider without errors', function () {
    expect(app()->getProviders(LaunchpadServiceProvider::class))->not->toBeEmpty();
});

it('publishes the launchpad config', function () {
    expect(Config::get('launchpad'))->not->toBeNull()
        ->and(Config::get('launchpad.branding.name'))->toBe('Launchpad');
});

it('is enabled by default and can be toggled', function () {
    $plugin = LaunchpadPlugin::make();

    expect($plugin->isEnabled())->toBeTrue();

    $plugin->enabled(false);

    expect($plugin->isEnabled())->toBeFalse();
});
