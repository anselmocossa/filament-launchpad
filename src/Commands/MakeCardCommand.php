<?php

namespace Filament\Launchpad\Commands;

use Filament\Launchpad\Commands\Concerns\GeneratesLaunchpadClass;
use Illuminate\Console\Command;

/**
 * Scaffolds a CardPreset class (extends BaseCardPreset) for the drag&drop
 * Builder's "Biblioteca de Cards" — the card-preset counterpart to
 * make:launchpad-kpi. By default it lands flat in app/Filament/Launchpad
 * (namespace App\Filament\Launchpad); `--model=User` places it under a
 * `User/` subfolder instead — see Concerns\GeneratesLaunchpadClass. The
 * class name always ends in "Card" (appended automatically if omitted), à
 * la Filament's own ...Exporter/...Resource convention:
 * `make:launchpad-card SalesToday` generates `SalesTodayCard`.
 */
class MakeCardCommand extends Command
{
    use GeneratesLaunchpadClass;

    protected $signature = 'make:launchpad-card {name : The name of the card preset class} {--model= : Place the class inside this model\'s subfolder instead of the flat default} {--force : Overwrite the class if it already exists}';

    protected $description = 'Create a new Launchpad card preset class.';

    protected string $suffix = 'Card';

    public function handle(): int
    {
        $location = $this->resolveGeneratorLocation($this->argument('name'), $this->suffix);

        $wrote = $this->writeGeneratedClass(
            stub: 'launchpad-card.stub',
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

        $this->components->info("Launchpad card preset [{$fqcn}] created successfully.");

        return self::SUCCESS;
    }
}
