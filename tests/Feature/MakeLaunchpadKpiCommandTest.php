<?php

use App\Filament\Launchpad\PedidosEmAtrasoKpi;
use App\Filament\Launchpad\TopUserKpi;
use App\Filament\Launchpad\User\TopUserKpi as ModelScopedTopUserKpi;
use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;
use Illuminate\Support\Facades\File;

/**
 * `make:launchpad-kpi` writes into the shared testbench skeleton
 * (app_path()), which persists across test runs, so (mirroring
 * InstallCommandTest's launchpad-migrations cleanup) we wipe anything this
 * command could have generated both before and after every test —
 * otherwise a leftover file from one run would make a later run's "doesn't
 * exist yet" / "doesn't overwrite without --force" assertions fail
 * non-deterministically.
 */
function cleanupGeneratedLaunchpadKpis(): void
{
    File::deleteDirectory(app_path('Filament/Launchpad'));
}

beforeEach(function () {
    cleanupGeneratedLaunchpadKpis();
});

afterEach(function () {
    cleanupGeneratedLaunchpadKpis();
});

it('generates a kpi source in the flat default location, appending the Kpi suffix', function () {
    $path = app_path('Filament/Launchpad/TopUserKpi.php');

    expect($path)->not->toBeFile();

    $this->artisan('make:launchpad-kpi', ['name' => 'TopUser'])->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad;')
        ->and($contents)->toContain('class TopUserKpi extends BaseKpiSource')
        ->and($contents)->toContain('function resolve(): KpiResult')
        ->and($contents)->toContain('"top_user"')
        ->and($contents)->toContain('"Top User"');

    require_once $path;

    expect(class_exists(TopUserKpi::class))->toBeTrue()
        ->and(is_subclass_of(TopUserKpi::class, BaseKpiSource::class))->toBeTrue();

    $instance = new TopUserKpi;
    expect($instance->resolve())->toBeInstanceOf(KpiResult::class)
        ->and(TopUserKpi::key())->toBe('top_user')
        ->and($instance->label())->toBe('Top User');
});

it('does not duplicate the Kpi suffix when the given name already ends with it', function () {
    $path = app_path('Filament/Launchpad/TopUserKpi.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'TopUserKpi'])->assertExitCode(0);

    expect($path)->toBeFile()
        ->and(File::get($path))->toContain('class TopUserKpi extends BaseKpiSource');
});

it('generates a kpi source inside a model subfolder when --model is given', function () {
    $path = app_path('Filament/Launchpad/User/TopUserKpi.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'TopUser', '--model' => 'User'])
        ->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad\User;')
        ->and($contents)->toContain('class TopUserKpi extends BaseKpiSource');

    require_once $path;

    expect(class_exists(ModelScopedTopUserKpi::class))->toBeTrue()
        ->and(is_subclass_of(ModelScopedTopUserKpi::class, BaseKpiSource::class))->toBeTrue()
        ->and(ModelScopedTopUserKpi::key())->toBe('top_user');
});

it('StudlyCases a lowercase --model value', function () {
    $path = app_path('Filament/Launchpad/User/TopUserKpi.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'TopUser', '--model' => 'user'])
        ->assertExitCode(0);

    expect($path)->toBeFile();
});

it('does not overwrite an existing kpi source without --force', function () {
    $path = app_path('Filament/Launchpad/PedidosPendentesKpi.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosPendentes'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    $before = File::get($path);

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosPendentes'])->assertExitCode(1);

    expect(File::get($path))->toBe($before)
        ->and($before)->toContain('marker-from-first-generation');
});

it('overwrites an existing kpi source with --force', function () {
    $path = app_path('Filament/Launchpad/PedidosEmAtrasoKpi.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosEmAtraso'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    expect(File::get($path))->toContain('marker-from-first-generation');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosEmAtraso', '--force' => true])
        ->assertExitCode(0);

    expect(File::get($path))->not->toContain('marker-from-first-generation');

    require_once $path;
    expect(class_exists(PedidosEmAtrasoKpi::class))->toBeTrue();
});
