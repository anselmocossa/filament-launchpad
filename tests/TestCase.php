<?php

namespace Filament\Launchpad\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Launchpad\LaunchpadServiceProvider;
use Filament\Launchpad\Tests\Support\TestUser;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        // Order matters: Filament\Support rebinds Livewire's internal
        // DataStore mechanism (non-singleton), so it must be registered
        // *before* LivewireServiceProvider runs its own mechanism
        // registration — otherwise Livewire's per-request state (error
        // bags, etc.) never persists across a request. In a normal
        // Laravel app this ordering falls out of composer's alphabetical
        // package auto-discovery (filament/* sorts before livewire/*);
        // here we replicate it explicitly since discovery is disabled.
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            // Registered so Phase E's gating (LaunchpadVisibility,
            // HasLaunchpadVisibility) has spatie/laravel-permission's Role
            // model available to exercise in tests — the plugin itself
            // only ever *requires* it in require-dev and stays SOFT
            // (class_exists-guarded) in production; see composer.json.
            PermissionServiceProvider::class,
            LaunchpadServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('session.driver', 'array');

        // The plugin's UI strings are wrapped in __('launchpad::launchpad....'); pin the
        // test locale to European Portuguese so assertions on the original PT
        // wording keep matching after the strings became translatable.
        $app['config']->set('app.locale', 'pt_PT');
        $app['config']->set('app.fallback_locale', 'pt_PT');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // spatie/laravel-permission's own migration (`.stub`, since it's
        // meant to be vendor:publish'd) is skipped by loadMigrationsFrom()'s
        // *.php glob — run it directly instead so `roles`/`permissions`
        // exist for gating tests without ever touching the app's own
        // migrations folder.
        $app['config']->set('permission.testing', true);

        // A minimal, explicit "web" guard/provider pointing at
        // Tests\Support\TestUser, so LaunchpadVisibility's gating tests can
        // assignRole()/hasRole() a real spatie/laravel-permission user
        // without Spatie's GuardDoesNotMatch guard-resolution kicking in.
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $migration = require __DIR__.'/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
        $migration->up();

        // A bare `users` table, solely so Tests\Support\TestUser (a real
        // spatie/laravel-permission HasRoles authenticatable) can exist and
        // be assigned roles when exercising LaunchpadVisibility's gate.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }
}
