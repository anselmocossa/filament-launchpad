<?php

use Filament\Launchpad\Models\Card;
use Filament\Launchpad\Models\Page;
use Filament\Launchpad\Models\Section;
use Filament\Launchpad\Models\Space;
use Filament\Launchpad\Models\UserCard;
use Filament\Launchpad\Pages\EditHome;
use Filament\Launchpad\Tests\Support\TestUser;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLaunchpadAdmin();
});

function homePage(): Page
{
    $space = Space::query()->create(['label' => 'Início', 'sort' => -100]);

    return Page::query()->create(['space_id' => $space->id, 'label' => 'Início', 'sort' => -100]);
}

function editHomeCardTitles(object $component): array
{
    $instance = $component->instance();
    $pageMethod = new ReflectionMethod($instance, 'getPageModel');
    $pageMethod->setAccessible(true);
    $sectionsMethod = new ReflectionMethod($instance, 'builderSections');
    $sectionsMethod->setAccessible(true);

    return collect($sectionsMethod->invoke($instance, $pageMethod->invoke($instance)))
        ->flatMap(fn (array $section): array => collect($section['cards'])->pluck('title')->all())
        ->values()
        ->all();
}

it('renders the standalone Editar Início page with no resource breadcrumb', function () {
    $page = homePage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Favoritos', 'sort' => 0]);
    $section->cards()->create(['title' => 'Aulas', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);

    $component = Livewire::test(EditHome::class)
        ->assertOk()
        ->assertSee('Cards Disponíveis')
        ->assertSee('Favoritos');

    expect($component->instance()->getBreadcrumbs())->toBe([])
        ->and($component->instance()->getTitle())->toBe('Editar Início');
});

it('operates on the home page personal layer when adding an available card', function () {
    $decoy = Space::query()->create(['label' => 'Decoy', 'sort' => 10]);
    Page::query()->create(['space_id' => $decoy->id, 'label' => 'Decoy', 'sort' => 10]);

    $firstSpace = Space::query()->create(['label' => 'Primeiro', 'sort' => -100]);
    $home = Page::query()->create(['space_id' => $firstSpace->id, 'label' => 'Home', 'sort' => -100]);
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'A', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);

    Livewire::test(EditHome::class)
        ->call('addUserCard', $section->id, $card->id, null);

    expect(UserCard::query()->where('user_id', auth()->id())->where('section_id', $section->id)->where('card_id', $card->id)->exists())->toBeTrue();
});

it('removes only the current user card from the home page, without deleting the catalog card', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'A', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);
    UserCard::query()->create([
        'user_id' => auth()->id(),
        'section_id' => $section->id,
        'card_id' => $card->id,
        'sort' => 0,
    ]);

    Livewire::test(EditHome::class)
        ->call('removeUserCard', $section->id, $card->id);

    expect(UserCard::query()->where('user_id', auth()->id())->where('card_id', $card->id)->exists())->toBeFalse()
        ->and($section->cards()->whereKey($card->id)->exists())->toBeTrue()
        ->and(Card::query()->whereKey($card->id)->exists())->toBeTrue();
});

it('keeps Editar Início personal per authenticated user, including super admins', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $adminCard = $section->cards()->create(['title' => 'Admin Custom', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);
    $anselmoCard = $section->cards()->create(['title' => 'Anselmo Custom', 'type' => 'kpi'], ['sort' => 1, 'is_pinned' => false]);

    $admin = auth()->user();
    $admin->update(['email' => 'admin@learnway.com']);

    $anselmo = TestUser::query()->create([
        'name' => 'Anselmo',
        'email' => 'anselmokossa.apk@gmail.com',
    ]);
    $anselmo->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(EditHome::class)
        ->call('addUserCard', $section->id, $adminCard->id, null);

    $anselmoComponent = Livewire::actingAs($anselmo)
        ->test(EditHome::class)
        ->call('addUserCard', $section->id, $anselmoCard->id, null);
    $anselmoTitles = editHomeCardTitles($anselmoComponent);

    $adminComponent = Livewire::actingAs($admin)->test(EditHome::class);
    $adminTitles = editHomeCardTitles($adminComponent);

    expect(UserCard::query()->where('user_id', $admin->id)->pluck('card_id')->all())->toBe([$adminCard->id])
        ->and(UserCard::query()->where('user_id', $anselmo->id)->pluck('card_id')->all())->toBe([$anselmoCard->id])
        ->and($adminTitles)->toContain('Admin Custom')->not->toContain('Anselmo Custom')
        ->and($anselmoTitles)->toContain('Anselmo Custom')->not->toContain('Admin Custom');
});

it('does not crash when only the seeded home page exists', function () {
    Livewire::test(EditHome::class)
        ->assertOk();
});
