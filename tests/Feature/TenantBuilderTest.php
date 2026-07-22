<?php

use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Pages\EditHome;
use Filament\Launchpad\Support\LaunchpadScope;
use Filament\Launchpad\Support\LaunchpadTenant;
use Livewire\Livewire;

/**
 * Phase H — the WRITE side: which layer the builder mutates, and the guarantee
 * that a store's edits never reach the template or another store.
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
 * @return array{0: Page, 1: Section, 2: Card}
 */
function builderTemplate(): array
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => -100]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => -100]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'KPIs', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'Encomendas', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => true]);

    LaunchpadPlugin::get()->tenantResolver(fn () => null /* the parent panel resolves no tenant of its own */);

    return [$page, $section, $card];
}

it('tombstones an inherited card instead of detaching the parent pivot', function () {
    [, $section, $card] = builderTemplate();
    LaunchpadTenant::setOverride('demo');

    Livewire::test(EditHome::class)
        ->set('layer', LaunchpadScope::TENANT)
        ->call('removeCard', $section->id, $card->id);

    expect($section->cards()->whereKey($card->id)->exists())->toBeTrue()
        ->and(UserCard::query()->where('scope_key', LaunchpadScope::key('demo', null))
            ->where('card_id', $card->id)->where('is_hidden', true)->exists())->toBeTrue();
});

it('refuses to let the personal layer hide an inherited card', function () {
    [, $section, $card] = builderTemplate();
    LaunchpadTenant::setOverride('demo');

    Livewire::test(EditHome::class)
        ->set('layer', LaunchpadScope::USER)
        ->call('removeCard', $section->id, $card->id);

    expect(UserCard::query()->where('is_hidden', true)->exists())->toBeFalse()
        ->and($section->cards()->whereKey($card->id)->exists())->toBeTrue();
});

it('writes a new section into the store layer, not the template', function () {
    [$page] = builderTemplate();
    LaunchpadTenant::setOverride('demo');

    Livewire::test(EditHome::class)
        ->set('layer', LaunchpadScope::TENANT)
        ->call('addSection');

    $created = Section::query()->where('page_id', $page->id)->where('title', '!=', 'KPIs')->firstOrFail();

    expect($created->tenant_id)->toBe('demo')
        ->and($created->user_id)->toBeNull();
});

it('restores a store to the parent template without touching other stores', function () {
    [$page, $section, $card] = builderTemplate();

    $extra = Card::query()->create(['title' => 'Vendas POS', 'type' => 'kpi']);

    foreach (['demo', 'tech'] as $tenant) {
        UserCard::query()->create([
            'tenant_id' => $tenant,
            'section_id' => $section->id,
            'card_id' => $extra->id,
            'sort' => 0,
        ]);
        UserCard::query()->create([
            'tenant_id' => $tenant,
            'section_id' => $section->id,
            'card_id' => $card->id,
            'is_hidden' => true,
            'sort' => 0,
        ]);
        Section::query()->create([
            'page_id' => $page->id,
            'tenant_id' => $tenant,
            'title' => 'Secção '.$tenant,
            'sort' => 1,
        ]);
    }

    LaunchpadTenant::setOverride('demo');

    Livewire::test(EditHome::class)
        ->set('layer', LaunchpadScope::TENANT)
        ->call('restoreParentTemplate');

    expect(UserCard::query()->where('scope_key', LaunchpadScope::key('demo', null))->exists())->toBeFalse()
        ->and(Section::query()->where('tenant_id', 'demo')->exists())->toBeFalse()
        // tech keeps everything it had, and the template is untouched
        ->and(UserCard::query()->where('scope_key', LaunchpadScope::key('tech', null))->count())->toBe(2)
        ->and(Section::query()->where('tenant_id', 'tech')->exists())->toBeTrue()
        ->and($section->cards()->whereKey($card->id)->exists())->toBeTrue();
});

it('offers the store layer only on a multi-tenant install', function () {
    builderTemplate();

    LaunchpadTenant::setOverride('demo');
    expect(Livewire::test(EditHome::class)->instance()->canManageTenantLayer())->toBeTrue();

    // No tenant resolves → the switcher has nothing to switch to.
    LaunchpadTenant::setOverride(null);
    expect(Livewire::test(EditHome::class)->instance()->canManageTenantLayer())->toBeFalse();

    // No resolver at all → single-tenant install, switcher never appears.
    LaunchpadPlugin::get()->tenantResolver(null);
    LaunchpadTenant::clearOverride();
    expect(Livewire::test(EditHome::class)->instance()->canManageTenantLayer())->toBeFalse();
});

it('defaults a store manager to the store layer', function () {
    builderTemplate();
    LaunchpadTenant::setOverride('demo');

    expect(Livewire::test(EditHome::class)->instance()->layer)->toBe(LaunchpadScope::TENANT);
});
