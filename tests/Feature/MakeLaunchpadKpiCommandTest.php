<?php

use App\Launchpad\Kpis\VendasHoje;
use App\Modules\Sales\Kpis\ReceitaMensal;
use Filament\Launchpad\Launchpad\BaseKpiSource;
use Filament\Launchpad\Launchpad\KpiResult;
use Illuminate\Support\Facades\File;

/**
 * `make:launchpad-kpi` writes into the shared testbench skeleton
 * (app_path()) and, for the --module scenarios, into a fixture directory
 * under the also-shared storage_path(). Both persist across test runs, so
 * (mirroring InstallCommandTest's launchpad-migrations cleanup) we wipe
 * anything this command could have generated both before and after every
 * test — otherwise a leftover file from one run would make a later run's
 * "doesn't exist yet" / "doesn't overwrite without --force" assertions
 * fail non-deterministically.
 */
function cleanupGeneratedLaunchpadKpis(): void
{
    File::deleteDirectory(app_path('Launchpad'));
    File::deleteDirectory(storage_path('framework/testing/launchpad-kpi-modules'));
}

beforeEach(function () {
    cleanupGeneratedLaunchpadKpis();
});

afterEach(function () {
    cleanupGeneratedLaunchpadKpis();
});

it('generates a kpi source in the generic default location', function () {
    $path = app_path('Launchpad/Kpis/VendasHoje.php');

    expect($path)->not->toBeFile();

    $this->artisan('make:launchpad-kpi', ['name' => 'VendasHoje'])->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Launchpad\Kpis;')
        ->and($contents)->toContain('class VendasHoje extends BaseKpiSource')
        ->and($contents)->toContain('function resolve(): KpiResult');

    require_once $path;

    expect(class_exists(VendasHoje::class))->toBeTrue()
        ->and(is_subclass_of(VendasHoje::class, BaseKpiSource::class))->toBeTrue();

    $instance = new VendasHoje;
    expect($instance->resolve())->toBeInstanceOf(KpiResult::class)
        ->and(VendasHoje::key())->toBe('vendas_hoje');
});

it('generates a kpi source inside a configured module when --module is given', function () {
    $modulePath = storage_path('framework/testing/launchpad-kpi-modules');
    config()->set('launchpad.generators.module_path', $modulePath);
    config()->set('launchpad.generators.module_namespace', 'App\\Modules');

    $path = $modulePath.'/Sales/Kpis/ReceitaMensal.php';

    $this->artisan('make:launchpad-kpi', ['name' => 'ReceitaMensal', '--module' => 'Sales'])
        ->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Modules\Sales\Kpis;')
        ->and($contents)->toContain('class ReceitaMensal extends BaseKpiSource');

    require_once $path;

    expect(class_exists(ReceitaMensal::class))->toBeTrue()
        ->and(is_subclass_of(ReceitaMensal::class, BaseKpiSource::class))->toBeTrue();
});

it('falls back to the generic default when --module is given without generator config', function () {
    $path = app_path('Launchpad/Kpis/PedidosHoje.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosHoje', '--module' => 'Sales'])
        ->assertExitCode(0);

    expect($path)->toBeFile();
    expect(File::get($path))->toContain('namespace App\Launchpad\Kpis;');
});

it('does not overwrite an existing kpi source without --force', function () {
    $path = app_path('Launchpad/Kpis/PedidosPendentes.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosPendentes'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    $before = File::get($path);

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosPendentes'])->assertExitCode(1);

    expect(File::get($path))->toBe($before)
        ->and($before)->toContain('marker-from-first-generation');
});

it('overwrites an existing kpi source with --force', function () {
    $path = app_path('Launchpad/Kpis/PedidosEmAtraso.php');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosEmAtraso'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    expect(File::get($path))->toContain('marker-from-first-generation');

    $this->artisan('make:launchpad-kpi', ['name' => 'PedidosEmAtraso', '--force' => true])
        ->assertExitCode(0);

    expect(File::get($path))->not->toContain('marker-from-first-generation');
});
