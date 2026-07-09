<?php

namespace Filament\Launchpad\Commands;

use Filament\Launchpad\Commands\Concerns\GeneratesLaunchpadClass;
use Illuminate\Console\Command;

/**
 * Scaffolds a KpiSource class (extends BaseKpiSource) for a live launchpad
 * tile value. By default it lands in app/Launchpad/Kpis (namespace
 * App\Launchpad\Kpis); when the host app configures
 * `launchpad.generators.module_path` / `module_namespace`, --module=X (or an
 * interactive pick) places it under that module's own Kpis subfolder
 * instead — see Concerns\GeneratesLaunchpadClass.
 */
class MakeKpiCommand extends Command
{
    use GeneratesLaunchpadClass;

    protected $signature = 'make:launchpad-kpi {name : The name of the KPI source class} {--module= : Place the class inside this module instead of the generic default} {--force : Overwrite the class if it already exists}';

    protected $description = 'Create a new Launchpad KPI source class.';

    public function handle(): int
    {
        $location = $this->resolveGeneratorLocation($this->argument('name'), 'Kpis');

        $wrote = $this->writeGeneratedClass(
            stub: 'launchpad-kpi.stub',
            directory: $location['directory'],
            namespace: $location['namespace'],
            class: $location['class'],
            force: (bool) $this->option('force'),
        );

        if (! $wrote) {
            return self::FAILURE;
        }

        $fqcn = $location['namespace'].'\\'.$location['class'];

        $this->components->info("Launchpad KPI source [{$fqcn}] created successfully.");
        $this->line("Next: implement resolve(), then register it by class — either LaunchpadPlugin::make()->kpis([\\{$fqcn}::class]) or auto-discover its folder with LaunchpadPlugin::make()->discoverKpis(in: ..., for: ...). Then reference its key() in a card's kpi_source field.");

        return self::SUCCESS;
    }
}
