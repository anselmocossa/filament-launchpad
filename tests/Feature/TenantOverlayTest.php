<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Support\LaunchpadScope;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Phase H — the overlay contract (docs/sdd/launchpad-multitenancy-phase-h.md).
 *
 * The whole point of an overlay rather than a copy: the parent keeps owning
 * every slot nobody overrode, so later template changes still flow downhill,
 * while a slot a store already replaced stays replaced.
 */
beforeEach(function () {
    actingAsLaunchpadAdmin();
    LaunchpadTenant::clearOverride();
});

afterEach(function () {
    LaunchpadTenant::clearOverride();
    LaunchpadPlugin::get()->tenantResolver(null);
});

/**
 * A template page with one section holding two parent cards.
 *
 * @return array{0: Page, 1: Section, 2: Card, 3: Card}
 */
function tenantTemplate(): array
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'KPIs', 'sort' => 0]);

    $produtos = $section->cards()->create(['title' => 'Produtos', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => true]);
    $encomendas = $section->cards()->create(['title' => 'Encomendas', 'type' => 'kpi'], ['sort' => 1, 'is_pinned' => true]);

    return [$page, $section, $produtos, $encomendas];
}

/**
 * Titles the launchpad renders inside ONE named section, for whatever tenant is
 * resolved. Narrowed to a single group on purpose: the package ships a seeded
 * default home (migration ..._000002), and asserting over the whole launchpad
 * would couple these tests to that seed.
 *
 * @return array<int, string>
 */
function renderedTitlesForTenant(?string $tenantId, string $group = 'KPIs'): array
{
    return LaunchpadTenant::actingAs($tenantId, function () use ($group): array {
        return collect(LaunchpadPlugin::get()->getSpaces())
            ->flatMap(fn ($space) => $space->getPages())
            ->flatMap(fn ($page) => $page->getSections())
            ->filter(fn ($tileGroup) => $tileGroup->getTitle() === $group)
            ->flatMap(fn ($tileGroup) => collect($tileGroup->getTiles())->map(fn ($tile) => $tile->getTitle()))
            ->values()
            ->all();
    });
}

function enableTenancy(): void
{
    LaunchpadPlugin::get()
        ->spaces([]) // force the DB-driven path (TestPanelProvider configures static spaces)
        ->tenantResolver(fn () => null /* the parent panel resolves no tenant of its own */);
}

it('keeps a card added by one store out of another store', function () {
    [, $section] = tenantTemplate();
    enableTenancy();

    $vendas = Card::query()->create(['title' => 'Vendas POS', 'type' => 'kpi']);

    UserCard::query()->create([
        'tenant_id' => 'demo',
        'section_id' => $section->id,
        'card_id' => $vendas->id,
        'sort' => 0,
    ]);

    expect(renderedTitlesForTenant('demo'))->toContain('Vendas POS')
        ->and(renderedTitlesForTenant('tech'))->not->toContain('Vendas POS');
});

it('hides a parent card for the store that tombstoned it and nobody else', function () {
    [, $section, , $encomendas] = tenantTemplate();
    enableTenancy();

    UserCard::query()->create([
        'tenant_id' => 'demo',
        'section_id' => $section->id,
        'card_id' => $encomendas->id,
        'is_hidden' => true,
        'sort' => 0,
    ]);

    expect(renderedTitlesForTenant('demo'))->not->toContain('Encomendas')
        ->and(renderedTitlesForTenant('demo'))->toContain('Produtos')
        ->and(renderedTitlesForTenant('tech'))->toContain('Encomendas');
});

it('propagates a card the parent adds later into every store', function () {
    [, $section] = tenantTemplate();
    enableTenancy();

    // demo has already personalised something else entirely.
    UserCard::query()->create([
        'tenant_id' => 'demo',
        'section_id' => $section->id,
        'card_id' => Card::query()->create(['title' => 'Vendas POS', 'type' => 'kpi'])->id,
        'sort' => 0,
    ]);

    // The parent now adds a brand new card to the shared template.
    $section->cards()->create(['title' => 'Faturas', 'type' => 'kpi'], ['sort' => 2, 'is_pinned' => true]);

    expect(renderedTitlesForTenant('demo'))->toContain('Faturas')
        ->and(renderedTitlesForTenant('tech'))->toContain('Faturas');
});

it('does not resurrect a slot the store already overrode when the parent edits it', function () {
    [, $section, , $encomendas] = tenantTemplate();
    enableTenancy();

    UserCard::query()->create([
        'tenant_id' => 'demo',
        'section_id' => $section->id,
        'card_id' => $encomendas->id,
        'is_hidden' => true,
        'sort' => 0,
    ]);

    // The parent renames the very card demo hid.
    $encomendas->update(['title' => 'Encomendas (novo)']);

    expect(renderedTitlesForTenant('demo'))->not->toContain('Encomendas (novo)')
        ->and(renderedTitlesForTenant('tech'))->toContain('Encomendas (novo)');
});

it('layers parent, store and user in that order', function () {
    [$page] = tenantTemplate();
    enableTenancy();

    Section::query()->create(['page_id' => $page->id, 'tenant_id' => 'demo', 'title' => 'Da Loja', 'sort' => 0]);
    Section::query()->create(['page_id' => $page->id, 'user_id' => (string) auth()->id(), 'tenant_id' => 'demo', 'title' => 'Minha', 'sort' => 0]);

    $titles = LaunchpadTenant::actingAs('demo', fn () => Section::query()
        ->where('page_id', $page->id)
        ->orderByRaw('case when tenant_id is null and user_id is null then 0 when user_id is null then 1 else 2 end')
        ->orderBy('sort')
        ->pluck('title')
        ->all());

    expect($titles)->toBe(['KPIs', 'Da Loja', 'Minha']);
});

it('never leaks a store section into the parent template view', function () {
    [$page] = tenantTemplate();
    enableTenancy();

    Section::query()->create(['page_id' => $page->id, 'tenant_id' => 'demo', 'title' => 'Da Loja', 'sort' => 1]);

    // The parent, with no store selected, is authoring the shared template.
    $sectionTitles = LaunchpadTenant::actingAs(null, fn () => collect(LaunchpadPlugin::get()->getSpaces())
        ->flatMap(fn ($space) => $space->getPages())
        ->flatMap(fn ($page) => $page->getSections())
        ->map(fn ($tileGroup) => $tileGroup->getTitle())
        ->all());

    expect($sectionTitles)->toContain('KPIs')->not->toContain('Da Loja');
});

it('derives the scope key even for rows inserted directly', function () {
    [, $section, $produtos] = tenantTemplate();

    $row = UserCard::query()->create([
        'tenant_id' => 'demo',
        'section_id' => $section->id,
        'card_id' => $produtos->id,
        'sort' => 0,
    ]);

    expect($row->fresh()->scope_key)->toBe(LaunchpadScope::key('demo', null));
});

it('behaves exactly as a single-tenant install when no resolver is wired', function () {
    [, $section] = tenantTemplate();

    $vendas = Card::query()->create(['title' => 'Vendas POS', 'type' => 'kpi']);
    UserCard::query()->create([
        'user_id' => (string) auth()->id(),
        'section_id' => $section->id,
        'card_id' => $vendas->id,
        'sort' => 0,
    ]);

    // No tenantResolver() call at all — LaunchpadTenant::id() stays null and
    // every tenant filter collapses against all-null columns.
    LaunchpadPlugin::get()->spaces([]); // force the DB-driven path for this test
    expect(renderedTitlesForTenant(null))->toBe(['Produtos', 'Encomendas', 'Vendas POS']);
});
