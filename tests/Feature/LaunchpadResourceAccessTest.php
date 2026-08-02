<?php

use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Support\LaunchpadResourceAccess;
use Filament\Launchpad\Tests\Support\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ->resourceAccess() lets the host hide Launchpad's management resources when
 * the installation does not include them (a licence tier, a feature flag, an
 * activated module). It sits ON TOP of the policies — never instead of them.
 */
$recursos = [
    'Space' => SpaceResource::class,
    'Page' => PageResource::class,
    'Section' => SectionResource::class,
    'Card' => CardResource::class,
];

function ligarResourceAccess(?Closure $resolver): void
{
    LaunchpadPlugin::get()->resourceAccess($resolver);
}

afterEach(function () {
    // The plugin instance is shared by the panel across tests in the process.
    ligarResourceAccess(null);
});

it('leaves every resource reachable when no host predicate is wired', function () use ($recursos) {
    actingAsLaunchpadAdmin();

    foreach ($recursos as $nome => $resource) {
        expect($resource::canAccess())->toBeTrue("{$nome} should stay reachable");
    }
});

it('hides every resource when the host predicate refuses', function () use ($recursos) {
    actingAsLaunchpadAdmin();

    ligarResourceAccess(fn (): bool => false);

    foreach ($recursos as $nome => $resource) {
        expect($resource::canAccess())->toBeFalse("{$nome} should be hidden");
    }
});

it('passes the resource class to the predicate so one closure can answer for all four', function () use ($recursos) {
    actingAsLaunchpadAdmin();

    $vistos = [];
    ligarResourceAccess(function (string $resource) use (&$vistos): bool {
        $vistos[] = $resource;

        return $resource !== SectionResource::class;
    });

    expect(SpaceResource::canAccess())->toBeTrue()
        ->and(SectionResource::canAccess())->toBeFalse()
        ->and($vistos)->toContain(SpaceResource::class, SectionResource::class);

    unset($recursos);
});

it('still refuses when the policy refuses, even if the host predicate allows', function () {
    // A real permission row exists, so LaunchpadPermission stops failing open
    // and actually consults the user — who does not hold it.
    Permission::query()->firstOrCreate(['name' => 'ViewAny:Space', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $user = TestUser::query()->create(['name' => 'Sem permissao']);
    $user->assignRole('staff');
    auth()->login($user);

    ligarResourceAccess(fn (): bool => true);

    expect(SpaceResource::canAccess())->toBeFalse();
});

it('falls open when the host predicate throws, rather than taking the panel down', function () {
    actingAsLaunchpadAdmin();

    ligarResourceAccess(function (): bool {
        throw new RuntimeException('host is misconfigured');
    });

    expect(SpaceResource::canAccess())->toBeTrue()
        ->and(LaunchpadResourceAccess::allows(SpaceResource::class))->toBeTrue();
});
