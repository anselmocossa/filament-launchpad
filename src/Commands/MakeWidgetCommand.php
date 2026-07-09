<?php

namespace Filament\Launchpad\Commands;

use Filament\Launchpad\Commands\Concerns\GeneratesLaunchpadClass;
use Illuminate\Console\Command;

/**
 * Scaffolds a Filament widget (extends StatsOverviewWidget, no custom view
 * needed) for use in a launchpad Section/Card slot. By default it lands
 * flat in app/Filament/Launchpad (namespace App\Filament\Launchpad);
 * `--model=User` places it under a `User/` subfolder instead — see
 * Concerns\GeneratesLaunchpadClass. The class name always ends in "Widget"
 * (appended automatically if omitted).
 *
 * The generated class simply needs to be discoverable (Filament's own
 * discoverWidgets()) or registered on the panel/plugin for launchpad's
 * widget slot to pick it up; no further wiring is done by this command.
 */
class MakeWidgetCommand extends Command
{
    use GeneratesLaunchpadClass;

    protected $signature = 'make:launchpad-widget {name : The name of the widget class} {--model= : Place the class inside this model\'s subfolder instead of the flat default} {--force : Overwrite the class if it already exists}';

    protected $description = 'Create a new Launchpad widget class.';

    protected string $suffix = 'Widget';

    public function handle(): int
    {
        $location = $this->resolveGeneratorLocation($this->argument('name'), $this->suffix);

        $wrote = $this->writeGeneratedClass(
            stub: 'launchpad-widget.stub',
            directory: $location['directory'],
            namespace: $location['namespace'],
            class: $location['class'],
            suffix: $this->suffix,
            force: (bool) $this->option('force'),
        );

        if (! $wrote) {
            return self::FAILURE;
        }

        $fqcn = $location['namespace'].'\\'.$location['class'];

        $this->components->info("Launchpad widget [{$fqcn}] created successfully.");

        return self::SUCCESS;
    }
}
