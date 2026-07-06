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

it('deletes a section and cascades its cards, reindexing the rest', function () {
    $page = buildLayoutPage();
    $a = Section::query()->create(['page_id' => $page->id, 'title' => 'A', 'sort' => 0]);
    $b = Section::query()->create(['page_id' => $page->id, 'title' => 'B', 'sort' => 1]);
    Card::query()->create(['section_id' => $a->id, 'title' => 'C1', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('deleteSection', $a->id);

    expect(Section::query()->whereKey($a->id)->exists())->toBeFalse()
        ->and(Card::query()->where('section_id', $a->id)->count())->toBe(0)
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

    $card = Card::query()->where('section_id', $section->id)->first();

    expect($card)->not->toBeNull()
        ->and($card->title)->toBe('Vendas Hoje')
        ->and($card->type)->toBe('kpi')
        ->and($card->subtitle)->toBe('Ponto de Venda')
        ->and($card->unit)->toBe('un')
        ->and($card->sort)->toBe(0);
});

it('adds a card at a specific index and shifts the rest', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $existing = Card::query()->create(['section_id' => $section->id, 'title' => 'Existente', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $section->id, 'pos', 0);

    $new = Card::query()->where('section_id', $section->id)->where('title', 'Ponto de Venda')->first();

    expect($new->sort)->toBe(0)
        ->and($existing->refresh()->sort)->toBe(1);
});

it('moves a card to another section at an index', function () {
    $page = buildLayoutPage();
    $from = Section::query()->create(['page_id' => $page->id, 'title' => 'From', 'sort' => 0]);
    $to = Section::query()->create(['page_id' => $page->id, 'title' => 'To', 'sort' => 1]);
    $card = Card::query()->create(['section_id' => $from->id, 'title' => 'Mover', 'type' => 'kpi', 'sort' => 0]);
    $toCard = Card::query()->create(['section_id' => $to->id, 'title' => 'Já lá', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('moveCard', $card->id, $to->id, 0);

    expect($card->refresh()->section_id)->toBe($to->id)
        ->and($card->sort)->toBe(0)
        ->and($toCard->refresh()->sort)->toBe(1)
        ->and(Card::query()->where('section_id', $from->id)->count())->toBe(0);
});

it('reorders cards within a section', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $a = Card::query()->create(['section_id' => $section->id, 'title' => 'A', 'type' => 'kpi', 'sort' => 0]);
    $b = Card::query()->create(['section_id' => $section->id, 'title' => 'B', 'type' => 'kpi', 'sort' => 1]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('reorderCards', $section->id, [$b->id, $a->id]);

    expect($b->refresh()->sort)->toBe(0)
        ->and($a->refresh()->sort)->toBe(1);
});

it('removes a card and reindexes the section', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $a = Card::query()->create(['section_id' => $section->id, 'title' => 'A', 'type' => 'kpi', 'sort' => 0]);
    $b = Card::query()->create(['section_id' => $section->id, 'title' => 'B', 'type' => 'kpi', 'sort' => 1]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('removeCard', $a->id);

    expect(Card::query()->whereKey($a->id)->exists())->toBeFalse()
        ->and($b->refresh()->sort)->toBe(0);
});

it('edits a card through the modal action using the shared form schema', function () {
    $page = buildLayoutPage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'S', 'sort' => 0]);
    $card = Card::query()->create(['section_id' => $section->id, 'title' => 'Antigo', 'type' => 'kpi', 'sort' => 0]);

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

    expect(Card::query()->where('section_id', $section->id)->first()->library_key)->toBe('vendas_hoje');
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

    expect(Card::query()->where('section_id', $section->id)->where('library_key', 'vendas_hoje')->count())->toBe(2);
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
    $otherCard = Card::query()->create(['section_id' => $otherSection->id, 'title' => 'Alheio', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('moveCard', $otherCard->id, $mySection->id, 0);

    expect($otherCard->refresh()->section_id)->toBe($otherSection->id);
});

it('does not add a card to a section of another page', function () {
    $page = buildLayoutPage();

    $otherSpace = Space::query()->create(['label' => 'Outro', 'sort' => 1]);
    $otherPage = Page::query()->create(['space_id' => $otherSpace->id, 'label' => 'Outra', 'sort' => 0]);
    $otherSection = Section::query()->create(['page_id' => $otherPage->id, 'title' => 'Alheia', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('addCardFromLibrary', $otherSection->id, 'pos', null);

    expect(Card::query()->where('section_id', $otherSection->id)->count())->toBe(0);
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
    $otherCard = Card::query()->create(['section_id' => $otherSection->id, 'title' => 'Alheio', 'type' => 'kpi', 'sort' => 0]);

    Livewire::test(BuildLayout::class, ['record' => $page->id])
        ->call('removeCard', $otherCard->id);

    expect(Card::query()->whereKey($otherCard->id)->exists())->toBeTrue();
});
