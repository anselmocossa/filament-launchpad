<?php

namespace Filament\Launchpad;

use Filament\Launchpad\Commands\InstallCommand;
use Filament\Launchpad\Commands\MakeCardCommand;
use Filament\Launchpad\Commands\MakeKpiCommand;
use Filament\Launchpad\Commands\MakeWidgetCommand;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Policies\CardPolicy;
use Filament\Launchpad\Policies\PagePolicy;
use Filament\Launchpad\Policies\SectionPolicy;
use Filament\Launchpad\Policies\SpacePolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\Permission\Models\Role;

class LaunchpadServiceProvider extends PackageServiceProvider
{
    public static string $name = 'launchpad';

    public static string $viewNamespace = 'launchpad';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasViews(static::$viewNamespace)
            ->hasTranslations()
            ->hasCommands([
                InstallCommand::class,
                MakeKpiCommand::class,
                MakeWidgetCommand::class,
                MakeCardCommand::class,
            ])
            ->hasMigrations([
                '2024_01_01_000001_create_launchpad_tables',
                '2024_01_01_000002_seed_default_launchpad_home',
                '2024_01_01_000003_add_panel_id_to_launchpad_spaces_table',
                '2024_01_01_000004_make_launchpad_user_ids_string',
                '2024_01_01_000005_add_tenant_scope_to_launchpad_tables',
                '2024_01_01_000006_add_tenant_id_to_launchpad_cards',
                '2024_01_01_000007_add_tenant_override_to_launchpad_tables',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        parent::packageRegistered();

        // Register the translation namespace deterministically so
        // __('launchpad::launchpad.*') resolves. Uses Laravel's own
        // loadTranslationsFrom (afterResolving translator → addNamespace)
        // WITHOUT wiping the translator's loaded-groups cache — an earlier
        // setLoaded([]) reset here leaked across tests and made whole-suite
        // runs order-dependent (strings rendered raw in later tests).
        $this->loadTranslationsFrom(
            realpath(__DIR__.'/../resources/lang') ?: __DIR__.'/../resources/lang',
            static::$name,
        );
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $this->registerLaunchpadPolicies();
    }

    /**
     * SOFT-registers the plugin's own policies for its Resource models
     * (Space/Page/Section/Card), ONLY when spatie/laravel-permission is
     * present. These models live in `Filament\Launchpad\Models\*`, outside
     * `App\Models`, so Laravel's convention-based policy auto-discovery
     * never finds `App\Policies\*Policy` for them — the host application's
     * generated (e.g. filament-shield `shield:generate`) policies are
     * simply never wired up. Registering the plugin's own policies here
     * closes that gap without requiring any consumer configuration.
     *
     * Absent spatie/laravel-permission, this is a no-op: every ability
     * stays implicitly allowed, exactly today's (pre-Phase E.2) behaviour.
     */
    protected function registerLaunchpadPolicies(): void
    {
        if (! class_exists(Role::class)) {
            return;
        }

        Gate::policy(Space::class, SpacePolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Section::class, SectionPolicy::class);
        Gate::policy(Card::class, CardPolicy::class);
    }
}
