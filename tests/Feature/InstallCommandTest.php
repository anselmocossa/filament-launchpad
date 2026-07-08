<?php

use Illuminate\Support\Facades\File;

/**
 * `launchpad:install` runs `vendor:publish` under the hood, which copies the
 * package migrations into the running app's database/migrations directory. In
 * the testbench harness that directory is SHARED across the whole suite and is
 * auto-loaded on every RefreshDatabase boot, so any published copy would make
 * the launchpad tables be created twice ("table already exists") and take the
 * entire suite down. We therefore delete anything the command publishes, both
 * before and after each test, keeping the shared app directory pristine.
 */
function cleanupPublishedLaunchpadMigrations(): void
{
    foreach (glob(database_path('migrations/*launchpad*.php')) as $file) {
        File::delete($file);
    }
}

beforeEach(function () {
    cleanupPublishedLaunchpadMigrations();
});

afterEach(function () {
    cleanupPublishedLaunchpadMigrations();
});

it('runs the launchpad:install command', function () {
    $this->artisan('launchpad:install')->assertExitCode(0);
});

it('runs launchpad:install with --migrate and skips when not confirmed', function () {
    $this->artisan('launchpad:install', ['--migrate' => true])
        ->expectsConfirmation('Run the pending migrations now?', 'no')
        ->assertExitCode(0);
});
