<?php

namespace Filament\Launchpad\Commands;

use Illuminate\Console\Command;

/**
 * Convenience installer: publishes the package config and optionally runs
 * migrations. Never passes --force to `migrate` — the consuming
 * app's developer must confirm interactively, in line with this project's
 * "never migrate --force" rule.
 */
class InstallCommand extends Command
{
    protected $signature = 'launchpad:install {--migrate : Run migrations after publishing}';

    protected $description = 'Install the Launchpad package: publish its config and optionally run migrations.';

    public function handle(): int
    {
        $this->info('Publishing Launchpad config...');
        $this->call('vendor:publish', ['--tag' => 'launchpad-config']);

        $this->line('Launchpad migrations are loaded automatically by the package.');

        if ($this->option('migrate')) {
            if ($this->confirm('Run the pending migrations now?')) {
                $this->call('migrate');
            } else {
                $this->line('Skipped running migrations. Run `php artisan migrate` yourself when ready.');
            }
        }

        $this->line('');
        $this->info('Launchpad installed.');
        $this->line('Next steps:');
        $this->line('  1. Register the plugin on your Filament panel provider:');
        $this->line('       ->plugin(\Filament\Launchpad\LaunchpadPlugin::make())');
        $this->line('  2. If you skipped --migrate, run `php artisan migrate` when ready.');
        $this->line('  3. Review the published config at config/launchpad.php.');

        return self::SUCCESS;
    }
}
