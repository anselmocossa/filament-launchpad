<?php

namespace Filament\Launchpad\Commands;

use Filament\Launchpad\Commands\Concerns\GeneratesLaunchpadClass;
use Illuminate\Console\Command;

/**
 * Scaffolds a KpiSource class (extends BaseKpiSource) for a live launchpad
 * tile value. By default it lands flat in app/Filament/Launchpad (namespace
 * App\Filament\Launchpad); `--model=User` places it under a `User/`
 * subfolder instead — see Concerns\GeneratesLaunchpadClass. The class name
 * always ends in "Kpi" (appended automatically if omitted), à la Filament's
 * own ...Exporter/...Resource convention: `make:launchpad-kpi TopUser`
 * generates `TopUserKpi`.
 */
class MakeKpiCommand extends Command
{
    use GeneratesLaunchpadClass;

    protected $signature = 'make:launchpad-kpi {name : The name of the KPI source class} {--model= : Place the class inside this model\'s subfolder instead of the flat default} {--force : Overwrite the class if it already exists}';

    protected $description = 'Create a new Launchpad KPI source class.';

    protected string $suffix = 'Kpi';

    public function handle(): int
    {
        $location = $this->resolveGeneratorLocation($this->argument('name'), $this->suffix);

        $wrote = $this->writeGeneratedClass(
            stub: 'launchpad-kpi.stub',
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

        $this->components->info("Launchpad KPI source [{$fqcn}] created successfully.");

        return self::SUCCESS;
    }
}
