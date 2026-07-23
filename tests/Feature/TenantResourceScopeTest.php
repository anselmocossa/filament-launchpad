<?php

use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\PageResource;
use Filament\Launchpad\Filament\Resources\SectionResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadTenant;

/**
 * Phase H.2 — the shopkeeper manages the full tree at /store, tenant-scoped:
 * sees the template (read-only) plus its own (editable), never another store's.
 */
beforeEach(function () {
    actingAsLaunchpadAdmin();
    LaunchpadTenant::clearOverride();
    LaunchpadPlugin::get()->tenantResolver(fn () => null /* parent-panel default */);
});

afterEach(function () {
    LaunchpadTenant::clearOverride();
    LaunchpadPlugin::get()->tenantResolver(null);
});

it('lists the template plus the store own spaces, never another store', function () {
    $template = Space::query()->create(['label' => 'Template', 'panel_id' => 'store', 'sort' => 0]);
    $mine = Space::query()->create(['label' => 'Minha', 'panel_id' => 'store', 'tenant_id' => 'demo', 'sort' => 1]);
    $other = Space::query()->create(['label' => 'Alheia', 'panel_id' => 'store', 'tenant_id' => 'tech', 'sort' => 2]);

    $ids = LaunchpadTenant::actingAs('demo', fn () => SpaceResource::getEloquentQuery()->pluck('id')->all());

    expect($ids)->toContain($template->id, $mine->id)->not->toContain($other->id);
});

it('makes an inherited record read-only for a store but editable for its owner', function () {
    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);
    $mine = Space::query()->create(['label' => 'Minha', 'tenant_id' => 'demo', 'sort' => 1]);

    LaunchpadTenant::actingAs('demo', function () use ($template, $mine): void {
        expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeFalse()
            ->and(SpaceResource::launchpadRecordEditableByCurrentTenant($mine))->toBeTrue()
            ->and(SpaceResource::launchpadRecordIsInherited($template))->toBeTrue()
            ->and(SpaceResource::launchpadRecordIsInherited($mine))->toBeFalse();
    });
});

it('lets the parent edit everything, including the template', function () {
    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);

    // No tenant resolved (parent panel): the template is editable.
    expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeTrue()
        ->and(SpaceResource::launchpadRecordIsInherited($template))->toBeFalse();
});

it('scopes pages, sections and cards the same way', function () {
    $space = Space::query()->create(['label' => 'S', 'tenant_id' => null, 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'P', 'tenant_id' => null, 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Sec', 'tenant_id' => null, 'sort' => 0]);
    $card = Card::query()->create(['title' => 'C', 'type' => 'kpi', 'tenant_id' => null]);

    $mineCard = Card::query()->create(['title' => 'Meu', 'type' => 'kpi', 'tenant_id' => 'demo']);
    $otherCard = Card::query()->create(['title' => 'Alheio', 'type' => 'kpi', 'tenant_id' => 'tech']);

    LaunchpadTenant::actingAs('demo', function () use ($page, $section, $card, $mineCard, $otherCard): void {
        expect(PageResource::launchpadRecordEditableByCurrentTenant($page))->toBeFalse()
            ->and(SectionResource::launchpadRecordEditableByCurrentTenant($section))->toBeFalse()
            ->and(CardResource::launchpadRecordEditableByCurrentTenant($card))->toBeFalse();

        $cardIds = CardResource::getEloquentQuery()->pluck('id')->all();
        expect($cardIds)->toContain($card->id, $mineCard->id)->not->toContain($otherCard->id);
    });
});

it('single-tenant install keeps every record editable (no resolver)', function () {
    LaunchpadPlugin::get()->tenantResolver(null);
    $space = Space::query()->create(['label' => 'S', 'tenant_id' => null, 'sort' => 0]);

    expect(SpaceResource::launchpadRecordEditableByCurrentTenant($space))->toBeTrue()
        ->and(SpaceResource::launchpadRecordIsInherited($space))->toBeFalse();
});
