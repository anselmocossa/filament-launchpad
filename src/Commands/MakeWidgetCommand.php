<?php

namespace Filament\Launchpad\Commands;

use Filament\Launchpad\Commands\Concerns\GeneratesLaunchpadClass;
use Illuminate\Console\Command;

/**
 * Scaffolds a Filament widget (extends StatsOverviewWidget, no custom view
 * needed) for use in a launchpad Section/Card slot. By default it lands in
 * app/Launchpad/Widgets (namespace App\Launchpad\Widgets); when the host app
 * configures `launchpad.generators.module_path` / `module_namespace`,
 * --module=X (or an interactive pick) places it under that module's own
 * Widgets subfolder instead — see Concerns\GeneratesLaunchpadClass.
 *
 * The generated class simply needs to be discoverable (Filament's own
 * discoverWidgets()) or registered on the panel/plugin for launchpad's
 * widget slot to pick it up; no further wiring is done by this command.
 */
class MakeWidgetCommand extends Command
{
    use GeneratesLaunchpadClass;

    protected $signature = 'make:launchpad-widget {name : The name of the widget class} {--module= : Place the class inside this module instead of the generic default} {--force : Overwrite the class if it already exists}';

    protected $description = 'Create a new Launchpad widget class.';

    public function handle(): int
    {
        $location = $this->resolveGeneratorLocation($this->argument('name'), 'Widgets');

        $wrote = $this->writeGeneratedClass(
            stub: 'launchpad-widget.stub',
            directory: $location['directory'],
            namespace: $location['namespace'],
            class: $location['class'],
            force: (bool) $this->option('force'),
        );

        if (! $wrote) {
            return self::FAILURE;
        }

        $fqcn = $location['namespace'].'\\'.$location['class'];

        $this->components->info("Launchpad widget [{$fqcn}] created successfully.");
        $this->line('Next: fill in getStats(), then register it with LaunchpadPlugin::make()->widgets([...]) or let discoverWidgets() find it automatically.');

        return self::SUCCESS;
    }
}
