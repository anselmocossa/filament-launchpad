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

function editHomeCatalogTitles(object $component): array
{
    $instance = $component->instance();
    $method = new ReflectionMethod($instance, 'getCardCatalog');
    $method->setAccessible(true);

    return collect($method->invoke($instance))
        ->pluck('title')
        ->values()
        ->all();
}

function editHomeSectionTitles(object $component): array
{
    $instance = $component->instance();
    $pageMethod = new ReflectionMethod($instance, 'getPageModel');
    $pageMethod->setAccessible(true);
    $sectionsMethod = new ReflectionMethod($instance, 'builderSections');
    $sectionsMethod->setAccessible(true);

    return collect($sectionsMethod->invoke($instance, $pageMethod->invoke($instance)))
        ->pluck('title')
        ->values()
        ->all();
}

it('renders the standalone Editar Início page with no resource breadcrumb', function () {
    $page = homePage();
    $section = Section::query()->create(['page_id' => $page->id, 'title' => 'Favoritos', 'sort' => 0]);
    $section->cards()->create(['title' => 'Aulas', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);

    $component = Livewire::test(EditHome::class)
        ->assertOk()
        ->assertSee('Cards e Widgets Disponíveis')
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

it('hides already added cards from the personal available catalog', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $card = $section->cards()->create(['title' => 'Novo', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => false]);

    $before = Livewire::test(EditHome::class);
    expect(editHomeCatalogTitles($before))->toContain('Novo');

    Livewire::test(EditHome::class)
        ->call('addUserCard', $section->id, $card->id, null);

    $after = Livewire::test(EditHome::class);
    expect(editHomeCatalogTitles($after))->not->toContain('Novo')
        ->and(editHomeCardTitles($after))->toContain('Novo');
});

it('lets users add existing global cards and widgets without creating new ones', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $account = Card::query()->create(['title' => 'Account Widget', 'type' => 'widget', 'widget_key' => 'account-widget']);
    $courses = Card::query()->create(['title' => 'Cursos', 'type' => 'shortcut']);

    $before = Livewire::test(EditHome::class);
    expect(editHomeCatalogTitles($before))->toContain('Account Widget')
        ->and(editHomeCatalogTitles($before))->toContain('Cursos');

    Livewire::test(EditHome::class)
        ->call('addUserCard', $section->id, $account->id, null);

    $after = Livewire::test(EditHome::class);
    expect(Card::query()->count())->toBe(2)
        ->and(UserCard::query()->where('user_id', auth()->id())->where('section_id', $section->id)->where('card_id', $account->id)->exists())->toBeTrue()
        ->and(editHomeCardTitles($after))->toContain('Account Widget')
        ->and(editHomeCatalogTitles($after))->not->toContain('Account Widget')
        ->and(editHomeCatalogTitles($after))->toContain('Cursos');
});

it('does not offer admin fixed cards again in the personal catalog', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $fixed = $section->cards()->create(['title' => 'Fixo', 'type' => 'kpi'], ['sort' => 0, 'is_pinned' => true]);
    $global = Card::query()->create(['title' => 'Outro Existente', 'type' => 'shortcut']);

    $component = Livewire::test(EditHome::class)
        ->call('addUserCard', $section->id, $fixed->id, null);

    expect(editHomeCardTitles($component))->toContain('Fixo')
        ->and(editHomeCatalogTitles($component))->not->toContain('Fixo')
        ->and(editHomeCatalogTitles($component))->toContain('Outro Existente')
        ->and(UserCard::query()->where('user_id', auth()->id())->where('card_id', $fixed->id)->exists())->toBeFalse();
});

it('lets users add existing available widget cards without creating new widgets', function () {
    $home = homePage();
    $section = Section::query()->create(['page_id' => $home->id, 'title' => 'S', 'sort' => 0]);
    $widget = $section->cards()->create([
        'title' => 'Info Learn Way Widget',
        'type' => 'widget',
        'widget_key' => 'test-stats-widget',
        'widget_column_span' => '6',
        'target_type' => 'none',
    ], ['sort' => 0, 'is_pinned' => false]);

    $before = Livewire::test(EditHome::class);
    expect(editHomeCatalogTitles($before))->toContain('Info Learn Way Widget');

    Livewire::test(EditHome::class)
        ->call('addUserCard', $section->id, $widget->id, null);

    expect(Card::query()->where('type', 'widget')->count())->toBe(1)
        ->and(UserCard::query()->where('user_id', auth()->id())->where('card_id', $widget->id)->exists())->toBeTrue();
});

it('lets Editar Início create and manage only the current user sections', function () {
    $home = homePage();
    $first = Section::query()->create(['page_id' => $home->id, 'title' => 'Primeira', 'sort' => 0]);
    $second = Section::query()->create(['page_id' => $home->id, 'title' => 'Segunda', 'sort' => 1]);

    $component = Livewire::test(EditHome::class)
        ->call('addSection');

    $personal = Section::query()
        ->where('page_id', $home->id)
        ->where('user_id', auth()->id())
        ->firstOrFail();

    $component
        ->call('renameSection', $first->id, 'Mudou Global')
        ->call('renameSection', $personal->id, 'Minha Secção')
        ->call('reorderSections', [$second->id, $personal->id, $first->id])
        ->call('deleteSection', $first->id);

    expect($home->sections()->count())->toBe(3)
        ->and($first->refresh()->title)->toBe('Primeira')
        ->and($first->refresh()->sort)->toBe(0)
        ->and($second->refresh()->sort)->toBe(1)
        ->and($personal->refresh()->title)->toBe('Minha Secção')
        ->and($personal->refresh()->sort)->toBe(0);
});

it('keeps personal sections isolated per authenticated user', function () {
    $home = homePage();
    Section::query()->create(['page_id' => $home->id, 'title' => 'Global', 'sort' => 0]);

    $admin = auth()->user();
    $admin->update(['email' => 'admin@learnway.com']);

    $anselmo = TestUser::query()->create([
        'name' => 'Anselmo',
        'email' => 'anselmokossa.apk@gmail.com',
    ]);
    $anselmo->assignRole('super_admin');

    Livewire::actingAs($admin)
        ->test(EditHome::class)
        ->call('addSection');

    $adminSection = Section::query()
        ->where('page_id', $home->id)
        ->where('user_id', $admin->id)
        ->firstOrFail();
    $adminSection->update(['title' => 'Admin Secção']);

    Livewire::actingAs($anselmo)
        ->test(EditHome::class)
        ->call('addSection');

    $anselmoSection = Section::query()
        ->where('page_id', $home->id)
        ->where('user_id', $anselmo->id)
        ->firstOrFail();
    $anselmoSection->update(['title' => 'Anselmo Secção']);

    $adminTitles = editHomeSectionTitles(Livewire::actingAs($admin)->test(EditHome::class));
    $anselmoTitles = editHomeSectionTitles(Livewire::actingAs($anselmo)->test(EditHome::class));

    expect($adminTitles)->toContain('Global', 'Admin Secção')->not->toContain('Anselmo Secção')
        ->and($anselmoTitles)->toContain('Global', 'Anselmo Secção')->not->toContain('Admin Secção');
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
