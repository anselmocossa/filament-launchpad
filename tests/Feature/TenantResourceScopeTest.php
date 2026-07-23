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

it('fork mode: a tenant may edit the template (it forks) and its own', function () {
    actingAsLaunchpadTenantUser();
    LaunchpadPlugin::get()->tenantInheritance('fork');

    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);
    $mine = Space::query()->create(['label' => 'Minha', 'tenant_id' => 'demo', 'sort' => 1]);

    LaunchpadTenant::actingAs('demo', function () use ($template, $mine): void {
        expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeTrue()
            ->and(SpaceResource::launchpadRecordEditableByCurrentTenant($mine))->toBeTrue()
            ->and(SpaceResource::launchpadRecordIsInherited($template))->toBeTrue();
    });
});

it('readonly mode: the template is read-only for a plain tenant user', function () {
    actingAsLaunchpadTenantUser();
    LaunchpadPlugin::get()->tenantInheritance('readonly');

    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);
    $mine = Space::query()->create(['label' => 'Minha', 'tenant_id' => 'demo', 'sort' => 1]);

    LaunchpadTenant::actingAs('demo', function () use ($template, $mine): void {
        expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeFalse()
            ->and(SpaceResource::launchpadRecordEditableByCurrentTenant($mine))->toBeTrue();
    });

    LaunchpadPlugin::get()->tenantInheritance('fork');
});

it('lets the main edit the shared template even from inside a tenant panel', function () {
    // actingAsLaunchpadAdmin() is the super_admin — the main who authors and
    // distributes. A tenant resolves, yet the template stays editable for them.
    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);

    LaunchpadTenant::actingAs('demo', function () use ($template): void {
        expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeTrue();
    });
});

it('lets the main edit everything from the primary panel too', function () {
    $template = Space::query()->create(['label' => 'Template', 'tenant_id' => null, 'sort' => 0]);

    // No tenant resolved (primary panel): the template is editable.
    expect(SpaceResource::launchpadRecordEditableByCurrentTenant($template))->toBeTrue()
        ->and(SpaceResource::launchpadRecordIsInherited($template))->toBeFalse();
});

it('scopes pages, sections and cards the same way', function () {
    actingAsLaunchpadTenantUser();

    $space = Space::query()->create(['label' => 'S', 'tenant_id' => null, 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'P', 'tenant_id' => null, 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Sec', 'tenant_id' => null, 'sort' => 0]);
    $card = Card::query()->create(['title' => 'C', 'type' => 'kpi', 'tenant_id' => null]);

    $mineCard = Card::query()->create(['title' => 'Meu', 'type' => 'kpi', 'tenant_id' => 'demo']);
    $otherCard = Card::query()->create(['title' => 'Alheio', 'type' => 'kpi', 'tenant_id' => 'tech']);

    LaunchpadPlugin::get()->tenantInheritance('readonly');

    LaunchpadTenant::actingAs('demo', function () use ($page, $section, $card, $mineCard, $otherCard): void {
        // readonly mode: template read-only for a plain tenant user, every resource.
        expect(PageResource::launchpadRecordEditableByCurrentTenant($page))->toBeFalse()
            ->and(SectionResource::launchpadRecordEditableByCurrentTenant($section))->toBeFalse()
            ->and(CardResource::launchpadRecordEditableByCurrentTenant($card))->toBeFalse();

        // Scope is independent of the viewer: effective set = template + own,
        // never another tenant's.
        $cardIds = CardResource::getEloquentQuery()->pluck('id')->all();
        expect($cardIds)->toContain($card->id, $mineCard->id)->not->toContain($otherCard->id);
    });

    LaunchpadPlugin::get()->tenantInheritance('fork');
});

it('single-tenant install keeps every record editable (no resolver)', function () {
    LaunchpadPlugin::get()->tenantResolver(null);
    $space = Space::query()->create(['label' => 'S', 'tenant_id' => null, 'sort' => 0]);

    expect(SpaceResource::launchpadRecordEditableByCurrentTenant($space))->toBeTrue()
        ->and(SpaceResource::launchpadRecordIsInherited($space))->toBeFalse();
});
