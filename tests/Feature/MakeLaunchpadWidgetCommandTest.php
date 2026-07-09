<?php

use App\Filament\Launchpad\EstoqueWidget;
use App\Filament\Launchpad\TotalVendasWidget;
use App\Filament\Launchpad\User\TotalVendasWidget as ModelScopedTotalVendasWidget;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\File;

/**
 * Same shared-testbench-skeleton gotcha as MakeLaunchpadKpiCommandTest and
 * InstallCommandTest: wipe anything this command could have generated
 * before and after every test.
 */
function cleanupGeneratedLaunchpadWidgets(): void
{
    File::deleteDirectory(app_path('Filament/Launchpad'));
}

beforeEach(function () {
    cleanupGeneratedLaunchpadWidgets();
});

afterEach(function () {
    cleanupGeneratedLaunchpadWidgets();
});

it('generates a widget in the flat default location, appending the Widget suffix', function () {
    $path = app_path('Filament/Launchpad/TotalVendasWidget.php');

    expect($path)->not->toBeFile();

    $this->artisan('make:launchpad-widget', ['name' => 'TotalVendas'])->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad;')
        ->and($contents)->toContain('class TotalVendasWidget extends StatsOverviewWidget')
        ->and($contents)->toContain('function getStats(): array');

    require_once $path;

    expect(class_exists(TotalVendasWidget::class))->toBeTrue()
        ->and(is_subclass_of(TotalVendasWidget::class, StatsOverviewWidget::class))->toBeTrue();

    $method = new ReflectionMethod(TotalVendasWidget::class, 'getStats');
    expect($method->isProtected())->toBeTrue();
});

it('does not duplicate the Widget suffix when the given name already ends with it', function () {
    $path = app_path('Filament/Launchpad/TotalVendasWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'TotalVendasWidget'])->assertExitCode(0);

    expect($path)->toBeFile()
        ->and(File::get($path))->toContain('class TotalVendasWidget extends StatsOverviewWidget');
});

it('generates a widget inside a model subfolder when --model is given', function () {
    $path = app_path('Filament/Launchpad/User/TotalVendasWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'TotalVendas', '--model' => 'User'])
        ->assertExitCode(0);

    expect($path)->toBeFile();

    $contents = File::get($path);
    expect($contents)->toContain('namespace App\Filament\Launchpad\User;')
        ->and($contents)->toContain('class TotalVendasWidget extends StatsOverviewWidget');

    require_once $path;

    expect(class_exists(ModelScopedTotalVendasWidget::class))->toBeTrue()
        ->and(is_subclass_of(ModelScopedTotalVendasWidget::class, StatsOverviewWidget::class))->toBeTrue();
});

it('does not overwrite an existing widget without --force', function () {
    $path = app_path('Filament/Launchpad/PedidosWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'Pedidos'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    $before = File::get($path);

    $this->artisan('make:launchpad-widget', ['name' => 'Pedidos'])->assertExitCode(1);

    expect(File::get($path))->toBe($before)
        ->and($before)->toContain('marker-from-first-generation');
});

it('overwrites an existing widget with --force', function () {
    $path = app_path('Filament/Launchpad/EstoqueWidget.php');

    $this->artisan('make:launchpad-widget', ['name' => 'Estoque'])->assertExitCode(0);

    File::append($path, "\n// marker-from-first-generation\n");
    expect(File::get($path))->toContain('marker-from-first-generation');

    $this->artisan('make:launchpad-widget', ['name' => 'Estoque', '--force' => true])
        ->assertExitCode(0);

    expect(File::get($path))->not->toContain('marker-from-first-generation');

    require_once $path;
    expect(class_exists(EstoqueWidget::class))->toBeTrue();
});
