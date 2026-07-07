<?php

use Filament\Launchpad\Filament\Resources\PageResource\Pages\BuildLayout;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

function buildLayoutPage(): Page
{
    $space = Space::query()->create(['label' => 'Ponto de Venda', 'sort' => 0]);

    return Page::query()->create(['space_id' => $space->id, 'label' => 'Vendas', 'sort' => 0]);
}

it('renders the build layout page for a page record', function () {
    $page = buildLayoutPage();
    Section::query()->create(['page_id' => $page->id, 'title' => 'Histórico', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->assertOk()
        ->assertSee('Biblioteca de Cards')
        ->assertSee('Histórico');
});

it('adds a section', function () {
    $page = buildLayoutPage();

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addSection');

    expect($page->sections()->count())->toBe(1)
        ->and($page->sections()->first()->title)->toBe('Nova Secção');
});

it('renames a section', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Antigo', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('renameSection', $section->id, 'Novo Nome');

    expect($section->refresh()->title)->toBe('Novo Nome');
});

it('deletes a section, detaching its cards without deleting them, and reindexes the rest', function () {
    $page = buildLayoutPage();
    $a = Section::query()->create(['page_id' => $page->id, 'title' => 'A', 'sort' => 0]);
    $b = Section::query()->create(['page_id' => $page->id, 'title' => 'B', 'sort' => 1]);
    $card = $a->cards()->create(['title' => 'C1', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('deleteSection', $a->id);

    expect(Section::query()->whereKey($a->id)->exists())->toBeFalse()
        ->and(Card::query()->whereKey($card->id)->exists())->toBeTrue() // the Card itself survives
        ->and($b->refresh()->sort)->toBe(0);
});

it('reorders sections', function () {
    $page = buildLayoutPage();
    $a = Section::query()->create(['page_id' => $page->id, 'title' => 'A', 'sort' => 0]);
    $b = Section::query()->create(['page_id' => $page->id, 'title' => 'B', 'sort' => 1]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('reorderSections', [$b->id, $a->id]);

    expect($b->refresh()->sort)->toBe(0)
        ->and($a->refresh()->sort)->toBe(1);
});

it('adds a card from a library preset carrying its fields', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Favoritos', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $section->id, 'vendas_hoje', null);

    $card = $section->cards()->first();

    expect($card)->not->toBeNull()
        ->and($card->title)->toBe('Vendas Hoje')
        ->and($card->type)->toBe('kpi')
        ->and($card->subtitle)->toBe('Ponto de Venda')
        ->and($card->unit)->toBe('un')
        ->and(pivotSort($section, $card))->toBe(0);
});

it('adds a card at a specific index and shifts the rest', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $existing = $section->cards()->create(['title' => 'Existente', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $section->id, 'pos', 0);

    $new = $section->cards()->where('title', 'Ponto de Venda')->first();

    expect(pivotSort($section, $new))->toBe(0)
        ->and(pivotSort($section, $existing))->toBe(1);
});

it('attaches an existing catalog card to a section, without creating a new record', function () {
    $page = buildLayoutPage();
    $sectionA = Section::query()->create(['page_id' => $page->id, 'title' => 'A', 'sort' => 0]);
    $sectionB = Section::query()->create(['page_id' => $page->id, 'title' => 'B', 'sort' => 1]);
    $card = $sectionA->cards()->create(['title' => 'Partilhado', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('attachCardFromCatalog', $sectionB->id, $card->id, null);

    expect(Card::query()->count())->toBe(1) // no new Card was created
        ->and($sectionA->cards()->whereKey($card->id)->exists())->toBeTrue()
        ->and($sectionB->cards()->whereKey($card->id)->exists())->toBeTrue();
});

it('exposes the full card catalog to the Builder view, alongside presets and widgets', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $section->cards()->create(['title' => 'Card Existente', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->assertSeeHtml('Card Existente')
        ->assertSeeHtml('catalog-card:');
});

it('moves a card to another section at an index', function () {
    $page = buildLayoutPage();
    $from = Section::query()->create(['page_id' => $page->id, 'title' => 'From', 'sort' => 0]);
    $to = Section::query()->create(['page_id' => $page->id, 'title' => 'To', 'sort' => 1]);
    $card = $from->cards()->create(['title' => 'Mover', 'type' => 'kpi']);
    $toCard = $to->cards()->create(['title' => 'Já lá', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('moveCard', $card->id, $from->id, $to->id, 0);

    expect($from->cards()->whereKey($card->id)->exists())->toBeFalse()
        ->and($to->cards()->whereKey($card->id)->exists())->toBeTrue()
        ->and(pivotSort($to, $card))->toBe(0)
        ->and(pivotSort($to, $toCard))->toBe(1)
        ->and($from->cards()->count())->toBe(0);
});

it('reorders cards within a section', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $a = $section->cards()->create(['title' => 'A', 'type' => 'kpi']);
    $b = $section->cards()->create(['title' => 'B', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('reorderCards', $section->id, [$b->id, $a->id]);

    expect(pivotSort($section, $b))->toBe(0)
        ->and(pivotSort($section, $a))->toBe(1);
});

it('removes a card from a section (detach), reindexing the rest, without deleting the card', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $a = $section->cards()->create(['title' => 'A', 'type' => 'kpi']);
    $b = $section->cards()->create(['title' => 'B', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('removeCard', $section->id, $a->id);

    expect($section->cards()->whereKey($a->id)->exists())->toBeFalse()
        ->and(Card::query()->whereKey($a->id)->exists())->toBeTrue() // the Card survives
        ->and(pivotSort($section, $b))->toBe(0);
});

it('keeps a card visible in another section after it is removed from one of them', function () {
    $page = buildLayoutPage();
    $sectionA = Section::query()->create(['page_id' => $page->id, 'title' => 'A', 'sort' => 0]);
    $sectionB = Section::query()->create(['page_id' => $page->id, 'title' => 'B', 'sort' => 1]);
    $card = $sectionA->cards()->create(['title' => 'Partilhado', 'type' => 'kpi']);
    $sectionB->cards()->attach($card->id, ['sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('removeCard', $sectionA->id, $card->id);

    expect($sectionA->cards()->whereKey($card->id)->exists())->toBeFalse()
        ->and($sectionB->cards()->whereKey($card->id)->exists())->toBeTrue()
        ->and(Card::query()->whereKey($card->id)->exists())->toBeTrue();
});

it('edits a card through the modal action using the shared form schema', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'Antigo', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->callAction('editCard', data: [
            'title' => 'Actualizado',
            'type' => 'kpi',
            'kpi_value' => '99',
            'target_type' => 'none',
        ], arguments: ['card' => $card->id])
        ->assertHasNoActionErrors();

    expect($card->refresh()->title)->toBe('Actualizado')
        ->and($card->kpi_value)->toBe('99');
});

// ---------------------------------------------------------------------
// Biblioteca de Cards: search + hide-after-use.
// ---------------------------------------------------------------------

it('stores the originating library key on a card created from a preset', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $section->id, 'vendas_hoje', null);

    expect($section->cards()->first()->library_key)->toBe('vendas_hoje');
});

it('keeps a library preset available after use (presets are reusable, like the design)', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->assertSeeHtml('preset:vendas_hoje')
        ->call('addCardFromLibrary', $section->id, 'vendas_hoje', null)
        ->assertSeeHtml('preset:vendas_hoje')
        ->call('addCardFromLibrary', $section->id, 'vendas_hoje', null)
        ->assertSeeHtml('preset:vendas_hoje');

    expect($section->cards()->where('library_key', 'vendas_hoje')->count())->toBe(2);
});

it('filters the library by the live search term', function () {
    $page = buildLayoutPage();

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->assertSeeHtml('preset:vendas_hoje')
        ->assertSeeHtml('preset:pos')
        ->set('librarySearch', 'vendas')
        ->assertSeeHtml('preset:vendas_hoje')
        ->assertDontSeeHtml('preset:pos');
});

// ---------------------------------------------------------------------
// Negative cases: ids from ANOTHER page must be safe no-ops.
// ---------------------------------------------------------------------

it('does not move a card belonging to another page', function () {
    $page = buildLayoutPage();
    $mySection = Section::query()->create(['page_id' => $page->id, 'title' => 'Mine', 'sort' => 0]);

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);
    $otherCard = $otherSection->cards()->create(['title' => 'Alheio', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('moveCard', $otherCard->id, $otherSection->id, $mySection->id, 0);

    expect($otherSection->cards()->whereKey($otherCard->id)->exists())->toBeTrue()
        ->and($mySection->cards()->whereKey($otherCard->id)->exists())->toBeFalse();
});

it('does not add a card to a section of another page', function () {
    $page = buildLayoutPage();

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $otherSection->id, 'pos', null);

    expect($otherSection->cards()->count())->toBe(0);
});

it('does not delete a section of another page', function () {
    $page = buildLayoutPage();

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('deleteSection', $otherSection->id);

    expect(Section::query()->whereKey($otherSection->id)->exists())->toBeTrue();
});

it('does not remove a card of another page', function () {
    $page = buildLayoutPage();

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);
    $otherCard = $otherSection->cards()->create(['title' => 'Alheio', 'type' => 'kpi']);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('removeCard', $otherSection->id, $otherCard->id);

    expect($otherSection->cards()->whereKey($otherCard->id)->exists())->toBeTrue();
});
