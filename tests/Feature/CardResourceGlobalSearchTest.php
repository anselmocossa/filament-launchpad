<?php

use Filament\Launchpad\Filament\Resources\CardResource;
use Filament\Launchpad\Filament\Resources\SpaceResource;
use Filament\Launchpad\Filament\Resources\SpaceResource\Pages\ListSpaces;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Pages\Launchpad;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

function makeCard(array $overrides = []): Card
{
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    return $section->cards()->create(array_merge([
        'title' => 'Vendas Hoje',
        'subtitle' => 'Ponto de Venda',
        'type' => 'kpi',
        'target_type' => 'none',
    ], $overrides));
}

it('exposes title and subtitle as globally searchable attributes', function () {
    expect(CardResource::getGloballySearchableAttributes())->toBe(['title', 'subtitle']);
});

it('finds cards by title through global search', function () {
    makeCard(['title' => 'Vendas Hoje Especial']);

    $results = CardResource::getGlobalSearchResults('Especial');

    expect($results)->toHaveCount(1)
        ->and($results->first()->title)->toBe('Vendas Hoje Especial');
});

it('only registers a flat index page (no create/edit routes of its own)', function () {
    expect(array_keys(CardResource::getPages()))->toBe(['index']);
});

it('never registers navigation', function () {
    // Ensure a panel + resources boot successfully and the card resource
    // does not surface anywhere in the navigation (registered only to
    // participate in global search).
    Livewire::test(ListSpaces::class)->assertOk();

    expect(CardResource::shouldRegisterNavigation())->toBeFalse();
});

it('resolves the launchpad url when target_type is none', function () {
    $card = makeCard(['target_type' => 'none', 'target_value' => null]);

    expect(CardResource::getGlobalSearchResultUrl($card))->toBe(Launchpad::getUrl());
});

it('resolves the launchpad url when target_value is blank', function () {
    $card = makeCard(['target_type' => 'url', 'target_value' => null]);

    expect(CardResource::getGlobalSearchResultUrl($card))->toBe(Launchpad::getUrl());
});

it('resolves a plain url target', function () {
    $card = makeCard(['target_type' => 'url', 'target_value' => '/vendas']);

    expect(CardResource::getGlobalSearchResultUrl($card))->toBe('/vendas');
});

it('resolves a resource target via its index url', function () {
    $card = makeCard([
        'target_type' => 'resource',
        'target_value' => SpaceResource::class,
    ]);

    expect(CardResource::getGlobalSearchResultUrl($card))
        ->toBe(SpaceResource::getUrl('index'));
});

it('resolves a page target via its url', function () {
    $card = makeCard([
        'target_type' => 'page',
        'target_value' => Launchpad::class,
    ]);

    expect(CardResource::getGlobalSearchResultUrl($card))->toBe(Launchpad::getUrl());
});

it('falls back to the launchpad url when the target class does not exist', function () {
    $card = makeCard([
        'target_type' => 'resource',
        'target_value' => 'App\\Filament\\Resources\\DoesNotExistResource',
    ]);

    expect(CardResource::getGlobalSearchResultUrl($card))->toBe(Launchpad::getUrl());
});

it('shows where the card lives in the global search result details', function () {
    $card = makeCard();

    expect(CardResource::getGlobalSearchResultDetails($card))->toBe([
        'Secções' => 'Ponto de Venda › Vendas › Histórico',
    ]);
});

it('lists every section when the card is referenced by more than one', function () {
    $card = makeCard();

    $space = Space::query()->create(['label' => 'Outro Space', 'sort' => 1]);
    $page = Page::query()->create(['space_id' => $space->id, 'label' => 'Outra Página', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $page->id, 'title' => 'Outra Secção', 'sort' => 0]);
    $otherSection->cards()->attach($card->id, ['sort' => 0]);

    expect(CardResource::getGlobalSearchResultDetails($card))->toBe([
        'Secções' => 'Ponto de Venda › Vendas › Histórico, Outro Space › Outra Página › Outra Secção',
    ]);
});

it('omits missing context from the details when the card has no sections', function () {
    $card = Card::query()->create(['title' => 'Órfão', 'type' => 'kpi', 'target_type' => 'none']);

    expect(CardResource::getGlobalSearchResultDetails($card))->toBe([]);
});

it('groups global search results under the "Cards" heading', function () {
    expect(CardResource::getPluralModelLabel())->toBe('Cards');
});
