<?php

use App\Launchpad\Widgets\TotalVendas;
use App\Modules\Sales\Widgets\ReceitaWidget;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\File;

/**
 * Same shared-testbench-skeleton gotcha as MakeLaunchpadKpiCommandTest and
 * InstallCommandTest: wipe anything this command could have generated
 * before and after every test.
 */
function cleanupGeneratedLaunchpadWidgets(): void
{
    File::deleteDirectory(app_path('Launchpad'));
    File::deleteDirectory(storage_path('framework/testing/launchpad-widget-modules'));
}

beforeEach(function () {
    cleanupGeneratedLaunchpadWidgets();
});

afterEach(function () {
    cleanupGeneratedLaunchpadWidgets();
});

it('generates a widget in the generic default location', function () {
    $path = app_path('Launchpad/Widgets/TotalVendas.php');

    expect($path)->not->toBeFile();

    $this->artisan('make:launchpad-widget', ['name' => 'TotalVendas'])->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Launchpad\Widgets;')
        ->and($contents)->toContain('class TotalVendas extends StatsOverviewWidget')
        ->and($contents)->toContain('function getStats(): array');

    require_once $path;

    expect(class_exists(TotalVendas::class))->toBeTrue()
        ->and(is_subclass_of(TotalVendas::class, StatsOverviewWidget::class))->toBeTrue();

    $method = new ReflectionMethod(TotalVendas::class, 'getStats');
    expect($method->isProtected())->toBeTrue();
});

it('generates a widget inside a configured module when --module is given', function () {
    $modulePath = storage_path('framework/testing/launchpad-widget-modules');
    config()->set('launchpad.generators.module_path', $modulePath);
    config()->set('launchpad.generators.module_namespace', 'App\\Modules');

    $path = $modulePath.'/Sales/Widgets/ReceitaWidget.php';

    $this->artisan('make:launchpad-widget', ['name' => 'ReceitaWidget', '--module' => 'Sales'])
        ->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Modules\Sales\Widgets;')
        ->and($contents)->toContain('class ReceitaWidget extends StatsOverviewWidget');

    require_once $path;

    expect(class_exists(ReceitaWidget::class))->toBeTrue()
        ->and(is_subclass_of(ReceitaWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('does not overwrite an existing widget without --force', function () {
    $path = app_path('Launchpad/Widgets/PedidosWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'PedidosWidget'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    $before = File::get($path);

    $this->artisan('make:launchpad-widget', ['name' => 'PedidosWidget'])->assertExitCode(1);

    expect(File::get($path))->toBe($before)
        ->and($before)->toContain('marker-from-first-generation');
});

it('overwrites an existing widget with --force', function () {
    $path = app_path('Launchpad/Widgets/EstoqueWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'EstoqueWidget'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    expect(File::get($path))->toContain('marker-from-first-generation');

    $this->artisan('make:launchpad-widget', ['name' => 'EstoqueWidget', '--force' => true])
        ->assertExitCode(0);

    expect(File::get($path))->not->toContain('marker-from-first-generation');
});
