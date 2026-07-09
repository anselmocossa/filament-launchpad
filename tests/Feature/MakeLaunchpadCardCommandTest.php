<?php

use App\Filament\Launchpad\PedidosEmAtrasoCard;
use App\Filament\Launchpad\SalesTodayCard;
use App\Filament\Launchpad\User\SalesTodayCard as ModelScopedSalesTodayCard;
use Filament\Launchpad\Launchpad\BaseCardPreset;
use Illuminate\Support\Facades\File;

/**
 * `make:launchpad-card` writes into the shared testbench skeleton
 * (app_path()), which persists across test runs, so (mirroring
 * MakeLaunchpadKpiCommandTest's cleanup) we wipe anything this command
 * could have generated both before and after every test — otherwise a
 * leftover file from one run would make a later run's "doesn't exist yet" /
 * "doesn't overwrite without --force" assertions fail non-deterministically.
 */
function cleanupGeneratedLaunchpadCards(): void
{
    File::deleteDirectory(app_path('Filament/Launchpad'));
}

beforeEach(function () {
    cleanupGeneratedLaunchpadCards();
});

afterEach(function () {
    cleanupGeneratedLaunchpadCards();
});

it('generates a card preset in the flat default location, appending the Card suffix', function () {
    $path = app_path('Filament/Launchpad/SalesTodayCard.php');

    expect($path)->not->toBeFile();

    $this->artisan('make:launchpad-card', ['name' => 'SalesToday'])->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad;')
        ->and($contents)->toContain('class SalesTodayCard extends BaseCardPreset')
        ->and($contents)->toContain('sales_today')
        ->and($contents)->toContain("\$title = 'Sales Today';");

    require_once $path;

    expect(class_exists(SalesTodayCard::class))->toBeTrue()
        ->and(is_subclass_of(SalesTodayCard::class, BaseCardPreset::class))->toBeTrue();

    $instance = new SalesTodayCard;
    expect(SalesTodayCard::key())->toBe('sales_today')
        ->and($instance->toArray()['title'])->toBe('Sales Today');
});

it('does not duplicate the Card suffix when the given name already ends with it', function () {
    $path = app_path('Filament/Launchpad/SalesTodayCard.php');

    $this->artisan('make:launchpad-card', ['name' => 'SalesTodayCard'])->assertExitCode(0);

    expect($path)->toBeFile()
        ->and(File::get($path))->toContain('class SalesTodayCard extends BaseCardPreset');
});

it('generates a card preset inside a model subfolder when --model is given', function () {
    $path = app_path('Filament/Launchpad/User/SalesTodayCard.php');

    $this->artisan('make:launchpad-card', ['name' => 'SalesToday', '--model' => 'User'])
        ->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad\User;')
        ->and($contents)->toContain('class SalesTodayCard extends BaseCardPreset');

    require_once $path;

    expect(class_exists(ModelScopedSalesTodayCard::class))->toBeTrue()
        ->and(is_subclass_of(ModelScopedSalesTodayCard::class, BaseCardPreset::class))->toBeTrue()
        ->and(ModelScopedSalesTodayCard::key())->toBe('sales_today');
});

it('StudlyCases a lowercase --model value', function () {
    $path = app_path('Filament/Launchpad/User/SalesTodayCard.php');

    $this->artisan('make:launchpad-card', ['name' => 'SalesToday', '--model' => 'user'])
        ->assertExitCode(0);

    expect($path)->toBeFile();
});

it('does not overwrite an existing card preset without --force', function () {
    $path = app_path('Filament/Launchpad/PedidosPendentesCard.php');

    $this->artisan('make:launchpad-card', ['name' => 'PedidosPendentes'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    $before = File::get($path);

    $this->artisan('make:launchpad-card', ['name' => 'PedidosPendentes'])->assertExitCode(1);

    expect(File::get($path))->toBe($before)
        ->and($before)->toContain('marker-from-first-generation');
});

it('overwrites an existing card preset with --force', function () {
    $path = app_path('Filament/Launchpad/PedidosEmAtrasoCard.php');

    $this->artisan('make:launchpad-card', ['name' => 'PedidosEmAtraso'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    expect(File::get($path))->toContain('marker-from-first-generation');

    $this->artisan('make:launchpad-card', ['name' => 'PedidosEmAtraso', '--force' => true])
        ->assertExitCode(0);

    expect(File::get($path))->not->toContain('marker-from-first-generation');

    require_once $path;
    expect(class_exists(PedidosEmAtrasoCard::class))->toBeTrue();
});
