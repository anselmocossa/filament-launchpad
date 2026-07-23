<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadOverride;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Phase H.3 — the "Windows profile" model: a tenant that edits an inherited
 * record diverges into its own copy, leaving the template and every other
 * tenant untouched.
 */
beforeEach(function () {
    actingAsLaunchpadAdmin();
    LaunchpadTenant::clearOverride();
    LaunchpadPlugin::get()->tenantInheritance('fork')->tenantResolver(fn () => null);
});

afterEach(function () {
    LaunchpadTenant::clearOverride();
    LaunchpadPlugin::get()->tenantResolver(null);
});

/**
 * @return array<int, string>
 */
function effectiveSpaceLabels(?string $tenantId): array
{
    return LaunchpadTenant::actingAs($tenantId, fn () => Space::query()
        ->effectiveForTenant($tenantId)
        ->orderBy('sort')
        ->pluck('label')
        ->all());
}

it('forks a template space on edit, leaving the template and other tenants intact', function () {
    $template = Space::query()->create(['label' => 'Administração', 'panel_id' => 'store', 'sort' => 4]);

    // demo edits the inherited space -> a private fork is created.
    $fork = LaunchpadOverride::resolveForEditing($template, 'demo');
    $fork->update(['label' => 'Administração (demo)']);

    expect($fork->id)->not->toBe($template->id)
        ->and($fork->tenant_id)->toBe('demo')
        ->and($fork->origin_id)->toBe($template->id)
        // template is untouched
        ->and($template->fresh()->label)->toBe('Administração')
        // demo sees its fork; sorelle still sees the original
        ->and(effectiveSpaceLabels('demo'))->toContain('Administração (demo)')->not->toContain('Administração')
        ->and(effectiveSpaceLabels('sorelle'))->toContain('Administração')->not->toContain('Administração (demo)');
});

it('reuses the same fork on a second edit instead of making another', function () {
    $template = Space::query()->create(['label' => 'X', 'panel_id' => 'store', 'sort' => 0]);

    $first = LaunchpadOverride::resolveForEditing($template, 'demo');
    $second = LaunchpadOverride::resolveForEditing($template, 'demo');

    expect($second->id)->toBe($first->id)
        ->and(Space::query()->where('tenant_id', 'demo')->where('origin_id', $template->id)->count())->toBe(1);
});

it('hides an inherited space for one tenant only (tombstone), template intact', function () {
    $template = Space::query()->create(['label' => 'Financeiro', 'panel_id' => 'store', 'sort' => 3]);

    LaunchpadOverride::hideFor($template, 'demo');

    expect(effectiveSpaceLabels('demo'))->not->toContain('Financeiro')
        ->and(effectiveSpaceLabels('sorelle'))->toContain('Financeiro')
        ->and($template->fresh())->not->toBeNull();
});

it('a tenant new space is invisible to the template and to other tenants', function () {
    Space::query()->create(['label' => 'Base', 'panel_id' => 'store', 'sort' => 0]);
    Space::query()->create(['label' => 'Só Demo', 'panel_id' => 'store', 'tenant_id' => 'demo', 'sort' => 1]);

    expect(effectiveSpaceLabels('demo'))->toContain('Base', 'Só Demo')
        ->and(effectiveSpaceLabels('sorelle'))->toContain('Base')->not->toContain('Só Demo')
        // the primary context sees only the template
        ->and(effectiveSpaceLabels(null))->toContain('Base')->not->toContain('Só Demo');
});

it('the primary context edits the template in place (no fork)', function () {
    $template = Space::query()->create(['label' => 'Y', 'panel_id' => 'store', 'sort' => 0]);

    // No tenant resolved -> resolveForEditing returns the record itself.
    $resolved = LaunchpadOverride::resolveForEditing($template, null);

    expect($resolved->id)->toBe($template->id)
        ->and(Space::query()->whereNotNull('origin_id')->count())->toBe(0);
});

it('a forked space carries its pages, sections and card links (deep copy)', function () {
    $space = Space::query()->create(['label' => 'Admin', 'panel_id' => 'store', 'sort' => 0]);
    $page = $space->pages()->create(['label' => 'P1', 'sort' => 0]);
    $section = $page->sections()->create(['title' => 'S1', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'C1', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => true]);

    $fork = LaunchpadOverride::forkFor($space, 'demo');

    $forkPages = $fork->pages()->get();
    expect($forkPages)->toHaveCount(1)
        ->and($forkPages->first()->tenant_id)->toBe('demo')
        ->and($forkPages->first()->origin_id)->toBe($page->id);

    $forkSections = $forkPages->first()->sections()->get();
    expect($forkSections)->toHaveCount(1)
        ->and($forkSections->first()->cards()->count())->toBe(1);

    // The catalogue card itself is shared, not duplicated.
    expect(Card::query()->where('title', 'C1')->count())->toBe(1);
});

it('deleting an inherited space in one tenant only hides it there — never for others', function () {
    $template = Space::query()->create(['label' => 'Operações', 'panel_id' => 'store', 'sort' => 2]);

    // A tenant deletes the inherited space through the normal model delete
    // (what every table/header/bulk action ultimately calls).
    LaunchpadTenant::actingAs('sorelle', fn () => $template->delete());

    // The shared template survives...
    expect($template->fresh())->not->toBeNull()
        // ...gone for sorelle...
        ->and(effectiveSpaceLabels('sorelle'))->not->toContain('Operações')
        // ...still there for demo and the primary context.
        ->and(effectiveSpaceLabels('demo'))->toContain('Operações')
        ->and(effectiveSpaceLabels(null))->toContain('Operações');
});

it('a tenant deletes its OWN space for real', function () {
    $own = Space::query()->create(['label' => 'Meu', 'panel_id' => 'store', 'tenant_id' => 'demo', 'sort' => 0]);

    LaunchpadTenant::actingAs('demo', fn () => $own->delete());

    expect(Space::query()->whereKey($own->id)->exists())->toBeFalse();
});
