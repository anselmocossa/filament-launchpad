<?php

use Filament\Facades\Filament;
use Filament\Launchpad\LaunchpadPlugin;
use Filament\Launchpad\Livewire\BookmarkButton;
use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Support\LaunchpadBookmarks;
use Filament\Launchpad\Tests\Support\TestUser;
use Livewire\Livewire;

/**
 * Personal bookmarks ("Save to Home", LaunchpadPlugin::bookmarks()).
 */
function homeComPagina(): Page
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => 0, 'is_default' => true, 'panel_id' => 'test']);

    return Page::query()->create(['space_id' => $space->id, 'label' => 'Visão Geral', 'sort' => 0]);
}

it('saves a page into the user own shortcuts section, once', function () {
    $page = homeComPagina();

    expect(LaunchpadBookmarks::save('7', 'test', '/test/orders?tableFilters[estado]=aberto', 'Encomendas'))->toBeTrue()
        ->and(LaunchpadBookmarks::save('7', 'test', '/test/orders?tableFilters[estado]=aberto', 'Encomendas'))->toBeTrue();

    $section = Section::query()->where('page_id', $page->id)->where('user_id', '7')->sole();
    $card = $section->cards()->sole();

    expect($section->title)->toBe('Os meus atalhos')
        ->and($card->type)->toBe('shortcut')
        ->and($card->target_type)->toBe('url')
        ->and($card->target_value)->toBe('/test/orders?tableFilters[estado]=aberto')
        ->and((bool) $card->pivot->is_pinned)->toBeTrue()
        ->and(LaunchpadBookmarks::isSaved('7', '/test/orders?tableFilters[estado]=aberto'))->toBeTrue();
});

it('removes a saved page', function () {
    homeComPagina();
    LaunchpadBookmarks::save('7', 'test', '/test/orders', 'Encomendas');

    LaunchpadBookmarks::remove('7', '/test/orders');

    expect(LaunchpadBookmarks::isSaved('7', '/test/orders'))->toBeFalse()
        ->and(Card::query()->where('library_key', LaunchpadBookmarks::key('7', '/test/orders'))->exists())->toBeFalse();
});

it('does nothing when the panel has no home page yet', function () {
    expect(LaunchpadBookmarks::save('7', 'test', '/test/orders', 'Encomendas'))->toBeFalse()
        ->and(Card::query()->count())->toBe(0);
});

it('keeps bookmarks out of shared listings', function () {
    homeComPagina();
    Card::query()->create(['title' => 'Catálogo', 'type' => 'shortcut', 'library_key' => 'kpi.vendas']);
    LaunchpadBookmarks::save('7', 'test', '/test/orders', 'Encomendas');

    expect(Card::query()->withoutBookmarks()->pluck('title')->all())->toBe(['Catálogo']);
});

it('shows a user bookmarks only to that user', function () {
    homeComPagina();

    $dono = TestUser::query()->create(['name' => 'Dono']);
    $outro = TestUser::query()->create(['name' => 'Outro']);

    LaunchpadBookmarks::save((string) $dono->getKey(), 'test', '/test/orders', 'Encomendas');

    Filament::setCurrentPanel(Filament::getPanel('test'));

    // Um plugin sem spaces no codigo le-os da base (o do painel de testes traz os seus).
    $titulos = function (TestUser $user): array {
        auth()->login($user);

        return collect(LaunchpadPlugin::make()->getSpaces()[0]->getPages()[0]->getSections())
            ->map(fn ($section) => $section->toArray()['title'] ?? null)
            ->all();
    };

    expect($titulos($dono))->toContain('Os meus atalhos')
        ->and($titulos($outro))->not->toContain('Os meus atalhos');
});

it('toggles from the page header button, naming the card after the tab title without the brand', function () {
    homeComPagina();
    $user = actingAsLaunchpadAdmin();
    Filament::setCurrentPanel(Filament::getPanel('test'));
    $marca = (string) Filament::getBrandName();

    $botao = Livewire::test(BookmarkButton::class, ['path' => '/test/orders'])
        ->assertSet('saved', false)
        ->call('toggle', "Encomendas - {$marca}")
        ->assertSet('saved', true);

    expect(Card::query()->where('library_key', LaunchpadBookmarks::key((string) $user->getKey(), '/test/orders'))->value('title'))->toBe('Encomendas');

    $botao->call('toggle')->assertSet('saved', false);

    expect(LaunchpadBookmarks::isSaved((string) $user->getKey(), '/test/orders'))->toBeFalse();
});
