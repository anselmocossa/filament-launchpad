<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Pages\EditHome;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

function homePage(): Page
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => 0]);

    return Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => 0]);
}

it('renders the standalone Editar Início page with no resource breadcrumb', function () {
    $page = homePage();
    Section::query()->create(['page_id' => $page->id, 'title' => 'Favoritos', 'sort' => 0]);

    $component = Livewire::test(EditHome::class)
        ->assertOk()
        ->assertSee('Biblioteca de Cards')
        ->assertSee('Favoritos');

    expect($component->instance()->getBreadcrumbs())->toBe([])
        ->and($component->instance()->getTitle())->toBe('Editar Início');
});

it('operates on the home page (first page of the first space) when adding a card', function () {
    homePage(); // decoy: not the actual first space
    $firstSpace = Space::query()->create(['label' => 'Primeiro', 'sort' => -1]);
    $home = Page::query()->create(['space_id' => $firstSpace->id, 'label' => 'Home', 'sort' => 0]);
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);

    Livewire::test(EditHome::class)
        ->call('addCardFromLibrary', $section->id, 'vendas_hoje', null);

    expect($section->cards()->count())->toBe(1);
});

it('removes a card from the home page (detach), without deleting it', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'A', 'type' => 'kpi']);

    Livewire::test(EditHome::class)
        ->call('removeCard', $section->id, $card->id);

    expect($section->cards()->whereKey($card->id)->exists())->toBeFalse()
        ->and(Card::query()->whereKey($card->id)->exists())->toBeTrue();
});

it('does not crash when there is no home page yet', function () {
    Livewire::test(EditHome::class)
        ->assertRedirect();
});
